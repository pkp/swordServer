<?php

/**
 * @file SwordServerPlugin.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SwordServerPlugin
 * @brief Sword gateway plugin
 */

import('lib.pkp.classes.plugins.GatewayPlugin');
import('lib.pkp.classes.security.authorization.PolicySet');
import('lib.pkp.classes.submission.Genre');
import('classes.publication.Publication');

require __DIR__ . '/ServiceDocument.inc.php';
require __DIR__ . '/DepositReceipt.inc.php';
require __DIR__ . '/SwordStatement.inc.php';
require __DIR__ . '/SwordSubmissionFileManager.inc.php';
require __DIR__ . '/SwordServerAccessPolicy.inc.php';
require __DIR__ . '/SwordError.inc.php';

class SwordServerPlugin extends GatewayPlugin {

	/**
	 * @copydoc Plugin::__construct()
	 */
	function __construct() {
		parent::__construct();

		$this->_endpoints = [
			[
				'pattern' => 'servicedocument',
				'method' => 'GET',
				'handler' => [$this, 'serviceDocument']
			],
			[
				'pattern' => 'sections/{id}',
				'method' => 'POST',
				'handler' => [$this, 'deposit']
			],
			[
				'pattern' => 'submissions/{id}/statement',
				'method' => 'GET',
				'handler' => [$this, 'statement']
			]
		];
	}

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		$this->addLocaleData();
		return $success;
	}

	/**
	 * Get the name of the settings file to be installed on new journal
	 * creation.
	 * @return string
	 */
	function getContextSpecificPluginSettingsFile() {
		return $this->getPluginPath() . '/settings.xml';
	}

	/**
	 * @copydoc Plugin::getName()
	 */
	function getName() {
		return 'swordserver';
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.gateways.swordserver.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.gateways.swordserver.description');
	}

	/**
	 * @copydoc GatewayPlugin::getPolicies()
	 */
	function getPolicies($request) {
		yield new SwordServerAccessPolicy($request);
	}

	/**
	 * Serve a SWORD Service Document
	 */
	function serviceDocument() {
		$journal = $this->request->getJournal();
		$journalId = $journal->getId();
		$sectionDAO = DAORegistry::getDAO('SectionDAO');
		$resultSet = $sectionDAO->getByJournalId($journalId);
		$sections = $resultSet->toAssociativeArray();
 		$serviceDocument = new ServiceDocument(
			$journal,
			$sections,
			$this->request->getRequestUrl()
		);
		header('Content-Type: application/xml');
		echo $serviceDocument->saveXML();
		exit;
	}

	/**
	 * Handle a SWORD Deposit request
	 *
	 * @param $opts array
	 */
	function deposit($opts) {
		$headers = getallheaders();
		$sectionId = intval($opts['id']);
		$locale = Locale::getDefault();
		$submissionDAO = Application::getSubmissionDAO();
		$submission = $submissionDAO->newDataObject();
		$submission->setContextId($this->request->getJournal()->getId());
		$submission->setDateSubmitted (Core::getCurrentDate());
		$submission->setLastModified (Core::getCurrentDate());
		$submission->setSubmissionProgress(0);
		$submission->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
		$submission->setSectionId($sectionId);
		$submission->setLocale($locale);

		$uploadDir = ini_get('upload_tmp_dir') . '/' . uniqid();
		mkdir($uploadDir, DIRECTORY_MODE_MASK);
		$uploadedFilePath = $uploadDir . '/sword_upload.dat';
		file_put_contents($uploadedFilePath, file_get_contents('php://input'));
		if ($headers['Content-Type'] == 'application/zip' && $headers['Packaging'] == "http://purl.org/net/sword/package/METSDSpaceSIP") {
			$zip = new ZipArchive;
			$res = $zip->open($uploadedFilePath);
			if ($res) {
				$zip->extractTo($uploadDir);
				$zip->close();
			} else {
				throw new Exception('Zip extraction failed');
			}
		}

		if (!file_exists($uploadDir . "/mets.xml")) {
			throw new Exception('No mets.xml document in extracted Zip package');
		}
		$metsDoc = simplexml_load_file($uploadDir . "/mets.xml");
		$metsDoc->registerXPathNamespace("mets", 'http://www.loc.gov/METS/');
		$metsDoc->registerXPathNamespace("epdcx", "http://purl.org/eprint/epdcx/2006-11-16/");
		$metsDoc->registerXPathNamespace("mods", "http://www.loc.gov/mods/v3");

		$submissionData = [
			'contextId' => $this->request->getJournal()->getId(),
			'status' => STATUS_QUEUED
		];
		$publicationData = [
			'status' => STATUS_QUEUED
		];

		$match = $metsDoc->xpath("//epdcx:statement[@epdcx:propertyURI='http://purl.org/dc/" .
			 "elements/1.1/title']/epdcx:valueString");
		if (!empty($match)) {
			$title = $match[0]->__toString();
			$publicationData['title'] = ['en_US' => $title];
		}
		$submission->_data = $submissionData;
		$submission = Services::get('submission')->add($submission, $this->request);
		$publicationData['submissionId'] = $submission->getId();
		$publication = DAORegistry::getDAO('PublicationDAO')->newDataObject();
		$publication->_data = $publicationData;
		$publication->setData('sectionId', $this->request->getJournal()->getId());
		$publication->setData('status', STATUS_QUEUED);
		if (!empty($match)) {
			$title = $match[0]->__toString();
			$publication->setData('title', $title, $locale);
		}
		$publication->setData('locale', $locale);
		$publication = Services::get('publication')->add($publication, $this->request);
		$submission = Services::get('submission')->edit($submission, ['currentPublicationId' => $publication->getId()], $this->request);

		$nameNodes = $metsDoc->xpath("//mods:name[mods:role/mods:roleTerm='author' or mods:role/mods:roleTerm='pkp_primary_contact']");
		$authorDAO = DAORegistry::getDAO("AuthorDAO");
		foreach ($nameNodes as $nameNode) {
			$nameNode->registerXPathNamespace("mods", "http://www.loc.gov/mods/v3");
			$email = $nameNode->xpath("mods:nameIdentifier[@type='email']");
			$given = $nameNode->xpath("mods:namePart[@type='given']");
			$family = $nameNode->xpath("mods:namePart[@type='family']");
			$primary = $nameNode->xpath("mods:role[mods:roleTerm='pkp_primary_contact']");
			if (!empty($email) && (!empty($given) || !empty($family))) {
				$author = $authorDAO->newDataObject();
				$author->_data = [
					'email' => $email[0]->__toString(),
					'seq' => 1,
					'publicationId' => $publication->getId(),
					'userGroupId' => 14,
					'includeInBrowse' => 1,
				];
				if (!empty($family)) {
					$author->setData('familyName', $family[0]->__toString(), $locale);
				}
				if (!empty($given)) {
					$author->setData('givenName', $given[0]->__toString(), $locale);
				}
				$author = Services::get('author')->add($author, $this->request);
				if (!empty($primary)) {
					$publication = Services::get('publication')->edit($publication, ['primaryContactId' => $author->getId()], $this->request);
				}
			}
		}
	
		$submissionFileManager = new SwordSubmissionFileManager($this->request->getJournal()->getId(), $submission->getId());
		$hrefs = array_unique(
			array_map(function($h) {
				return $h['href']->__toString();
			}, $metsDoc->xpath("//mets:FLocat[@LOCTYPE='URL']/@xlink:href")
			)
		);

		foreach ($hrefs as $href) {
			if (file_exists($uploadDir . "/" . $href)) {
				$submissionFile = $submissionFileManager->uploadSubmissionFile(
					$href, SUBMISSION_FILE_SUBMISSION, $this->user->getId(), null, GENRE_CATEGORY_SUPPLEMENTARY, null, null
				);
					
			}
		}

		// Attach the original package as well
		$submissionFile = $submissionFileManager->uploadSubmissionFile(
			'sword_upload.dat', SUBMISSION_FILE_SUBMISSION, $this->user->getId(), null, GENRE_CATEGORY_SUPPLEMENTARY, null, null
		);

		$serverHost = $this->request->_serverHost;
		$requestPath = $this->request->_requestPath;
		$editIri = $this->request->getRouter()->url($this->request,
			null,
			null,
			null,
			['swordserver', 'submissions', $submission->getId()]
		);
		$stmtIri = $this->request->getRouter()->url($this->request,
			null,
			null,
			null,
			['swordserver', 'submissions', $submission->getId(), 'statement']
		);

		$depositReceipt = new DepositReceipt(
			[
				'title' => $submission->getTitle($locale),
				'edit-iri' => $editIri,
				'stmt-iri' => $stmtIri,
			]
		);

		$submissionFileManager->rmtree($uploadDir);
		header('Content-Type: application/xml');
		echo $depositReceipt->saveXML();
		exit;
	}

	/**
	 * Handle a SWORD Statement request
	 *
	 * @param $opts array
	 */
	function statement($opts) {
		$locale = Locale::getDefault();
		$submissionDAO = Application::getSubmissionDAO();
		$submission = $submissionDAO->getById($opts['id']);
		$sectionDAO = DAORegistry::getDAO('SectionDAO');
		$section = $sectionDAO->getById($submission->getSectionId());
		$swordStatement = new SwordStatement(
			[
				'state_href' => $this->_getStateIRI(),
				'state_description' => "Deposited to " . $section->getData('title', $locale),
			]
		);

		header('Content-Type: application/xml');
		echo $swordStatement->saveXML();
		exit;
	}

	/**
	 * @copydoc GatewayPlugin::fetch()
	 */
	function fetch($args, $request) {
		if (!$this->getEnabled()) {
			return false;
		}
		$this->request = $request;
		$handler = $request->_router->getHandler();
		$this->user = $handler->getAuthorizedContextObject(ASSOC_TYPE_USER);
		$method = $request->getRequestMethod();
		$methodsAllowed = [];
		foreach ($this->_endpoints as $endpoint) {
			if ($opts = $this->_matchRoute($args, $endpoint['pattern'])) {
				if ($method == $endpoint['method']) {
					try {
						call_user_func($endpoint['handler'], $opts);
					} catch (Throwable $e) {
						error_log("=============");
						error_log($e->getMessage());
						$swordError = new SwordError([
							'summary' => "Application Error." . $e->__toString()
						]);

						header('Content-Type: application/xml');
						header('HTTP/1.1 500 Not ServerError');
						header('Allow ' . implode($methodsAllowed, ', '));
						echo $swordError->saveXML();
						exit;
					}
					break;
				}
				array_push($methodsAllowed, $endpoint['method']);
			}
		}

		if (!empty($methodsAllowed)) {
			$swordError = new SwordError([
				'summary' => "Method not Allowed."
			]);

			header('Content-Type: application/xml');
			header('HTTP/1.1 405 Not Allowed');
			header('Allow ' . implode($methodsAllowed, ', '));
			echo $swordError->saveXML();
			exit;
		} else {
			$swordError = new SwordError([
				'summary' => "Not found."
			]);

			header('Content-Type: application/xml');
			header('HTTP/1.1 404 Not Found');
			echo $swordError->saveXML();
			exit;
		}
	}

	/**
	 * Match Gateway::fetch @args to an endpoint
	 *
	 * @param $args array
	 * @param $test string
	 * @param $opts array
	 * @return boolean
	 */
	function _matchRoute($args, $test, $opts = []) {
		if ($pos = strpos($test, '/')) {
			$rest = substr($test, $pos);
			$test = substr($test, 0, $pos);
		} else {
			$rest = false;
		}
		if (preg_match('/{([a-z]*)}/', $test, $sp)) {
			$opts[$sp[1]] = array_shift($args);
		} else if (array_shift($args) != $test) {
			return false;
		}
		if ($rest) {
			return $this->_matchRoute($args, substr($rest, 1), $opts);
		} else if ($opts){
			return $opts;
		}
		return true;
	}

	/**
	 * Get the IRI for the SWORD statement
	 *
	 * @return string SWORD Statement IRI
	 */
	function _getStateIRI() {
		return $this->request->_protocol
			. '://'
			. $this->request->_serverHost
			. $this->request->_requestPath;
	}
}

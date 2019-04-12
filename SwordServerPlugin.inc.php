<?php

/**
 * @file plugins/gateways/swordserver/SwordServerPlugin.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SwordServerPlugin
 * @ingroup plugins_gateways_swordserver
 *
 * @brief Sword gateway plugin
 */

import('lib.pkp.classes.plugins.GatewayPlugin');
import('classes.journal.SectionDAO');
import('classes.article.ArticleDAO');
require __DIR__ . '/ServiceDocument.inc.php';
require __DIR__ . '/DepositReceipt.inc.php';
require __DIR__ . '/SwordSubmissionFileManager.inc.php';
require __DIR__ . '/SwordServerAccessPolicy.inc.php';

class SwordServerPlugin extends GatewayPlugin {


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
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	function getName() {
		return 'swordserver';
	}

	function getDisplayName() {
		return __('plugins.gateways.swordserver.displayName');
	}

	function getDescription() {
		return __('plugins.gateways.swordserver.description');
	}

	function addPolicies($request) {
		yield new SwordServerAccessPolicy($request, );
	}

	function serviceDocument() {
		$journal = $this->request->getJournal();
		$journalId = $journal->getId();
		$sectionDAO = new SectionDAO();
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

	function deposit($args) {
		$headers = getallheaders();
		$sectionId = intval(array_shift($args));
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->newDataObject();
		$submission->setContextId($this->request->getJournal()->getId());
		$submission->setDateSubmitted (Core::getCurrentDate());
		$submission->setLastModified (Core::getCurrentDate());
		$submission->setSubmissionProgress(0);
		$submission->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
		$submission->setTitle("SWORD TEST DEPOSIT " . time(), 'en_US');
		$submission->setSectionId($sectionId);
		$submission->setLocale('en_US');

		$uploadDir = ini_get('upload_tmp_dir');
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
		$match = $metsDoc->xpath("//epdcx:statement[@epdcx:propertyURI='http://purl.org/dc/" .
								 "elements/1.1/title']/epdcx:valueString");
		if (!empty($match)) {
			$title = $match[0]->__toString();
			$submission->setTitle($title, 'en_US');
		}

		$submissionId = $submissionDao->insertObject($submission);

		$hrefs = array_unique(
			array_map(function($h) {
				return $h['href']->__toString();
			}, $metsDoc->xpath("//mets:FLocat[@LOCTYPE='URL']/@xlink:href")
			)
		);
		foreach ($hrefs as $href) {
			if (file_exists($uploadDir . "/" . $href)) {
				$submissionFileManager = new SwordSubmissionFileManager($this->request->getJournal()->getId(), $submissionId);
				$submissionFile = $submissionFileManager->uploadSubmissionFile(
					$href, 2, 1, null, 11, null, null
				);
			}
		}

		// Attach the original package as well
		$submissionFileManager = new SwordSubmissionFileManager($this->request->getJournal()->getId(), $submissionId);
		$submissionFile = $submissionFileManager->uploadSubmissionFile(
			'sword_upload.dat', 2, 1, null, 11, null, null
		);

		$serverHost = $this->request->_serverHost;
		$requestPath = $this->request->_requestPath;
		$editIri = 'http://' . $serverHost .
				 substr($requestPath, 0, strrpos($requestPath, "sections")) .
				 'submissions/' . $submissionId;

		$depositReceipt = new DepositReceipt(
			[
				'title' => $submission->getTitle('en_US'),
				'edit-iri' => $editIri
			]
		);
		header('Content-Type: application/xml');
		echo $depositReceipt->saveXML();
		exit;
	}


	/**
	 * Handle fetch requests for this plugin.
	 */
	function fetch($args, $request) {
		if (!$this->getEnabled()) {
			return false;
		}
		$this->request = $request;

		$route = (array_shift($args));
		$method = $request->getRequestMethod();
		$methodsAllowed = [];
		foreach ($this->_endpoints as $endpoint) {
			if (substr($endpoint['pattern'], 0, strlen($route)) == $route) {
				if ($method == $endpoint['method']) {
					call_user_func($endpoint['handler'], $args);
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

}

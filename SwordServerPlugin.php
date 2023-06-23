<?php

/**
 * @file SwordServerPlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SwordServerPlugin
 * @brief Sword gateway plugin
 */

namespace APP\plugins\gateways\swordServer;

use PKP\plugins\GatewayPlugin;
use PKP\security\authorization\PolicySet;
use PKP\submission\Genre;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\LinkAction;
use PKP\core\JSONMessage;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\core\Core;
use PKP\submission\PKPSubmission;
use PKP\submissionFile\SubmissionFile;

use APP\facades\Repo;
use APP\publication\Publication;
use APP\core\Application;
use APP\notification\NotificationManager;
use APP\core\Services;

use APP\plugins\gateways\swordServer\ServiceDocument;
use APP\plugins\gateways\swordServer\DepositReceipt;
use APP\plugins\gateways\swordServer\SwordStatement;
use APP\plugins\gateways\swordServer\SwordServerAccessPolicy;
use APP\plugins\gateways\swordServer\SwordError;
use APP\plugins\gateways\swordServer\SwordServerSettingsForm;

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
		return 'swordServer';
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
		$sections = iterator_to_array(Repo::section()->getCollector()->filterByContextIds([$journal->getId()])->getMany(), true);

		// Exclude inactive sections
		$sections = array_filter($sections, function($section) {
			return !$section->getIsInactive();
		});
		if ($this->request->getUser()->hasRole(ROLE_ID_MANAGER, $journal->getId())) {
			// This is not a managerial user; exclude editor restricted sections.
			$sections = array_filter($sections, function($section) {
				return !$section->getEditorRestricted();
			});
		}

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
		$journal = $this->request->getJournal();
		$locale = $journal->getPrimaryLocale();
		$user = $this->request->getUser();

		// Validate and fetch the target section for this deposit.
		$section = Repo::section()->get(intval($opts['id']), $journal->getId());
		$isManager = $user->hasRole(ROLE_ID_MANAGER, $journal->getId());
		if (!$section || $section->getIsInactive() || (!$isManager && $section->getEditorRestricted())) {
			throw new Exception('Unable to determine target section!');
		}

		$authorUserGroup = Repo::userGroup()->getCollector()->filterByContextIds([$journal->getId()])->filterByIsDefault(true)->filterByRoleIds([Role::ROLE_ID_AUTHOR])->getMany()->first();
		if (!$authorUserGroup) throw new Exception('Unable to determine author user group!');

		// Save the sword package to a local file.
		$headers = getallheaders();
		if ($headers['Content-Type'] != 'application/zip' || $headers['Packaging'] != 'http://purl.org/net/sword/package/METSDSpaceSIP') throw new Exception('Unknown content type or packaging.');
		$zipPath = tempnam(sys_get_temp_dir(), 'sword');
		$zipContents = file_get_contents('php://input');
		file_put_contents($zipPath, $zipContents);
		$zip = new \ZipArchive;
		if (!$zip->open($zipPath)) throw new Exception('Unable to open zip file.');

		// Load the metadata from the package.
		$metsString = $zip->getFromName('mets.xml');
		if ($metsString === false) throw new Exception('No mets.xml document in extracted Zip package');
		$metsDoc = simplexml_load_string($metsString);
		$metsDoc->registerXPathNamespace('mets', 'http://www.loc.gov/METS/');
		$metsDoc->registerXPathNamespace('epdcx', "http://purl.org/eprint/epdcx/2006-11-16/");
		$metsDoc->registerXPathNamespace('mods', "http://www.loc.gov/mods/v3");

		// Populate a Publication object
		$publication = Repo::publication()->newDataObject();
		$publication->setData('sectionId', $section->getId());
		$publication->setData('status', PKPSubmission::STATUS_QUEUED);

		// Populate a Submission object
		$submission = Repo::submission()->newDataObject();
		$submission->setContextId($journal->getId());
		$submission->setDateSubmitted (Core::getCurrentDate());
		$submission->setLastModified (Core::getCurrentDate());
		$submission->setSubmissionProgress($isManager ? 0 : 1); // Force non-editor users to review submission steps.
		$submission->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
		$submission->setLocale($locale);
		$submission->setStatus(STATUS_QUEUED);
		$submission->setContextId($journal->getId());
		Repo::submission()->add($submission, $publication, $journal);

		$match = $metsDoc->xpath("//epdcx:statement[@epdcx:propertyURI='http://purl.org/dc/elements/1.1/title']/epdcx:valueString");
		if (!empty($match)) {
			$publication->setData('title', $match[0]->__toString(), $locale);
		}
		$match = $metsDoc->xpath("//epdcx:statement[@epdcx:propertyURI='http://purl.org/dc/terms/abstract']/epdcx:valueString");
		if (!empty($match)) {
			$publication->setData('abstract', $match[0]->__toString(), $locale);
		}
		$publication->setData('locale', $locale);
		Repo::publication()->add($publication);

		// Set the current submission publication to the new publication object.
		Repo::submission()->edit($submission, ['currentPublicationId' => $publication->getId()]);

		// Assign the user author to the stage
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$stageAssignmentDao->build($submission->getId(), $authorUserGroup->getId(), $user->getId());

		// Store the list of authors.
		$nameNodes = $metsDoc->xpath("//mods:name[mods:role/mods:roleTerm='author' or mods:role/mods:roleTerm='pkp_primary_contact']");
		$i = 0;
		foreach ($nameNodes as $nameNode) {
			$nameNode->registerXPathNamespace('mods', 'http://www.loc.gov/mods/v3');
			$email = $nameNode->xpath("mods:nameIdentifier[@type='email']");
			$given = $nameNode->xpath("mods:namePart[@type='given']");
			$family = $nameNode->xpath("mods:namePart[@type='family']");
			$primary = $nameNode->xpath("mods:role[mods:roleTerm='pkp_primary_contact']");
			if (!empty($email) && (!empty($given) || !empty($family))) {
				$author = Repo::author()->newDataObject();
				$author->setEmail($email[0]->__toString());
				$author->setSequence(++$i);
				$author->setData('publicationId', $publication->getId());
				$author->setUserGroupId($authorUserGroup->getId());
				$author->setIncludeInBrowse(true);
				if ($family) $author->setData('familyName', $family[0]->__toString(), $locale);
				if ($given) $author->setData('givenName', $given[0]->__toString(), $locale);

				Repo::author()->add($author);

				if ($primary) Repo::publication()->edit($publication, ['primaryContactId' => $author->getId()]);
			}
		}

		// Attach all included files to the submission
		$hrefs = array_unique(
			array_map(function($h) {
				return $h['href']->__toString();
			}, $metsDoc->xpath("//mets:FLocat[@LOCTYPE='URL']/@xlink:href")
			)
		);
		foreach ($hrefs as $href) {
			if (($fileContents = $zip->getFromName($href)) !== false) {
				// Add a file entry
				$filePath = tempnam(sys_get_temp_dir(), 'sword');
				file_put_contents($filePath, $fileContents);
				$this->_addFile($submission, $filePath, $href, $locale);
				unlink($filePath);
			}
		}

		$zip->close();

		// Create and send the deposit receipt
		$depositReceipt = new DepositReceipt([
			'title' => $submission->getTitle($locale),
			'edit-iri' => $this->request->getRouter()->url($this->request, null, null, null, ['swordServer', 'submissions', $submission->getId()]),
			'stmt-iri' => $this->request->getRouter()->url($this->request, null, null, null, ['swordServer', 'submissions', $submission->getId(), 'statement']),
			'alternateLink' => $this->request->getRouter()->url($this->request, null, 'submission', 'wizard', [1], ['submissionId' => $submission->getId()]),
		]);
		header('Content-Type: application/xml');
		echo $depositReceipt->saveXML();
		exit;
	}

	/**
	 * Add a file to the submission from the SWORD deposit.
	 * @param Submission $submission
	 * @param string $localFilename The name of the file to add on the local filesystem
	 * @param string $targetFilename The name of the file to represent in the target submission
	 * @param string $locale Locale code xx_YY
	 */
	protected function _addFile($submission, $localFilename, $targetFilename, $locale) {
		// Identify a genre for uploaded files.
		$genre = DAORegistry::getDAO('GenreDAO')->getByKey('OTHER', $this->request->getJournal()->getId());
		if (!$genre) throw new Exception('Could not find genre with key OTHER for SWORD deposit!');

		$submissionDir = Repo::submissionFile()->getSubmissionDir($submission->getData('contextId'), $submission->getId());
		$fileService = Services::get('file');
		$info = pathinfo($targetFilename);
		$newFileId = $fileService->add(
			$localFilename,
			$submissionDir . '/' . uniqid() . '.' . ($info['extension'] ?? 'txt')
		);

		// Add a submission file entry
		$submissionFile = new SubmissionFile();
		$submissionFile->setData('fileStage', SUBMISSION_FILE_SUBMISSION);
		$submissionFile->setData('viewable', true);
		$submissionFile->setData('fileId', $newFileId);
		$submissionFile->setData('submissionId', $submission->getId());
		$submissionFile->setData('name', $targetFilename, $locale);
		$submissionFile->setData('genreId', $genre->getId());

		return Repo::submissionFile()->add($submissionFile);
	}

	/**
	 * Handle a SWORD Statement request
	 *
	 * @param $opts array
	 */
	function statement($opts) {
		$locale = Locale::getDefault();
		$submission = Repo::submission()->get($opts['id']);

		// Ensure that the requested submission is in the appropriate journal
		if ($submission->getContextId() != $this->getRequest()->getJournal()->getId()) {
			throw new Exception('The specified submission is not allowed!');
		}
		$section = Repo::section()->get($submission->getSectionId());
		$swordStatement = new SwordStatement(
			[
				'state_href' => $this->request->getRouter()->url($this->request, null, null, null, ['swordServer', 'submissions', $submission->getId(), 'statement']),
				'state_description' => 'Deposited to ' . $section->getData('title', $locale),
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
							'summary' => 'Application Error: ' . $e->__toString()
						]);

						header('Content-Type: application/xml');
						header('HTTP/1.1 500 Not ServerError');
						header('Allow ' . implode(', ', $methodsAllowed));
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
	 * @copydoc Plugin::getActions()
	 */
	public function getActions($request, $verb) {
		$router = $request->getRouter();
		return array_merge(
			$this->getEnabled()?array(
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'gateways')),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			):array(),
			parent::getActions($request, $verb)
		);
	}

 	/**
	 * @copydoc Plugin::manage()
	 */
	public function manage($args, $request) {
		switch ($request->getUserVar('verb')) {
			case 'settings':
				$form = new SwordServerSettingsForm($this, $request->getContext()->getId());

				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						$notificationManager = new NotificationManager();
						$notificationManager->createTrivialNotification($request->getUser()->getId());
						return new JSONMessage(true);
					}
				} else {
					$form->initData();
				}
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}
}

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


class SwordServerPlugin extends GatewayPlugin {
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

	/**
	 * Handle fetch requests for this plugin.
	 */
	function fetch($args, $request) {

		if (!$this->getEnabled()) {
			return false;
		}

		switch (array_shift($args)) {
		case 'servicedocument':
			if (!$request->isGet()) {
				return false;
			}
			$journal = $request->getJournal();
			$journalId = $journal->getId();
			$sectionDAO = new SectionDAO();
			$resultSet = $sectionDAO->getByJournalId($journalId);
			$sections = $resultSet->toAssociativeArray();
			$serviceDocument = new ServiceDocument(
				$journal,
				$sections,
				$request->getRequestUrl()
			);
			header('Content-Type: application/xml');
			echo $serviceDocument->saveXML();
			exit;
		case 'sections':
			if (!$request->isPost()) {
				return false;
			}
			$headers = getallheaders();
			$sectionId = intval(array_shift($args));
			$submissionDao = Application::getSubmissionDAO();
			$submission = $submissionDao->newDataObject();
			$submission->setContextId($request->getJournal()->getId());
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
							$submissionFileManager = new SwordSubmissionFileManager($request->getJournal()->getId(), $submissionId);
							$submissionFile = $submissionFileManager->uploadSubmissionFile(
									$href, 2, 1, null, 11, null, null
							);
					}
			}

			// Attach the original package as well
			$submissionFileManager = new SwordSubmissionFileManager($request->getJournal()->getId(), $submissionId);
			$submissionFile = $submissionFileManager->uploadSubmissionFile(
					'sword_upload.dat', 2, 1, null, 11, null, null
			);

			$serverHost = $request->_serverHost;
			$requestPath = $request->_requestPath;
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

		// Failure.
		header('HTTP/1.0 404 Not Found');
		$templateMgr = TemplateManager::getManager($request);
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON);
		$templateMgr->assign('message', 'plugins.gateways.swordserver.errors.errorMessage');
		$templateMgr->display('frontend/pages/message.tpl');
		exit;
	}
}

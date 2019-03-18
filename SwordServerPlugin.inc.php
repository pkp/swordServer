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
            // Not pictured: authn / authz, actually saving submission files, etc.
            // This is just going to create a submission and respond.
            $sectionId = intval(array_shift($args));
            $submissionDao = Application::getSubmissionDAO();
            $submission = $submissionDao->newDataObject();
            $submission->setDateSubmitted (Core::getCurrentDate());
            $submission->setLastModified (Core::getCurrentDate());
            $submission->setSubmissionProgress(0);
            $submission->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
            $submission->setTitle("SWORD TEST DEPOSIT " . time(), 'en_US');
            $submission->setSectionId($sectionId);
            $submission->setLocale('en_US');
            $submissionId = $submissionDao->insertObject($submission);
            error_log("SECTIONS " . $sectionId);

            // file_put_contents('/tmp/test.txt', file_get_contents('php://input'));
            $depositReceipt = new DepositReceipt(
                $submission->getTitle('en_US')
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

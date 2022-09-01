<?php

/**
 * @file SwordServerSettingsForm.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SwordServerSettingsForm
 * @brief Form for managers to modify sword server plugin settings
 */

import('lib.pkp.classes.form.Form');

class SwordServerSettingsForm extends Form {

	/** @var int Associated context ID */
	private $_contextId;

	/** @var SwordServerPlugin SWORD server plugin */
	private $_plugin;

	/**
	 * Constructor
	 * @param $plugin SwordServerPlugin SWORD server plugin
	 * @param $contextId int Context ID
	 */
	function __construct($plugin, $contextId) {
		$this->_contextId = $contextId;
		$this->_plugin = $plugin;

		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Initialize form data.
	 */
	function initData() {
		$contextId = $this->_contextId;
		$plugin = $this->_plugin;
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
	}

	/**
	 * Fetch the form.
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'pluginName' => $this->_plugin->getName(),
		]);
		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionArgs) {
		parent::execute(...$functionArgs);
	}
}

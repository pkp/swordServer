{**
 * templates/settingsForm.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Sword server plugin settings
 *
 *}
<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#swordServerSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<style type="text/css">
pre {
    white-space: pre-wrap;       /* Since CSS 2.1 */
    white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
    white-space: -pre-wrap;      /* Opera 4-6 */
    white-space: -o-pre-wrap;    /* Opera 7 */
    word-wrap: break-word;       /* Internet Explorer 5.5+ */
}
</style>

<form class="pkp_form" id="swordServerSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="gateways" plugin="swordServer" verb="settings" save=true}">
	<div id="swordServerSettings">

		{csrf}
		{include file="controllers/notification/inPlaceNotification.tpl" notificationId="swordServerSettingsFormNotification"}

		{fbvFormArea id="swordServerSettingsFormArea"}
			{capture assign="exampleDepositPointUrl"}{url router=$smarty.const.ROUTE_PAGE page="gateway" op="plugin" path="swordServer"|to_array:"sections":"1"}{/capture}
			{capture assign="serviceDocumentUrl"}{url router=$smarty.const.ROUTE_PAGE page="gateway" op="plugin" path="swordServer"|to_array:"servicedocument"}{/capture}
			{translate key="plugins.gateways.swordserver.servicedocument" serviceDocumentUrl=$serviceDocumentUrl|escape exampleDepositPointUrl=$exampleDepositPointUrl|escape}
		{/fbvFormArea}

		{fbvFormButtons}
	</div>
</form>

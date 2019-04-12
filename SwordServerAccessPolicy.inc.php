<?php
/**
 * @file classes/security/authorization/SwordServerAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SwordServerAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Class to that makes sure that a user is logged in.
 */

import('lib.pkp.classes.security.authorization.PolicySet');
require __DIR__ . '/SwordError.inc.php';
class SwordServerAccessPolicy extends AuthorizationPolicy {

	function __construct($request) {
		$this->request = $request;
	}

	function unauthorizedResponse() {
		$swordError = new SwordError([
			'summary' => "You are not authorized to make this request"
		]);
		header('Content-Type: application/xml');
		header("HTTP/1.1 401 Unauthorized");

		echo $swordError->saveXML();
		exit;
	}

	function effect() {
		$callOnDeny = array($this, 'unauthorizedResponse', array());
		$this->setAdvice(AUTHORIZATION_ADVICE_CALL_ON_DENY, $callOnDeny);
		$headers = getallheaders();
		if (array_key_exists('Authorization', $headers)) {
			$auth_header = $headers["Authorization"];
			$userPass = base64_decode(substr($auth_header, 6));
			$userPass = explode(":", $userPass);

			if (!Validation::checkCredentials($userPass[0], $userPass[1])) {
				return AUTHORIZATION_DENY;
			}

			$userDao = new UserDao();
			$user = $userDao->getByUsername($userPass[0]);
			if (! $user->hasRole(ROLE_ID_MANAGER, $this->request->getJournal()->getId())) {
				return AUTHORIZATION_DENY;
			}

			return AUTHORIZATION_PERMIT;
		}
		return AUTHORIZATION_DENY;
	}
}

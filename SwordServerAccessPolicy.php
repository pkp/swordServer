<?php
/**
 * @file SwordServerAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SwordServerAccessPolicy
 * @brief Class to that makes sure that a user is logged in.
 */

namespace APP\plugins\gateways\swordServer;

use \Firebase\JWT\JWT;

use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\Validation;
use PKP\db\DAORegistry;
use PKP\core\Registry;
use PKP\security\Role;

use APP\facades\Repo;

class SwordServerAccessPolicy extends AuthorizationPolicy {

	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function __construct($request) {
		$this->request = $request;
	}

	/**
	 * Serve a SWORD Error Document to unauthorized requests
	 */
	function unauthorizedResponse() {
		$swordError = new SwordError([
			'summary' => "You are not authorized to make this request"
		]);
		header('Content-Type: application/xml');
		header("HTTP/1.1 401 Unauthorized");
		error_log('Unauthorized access to PKP SWORD gateway.');
		echo $swordError->saveXML();
		exit;
	}

	/**
	 * @copydoc AuthorizationPolicy::effect()
	 */
	function effect() {
		$callOnDeny = [$this, 'unauthorizedResponse', []];
		$this->setAdvice(AUTHORIZATION_ADVICE_CALL_ON_DENY, $callOnDeny);
		$headers = getallheaders();
		$user = null;
		// 1. Try Http Basic Auth
		if (array_key_exists('Authorization', $headers)) {
			$auth_header = $headers["Authorization"];
			$userPass = base64_decode(substr($auth_header, 6));
			$userPass = explode(":", $userPass);
			if (Validation::checkCredentials($userPass[0], $userPass[1])) {
				$user = Repo::user()->getByUsername($userPass[0]);
				Registry::set('user', $user);
			}
		}
		// 2. Try API Key
		if (!$user && $apiToken = ($headers['X-Ojs-Sword-Api-Token'] ?? null)) {
				$secret = Config::getVar('security', 'api_key_secret', '');
			try {
				$decoded = JWT::decode($apiToken, $secret, ['HS256']);
				// Compatibility with old API keys
				// https://github.com/pkp/pkp-lib/issues/6462
				if (substr($decoded, 0, 2) === '""') {
					$decoded = json_decode($decoded);
				}
				$userDao = DAORegistry::getDAO('UserDAO');
				$user = $userDao->getBySetting('apiKey', $decoded);
				Registry::set('user', $user);
			} catch (Firebase\JWT\SignatureInvalidException $e) {
			} catch (DomainException $e) {
			}
		}

		if ($user && $user->hasRole(Role::ROLE_ID_AUTHOR, $this->request->getJournal()->getId())) {
			$this->addAuthorizedContextObject(ASSOC_TYPE_USER, $user);
			return AUTHORIZATION_PERMIT;
		}
		return AUTHORIZATION_DENY;
	}
}

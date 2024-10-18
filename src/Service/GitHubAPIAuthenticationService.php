<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Service;

use Combodo\iTop\VCSManagement\Helper\ModuleHelper;
use Combodo\iTop\VCSManagement\Helper\SessionHelper;
use DateTime;
use DateTimeZone;
use DBObject;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

/**
 * Service responsible for the GitHub API authentication.
 */
class GitHubAPIAuthenticationService extends AbstractGitHubAPI
{
	// global
	private static string $API_VERSION = '2022-11-28';
	private static int $LIFETIME = 5 * 60;
	private static string $JWT_ALGORITHM = 'RS256';

	// authentication
	private static string $AUTHENTICATION_MODE_PERSONAL_TOKEN = 'personal';
	private static string $AUTHENTICATION_MODE_APP_USER_INSTALLATION_TOKEN = 'app_user';
	private static string $AUTHENTICATION_MODE_APP_REPOSITORY_INSTALLATION_TOKEN = 'app_repository';
	private static string $AUTHENTICATION_MODE_APP_ORGANIZATION_INSTALLATION_TOKEN = 'app_organization';

	/** @var GitHubAPIAuthenticationService|null Singleton */
	static private ?GitHubAPIAuthenticationService $oSingletonInstance = null;

	/**
	 * GetInstance.
	 *
	 * @return GitHubAPIAuthenticationService
	 * @throws \Exception
	 */
	public static function GetInstance(): GitHubAPIAuthenticationService
	{
		if (is_null(self::$oSingletonInstance)) {
			self::$oSingletonInstance = new GitHubAPIAuthenticationService();
		}

		return self::$oSingletonInstance;
	}

	/**
	 * Create authorization request header.
	 *
	 * @param \VCSWebhook $oWebhook VCS Webhook
	 *
	 * @return array header elements array
	 * @throws \CoreException
	 */
	public function CreateAuthorizationHeader(DBObject $oWebhook) : array
	{
		$oConnector = $oWebhook->GetConnector();
		$sMode = $oConnector->Get('mode');

		// get authorization header
		$sAuthorizationHeader = match ($sMode)
		{
			self::$AUTHENTICATION_MODE_PERSONAL_TOKEN => self::GetPersonalTokenAuthorizationHeader($oConnector),
			self::$AUTHENTICATION_MODE_APP_REPOSITORY_INSTALLATION_TOKEN,
			self::$AUTHENTICATION_MODE_APP_USER_INSTALLATION_TOKEN,
			self::$AUTHENTICATION_MODE_APP_ORGANIZATION_INSTALLATION_TOKEN => self::GetAppInstallationAccessTokenAuthorizationHeader($oConnector, $oWebhook, $sMode),
			default => null,
		};

		return [
			'Accept' => 'application/vnd.github+json',
			'Authorization' => $sAuthorizationHeader,
			'X-GitHub-Api-Version' => self::$API_VERSION
		];
	}

	/**
	 * Create app authorization request header.
	 *
	 * @param  DBObject $oConnector VCS Connector
	 *
	 * @return array header elements array
	 * @throws \CoreException
	 */
	private function CreateAppAuthorizationHeader(DBObject $oConnector) : array
	{
		return [
			'Accept' => 'application/vnd.github+json',
			'Authorization' =>  self::GetAppAuthorizationHeader($oConnector),
			'X-GitHub-Api-Version' => self::$API_VERSION
		];
	}

	/**
	 * Get app authorization header.
	 *
	 * @param \DBObject $oConnector VCS Connector
	 *
	 * @return string
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	private function GetAppAuthorizationHeader(DBObject $oConnector) : string
	{
		return 'Bearer ' . self::CreateAppJWT($oConnector);
	}

	/**
	 * Create an app JWT.
	 *
	 * @param \DBObject $oConnector VCS Connector
	 *
	 * @return string
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	private function CreateAppJWT(DBObject $oConnector): string
	{
		// get authentication information
		$sAppId = $oConnector->Get('app_id');
		$sAppPrivateKey = $oConnector->Get('app_private_key');

		// prepare payload
		$aPayload = [
			'iat' => time() - 60,
			'exp' => time() + self::$LIFETIME,
			'iss' => $sAppId,
			'alg' => self::$JWT_ALGORITHM
		];

		return ModuleHelper::CallFunctionWithoutDisplayingPHPErrors(function() use ($aPayload,$sAppPrivateKey) {
			return JWT::encode($aPayload, $sAppPrivateKey, self::$JWT_ALGORITHM);
		});
	}

	/**
	 * Get personal token authorization header.
	 *
	 * @param \DBObject $oConnector VCS Connector
	 *
	 * @return string
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	private function GetPersonalTokenAuthorizationHeader(DBObject $oConnector) : string
	{
		return 'Bearer ' . $oConnector->Get('personal_access_token');
	}

	/**
	 * Get the connector session name.
	 *
	 * @param \DBObject $oConnector
	 *
	 * @return string
	 */
	private function GetConnectorSessionName(DBObject $oConnector) : string
	{
		return 'connectors_' . $oConnector->GetKey();
	}

	/**
	 * Get app installation authorization header.
	 *
	 * @param DBObject $oConnector VCS Connector
	 * @param DBObject $oWebhook VCS Webhook
	 * @param string $sType Authentication type
	 *
	 * @return string
	 * @throws \CoreException
	 */
	private function GetAppInstallationAccessTokenAuthorizationHeader(DBObject $oConnector, DBObject $oWebhook, string $sType) : string
	{
		$sName = $this->GetConnectorSessionName($oConnector);

		// no session token or expired
		if(!SessionHelper::IsSetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN, $sName)
		|| self::IsCurrentAppInstallationTokenExpired($sName) ){

			// app installation ID
			$sInstallationId = SessionHelper::IsSetVar(SessionHelper::$SESSION_APP_INSTALLATION_ID, $sName) ?
				SessionHelper::GetVar(SessionHelper::$SESSION_APP_INSTALLATION_ID, $sName) : $this->GetAppInstallation($oConnector, $oWebhook, $sType)['id'];

			// create app installation access token
			$aResponse = self::CreateApplicationInstallationAccessToken($oConnector, $sInstallationId);
			$sAppInstallationAccessToken = $aResponse['token'];

			// log
			ModuleHelper::LogDebug('Create new application access token for Webhook ' . $sName);

			// store it in session
			SessionHelper::SetVar(SessionHelper::$SESSION_APP_INSTALLATION_ID, $sName, $sInstallationId);
			SessionHelper::SetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN, $sName, $sAppInstallationAccessToken);
			SessionHelper::SetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN_EXPIRATION_DATE, $sName, $aResponse['expires_at']);
		}
		else{

			// get session app installation access token
			$sAppInstallationAccessToken = SessionHelper::GetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN, $sName);
		}

		return 'Bearer ' . $sAppInstallationAccessToken;
	}

	/**
	 * Regenerate an access token.
	 *
	 * @param \DBObject $oConnector
	 *
	 * @return void
	 */
	public function RegenerateAccessToken(DBObject $oConnector) : void
	{
		$sName = $this->GetConnectorSessionName($oConnector);
		SessionHelper::UnsetVar(SessionHelper::$SESSION_APP_INSTALLATION_ID, $sName);
		SessionHelper::UnsetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN, $sName);
		SessionHelper::UnsetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN_EXPIRATION_DATE, $sName);
	}

	/**
	 * @param \DBObject $oConnector VCS Connector
	 * @param \DBObject $oWebhook VCS Webhook
	 * @param string $sType Authentication type
	 *
	 * @return array
	 * @throws \CoreException
	 */
	private function GetAppInstallation(DBObject $oConnector, DBObject $oWebhook, string $sType) : array
	{
		return match($sType){
			self::$AUTHENTICATION_MODE_APP_REPOSITORY_INSTALLATION_TOKEN => $this->GetRepositoryAppInstallation($oConnector, $oWebhook),
			self::$AUTHENTICATION_MODE_APP_USER_INSTALLATION_TOKEN => $this->GetUSerAppInstallation($oConnector),
			self::$AUTHENTICATION_MODE_APP_ORGANIZATION_INSTALLATION_TOKEN => $this->GetOrganizationAppInstallation($oConnector),
		};
	}

	/**
	 * Get repository application installation.
	 *
	 * https://docs.github.com/en/rest/apps/apps?apiVersion=2022-11-28#get-a-repository-installation-for-the-authenticated-app
	 * GET /repos/{owner}/{repo}/installation
	 *
	 * @param DBObject $oConnector VCS Connector
	 * @param DBObject $oWebhook VCS Webhook
	 *
	 * @return array
	 * @throws \CoreException
	 */
	private function GetRepositoryAppInstallation(DBObject $oConnector, DBObject $oWebhook) : array
	{
		// log
		ModuleHelper::LogDebug(__FUNCTION__);

		// retrieve useful settings
		$sRepositoryName = $oConnector->Get('app_repository_name');
		$sOwner = $oConnector->Get('app_repository_owner');

		// API call
		$client = new Client();
		$request = new Request('GET',  $this->GetAPIUri("/repos/$sOwner/$sRepositoryName/installation"), $this->CreateAppAuthorizationHeader($oConnector));
		$res = $client->sendAsync($request)->wait();

		return json_decode($res->getBody(), true);
	}

	/**
	 * Get user application installation.
	 *
	 * https://docs.github.com/en/rest/apps/apps?apiVersion=2022-11-28#get-a-user-installation-for-the-authenticated-app
	 * GET /users/{username}/installation
	 *
	 * @param DBObject $oConnector VCS Connector
	 *
	 * @return array
	 * @throws \CoreException
	 */
	private function GetUserAppInstallation(DBObject $oConnector) : array
	{
		// log
		ModuleHelper::LogDebug(__FUNCTION__);

		// retrieve useful settings
		$sUser = $oConnector->Get('app_user_name');

		// API call
		$client = new Client();
		$request = new Request('GET',  $this->GetAPIUri("/repos/$sUser/installation"), $this->CreateAppAuthorizationHeader($oConnector));
		$res = $client->sendAsync($request)->wait();

		return json_decode($res->getBody(), true);
	}

	/**
	 * Get organization application installation.
	 *
	 * https://docs.github.com/en/rest/apps/apps?apiVersion=2022-11-28#get-an-organization-installation-for-the-authenticated-app
	 * GET /orgs/{org}/installation
	 *
	 * @param DBObject $oConnector VCS Connector
	 *
	 * @return array
	 * @throws \CoreException
	 */
	private function GetOrganizationAppInstallation(DBObject $oConnector) : array
	{
		// log
		ModuleHelper::LogDebug(__FUNCTION__);

		// retrieve useful settings
		$sOrganization = $oConnector->Get('app_organization_name');

		// API call
		$client = new Client();
		$request = new Request('GET',  $this->GetAPIUri("/orgs/$sOrganization/installation"), $this->CreateAppAuthorizationHeader($oConnector));
		$res = $client->sendAsync($request)->wait();

		return json_decode($res->getBody(), true);
	}

	/**
	 * Create application installation access token.
	 *
	 * https://docs.github.com/en/rest/apps/apps?apiVersion=2022-11-28#create-an-installation-access-token-for-an-app
	 * POST /app/installations/{installation_id}/access_tokens
	 *
	 * @param DBObject $oConnector
	 * @param string $InstallationId ID of the application installation.
	 *
	 * @return array Application installation access token.
	 * @throws \CoreException
	 */
	private function CreateApplicationInstallationAccessToken(DBObject $oConnector, string $InstallationId) : array
	{
		// log
		ModuleHelper::LogDebug(__FUNCTION__);

		// API call
		$client = new Client();
		$request = new Request('POST', $this->GetAPIUri("/app/installations/$InstallationId/access_tokens"), $this->CreateAppAuthorizationHeader($oConnector));
		$res = $client->sendAsync($request)->wait();
		return json_decode($res->getBody(), true);
	}

	/**
	 * Check if current app installation access token is expired.
	 *
	 * @param string $sRepository
	 *
	 * @return bool
	 */
	static public function IsCurrentAppInstallationTokenExpired(string $sRepository): bool
	{
		try{
			// no session var
			if(!SessionHelper::IsSetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN_EXPIRATION_DATE, $sRepository))
				return true;

			// compute dates
			$oDateNow = new DateTime('now',  new DateTimeZone('Z'));
			$oDateExpiration = new DateTime(SessionHelper::GetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN_EXPIRATION_DATE, $sRepository));

			// now > expiration_date
			return $oDateNow->getTimestamp() > $oDateExpiration->getTimestamp();
		}
		catch(Exception){

			return true;
		}
	}
}
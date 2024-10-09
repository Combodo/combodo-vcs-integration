<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Service;

use Combodo\iTop\VCSManagement\Helper\ModuleHelper;
use Combodo\iTop\VCSManagement\Helper\SessionHelper;
use DBObject;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use MetaModel;

/**
 * Service responsible for the GitHub API requests.
 */
class GitHubAPIService
{
	// global
	private static string $BASE_URL = 'https://api.github.com';
	private static string $API_VERSION = '2022-11-28';
	private static int $LIFETIME = 5 * 60;

	// authentication
	private static string $AUTHENTICATION_MODE_PERSONAL_TOKEN = 'personal';
	private static string $AUTHENTICATION_MODE_APP_INSTALLATION_TOKEN = 'app';

	/** @var GitHubAPIService|null Singleton */
	static private ?GitHubAPIService $oSingletonInstance = null;

	/**
	 * GetInstance.
	 *
	 * @return GitHubAPIService
	 * @throws \Exception
	 */
	public static function GetInstance(): GitHubAPIService
	{
		if (is_null(self::$oSingletonInstance)) {
			self::$oSingletonInstance = new GitHubAPIService();
		}

		return self::$oSingletonInstance;
	}

	/**
	 * Create a resource URI.
	 *
	 * @param string $sResource
	 *
	 * @return string
	 */
	private function GetAPIUri(string $sResource) : string
	{
		return static::$BASE_URL . $sResource;
	}

	/**
	 * Create app authorization request header.
	 *
	 * @param  DBObject $oConnector
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
	 * @param \DBObject $oConnector
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
	 * @param \DBObject $oConnector
	 *
	 * @return string
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	public function CreateAppJWT(DBObject $oConnector): string
	{
		// get authentication information
		$sAppId = $oConnector->Get('app_id');
		$sAppPrivateKey = $oConnector->Get('app_private_key');

		// prepare payload
		$aPayload = [
			'iat' => time() - 60,
			'exp' => time() + self::$LIFETIME,
			'iss' => $sAppId,
			'alg' => 'RS256'
		];

		return ModuleHelper::CallFunctionWithoutDisplayingPHPErrors(function() use ($aPayload,$sAppPrivateKey) {
			return JWT::encode($aPayload, $sAppPrivateKey, 'RS256');
		});
	}

	/**
	 * Create authorization request header.
	 *
	 * @param DBObject $oWebhook VCS Webhook
	 *
	 * @return array header elements array
	 * @throws \CoreException
	 */
	private function CreateAuthorizationHeader(DBObject $oWebhook) : array
	{
		$oConnector = MetaModel::GetObject('VCSConnector', $oWebhook->Get('connector_id'));

		// get authorization header
		$sAuthorizationHeader = match ($oConnector->Get('mode'))
		{
			self::$AUTHENTICATION_MODE_PERSONAL_TOKEN => self::GetPersonalTokenAuthorizationHeader($oWebhook),
			self::$AUTHENTICATION_MODE_APP_INSTALLATION_TOKEN => self::GetAppInstallationAccessTokenAuthorizationHeader($oWebhook),
			default => null,
		};

		return [
			'Accept' => 'application/vnd.github+json',
			'Authorization' => $sAuthorizationHeader,
			'X-GitHub-Api-Version' => self::$API_VERSION
		];
	}

	/**
	 * Get personal token authorization header.
	 *
	 * @param \DBObject $oWebhook
	 *
	 * @return string
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	private function GetPersonalTokenAuthorizationHeader(DBObject $oWebhook) : string
	{
		$oConnector = MetaModel::GetObject('VCSConnector', $oWebhook->Get('connector_id'));

		return 'Bearer ' . $oConnector->Get('personal_access_token');
	}


	/**
	 * Get app installation authorization header.
	 *
	 * @param DBObject $oWebhook
	 *
	 * @return string
	 * @throws \CoreException
	 */
	private function GetAppInstallationAccessTokenAuthorizationHeader(DBObject $oWebhook) : string
	{
		$sWebhookName = $oWebhook->Get('name');

		// no session token or expired
		if(!SessionHelper::IsSetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN, $sWebhookName)
		|| SessionHelper::IsCurrentAppInstallationTokenExpired($sWebhookName) ){

			// app installation ID
			$sInstallationId = SessionHelper::IsSetVar(SessionHelper::$SESSION_APP_INSTALLATION_ID, $sWebhookName) ?
				SessionHelper::GetVar(SessionHelper::$SESSION_APP_INSTALLATION_ID, $sWebhookName) : $this->GetRepositoryAppInstallation($oWebhook)['id'];

			// create app installation access token
			$aResponse = self::CreateApplicationInstallationAccessToken($oWebhook, $sInstallationId);
			$sAppInstallationAccessToken = $aResponse['token'];

			// log
			ModuleHelper::LogDebug('Create new application access token for Webhook ' . $sWebhookName);

			// store it in session
			SessionHelper::SetVar(SessionHelper::$SESSION_APP_INSTALLATION_ID, $sWebhookName, $sInstallationId);
			SessionHelper::SetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN, $sWebhookName, $sAppInstallationAccessToken);
			SessionHelper::SetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN_EXPIRATION_DATE, $sWebhookName, $aResponse['expires_at']);
		}
		else{

			// get session app installation access token
			$sAppInstallationAccessToken = SessionHelper::GetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN, $sWebhookName);
		}

		return 'Bearer ' . $sAppInstallationAccessToken;
	}


	/**
	 * Check if a webhook configuration with id exist.
	 *
	 * @param DBObject $oWebhook The Webhook.
	 * @param string $sHookId The webhook configuration id.
	 *
	 * @return array
	 * @throws \CoreException
	 */
	public function RepositoryWebhookConfigurationExist(DBObject $oWebhook, string $sHookId) : array
	{
		try{
			$data = $this->GetRepositoryWebhookConfiguration($oWebhook, $sHookId);

			return [
				'configuration_exist' => true,
				'github_data' => $data
			];
		}
		catch(ClientException $e){

			// not found
			if($e->getResponse()->getStatusCode() === 404){
				return [
					'configuration_exist' => false,
					'github_data' => null
				];
			}

			throw $e;
		}
	}

	// API /////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Get information about a GitHub repository.
	 *
	 * https://docs.github.com/fr/rest/repos/repos?apiVersion=2022-11-28#get-a-repository
	 * GET /repos/{owner}/{repo}
	 *
	 * @param DBObject $oWebhook. The Webhook
	 *
	 * @return array The repository information, including the number of watchers, forks,
	 *               open issues, and clone URL.
	 * @throws \CoreException
	 */
	public function GetRepositoryInfo(DBObject $oWebhook) : array
	{
		// log
		ModuleHelper::LogDebug('GetRepositoryInfo');

		// retrieve useful settings
		$sOwner = MetaModel::GetObject('VCSConnector', $oWebhook->Get('connector_id'))->Get('owner');
		$sRepositoryName = $oWebhook->Get('name');

		// API call
		$client = new Client();
		$request = new Request('GET',  self::GetAPIUri("/repos/$sOwner/$sRepositoryName"), $this->CreateAuthorizationHeader($oWebhook));
		$res = $client->sendAsync($request)->wait();
		$object = json_decode($res->getBody(), true);

		return [
			'watchers_count' => $object['watchers_count'],
			'forks' => $object['forks'],
			'open_issues' => $object['open_issues'],
			'clone_url' => $object['clone_url'],
			'description' => $object['description'],
			'owner' => [
				'login' => $object['owner']['login'],
				'avatar_url' => $object['owner']['avatar_url'],
				'url' => $object['owner']['html_url']
			]
		];
	}

	/**
	 * Create a webhook for a GitHub repository.
	 *
	 * https://docs.github.com/fr/rest/repos/webhooks?apiVersion=2022-11-28#create-a-repository-webhook
	 * POST /repos/{owner}/{repo}/hooks
	 *
	 * @param DBObject $oWebhook The webhook.
	 * @param string $sUrl The webhook url.
	 * @param string $sSecret The shared secret for the webhook.
	 * @param array $aListeningEvents events to listen
	 *
	 * @return array The created webhook object.
	 * @throws \CoreException
	 */
	public function CreateRepositoryWebhook(DBObject $oWebhook, string $sUrl, string $sSecret, array $aListeningEvents) : array
	{
		// log
		ModuleHelper::LogDebug('CreateRepositoryWebhook');

		// retrieve useful settings
		$sOwner = MetaModel::GetObject('VCSConnector', $oWebhook->Get('connector_id'))->Get('owner');
        $sRepositoryName = $oWebhook->Get('name');

		// request body
		$aBody = [
			"name" => "web",
			"active" => true,
			"events" => $aListeningEvents,
			"config" => [
				"url" => $sUrl,
				"content_type" => "json",
				"insecure_ssl" => "0",
				"secret" => $sSecret
			]
		];

		// API call
		$client = new Client();
		$request = new Request('POST',  self::GetAPIUri("/repos/$sOwner/$sRepositoryName/hooks"), $this->CreateAuthorizationHeader($oWebhook), json_encode($aBody,JSON_UNESCAPED_SLASHES));
		$res = $client->sendAsync($request)->wait();

		return json_decode($res->getBody(), true);
	}

	/**
	 * Update a webhook for a GitHub repository.
	 *
	 * https://docs.github.com/en/rest/repos/webhooks?apiVersion=2022-11-28#update-a-repository-webhook
	 * PATCH /repos/{owner}/{repo}/hooks/{hook_id}
	 *
	 * @param DBObject $oWebhook The webhook.
	 * @param string $sUrl The webhook url.
	 * @param string $sHookId The webhook configuration id.
	 * @param string $sSecret The shared secret for the webhook.
	 * @param array $aListeningEvents events to listen
	 *
	 * @return array The created webhook object.
	 * @throws \CoreException
	 */
	public function UpdateRepositoryWebhook(DBObject $oWebhook, string $sUrl, string $sHookId, string $sSecret, array $aListeningEvents) : array
	{
		// log
		ModuleHelper::LogDebug('UpdateRepositoryWebhook');

		// retrieve useful settings
		$sOwner = MetaModel::GetObject('VCSConnector', $oWebhook->Get('connector_id'))->Get('owner');
        $sRepositoryName = $oWebhook->Get('name');

		// request body
		$aBody = [
			"events" => $aListeningEvents,
			"config" => [
				"url" => $sUrl,
				"secret" => $sSecret
			]
		];

		// API call
		$client = new Client();
		$request = new Request('PATCH',  self::GetAPIUri("/repos/$sOwner/$sRepositoryName/hooks/$sHookId"), $this->CreateAuthorizationHeader($oWebhook), json_encode($aBody,JSON_UNESCAPED_SLASHES));
		$res = $client->sendAsync($request)->wait();

		return json_decode($res->getBody(), true);
	}

	/**
	 * Delete a webhook for a GitHub repository.
	 *
	 * https://docs.github.com/en/rest/repos/webhooks?apiVersion=2022-11-28#delete-a-repository-webhook
	 * DELETE /repos/{owner}/{repo}/hooks/{hook_id}
	 *
	 * @param DBObject $oWebhook The webhook.
	 * @param string $sHookId The webhook configuration id.
	 *
	 * @return bool
	 * @throws \CoreException
	 */
	public function DeleteRepositoryWebhook(DBObject $oWebhook, string $sHookId) : bool
	{
		// log
		ModuleHelper::LogDebug('DeleteRepositoryWebhook');

		// security
		if($oWebhook->Get('connector_id') === 0){
			return -1;
		}

		// retrieve useful settings
		$sOwner = MetaModel::GetObject('VCSConnector', $oWebhook->Get('connector_id'))->Get('owner');
        $sRepositoryName = $oWebhook->Get('name');

		// API call
		$client = new Client();
		$request = new Request('DELETE',  self::GetAPIUri("/repos/$sOwner/$sRepositoryName/hooks/$sHookId"), $this->CreateAuthorizationHeader($oWebhook));
		$res = $client->sendAsync($request)->wait();

		return $res->getStatusCode() === 204;
	}


	/**
	 * Get information about a webhook in a GitHub repository.
	 *
	 * https://docs.github.com/en/rest/repos/webhooks?apiVersion=2022-11-28#get-a-repository-webhook
	 * GET /repos/{owner}/{repo}/hooks/{hook_id}
	 *
	 * @param DBObject $oWebhook The webhook.
	 * @param string $sHookId The ID of the webhook.
	 *
	 * @return array The webhook information, including its ID, URL, events, and configuration.
	 * @throws \CoreException
	 */
	public function GetRepositoryWebhookConfiguration(DBObject $oWebhook, string $sHookId) : array
	{
		// log
		ModuleHelper::LogDebug('GetRepositoryWebhookConfiguration');

		// retrieve useful settings
		$sOwner = MetaModel::GetObject('VCSConnector', $oWebhook->Get('connector_id'))->Get('owner');
        $sRepositoryName = $oWebhook->Get('name');

		// API call
		$client = new Client();
		$request = new Request('GET', self::GetAPIUri("/repos/$sOwner/$sRepositoryName/hooks/$sHookId"), $this->CreateAuthorizationHeader($oWebhook));
		$res = $client->sendAsync($request)->wait();
		return json_decode($res->getBody(), true);
	}

	/**
	 * Get repository application installation.
	 *
	 * https://docs.github.com/en/rest/apps/apps?apiVersion=2022-11-28#get-a-repository-installation-for-the-authenticated-app
	 * GET /repos/{owner}/{repo}/installation
	 *
	 * @param DBObject $oWebhook The webhook
	 *
	 * @return array
	 * @throws \CoreException
	 */
	public function GetRepositoryAppInstallation(DBObject $oWebhook) : array
	{
		// log
		ModuleHelper::LogDebug('GetRepositoryAppInstallation');

		// retrieve useful settings
		$sOwner = MetaModel::GetObject('VCSConnector', $oWebhook->Get('connector_id'))->Get('owner');
        $sRepositoryName = $oWebhook->Get('name');
		$oConnector = MetaModel::GetObject('VCSConnector', $oWebhook->Get('connector_id'));

		// API call
		$client = new Client();
		$request = new Request('GET',  self::GetAPIUri("/repos/$sOwner/$sRepositoryName/installation"), $this->CreateAppAuthorizationHeader($oConnector));
		$res = $client->sendAsync($request)->wait();

		return json_decode($res->getBody(), true);
	}

	/**
	 * Create application installation access token.
	 *
	 * https://docs.github.com/en/rest/apps/apps?apiVersion=2022-11-28#create-an-installation-access-token-for-an-app
	 * POST /app/installations/{installation_id}/access_tokens
	 *
	 * @param DBObject $oWebhook
	 * @param string $InstallationId ID of the application installation.
	 *
	 * @return array Application installation access token.
	 * @throws \CoreException
	 */
	public function CreateApplicationInstallationAccessToken(DBObject $oWebhook, string $InstallationId) : array
	{
		// log
		ModuleHelper::LogDebug('CreateApplicationInstallationAccessToken');

		// retrieve useful settings
		$oConnector = MetaModel::GetObject('VCSConnector', $oWebhook->Get('connector_id'));

		// API call
		$client = new Client();
		$request = new Request('POST', self::GetAPIUri("/app/installations/$InstallationId/access_tokens"), $this->CreateAppAuthorizationHeader($oConnector));
		$res = $client->sendAsync($request)->wait();
		return json_decode($res->getBody(), true);
	}
}
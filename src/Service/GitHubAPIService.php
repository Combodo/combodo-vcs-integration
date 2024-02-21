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

		return JWT::encode($aPayload, $sAppPrivateKey, 'RS256');
	}

	/**
	 * Create authorization request header.
	 *
	 * @param DBObject $oRepository VCS repository
	 *
	 * @return array header elements array
	 */
	private function CreateAuthorizationHeader(DBObject $oRepository) : array
	{
		$oConnector = MetaModel::GetObject('VCSConnector', $oRepository->Get('connector_id'));

		// get authorization header
		$sAuthorizationHeader = match ($oConnector->Get('mode'))
		{
			self::$AUTHENTICATION_MODE_PERSONAL_TOKEN => self::GetPersonalTokenAuthorizationHeader($oRepository),
			self::$AUTHENTICATION_MODE_APP_INSTALLATION_TOKEN => self::GetAppInstallationAccessTokenAuthorizationHeader($oRepository),
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
	 * @param \DBObject $oRepository
	 *
	 * @return string
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	private function GetPersonalTokenAuthorizationHeader(DBObject $oRepository) : string
	{
		$oConnector = MetaModel::GetObject('VCSConnector', $oRepository->Get('connector_id'));

		return 'Bearer ' . $oConnector->Get('personal_access_token');
	}


	/**
	 * Get app installation authorization header.
	 *
	 * @param DBObject $oRepository
	 *
	 * @return string
	 */
	private function GetAppInstallationAccessTokenAuthorizationHeader(DBObject $oRepository) : string
	{
		$sRepositoryName = $oRepository->Get('name');

		// no session token or expired
		if(!SessionHelper::IsSetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN, $sRepositoryName)
		|| SessionHelper::IsCurrentAppInstallationTokenExpired($sRepositoryName) ){

			// app installation ID
			$sInstallationId = SessionHelper::IsSetVar(SessionHelper::$SESSION_APP_INSTALLATION_ID, $sRepositoryName) ?
				SessionHelper::GetVar(SessionHelper::$SESSION_APP_INSTALLATION_ID, $sRepositoryName) : $this->GetRepositoryAppInstallation($oRepository)['id'];

			// create app installation access token
			$aResponse = self::CreateApplicationInstallationAccessToken($oRepository, $sInstallationId);
			$sAppInstallationAccessToken = $aResponse['token'];

			// log
			ModuleHelper::LogInfo('Create new application access token for repository ' . $sRepositoryName);

			// store it in session
			SessionHelper::SetVar(SessionHelper::$SESSION_APP_INSTALLATION_ID, $sRepositoryName, $sInstallationId);
			SessionHelper::SetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN, $sRepositoryName, $sAppInstallationAccessToken);
			SessionHelper::SetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN_EXPIRATION_DATE, $sRepositoryName, $aResponse['expires_at']);
		}
		else{

			// get session app installation access token
			$sAppInstallationAccessToken = SessionHelper::GetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN, $sRepositoryName);
		}

		return 'Bearer ' . $sAppInstallationAccessToken;
	}


	/**
	 * Check if a webhook configuration with id exist.
	 *
	 * @param DBObject $oRepository The repository.
	 * @param string $sHookId The webhook configuration id.
	 * @param array|null $data
	 *
	 * @return bool true if exist
	 */
	public function RepositoryWebhookExist(DBObject $oRepository, string $sHookId, array &$data = null) : bool
	{
		try{
			$data = $this->GetRepositoryWebhook($oRepository, $sHookId);

			return true;
		}
		catch(ClientException $e){

			// not found
			if($e->getResponse()->getStatusCode() === 404){
				return false;
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
	 * @param DBObject $oRepository The name of the repository.
	 *
	 * @return array The repository information, including the number of watchers, forks,
	 *               open issues, and clone URL.
	 */
	public function GetRepositoryInfo(DBObject $oRepository) : array
	{
		// retrieve useful settings
		$sOwner = MetaModel::GetObject('VCSConnector', $oRepository->Get('connector_id'))->Get('owner');
		$sRepositoryName = $oRepository->Get('name');

		// API call
		$client = new Client();
		$request = new Request('GET',  self::GetAPIUri("/repos/$sOwner/$sRepositoryName"), $this->CreateAuthorizationHeader($oRepository));
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
	 * @param DBObject $oRepository The name of the repository.
	 * @param string $sUrl The webhook url.
	 * @param string $sSecret The shared secret for the webhook.
	 * @param array $aListeningEvents events to listen
	 *
	 * @return array The created webhook object.
	 */
	public function CreateRepositoryWebhook(DBObject $oRepository, string $sUrl, string $sSecret, array $aListeningEvents) : array
	{
		// retrieve useful settings
		$sOwner = MetaModel::GetObject('VCSConnector', $oRepository->Get('connector_id'))->Get('owner');
		$sRepositoryName = $oRepository->Get('name');

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
		$request = new Request('POST',  self::GetAPIUri("/repos/$sOwner/$sRepositoryName/hooks"), $this->CreateAuthorizationHeader($oRepository), json_encode($aBody,JSON_UNESCAPED_SLASHES));
		$res = $client->sendAsync($request)->wait();

		return json_decode($res->getBody(), true);
	}

	/**
	 * Update a webhook for a GitHub repository.
	 *
	 * https://docs.github.com/en/rest/repos/webhooks?apiVersion=2022-11-28#update-a-repository-webhook
	 * PATCH /repos/{owner}/{repo}/hooks/{hook_id}
	 *
	 * @param DBObject $oRepository The name of the repository.
	 * @param string $sUrl The webhook url.
	 * @param string $sHookId The webhook configuration id.
	 * @param string $sSecret The shared secret for the webhook.
	 * @param array $aListeningEvents events to listen
	 *
	 * @return array The created webhook object.
	 */
	public function UpdateRepositoryWebhook(DBObject $oRepository, string $sUrl, string $sHookId, string $sSecret, array $aListeningEvents) : array
	{
		// retrieve useful settings
		$sOwner = MetaModel::GetObject('VCSConnector', $oRepository->Get('connector_id'))->Get('owner');
		$sRepositoryName = $oRepository->Get('name');

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
		$request = new Request('PATCH',  self::GetAPIUri("/repos/$sOwner/$sRepositoryName/hooks/$sHookId"), $this->CreateAuthorizationHeader($oRepository), json_encode($aBody,JSON_UNESCAPED_SLASHES));
		$res = $client->sendAsync($request)->wait();

		return json_decode($res->getBody(), true);
	}

	/**
	 * Delete a webhook for a GitHub repository.
	 *
	 * https://docs.github.com/en/rest/repos/webhooks?apiVersion=2022-11-28#delete-a-repository-webhook
	 * DELETE /repos/{owner}/{repo}/hooks/{hook_id}
	 *
	 * @param DBObject $oRepository The repository.
	 * @param string $sHookId The webhook configuration id.
	 *
	 * @return bool
	 */
	public function DeleteRepositoryWebhook(DBObject $oRepository, string $sHookId) : bool
	{
		// retrieve useful settings
		$sOwner = MetaModel::GetObject('VCSConnector', $oRepository->Get('connector_id'))->Get('owner');
		$sRepositoryName = $oRepository->Get('name');

		// API call
		$client = new Client();
		$request = new Request('DELETE',  self::GetAPIUri("/repos/$sOwner/$sRepositoryName/hooks/$sHookId"), $this->CreateAuthorizationHeader($oRepository));
		$res = $client->sendAsync($request)->wait();

		return $res->getStatusCode() === 204;
	}


	/**
	 * Get information about a webhook in a GitHub repository.
	 *
	 * https://docs.github.com/en/rest/repos/webhooks?apiVersion=2022-11-28#get-a-repository-webhook
	 * GET /repos/{owner}/{repo}/hooks/{hook_id}
	 *
	 * @param DBObject $oRepository The name of the repository.
	 * @param string $sHookId The ID of the webhook.
	 *
	 * @return array The webhook information, including its ID, URL, events, and configuration.
	 */
	public function GetRepositoryWebhook(DBObject $oRepository, string $sHookId) : array
	{
		// retrieve useful settings
		$sOwner = MetaModel::GetObject('VCSConnector', $oRepository->Get('connector_id'))->Get('owner');
		$sRepositoryName = $oRepository->Get('name');

		// API call
		$client = new Client();
		$request = new Request('GET', self::GetAPIUri("/repos/$sOwner/$sRepositoryName/hooks/$sHookId"), $this->CreateAuthorizationHeader($oRepository));
		$res = $client->sendAsync($request)->wait();
		return json_decode($res->getBody(), true);
	}

	/**
	 * Get repository application installation.
	 *
	 * https://docs.github.com/en/rest/apps/apps?apiVersion=2022-11-28#get-a-repository-installation-for-the-authenticated-app
	 * GET /repos/{owner}/{repo}/installation
	 *
	 * @param DBObject $oRepository The repository
	 *
	 * @return array
	 */
	public function GetRepositoryAppInstallation(DBObject $oRepository) : array
	{
		// retrieve useful settings
		$sOwner = MetaModel::GetObject('VCSConnector', $oRepository->Get('connector_id'))->Get('owner');
		$sRepositoryName = $oRepository->Get('name');
		$oConnector = MetaModel::GetObject('VCSConnector', $oRepository->Get('connector_id'));

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
	 * @param DBObject $oRepository
	 * @param string $InstallationId ID of the application installation.
	 *
	 * @return array Application installation access token.
	 */
	public function CreateApplicationInstallationAccessToken(DBObject $oRepository, string $InstallationId) : array
	{
		// retrieve useful settings
		$oConnector = MetaModel::GetObject('VCSConnector', $oRepository->Get('connector_id'));

		// API call
		$client = new Client();
		$request = new Request('POST', self::GetAPIUri("/app/installations/$InstallationId/access_tokens"), $this->CreateAppAuthorizationHeader($oConnector));
		$res = $client->sendAsync($request)->wait();
		return json_decode($res->getBody(), true);
	}
}
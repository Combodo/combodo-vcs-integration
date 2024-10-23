<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Service;

use Combodo\iTop\VCSManagement\Helper\ModuleHelper;
use DBObject;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

/**
 * Service responsible for the GitHub API requests.
 */
class GitHubAPIService extends AbstractGitHubAPI
{
	/** @var GitHubAPIService|null Singleton */
	static private ?GitHubAPIService $oSingletonInstance = null;

	private GitHubAPIAuthenticationService $oAPIAuthenticationService;

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
	 * @throws \Exception
	 */
	public function __construct()
	{
		$this->oAPIAuthenticationService = GitHubAPIAuthenticationService::GetInstance();
	}


	/**
	 * Get information about a GitHub repository.
	 *
	 * https://docs.github.com/fr/rest/repos/repos?apiVersion=2022-11-28#get-a-repository
	 * GET /repos/{owner}/{repo}
	 *
	 * @param DBObject $oApplication. The Webhook
	 *
	 * @return array The repository information, including the number of watchers, forks,
	 *               open issues, and clone URL.
	 * @throws \CoreException
	 */
	public function GetRepositoryInfo(DBObject $oApplication) : array
	{
		// retrieve useful settings
		$sOwner = $oApplication->Get('owner');
		$sRepositoryName = $oApplication->Get('name');

		// API call
		$client = new Client();
		$request = new Request('GET',  $this->GetAPIUri("/repos/$sOwner/$sRepositoryName"), $this->oAPIAuthenticationService->CreateAuthorizationHeader($oApplication));
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
	 * @param DBObject $oApplication The webhook.
	 * @param string $sOwner The owner of the repository.
	 * @param string $sUrl The webhook url.
	 * @param string $sSecret The shared secret for the webhook.
	 * @param array $aListeningEvents events to listen
	 *
	 * @return array The created webhook object.
	 * @throws \CoreException
	 */
	public function CreateRepositoryWebhook(DBObject $oApplication, string $sOwner, string $sUrl, string $sSecret, array $aListeningEvents) : array
	{
		// log
		ModuleHelper::LogDebug(__FUNCTION__, [
			'VCSWebhook' => $oApplication->GetKey(),
			'owner' => $sOwner,
			'url' => $sUrl,
			'events' => json_encode($aListeningEvents)
		]);

		// retrieve useful settings
        $sRepositoryName = $oApplication->Get('name');

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
		$request = new Request('POST',  $this->GetAPIUri("/repos/$sOwner/$sRepositoryName/hooks"), $this->oAPIAuthenticationService->CreateAuthorizationHeader($oApplication), json_encode($aBody,JSON_UNESCAPED_SLASHES));
		$res = $client->sendAsync($request)->wait();

		return json_decode($res->getBody(), true);
	}

	/**
	 * Create a webhook for a GitHub organization.
	 *
	 * https://docs.github.com/fr/rest/orgs/webhooks?apiVersion=2022-11-28#create-an-organization-webhook
	 * POST /orgs/{org}/hooks
	 *
	 * @param DBObject $oApplication The webhook.
	 * @param string $sOrganization Organization name.
	 * @param string $sUrl The webhook url.
	 * @param string $sSecret The shared secret for the webhook.
	 * @param array $aListeningEvents events to listen
	 *
	 * @return array The created webhook object.
	 * @throws \CoreException
	 */
	public function CreateOrganizationWebhook(DBObject $oApplication, string $sOrganization, string $sUrl, string $sSecret, array $aListeningEvents) : array
	{
		// log
		ModuleHelper::LogDebug(__FUNCTION__, [
			'VCSWebhook' => $oApplication->GetKey(),
			'organization' => $sOrganization,
			'url' => $sUrl,
			'events' => json_encode($aListeningEvents)
		]);

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
		$request = new Request('POST',  $this->GetAPIUri("/orgs/$sOrganization/hooks"), $this->oAPIAuthenticationService->CreateAuthorizationHeader($oApplication), json_encode($aBody,JSON_UNESCAPED_SLASHES));
		$res = $client->sendAsync($request)->wait();

		return json_decode($res->getBody(), true);
	}

	/**
	 * Update a webhook for a GitHub repository.
	 *
	 * https://docs.github.com/en/rest/repos/webhooks?apiVersion=2022-11-28#update-a-repository-webhook
	 * PATCH /repos/{owner}/{repo}/hooks/{hook_id}
	 *
	 * @param DBObject $oApplication The webhook.
	 * @param string $sOwner The owner of the repository.
	 * @param string $sUrl The webhook url.
	 * @param string $sHookId The webhook configuration id.
	 * @param string $sSecret The shared secret for the webhook.
	 * @param array $aListeningEvents events to listen
	 *
	 * @return array The created webhook object.
	 * @throws \CoreException
	 */
	public function UpdateRepositoryWebhook(DBObject $oApplication, string $sOwner, string $sUrl, string $sHookId, string $sSecret, array $aListeningEvents) : array
	{
		// log
		ModuleHelper::LogDebug(__FUNCTION__, [
			'VCSWebhook' => $oApplication->GetKey(),
			'owner' => $sOwner,
			'url' => $sUrl,
			'events' => json_encode($aListeningEvents)
		]);

		// retrieve useful settings
        $sRepositoryName = $oApplication->Get('name');

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
		$request = new Request('PATCH',  $this->GetAPIUri("/repos/$sOwner/$sRepositoryName/hooks/$sHookId"), $this->oAPIAuthenticationService->CreateAuthorizationHeader($oApplication), json_encode($aBody,JSON_UNESCAPED_SLASHES));
		$res = $client->sendAsync($request)->wait();

		return json_decode($res->getBody(), true);
	}

	/**
	 * Update a webhook for a GitHub organization.
	 *
	 * https://docs.github.com/fr/rest/orgs/webhooks?apiVersion=2022-11-28#update-an-organization-webhook
	 * PATCH /orgs/{org}/hooks/{hook_id}
	 *
	 * @param DBObject $oApplication The webhook.
	 * @param string $sOrganization Organization name.
	 * @param string $sUrl The webhook url.
	 * @param string $sHookId The webhook configuration id.
	 * @param string $sSecret The shared secret for the webhook.
	 * @param array $aListeningEvents events to listen
	 *
	 * @return array The created webhook object.
	 * @throws \CoreException
	 */
	public function UpdateOrganizationWebhook(DBObject $oApplication, string $sOrganization, string $sUrl, string $sHookId, string $sSecret, array $aListeningEvents) : array
	{
		// log
		ModuleHelper::LogDebug(__FUNCTION__, [
			'VCSWebhook' => $oApplication->GetKey(),
			'organization' => $sOrganization,
			'url' => $sUrl,
			'events' => json_encode($aListeningEvents)
		]);

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
		$request = new Request('PATCH',  $this->GetAPIUri("/orgs/$sOrganization/hooks/$sHookId"), $this->oAPIAuthenticationService->CreateAuthorizationHeader($oApplication), json_encode($aBody,JSON_UNESCAPED_SLASHES));
		$res = $client->sendAsync($request)->wait();

		return json_decode($res->getBody(), true);
	}

	/**
	 * Delete a webhook for a GitHub repository.
	 *
	 * https://docs.github.com/en/rest/repos/webhooks?apiVersion=2022-11-28#delete-a-repository-webhook
	 * DELETE /repos/{owner}/{repo}/hooks/{hook_id}
	 *
	 * @param DBObject $oApplication The webhook.
	 * @param string $sOwner The owner of the repository.
	 * @param string $sHookId The webhook configuration id.
	 *
	 * @return bool
	 * @throws \CoreException
	 */
	public function DeleteRepositoryWebhook(DBObject $oApplication, string $sOwner, string $sHookId) : bool
	{
		// log
		ModuleHelper::LogDebug(__FUNCTION__, [
			'VCSWebhook' => $oApplication->GetKey(),
			'owner' => $sOwner,
			'hook id' => $sHookId
		]);

		// retrieve useful settings
        $sRepositoryName = $oApplication->Get('name');

		// API call
		$client = new Client();
		$request = new Request('DELETE',  $this->GetAPIUri("/repos/$sOwner/$sRepositoryName/hooks/$sHookId"), $this->oAPIAuthenticationService->CreateAuthorizationHeader($oApplication));
		$res = $client->sendAsync($request)->wait();

		return $res->getStatusCode() === 204;
	}

	/**
	 * Delete a webhook for a GitHub organization.
	 *
	 * https://docs.github.com/fr/rest/orgs/webhooks?apiVersion=2022-11-28#delete-an-organization-webhook
	 * DELETE /orgs/{org}/hooks/{hook_id}
	 *
	 * @param DBObject $oApplication The webhook.
	 * @param string $sOrganization Organization name.
	 * @param string $sHookId The webhook configuration id.
	 *
	 * @return bool
	 * @throws \CoreException
	 */
	public function DeleteOrganizationWebhook(DBObject $oApplication, string $sOrganization, string $sHookId) : bool
	{
		// log
		ModuleHelper::LogDebug(__FUNCTION__, [
			'VCSWebhook' => $oApplication->GetKey(),
			'organization' => $sOrganization,
			'hook id' => $sHookId
		]);

		// API call
		$client = new Client();
		$request = new Request('DELETE',  $this->GetAPIUri("/orgs/$sOrganization/hooks/$sHookId"), $this->oAPIAuthenticationService->CreateAuthorizationHeader($oApplication));
		$res = $client->sendAsync($request)->wait();

		return $res->getStatusCode() === 204;
	}

	/**
	 * Get information about a webhook in a GitHub repository.
	 *
	 * https://docs.github.com/en/rest/repos/webhooks?apiVersion=2022-11-28#get-a-repository-webhook
	 * GET /repos/{owner}/{repo}/hooks/{hook_id}
	 *
	 * @param DBObject $oApplication The webhook.
	 * @param string $sOwner The owner of the repository.
	 * @param string $sHookId The ID of the webhook.
	 *
	 * @return array The webhook information, including its ID, URL, events, and configuration.
	 * @throws \CoreException
	 */
	public function GetRepositoryWebhookConfiguration(DBObject $oApplication, string $sOwner, string $sHookId) : array
	{
		// retrieve useful settings
        $sRepositoryName = $oApplication->Get('name');

		// API call
		$client = new Client();
		$request = new Request('GET', $this->GetAPIUri("/repos/$sOwner/$sRepositoryName/hooks/$sHookId"), $this->oAPIAuthenticationService->CreateAuthorizationHeader($oApplication));
		$res = $client->sendAsync($request)->wait();
		return json_decode($res->getBody(), true);
	}

	/**
	 * Get information about a webhook in a GitHub organization.
	 *
	 * https://docs.github.com/fr/rest/orgs/webhooks?apiVersion=2022-11-28#get-an-organization-webhook
	 * GET /orgs/{org}/hooks/{hook_id}
	 *
	 * @param DBObject $oApplication The webhook.
	 * @param string $sOrganization Organization name.
	 * @param string $sHookId The ID of the webhook.
	 *
	 * @return array The webhook information, including its ID, URL, events, and configuration.
	 * @throws \CoreException
	 */
	public function GetOrganizationWebhookConfiguration(DBObject $oApplication, string $sOrganization, string $sHookId) : array
	{
		// API call
		$client = new Client();
		$request = new Request('GET', $this->GetAPIUri("/orgs/$sOrganization/hooks/$sHookId"), $this->oAPIAuthenticationService->CreateAuthorizationHeader($oApplication));
		$res = $client->sendAsync($request)->wait();
		return json_decode($res->getBody(), true);
	}
}
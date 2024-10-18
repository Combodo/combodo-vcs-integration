<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Service;

use AttributeDateTime;
use Combodo\iTop\VCSManagement\Helper\ModuleHelper;
use DateTime;
use DBObject;
use Error;
use Exception;
use ExceptionLog;
use GuzzleHttp\Exception\ClientException;
use MetaModel;
use utils;
use VCSWebhook;

/**
 * GitHub manager service.
 *
 */
class GitHubManager
{
	/** @var string regex for host replacement */
	static private string $REGEX_HOST_REPLACEMENT = '#(https?)://([\.\-\w]+)/#';

	/** @var GitHubManager|null Singleton */
	static private ?GitHubManager $oSingletonInstance = null;

	/** @var \Combodo\iTop\VCSManagement\Service\GitHubAPIService|null GitHub API service */
	private ?GitHubAPIService $oGitHubAPIService = null;

	/**
	 * GetInstance.
	 *
	 * @return GitHubManager
	 * @throws \Exception
	 */
	public static function GetInstance(): GitHubManager
	{
		if (is_null(self::$oSingletonInstance)) {
			self::$oSingletonInstance = new GitHubManager();
			// service
			self::$oSingletonInstance->oGitHubAPIService = GitHubAPIService::GetInstance();
		}

		return self::$oSingletonInstance;
	}



	/**
	 * @param DBObject $oWebhook
	 *
	 * @return void
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
	public function DeleteWebhookSynchronization(DBObject $oWebhook) : void
	{
		// delete github webhook
		$iExistingWebhookId = $this->GetGithubWebhookConfigurationId($oWebhook);
		if($this->WebhookConfigurationExist($oWebhook, $iExistingWebhookId)['configuration_exist']){
			$this->DeleteWebhook($oWebhook, $iExistingWebhookId);
		}
	}

	/**
	 * GetWebhookUrl
	 *
	 * Constructs the webhook URL for a given reference.
	 *
	 * @param string $oWebhookReference The reference of the webhook.
	 *
	 * @return string The webhook URL.
	 * @throws \Exception
	 */
	public function GetWebhookUrl(string $oWebhookReference) : string
	{
		$sUrl = utils::GetAbsoluteUrlAppRoot() . 'pages/exec.php?exec_module=combodo-vcs-integration&exec_page=github.php&webhook=' . $oWebhookReference;

		$sHost = ModuleHelper::GetModuleSetting(ModuleHelper::$PARAM_WEBHOOK_HOST_OVERLOAD);
        $sScheme = ModuleHelper::GetModuleSetting(ModuleHelper::$PARAM_WEBHOOK_SCHEME_OVERLOAD);
		if($sHost !== null){
			$sUrl = preg_replace(self::$REGEX_HOST_REPLACEMENT, $sScheme.'://' . $sHost . '/', $sUrl);
		}

		return $sUrl;
	}

	/**
	 * Return listened events.
	 *
	 * @param DBObject $oWebhook
	 *
	 * @return array|string[]
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	public function GetWebhookListeningEvents(DBObject $oWebhook) : array
	{
		$aEvents = [];
		foreach($oWebhook->Get('automations_list')->GetValues() as $sLinkRef){
			$oLink = MetaModel::GetObject('lnkVCSAutomationToVCSWebhook', $sLinkRef);
			if($oLink->Get('status') == 'active'){
				$oAutomation = MetaModel::GetObject('VCSAutomation', $oLink->Get('automation_id'));
				$aAutomationEvents = [];
				$oLnkAutomationToEventSet = $oAutomation->Get('events_list');
				while ($oLnkAutomationToEvent = $oLnkAutomationToEventSet->Fetch()) {
					$aAutomationEvents[] = $oLnkAutomationToEvent->Get('event_name');
				}
				$aEvents = array_unique(array_merge($aEvents, $aAutomationEvents));
			}
		}

		if(empty($aEvents)){
			$aEvents = ['push'];
		}

		sort($aEvents, SORT_STRING);

		return $aEvents;
	}

	/**
	 * Get GitHub webhook configuration ID.
	 *
	 * @param $oWebhook
	 *
	 * @return false|mixed
	 */
	public function GetGithubWebhookConfigurationId($oWebhook) : mixed
	{
		$sGitHubWebhookConfiguration = $oWebhook->Get('configuration');
		if(!utils::IsNullOrEmptyString($sGitHubWebhookConfiguration)){
			$aGitHubWebhookConfiguration = json_decode($sGitHubWebhookConfiguration, true);
			return $aGitHubWebhookConfiguration['github']['id'];
		}

		return false;
	}

	/**
	 * Test if webhook config is equals to the passed GitHub webhook configuration.
	 *
	 * @param $oWebhook
	 * @param array $aGitHubWebhookConfiguration
	 *
	 * @return bool
	 * @throws \CoreException
	 */
	public function IsWebhookConfigurationEquals($oWebhook, array $aGitHubWebhookConfiguration) : bool
	{
		// check url
		if($oWebhook->Get('url') !== $aGitHubWebhookConfiguration['config']['url']){
			return false;
		}

		// check events
		$aListeningEvents = $this->GetWebhookListeningEvents($oWebhook);
		if($aGitHubWebhookConfiguration['events'] != $aListeningEvents){
			return false;
		}

		return true;
	}

	/**
	 * Update webhook url.
	 *
	 * @param DBObject $oWebhook
	 *
	 * @return void
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \Exception
	 */
	public function UpdateWebhookURL(DBObject $oWebhook) : void
	{
		$oGitHubManager = GitHubManager::GetInstance();
		$sUrlWebhookUrl = $oGitHubManager->GetWebhookUrl($oWebhook->Get('id'));
		$oWebhook->Set('url', $sUrlWebhookUrl);
	}

	/**
	 * Generate a secret.
	 *
	 * @param $lower
	 * @param $upper
	 * @param $digits
	 * @param $special_characters
	 *
	 * @return string
	 * @noinspection PhpUnused
	 */
	public function GenerateSecret($lower, $upper, $digits, $special_characters) : string
	{
		$lower_case = "abcdefghijklmnopqrstuvwxyz";
		$upper_case = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$numbers = "1234567890";
		$symbols = "!@#$%^&*";

		$lower_case = str_shuffle($lower_case);
		$upper_case = str_shuffle($upper_case);
		$numbers = str_shuffle($numbers);
		$symbols = str_shuffle($symbols);

		$random_password = substr($lower_case, 0, $lower);
		$random_password .= substr($upper_case, 0, $upper);
		$random_password .= substr($numbers, 0, $digits);
		$random_password .= substr($symbols, 0, $special_characters);

		return  str_shuffle($random_password);
	}

	/**
	 * @param DBObject $oWebhook
	 *
	 * @return void
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
	public function PerformWebhookAutoSynchronization(DBObject $oWebhook) : void
	{
		if(in_array($oWebhook->Get('status'), ['unsynchronized', 'error'])) {
			$aOperationResult = $this->SynchronizeWebhook($oWebhook);
			if($oWebhook->Get('type') !== 'organization'
			&& !$aOperationResult['has_error']){
				$this->UpdateExternalData($oWebhook);
			}
			$oWebhook->DBUpdate();
		}
	}

	/**
	 * Synchronize webhook.
	 *
	 * @param DBObject $oWebhook
	 *
	 * @return array|null
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \Exception
	 */
	public function SynchronizeWebhook(DBObject $oWebhook) : ?array
	{
		// execute VCS operation (handle exceptions)
		$aOperationResult = $this->ExecuteVCSOperation('SynchronizeWebhook', function() use ($oWebhook){

			// retrieve events (computed with active automations)
			$aEvents = $this->GetWebhookListeningEvents($oWebhook);

			// webhook info
			$sWebhookId = $oWebhook->Get('id');
			$sWebhookSecret = $oWebhook->Get('secret');
			$sWebhookUrl = $this->GetWebhookUrl($sWebhookId);

			// get possibly existing webhook configuration id
			$iWebhookId = $this->GetGithubWebhookConfigurationId($oWebhook);

			// webhook configuration doesn't exist
			if($iWebhookId === false || !$this->WebhookConfigurationExist($oWebhook, $iWebhookId)['configuration_exist']){

				// API: create new webhook configuration
				$aGitHubData = $this->CreateWebhook(
					$oWebhook,
					$sWebhookUrl,
					$sWebhookSecret,
					$aEvents
				);

			}
			else{ // exist

				// API: update webhook configuration
				$aGitHubData = $this->UpdateWebhook(
					$oWebhook,
					$sWebhookUrl,
					$iWebhookId,
					$sWebhookSecret,
					$aEvents
				);

			}

			return [
				'github_data' => $aGitHubData
			];
		});

		if($aOperationResult['has_error']){

			// update webhook status
			$oWebhook->Set('status', 'error');
		}
		else{

			// update webhook configuration
			$aWebhookConfigurationData['github'] = [
				'id' => $aOperationResult['data']['github_data']['id'],
				'date' => AttributeDateTime::GetFormat()->format(new DateTime('now'))
			];
			$oWebhook->Set('configuration', json_encode($aWebhookConfigurationData, JSON_UNESCAPED_SLASHES));

			// update webhook status
			$oWebhook->Set('status', $aOperationResult['data']['github_data']['active'] ? 'active' : 'inactive');
		}

		return $aOperationResult;
	}

	/**
	 * Update external data from GitHub.
	 *
	 * @param DBObject $oWebhook
	 *
	 * @return array
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \Exception
	 */
	public function UpdateExternalData(DBObject $oWebhook) : array
	{
		// execute VCS operation (handle exceptions)
		$aOperationResult = $this->ExecuteVCSOperation('UpdateExternalData', function() use ($oWebhook){

			$aGitHubData = $this->oGitHubAPIService->GetRepositoryInfo($oWebhook);

			return [
				'github_data' => $aGitHubData
			];
		});

		if(!$aOperationResult['has_error']){

			// Update external data
			$aExternalData = [
				'date' => AttributeDateTime::GetFormat()->format(new DateTime('now')),
				'github' => $aOperationResult['data']['github_data']
			];
			$oWebhook->Set('external_data',  json_encode($aExternalData, JSON_UNESCAPED_SLASHES));

		}

		return $aOperationResult;
	}

	/**
	 * Update webhook status.
	 *
	 * @param $oWebhook
	 *
	 * @return array
	 */
	public function UpdateWebhookStatus($oWebhook) : array
	{
		// execute VCS operation (handle exceptions)
		$aOperationResult = $this->ExecuteVCSOperation('UpdateWebhookStatus', function() use ($oWebhook) {

			// variables
			$bSynchro = null;
			$aResult = null;

			if ($oWebhook->Get('connector_id') !== null)
			{
				// retrieve webhook configuration
				$sWebhookConfigurationData = $oWebhook->Get('configuration');
				$aWebhookConfigurationData = json_decode($sWebhookConfigurationData, true);

				if($aWebhookConfigurationData !== null){

					// test webhook configuration exist on remote
					$sWebhookId = $aWebhookConfigurationData['github']['id'];
					$aResult = $this->WebhookConfigurationExist($oWebhook, $sWebhookId);

					// webhook configuration exist
					if ($aResult['configuration_exist'])
					{
						// check if webhook configuration is synchro
						$bSynchro = $this->IsWebhookConfigurationEquals($oWebhook, $aResult['github_data']);
					}
				}
			}

			return [
				'github_data' => $aResult !== null ? $aResult['github_data'] : null,
				'is_synchro' => $bSynchro
			];

		});

		// Update webhook and save
		if($aOperationResult['has_error']){
			$oWebhook->Set('status', 'error');
		}
		else if(!$aOperationResult['data']['is_synchro']){
			$oWebhook->Set('status', 'unsynchronized');
		}
		else{
			$oWebhook->Set('status', $aOperationResult['data']['github_data']['active'] ? 'active' : 'inactive');
		}

		return $aOperationResult;
	}

	/**
	 * Extract webhook from request param.
	 *
	 * @return \DBObject
	 * @throws \Exception
	 */
	public function ExtractWebhookFromRequestParam() : DBObject
	{
		$sWebhookRef = utils::ReadParam('webhook_id', '-1');

		if($sWebhookRef === -1){
			throw new Exception('Missing `webhook_id` query parameter');
		}

		return MetaModel::GetObject(VCSWebhook::class, $sWebhookRef);
	}

	/**
	 * Append webhook status field html to data.
	 *
	 * @param DBObject $oWebhook
	 * @param array $aData
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function AppendWebhookStatusFieldHtml(DBObject $oWebhook, array &$aData) : void
	{
		/** @var \AttributeEnumSet $oAttributeSet */
		$oAttributeEnumSet = MetaModel::GetAttributeDef(VCSWebhook::class, 'status');
		$aData['status_field_html'] = $oAttributeEnumSet->GetAsHTML($oWebhook->Get('status'));
		$aData['status'] = $oWebhook->Get('status');
	}

	/**
	 * @param \GuzzleHttp\Exception\ClientException $oException
	 *
	 * @return string
	 */
	public function GetAPICallErrorMessage(ClientException $oException) : string
	{

		// get error array
		$aExceptionError = json_decode($oException->getResponse()->getBody()->getContents(), true);

		// extract REST error message
		$sMessage = $aExceptionError['message'];

		// append potential errors information
		if(array_key_exists('errors', $aExceptionError)){
			foreach($aExceptionError['errors'] as $aError){
				$sMessage .= '<br>- Resource: ' . $aError['resource'] . ', Code: ' . $aError['code'] . ', Message: <span style="color:orange">' . $aError['message'] . '</span>';
			}
		}

		// compute help message
		return match ($aExceptionError['message'])
		{
			'Not Found' => "$sMessage<br><i>Verify webhook name and connector owner</i>",
			'Bad credentials' => "$sMessage<br><i>️️Verify connector authentication</i>",
			'Validation Failed' => "$sMessage<br><i>️️Refer to the above message(s)</i>",
			'Integration not found' => "$sMessage<br><i>️️Verify connector app id</i>",
			'A JSON web token could not be decoded' => "$sMessage<br><i>️️Verify connector app private key</i>",
			default => $sMessage,
		};

	}

	/**
	 * @param string $sName
	 * @param callable $oCallable
	 *
	 * @return array
	 */
	private function ExecuteVCSOperation(string $sName, callable $oCallable) : array
	{
		// variables
		$bError = false;
		$aData = [];
		$aErrors = [];

		try{
			$aData = $oCallable();
		}
		catch(ClientException $e){
			ExceptionLog::LogException($e, [
				'happened_on' => "ExecuteVCSOperation $sName in GitHubManager.php",
				'error_msg' => $e->getMessage(),
			]);
			$bError = true;
			$aErrors[] = self::GetAPICallErrorMessage($e);
		}
		catch(Exception $e){
			ExceptionLog::LogException($e, [
				'happened_on' => "ExecuteVCSOperation $sName in GitHubManager.php",
				'error_msg' => $e->getMessage(),
			]);
			$bError = true;
			$aErrors[] = $e->getMessage();
		}
		catch(Error $e){
			$bError = true;
			$aErrors[] = $e->getMessage();
		}
		finally{
			return [
				'data' => $aData,
				'has_error' => $bError,
				'errors' => $aErrors
			];
		}

	}

	/**
	 * Update webhook.
	 *
	 * @param DBObject $oWebhook
	 * @param bool $bUpdateSecret
	 *
	 * @return void
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
    public function UpdateVCSWebhook(DBObject $oWebhook, bool $bUpdateSecret = false): void
    {
        // update web hook url (may have changed with module configuration)
        $this->UpdateWebhookURL($oWebhook);

        // update synchro state
        $this->UpdateWebhookStatus($oWebhook);

        // cannot detect change with UpdateWebhookStatus (secret isn't visible entirely)
        if($bUpdateSecret){
            $oWebhook->Set('status', 'unsynchronized');
        }

        // auto synchronize
        $this->PerformWebhookAutoSynchronization($oWebhook);
    }

	/**
	 * @param VCSWebhook $oWebhook
	 * @param string $sUrl
	 * @param string $sSecret
	 * @param array $aEvents
	 *
	 * @return array
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	public function CreateWebhook(DBObject $oWebhook, string $sUrl, string $sSecret, array $aEvents) : array
	{
		$sType = $oWebhook->Get('type');

		return match($sType){
			'repository' => $this->oGitHubAPIService->CreateRepositoryWebhook($oWebhook, $oWebhook->GetConnector()->Get('app_repository_owner'),  $sUrl, $sSecret, $aEvents),
			'organization' => $this->oGitHubAPIService->CreateOrganizationWebhook($oWebhook, $oWebhook->GetConnector()->Get('app_organization_name'), $sUrl, $sSecret, $aEvents),
		};
	}

	/**
	 * @param VCSWebhook $oWebhook
	 * @param string $sHookId
	 * @param string $sUrl
	 * @param string $sSecret
	 * @param array $aEvents
	 *
	 * @return array
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	public function UpdateWebhook(DBObject $oWebhook, string $sHookId, string $sUrl, string $sSecret, array $aEvents) : array
	{
		$sType = $oWebhook->Get('type');

		return match($sType){
			'repository' => $this->oGitHubAPIService->UpdateRepositoryWebhook($oWebhook, $oWebhook->GetConnector()->Get('app_repository_owner'), $sHookId, $sUrl, $sSecret, $aEvents),
			'organization' => $this->oGitHubAPIService->UpdateOrganizationWebhook($oWebhook, $oWebhook->GetConnector()->Get('app_organization_name'), $sHookId, $sUrl, $sSecret, $aEvents),
		};
	}

	/**
	 * @param VCSWebhook $oWebhook
	 * @param string $sHookId
	 *
	 * @return array
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	public function DeleteWebhook(DBObject $oWebhook, string $sHookId) : array
	{
		try{
			$sType = $oWebhook->Get('type');

			$data = match($sType){
				'repository' => $this->oGitHubAPIService->DeleteRepositoryWebhook($oWebhook, $oWebhook->GetConnector()->Get('app_repository_owner'), $sHookId),
				'organization' => $this->oGitHubAPIService->DeleteOrganizationWebhook($oWebhook, $oWebhook->GetConnector()->Get('app_organization_name'), $sHookId),
			};

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

	/**
	 * Check if a webhook configuration with id exist.
	 *
	 * @param VCSWebhook $oWebhook The Webhook.
	 * @param string $sHookId The webhook configuration id.
	 *
	 * @return array
	 * @throws \CoreException
	 */
	public function WebhookConfigurationExist(DBObject $oWebhook, string $sHookId) : array
	{
		try{
			$sType = $oWebhook->Get('type');

			$data = match($sType){
				'repository' => $this->oGitHubAPIService->GetRepositoryWebhookConfiguration($oWebhook, $oWebhook->GetConnector()->Get('app_repository_owner'), $sHookId),
				'organization' => $this->oGitHubAPIService->GetOrganizationWebhookConfiguration($oWebhook, $oWebhook->GetConnector()->Get('app_organization_name'), $sHookId),
			};

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


}
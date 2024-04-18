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
use Exception;
use ExceptionLog;
use GuzzleHttp\Exception\ClientException;
use MetaModel;
use utils;

/**
 * GitHub manager service.
 *
 */
class GitHubManager
{
	/** @var string regex for host replacement */
	static private string $REGEX_HOST_REPLACEMENT = '#(https?)://([\.\w]+)/#';

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
	 * @param \DBObject $oRepository
	 *
	 * @return void
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
	public function DeleteWebhookSynchronization(DBObject $oRepository) : void
	{
		// delete github webhook
		$iExistingWebhookId = $this->GetGithubWebhookConfigurationId($oRepository);
		if($this->oGitHubAPIService->RepositoryWebhookConfigurationExist($oRepository, $iExistingWebhookId)['webhook_configuration_exist']){
			$this->oGitHubAPIService->DeleteRepositoryWebhook($oRepository, $iExistingWebhookId);
		}

		// set to unset
		$oRepository->Set('webhook_status', 'unset');
		$oRepository->Set('webhook_configuration',  '');
		$oRepository->Set('external_data',  '');
	}

	/**
	 * GetWebhookUrl
	 *
	 * Constructs the webhook URL for a given repository reference.
	 *
	 * @param string $sRepositoryReference The reference of the repository.
	 *
	 * @return string The webhook URL.
	 * @throws \Exception
	 */
	public function GetWebhookUrl(string $sRepositoryReference) : string
	{
		$sUrl = utils::GetAbsoluteUrlAppRoot() . 'pages/exec.php?exec_module=combodo-vcs-integration&exec_page=github.php&repository=' . $sRepositoryReference;

		$sHost = ModuleHelper::GetModuleSetting(ModuleHelper::$PARAM_WEBHOOK_HOST_OVERLOAD);
		if($sHost !== null){
			$sUrl = preg_replace(self::$REGEX_HOST_REPLACEMENT, '${1}://' . $sHost . '/', $sUrl);
		}
		$sScheme = ModuleHelper::GetModuleSetting(ModuleHelper::$PARAM_WEBHOOK_SCHEME_OVERLOAD);
		if($sScheme !== null){
			$sUrl = str_replace('http', $sScheme, $sUrl);
		}

		return $sUrl;
	}

	/**
	 * Return listened events.
	 *
	 * @param DBObject $oRepository
	 *
	 * @return array|string[]
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	public function GetRepositoryListeningEvents(DBObject $oRepository) : array
	{
		$aEvents = [];
		foreach($oRepository->Get('automations_list')->GetValues() as $sLinkRef){
			$oLink = MetaModel::GetObject('lnkVCSAutomationToVCSRepository', $sLinkRef);
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

		return $aEvents;
	}

	/**
	 * Get GitHub webhook configuration ID.
	 *
	 * @param $oRepository
	 *
	 * @return false|mixed
	 */
	public function GetGithubWebhookConfigurationId($oRepository) : mixed
	{
		$sGitHubWebhookConfiguration = $oRepository->Get('webhook_configuration');
		if(!utils::IsNullOrEmptyString($sGitHubWebhookConfiguration)){
			$aGitHubWebhookConfiguration = json_decode($sGitHubWebhookConfiguration, true);
			return $aGitHubWebhookConfiguration['github']['id'];
		}

		return false;
	}

	/**
	 * Test if repository webhook config is equals to the passed GitHub webhook configuration.
	 *
	 * @param $oRepository
	 * @param array $aGitHubWebhookConfiguration
	 *
	 * @return bool
	 * @throws \CoreException
	 */
	public function IsWebhookConfigurationEquals($oRepository, array $aGitHubWebhookConfiguration) : bool
	{
		// check url
		if($oRepository->Get('webhook_url') !== $aGitHubWebhookConfiguration['config']['url']){
			return false;
		}

		// check events
		$aListeningEvents = $this->GetRepositoryListeningEvents($oRepository);
		if($aGitHubWebhookConfiguration['events'] != $aListeningEvents){
			return false;
		}

		return true;
	}

	/**
	 * Update webhook url.
	 *
	 * @param \DBObject $oRepository
	 *
	 * @return void
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \Exception
	 */
	public function UpdateWebhookURL(DBObject $oRepository) : void
	{
		$oGitHubManager = GitHubManager::GetInstance();
		$sUrlWebhookUrl = $oGitHubManager->GetWebhookUrl($oRepository->Get('id'));
		$oRepository->Set('webhook_url', $sUrlWebhookUrl);
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
	 * Synchronize repository.
	 *
	 * @param \DBObject $oRepository
	 *
	 * @return array|null
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \Exception
	 */
	public function SynchronizeRepository(DBObject $oRepository) : ?array
	{
		// execute VCS operation (handle exceptions)
		$aOperationResult = $this->ExecuteVCSOperation(function() use ($oRepository){

			// retrieve events (computed with active automations)
			$aEvents = $this->GetRepositoryListeningEvents($oRepository);

			// repository info
			$sRepositoryId = $oRepository->Get('id');
			$sRepositorySecret = $oRepository->Get('secret');
			$sWebhookUrl = $this->GetWebhookUrl($sRepositoryId);

			// get possibly existing webhook configuration id
			$iWebhookId = $this->GetGithubWebhookConfigurationId($oRepository);

			// webhook configuration doesn't exist
			if($iWebhookId === false || !$this->oGitHubAPIService->RepositoryWebhookConfigurationExist($oRepository, $iWebhookId)['webhook_configuration_exist']){

				// API: create new webhook configuration
				$aGitHubData = $this->oGitHubAPIService->CreateRepositoryWebhook(
					$oRepository,
					$sWebhookUrl,
					$sRepositorySecret,
					$aEvents
				);

			}
			else{ // exist

				// API: update webhook configuration
				$aGitHubData = $this->oGitHubAPIService->UpdateRepositoryWebhook(
					$oRepository,
					$sWebhookUrl,
					$iWebhookId,
					$sRepositorySecret,
					$aEvents
				);

			}

			return [
				'github_data' => $aGitHubData
			];
		});

		if($aOperationResult['has_error']){

			// update webhook status
			$oRepository->Set('webhook_status', 'error');
		}
		else{

			// update webhook configuration
			$aWebhookConfigurationData['github'] = [
				'id' => $aOperationResult['data']['github_data']['id'],
				'date' => AttributeDateTime::GetFormat()->format(new DateTime('now'))
			];
			$oRepository->Set('webhook_configuration', json_encode($aWebhookConfigurationData, JSON_UNESCAPED_SLASHES));

			// update webhook status
			$oRepository->Set('webhook_status', $aOperationResult['data']['github_data']['active'] ? 'active' : 'inactive');
		}

		return $aOperationResult;
	}

	/**
	 * Update external data from GitHub.
	 *
	 * @param \DBObject $oRepository
	 *
	 * @return array
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \Exception
	 */
	public function UpdateExternalData(DBObject $oRepository) : array
	{
		// execute VCS operation (handle exceptions)
		$aOperationResult = $this->ExecuteVCSOperation(function() use ($oRepository){

			$aGitHubData = $this->oGitHubAPIService->GetRepositoryInfo($oRepository);

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
			$oRepository->Set('external_data',  json_encode($aExternalData, JSON_UNESCAPED_SLASHES));

		}

		return $aOperationResult;
	}

	/**
	 * Update webhook status.
	 *
	 * @param $oRepository
	 *
	 * @return array
	 */
	public function UpdateWebhookStatus($oRepository) : array
	{
		// execute VCS operation (handle exceptions)
		$aOperationResult = $this->ExecuteVCSOperation(function() use ($oRepository) {

			// variables
			$bSynchro = null;
			$aResult = null;

			// only if synchro mode is enabled
			if ($oRepository->Get('synchro_mode') !== 'none')
			{
				// retrieve webhook configuration
				$sWebhookConfigurationData = $oRepository->Get('webhook_configuration');
				$aWebhookConfigurationData = json_decode($sWebhookConfigurationData, true);

				// text webhook configuration exist on remote
				$sWebhookId = $aWebhookConfigurationData['github']['id'];
				$aResult = $this->oGitHubAPIService->RepositoryWebhookConfigurationExist($oRepository, $sWebhookId);

				// webhook configuration exist
				if ($aResult['webhook_configuration_exist'])
				{
					// check if webhook configuration is synchro
					$bSynchro = $this->IsWebhookConfigurationEquals($oRepository, $aResult['github_data']);
				}
			}

			return [
				'github_data' => $aResult !== null ? $aResult['github_data'] : null,
				'is_synchro' => $bSynchro
			];

		});

		// Update repository and save
		if($aOperationResult['has_error']){
			$oRepository->Set('webhook_status', 'error');
		}
		else if($aOperationResult['data']['is_synchro'] === null){
			$oRepository->Set('webhook_status', 'unset');
		}
		else if(!$aOperationResult['data']['is_synchro']){
			$oRepository->Set('webhook_status', 'unsynchronized');
		}
		else{
			$oRepository->Set('webhook_status', $aOperationResult['data']['github_data']['active'] ? 'active' : 'inactive');
		}

		return $aOperationResult;
	}

	/**
	 * Extract repository from request param.
	 *
	 * @return \DBObject
	 * @throws \Exception
	 */
	public function ExtractRepositoryFromRequestParam() : DBObject
	{
		$sRepositoryRef = utils::ReadParam('repository_id', '-1');

		if($sRepositoryRef === -1){
			throw new Exception('Missing `repository_id` query parameter');
		}

		return MetaModel::GetObject('VCSRepository', $sRepositoryRef);
	}

	/**
	 * Append webhook status field html to data.
	 *
	 * @param DBObject $oRepository
	 * @param array $aData
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function AppendWebhookStatusFieldHtml(DBObject $oRepository, array &$aData) : void
	{
		/** @var \AttributeEnumSet $oAttributeSet */
		$oAttributeEnumSet = MetaModel::GetAttributeDef('VCSRepository', 'webhook_status');
		$aData['webhook_status_field_html'] = $oAttributeEnumSet->GetAsHTML($oRepository->Get('webhook_status'));
		$aData['webhook_status'] = $oRepository->Get('webhook_status');
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
			'Not Found' => "$sMessage<br><i>Verify repository name and connector owner</i>",
			'Bad credentials' => "$sMessage<br><i>️️Verify connector authentication</i>",
			'Validation Failed' => "$sMessage<br><i>️️Refer to the above message(s)</i>",
			'Integration not found' => "$sMessage<br><i>️️Verify connector app id</i>",
			'A JSON web token could not be decoded' => "$sMessage<br><i>️️Verify connector app private key</i>",
			default => $sMessage,
		};

	}

	/**
	 * @param callable $oCallable
	 *
	 * @return array
	 */
	private function ExecuteVCSOperation(callable $oCallable) : array
	{
		// variables
		$bError = false;
		$aData = [];
		$aErrors = [];

		try{
			$aData = $oCallable();
		}
		catch(ClientException $e){
			ExceptionLog::LogException($e);
			$bError = true;
			$aErrors[] = self::GetAPICallErrorMessage($e);
		}
		catch(Exception $e){
			ExceptionLog::LogException($e);
			$bError = true;
			$aErrors[] = $e->getMessage();
		} finally
		{
			return [
				'data' => $aData,
				'has_error' => $bError,
				'errors' => $aErrors
			];
		}

	}
}
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
	 * Update webhook status.
	 *
	 * @param $oRepository
	 *
	 * @return void
	 */
	public function UpdateWebhookStatus($oRepository) : void
	{
		// synchro flag
		$bSynchro = false;
		$bError = true;

		// check webhook configuration
		$sData = $oRepository->Get('github_webhook_configuration');

		if(!utils::IsNullOrEmptyString($sData)){

			// retrieve GitHub configuration
			$aGitHubData = json_decode($sData, true);

			// check synchro state
			try{
				$aGitHubDataCheck = $this->oGitHubAPIService->GetRepositoryWebhook($oRepository, $aGitHubData['github']['id']);
				$bSynchro = $this->CheckGitHubWebhookConfiguration($oRepository, $aGitHubDataCheck);
				$bError = false;
			}
			catch(Exception $e){
				ExceptionLog::LogException($e);
			}
		}

		// Update repository and save
		if($bError){
			$oRepository->Set('webhook_status', 'error');
		}
		else if(!$bSynchro){
			$oRepository->Set('webhook_status', 'unsynchronized');
		}
		else{
			$oRepository->Set('webhook_status', $aGitHubDataCheck['active'] ? 'active' : 'inactive');
		}
	}

	/**
	 * @param \DBObject $oRepository
	 *
	 * @return void
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
	public function DeleteWebhookSynchronization(DBObject $oRepository)
	{
		// delete github webhook
		$iExistingWebhookId = $this->GetGithubWebhookConfigurationId($oRepository);
		$this->oGitHubAPIService->DeleteRepositoryWebhook($oRepository, $iExistingWebhookId);

		// set to unsynchronized
		$oRepository->Set('webhook_status', 'unsynchronized');
		$oRepository->Set('github_webhook_configuration',  '');
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
	 */
	public function GetWebhookUrl(string $sRepositoryReference) : string
	{
		$sUrl = utils::GetAbsoluteUrlAppRoot() . 'pages/exec.php?exec_module=combodo-github-integration&exec_page=github.php&repository=' . $sRepositoryReference;

		$sHost = ModuleHelper::GetModuleSetting('webhook_host_overload', null);
		if($sHost !== null){
			$sUrl = preg_replace(self::$REGEX_HOST_REPLACEMENT, '${1}://' . $sHost . '/', $sUrl);
		}
		$sScheme = ModuleHelper::GetModuleSetting('webhook_scheme_overload', null);
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
		foreach($oRepository->Get('automations')->GetValues() as $sLinkRef){
			$oLink = MetaModel::GetObject('lnkVCSRepositoryToVCSAutomation', $sLinkRef);
			$oAutomation = MetaModel::GetObject('AbstractVCSAutomation', $oLink->Get('automation_id'));
			$aEvents = array_merge($aEvents, $oAutomation->Get('events')->GetValues());
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
		$sGitHubWebhookConfiguration = $oRepository->Get('github_webhook_configuration');
		if(!utils::IsNullOrEmptyString($sGitHubWebhookConfiguration)){
			$aGitHubWebhookConfiguration = json_decode($sGitHubWebhookConfiguration, true);
			return $aGitHubWebhookConfiguration['github']['id'];
		}

		return false;
	}

	/**
	 * Check GitHub webhook configuration.
	 *
	 * @param $oRepository
	 * @param array $aGitHubWebhookConfiguration
	 *
	 * @return bool
	 */
	public function CheckGitHubWebhookConfiguration($oRepository, array $aGitHubWebhookConfiguration) : bool
	{
		// check url
		if($oRepository->Get('webhook') !== $aGitHubWebhookConfiguration['config']['url']){
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
	 */
	public function UpdateWebhookURL(DBObject $oRepository)
	{
		$oGitHubManager = GitHubManager::GetInstance();
		$sUrlWebhookUrl = $oGitHubManager->GetWebhookUrl($oRepository->Get('id'));
		$oRepository->Set('webhook', $sUrlWebhookUrl);
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
	 */
	public function SynchronizeRepository(DBObject $oRepository) : ?array
	{
		$bError = false;
		$aGitHubData = null;

		// retrieve events (compute with active automations)
		$aEvents = $this->GetRepositoryListeningEvents($oRepository);

		// repository info
		$sRepositoryId = $oRepository->Get('id');
		$sRepositorySecret = $oRepository->Get('secret');
		$sWebhookUrl = $this->GetWebhookUrl($sRepositoryId);

		// get possibly existing webhook configuration id
		$iWebhookId = $this->GetGithubWebhookConfigurationId($oRepository);

		try{

			// doesn't exist
			if($iWebhookId === false){

				// API: create webhook configuration
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

			// synchronized data
			$aData['github']['id'] = $aGitHubData['id'];
			$aData['github']['date'] = AttributeDateTime::GetFormat()->format(new DateTime('now'));
			$oRepository->Set('github_webhook_configuration',  json_encode($aData, JSON_UNESCAPED_SLASHES));

		}
		catch(Exception $e){
			ExceptionLog::LogException($e);
			$bError = true;
		}

		// update webhook status
		if($bError){
			$oRepository->Set('webhook_status', 'error');
		}
		else{
			$oRepository->Set('webhook_status', $aGitHubData['active'] ? 'active' : 'inactive');
		}

		return $aGitHubData;
	}

	public function GetRepositoryInfo(DBObject $oRepository)
	{
		// API: get repository info
		$aGitHubData = $this->oGitHubAPIService->GetRepositoryInfo($oRepository);

		// prepare data
		$aData = [
			'date' => AttributeDateTime::GetFormat()->format(new DateTime('now')),
			'github' => $aGitHubData
		];

		// Update repository and save
		$oRepository->Set('external_data',  json_encode($aData, JSON_UNESCAPED_SLASHES));
	}
}
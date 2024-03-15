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
		$bSynchro = null;
		$bError = false;
		$bActive = null;

		// synchro disabled
		if($oRepository->Get('synchro_mode') === 'none'){
			$oRepository->Set('webhook_status', 'unset');
			return;
		}

		// check webhook configuration
		$sData = $oRepository->Get('github_webhook_configuration');
		if(utils::IsNullOrEmptyString($sData)){
			$oRepository->Set('webhook_status', 'unsynchronized');
			return;
		}
		$aGitHubData = json_decode($sData, true);

		// check synchro state
		try{
			$sWebhookId = $aGitHubData['github']['id'];
			$aGitHubDataCheck = [];
			if($this->oGitHubAPIService->RepositoryWebhookExist($oRepository, $sWebhookId, $aGitHubDataCheck)){
				$bSynchro = $this->IsWebhookConfigurationEquals($oRepository, $aGitHubDataCheck);
				$bActive = $aGitHubDataCheck['active'] ? 'active' : 'inactive';
			}
		}
		catch(Exception $e){
			ExceptionLog::LogException($e);
			$bError = true;
		}

		// Update repository and save
		if($bError){
			$oRepository->Set('webhook_status', 'error');
		}
		else if($bSynchro !== null && !$bSynchro){
			$oRepository->Set('webhook_status', 'unsynchronized');
		}
		else if($bActive !== null){
			$oRepository->Set('webhook_status', $bActive);
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
	public function DeleteWebhookSynchronization(DBObject $oRepository) : void
	{
		// delete github webhook
		$iExistingWebhookId = $this->GetGithubWebhookConfigurationId($oRepository);
		if($this->oGitHubAPIService->RepositoryWebhookExist($oRepository, $iExistingWebhookId)){
			$this->oGitHubAPIService->DeleteRepositoryWebhook($oRepository, $iExistingWebhookId);
		}

		// set to unset
		$oRepository->Set('webhook_status', 'unset');
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
	 * @throws \Exception
	 */
	public function GetWebhookUrl(string $sRepositoryReference) : string
	{
		$sUrl = utils::GetAbsoluteUrlAppRoot() . 'pages/exec.php?exec_module=combodo-github-integration&exec_page=github.php&repository=' . $sRepositoryReference;

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
		foreach($oRepository->Get('automations')->GetValues() as $sLinkRef){
			$oLink = MetaModel::GetObject('lnkVCSRepositoryToVCSAutomation', $sLinkRef);
			if($oLink->Get('status') == 'active'){
				$oAutomation = MetaModel::GetObject('VCSAutomation', $oLink->Get('automation_id'));
				$aEvents = array_unique(array_merge($aEvents, $oAutomation->Get('events')->GetValues()));
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
		$sGitHubWebhookConfiguration = $oRepository->Get('github_webhook_configuration');
		if(!utils::IsNullOrEmptyString($sGitHubWebhookConfiguration)){
			$aGitHubWebhookConfiguration = json_decode($sGitHubWebhookConfiguration, true);
			return $aGitHubWebhookConfiguration['github']['id'];
		}

		return false;
	}

	/**
	 * Test if repository webhook config is equals to the passed github webhook configuration.
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
			if($iWebhookId === false
			| !$this->oGitHubAPIService->RepositoryWebhookExist($oRepository, $iWebhookId)){

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
			$oRepository->Set('github_webhook_configuration',  null);
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

	/**
	 * Update external data from GitHub.
	 *
	 * @param \DBObject $oRepository
	 *
	 * @return void
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \Exception
	 */
	public function UpdateExternalData(DBObject $oRepository) : void
	{
		// API: get repository info
		try{
			$aGitHubData = $this->oGitHubAPIService->GetRepositoryInfo($oRepository);
		}
		catch(Exception $e){
			// log exception
			ExceptionLog::LogException($e);
			$oRepository->Set('external_data',  null);
			return;
		}

		// prepare data
		$aData = [
			'date' => AttributeDateTime::GetFormat()->format(new DateTime('now')),
			'github' => $aGitHubData
		];

		// Update repository and save
		$oRepository->Set('external_data',  json_encode($aData, JSON_UNESCAPED_SLASHES));
	}

	/**
	 * Extract repository from request param.
	 *
	 * @return \DBObject
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
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
}
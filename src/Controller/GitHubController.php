<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Controller;

use Combodo\iTop\Controller\AbstractController;
use Combodo\iTop\VCSManagement\Helper\GithubAPIHelper;
use Combodo\iTop\VCSManagement\Helper\ModuleHelper;
use Combodo\iTop\Application\WebPage\JsonPage;
use Combodo\iTop\VCSManagement\Helper\SessionHelper;
use Combodo\iTop\VCSManagement\Service\GitHubAPIService;
use Combodo\iTop\VCSManagement\Service\GitHubManager;
use Exception;
use ExceptionLog;
use MetaModel;
use utils;

/**
 * GitHub integration endpoints.
 *
 */
class GitHubController extends AbstractController
{
	public const ROUTE_NAMESPACE = 'github';

	/**
	 * Get repository app installation.
	 *
	 * @return JsonPage|null
	 * @noinspection PhpUnused
	 */
	public function OperationGetRepositoryAppInstallation(): ?JsonPage
	{
		// variables
		$oPage = new JsonPage();
		$aData = [];

		try{

			// services injection
			$oGitHubAPIService = GitHubAPIService::GetInstance();

			// retrieve repository
			$sRepositoryRef = utils::ReadParam('repository_id', '-1');
			if($sRepositoryRef === -1){
				throw new Exception('Missing `repository_id` query parameter');
			}
			$oRepository = MetaModel::GetObject('VCSRepository', $sRepositoryRef);

			// API: get repository app installation
			$aGitHubData = $oGitHubAPIService->GetRepositoryAppInstallation($oRepository);
			$aData['installation'] = $aGitHubData;
		}
		catch(Exception $e){

			// error handling
			ExceptionLog::LogException($e);
			$aData['errors'][] = $e->getMessage();
		}

		return $oPage->SetData($aData);
	}

	/**
	 * Get repository information.
	 *
	 * @return JsonPage|null
	 * @noinspection PhpUnused
	 */
	public function OperationGetRepositoryInfo(): ?JsonPage
	{
		// variables
		$oPage = new JsonPage();
		$aData = [];

		try
		{

			// services injection
			$oGitHubManager = GitHubManager::GetInstance();

			// retrieve repository
			$sRepositoryRef = utils::ReadParam('repository_id', '-1');
			if($sRepositoryRef === -1){
				throw new Exception('Missing `repository_id` query parameter');
			}
			$oRepository = MetaModel::GetObject('VCSRepository', $sRepositoryRef);

			// get repository info
			$oGitHubManager->GetRepositoryInfo($oRepository);
			$oRepository->DBUpdate();

			// get repository info template
			$aExternalData = json_decode($oRepository->Get('external_data'), true);
			$aData['template'] = ModuleHelper::RenderGitHubInfoTemplate($oRepository, $aExternalData);
		}
		catch(Exception $e){

			// error handling
			ExceptionLog::LogException($e);
			$aData['errors'][] = $e->getMessage();

		}

		return $oPage->SetData($aData);
	}

	/**
	 * Synchronize repository webhook configuration.
	 *
	 * @return JsonPage|null
	 * @noinspection PhpUnused
	 */
	public function OperationSynchronizeRepositoryWebhook(): ?JsonPage
	{
		// variables
		$oPage = new JsonPage();
		$aData = [];

		try{

			// services injection
			$oGitHubManager = GitHubManager::GetInstance();

			// retrieve repository
			$sRepositoryRef = utils::ReadParam('repository_id', '-1');
			if($sRepositoryRef === -1){
				throw new Exception('Missing `repository_id` query parameter');
			}
			$oRepository = MetaModel::GetObject('VCSRepository', $sRepositoryRef);

			// synchronize repository
			$aGitHubDataCheck = $oGitHubManager->SynchronizeRepository($oRepository);
			$oRepository->DBUpdate();

			// add webhook_status HTML
			/** @var \AttributeEnumSet $oAttributeSet */
			$oAttributeEnumSet = MetaModel::GetAttributeDef('VCSRepository', 'webhook_status');
			$aData['webhook_status_field_html'] = $oAttributeEnumSet->GetAsHTML($aGitHubDataCheck['active'] ? 'active' : 'inactive');
		}
		catch(Exception $e){

			// error handling
			ExceptionLog::LogException($e);
			$aData['errors'][] = $e->getMessage();

		}

		return $oPage->SetData($aData);
	}

	/**
	 * Check repository webhook synchro state.
	 *
	 * @return JsonPage|null
	 * @noinspection PhpUnused
	 */
	public function OperationCheckRepositoryWebhookSynchro(): ?JsonPage
	{
		// variables
		$oPage = new JsonPage();
		$aData = [];

		try{

			// services injection
			$oGitHubManager  = GitHubManager::GetInstance();

			// retrieve repository
			$sRepositoryRef = utils::ReadParam('repository_id', '-1');
			if($sRepositoryRef === -1){
				throw new Exception('Missing `repository_id` query parameter');
			}
			$oRepository = MetaModel::GetObject('VCSRepository', $sRepositoryRef);

			// test GitHub repository existence
			$oGitHubManager->UpdateWebhookStatus($oRepository);
			$oRepository->DBUpdate();

			/** @var \AttributeEnumSet $oAttributeSet */
			$oAttributeEnumSet = MetaModel::GetAttributeDef('VCSRepository', 'webhook_status');
			$aData['webhook_status_field_html'] = $oAttributeEnumSet->GetAsHTML($oRepository->Get('webhook_status'));
			$aData['webhook_status'] = $oRepository->Get('webhook_status');
		}
		catch(Exception  $e){
			ExceptionLog::LogException($e);
			$aData['errors'][] = $e->getMessage();
		}

		return $oPage->SetData($aData);
	}

	/**
	 * Stop repository synchronization.
	 *
	 * @return JsonPage|null
	 * @noinspection PhpUnused
	 */
	public function OperationStopRepositorySynchronization(): ?JsonPage
	{
		// variables
		$oPage = new JsonPage();
		$aData = [];

		try{

			// services injection
			$oGitHubManager  = GitHubManager::GetInstance();

			// retrieve repository
			$sRepositoryRef = utils::ReadParam('repository_id', '-1');
			if($sRepositoryRef === -1){
				throw new Exception('Missing `repository_id` query parameter');
			}
			$oRepository = MetaModel::GetObject('VCSRepository', $sRepositoryRef);

			// delete synchronization
			$oGitHubManager->DeleteWebhookSynchronization($oRepository);
			$oRepository->DBUpdate();

			/** @var \AttributeEnumSet $oAttributeSet */
			$oAttributeEnumSet = MetaModel::GetAttributeDef('VCSRepository', 'webhook_status');
			$aData['webhook_status_field_html'] = $oAttributeEnumSet->GetAsHTML($oRepository->Get('webhook_status'));
		}
		catch(Exception  $e){
			ExceptionLog::LogException($e);
			$aData['errors'][] = $e->getMessage();
		}

		return $oPage->SetData($aData);
	}

	/**
	 * Clear session.
	 *
	 * @return JsonPage|null
	 * @noinspection PhpUnused
	 */
	public function OperationClearSession(): ?JsonPage
	{
		// variables
		$oPage = new JsonPage();
		$aData = [];

		try{

			// retrieve repository
			$sRepositoryRef = utils::ReadParam('repository_id', '-1');
			if($sRepositoryRef === -1){
				throw new Exception('Missing `repository_id` query parameter');
			}
			$oRepository = MetaModel::GetObject('VCSRepository', $sRepositoryRef);
			$sRepoName = $oRepository->Get('name');

			// clear repository session
			SessionHelper::ClearVars($sRepoName);

			// log
			ModuleHelper::LogInfo('Session variables reset for repository ' . $sRepoName);

			$aData['success'] = 'session reset';
		}
		catch(Exception  $e){
			ExceptionLog::LogException($e);
			$aData['errors'][] = $e->getMessage();
		}

		return $oPage->SetData($aData);
	}
}
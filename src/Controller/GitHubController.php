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
use Combodo\iTop\VCSManagement\Service\GitHubManager;
use Exception;
use ExceptionLog;

/**
 * GitHub integration endpoints.
 *
 */
class GitHubController extends AbstractController
{
	public const ROUTE_NAMESPACE = 'github';

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
			$oRepository = $oGitHubManager->ExtractRepositoryFromRequestParam();

			// get repository info
			$oGitHubManager->UpdateExternalData($oRepository);
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
			$oRepository = $oGitHubManager->ExtractRepositoryFromRequestParam();

			// synchronize repository
			$oGitHubManager->SynchronizeRepository($oRepository);
			$oRepository->DBUpdate();

			// append webhook status field html
			$oGitHubManager->AppendWebhookStatusFieldHtml($oRepository, $aData);
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
			$oRepository = $oGitHubManager->ExtractRepositoryFromRequestParam();

			// test GitHub repository existence
			$oGitHubManager->UpdateWebhookStatus($oRepository);
			$oRepository->DBUpdate();

			// append webhook status field html
			$oGitHubManager->AppendWebhookStatusFieldHtml($oRepository, $aData);
		}
		catch(Exception  $e){

			ExceptionLog::LogException($e);
			$aData['errors'][] = $e->getMessage();
		}

		return $oPage->SetData($aData);
	}

}
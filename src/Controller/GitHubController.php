<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Controller;

use Combodo\iTop\Controller\AbstractController;
use Combodo\iTop\VCSManagement\Service\GitHubManager;
use Combodo\iTop\VCSManagement\Service\TemplatingService;
use Exception;
use ExceptionLog;
use JsonPage;

/**
 * GitHub integration endpoints.
 *
 */
class GitHubController extends AbstractController
{
	public const ROUTE_NAMESPACE = 'github';

	/**
	 * Get webhook information.
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
			$oTemplatingService = TemplatingService::GetInstance();

			// retrieve webhook
			$oWebhook = $oGitHubManager->ExtractRepositoryFromRequestParam();

			// get webhook info
			$aWebhookInfoResult = $oGitHubManager->UpdateExternalData($oWebhook);
			foreach($aWebhookInfoResult['errors'] as $sError){
				$aData['errors'][] = $sError;
			}
			$oWebhook->DBUpdate();

			// get webhook info template
			$aExternalData = json_decode($oWebhook->Get('external_data'), true);
			$aData['template'] = $oTemplatingService->RenderGitHubInfoTemplate($oWebhook, $aExternalData);
		}
		catch(Exception $e){

			// error handling
			ExceptionLog::LogException($e, [
				'happened_on' => 'OperationGetRepositoryInfo in GitHubController.php',
				'error_msg' => $e->getMessage(),
			]);
			$aData['errors'][] = $e->getMessage();
			$aData['fatal'] = true;
		}

		return $oPage->SetData($aData);
	}

	/**
	 * Synchronize webhook configuration.
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

			// retrieve webhook
			$oWebhook = $oGitHubManager->ExtractRepositoryFromRequestParam();

			// synchronize webhook
			$aSynchronizationResult = $oGitHubManager->SynchronizeRepository($oWebhook);
			foreach($aSynchronizationResult['errors'] as $sError){
				$aData['errors'][] = $sError;
			}
			$oWebhook->DBUpdate();

			// append webhook status field html
			$oGitHubManager->AppendWebhookStatusFieldHtml($oWebhook, $aData);
		}
		catch(Exception $e){

			// error handling
			ExceptionLog::LogException($e, [
				'happened_on' => 'OperationSynchronizeRepositoryWebhook in GitHubController.php',
				'error_msg' => $e->getMessage(),
			]);
			$aData['errors'][] = $e->getMessage();
			$aData['fatal'] = true;
		}

		return $oPage->SetData($aData);
	}

	/**
	 * Check webhook synchro state.
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

			// retrieve webhook
			$oWebhook = $oGitHubManager->ExtractRepositoryFromRequestParam();

			// test GitHub webhook existence
			$aCheckWebhookWebhookSynchroResult = $oGitHubManager->UpdateWebhookStatus($oWebhook);
			foreach($aCheckWebhookWebhookSynchroResult['errors'] as $sError){
				$aData['errors'][] = $sError;
			}
			$oWebhook->DBUpdate();

			// append webhook status field html
			$oGitHubManager->AppendWebhookStatusFieldHtml($oWebhook, $aData);
		}
		catch(Exception  $e){

			ExceptionLog::LogException($e, [
				'happened_on' => 'OperationCheckRepositoryWebhookSynchro in GitHubController.php',
				'error_msg' => $e->getMessage(),
			]);
			$aData['errors'][] = $e->getMessage();
			$aData['fatal'] = true;
		}

		return $oPage->SetData($aData);
	}

}
<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Controller;

use Combodo\iTop\Controller\AbstractController;
use Combodo\iTop\VCSManagement\Service\GitHubAPIAuthenticationService;
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
			$oTemplatingService = TemplatingService::GetInstance();

			// retrieve webhook
			$oWebhook = $oGitHubManager->ExtractWebhookFromRequestParam();

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
				'happened on' => 'OperationGetRepositoryInfo in GitHubController.php',
				'error message' => $e->getMessage(),
			]);
			$aData['errors'][] = $e->getMessage();
		}

		return $oPage->SetData($aData);
	}

	/**
	 * Synchronize webhook configuration.
	 *
	 * @return JsonPage|null
	 * @noinspection PhpUnused
	 */
	public function OperationSynchronizeWebhookConfiguration(): ?JsonPage
	{
		// variables
		$oPage = new JsonPage();
		$aData = [];

		try{

			// services injection
			$oGitHubManager = GitHubManager::GetInstance();

			// retrieve webhook
			$oWebhook = $oGitHubManager->ExtractWebhookFromRequestParam();

			// synchronize webhook
			$aSynchronizationResult = $oGitHubManager->SynchronizeWebhook($oWebhook);
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
				'happened on' => 'OperationSynchronizeWebhookConfiguration in GitHubController.php',
				'error message' => $e->getMessage(),
			]);
			$aData['errors'][] = $e->getMessage();
		}

		return $oPage->SetData($aData);
	}

	/**
	 * Check webhook synchro state.
	 *
	 * @return JsonPage|null
	 * @noinspection PhpUnused
	 */
	public function OperationCheckWebhookConfigurationSynchro(): ?JsonPage
	{
		// variables
		$oPage = new JsonPage();
		$aData = [];

		try{

			// services injection
			$oGitHubManager  = GitHubManager::GetInstance();

			// retrieve webhook
			$oWebhook = $oGitHubManager->ExtractWebhookFromRequestParam();

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
				'happened on' => 'OperationCheckWebhookConfigurationSynchro in GitHubController.php',
				'error message' => $e->getMessage(),
			]);
			$aData['errors'][] = $e->getMessage();
		}

		return $oPage->SetData($aData);
	}

	/**
	 * Regenerate an access token.
	 *
	 * @return JsonPage|null
	 * @noinspection PhpUnused
	 */
	public function OperationRegenerateAccessToken(): ?JsonPage
	{
		// variables
		$oPage = new JsonPage();
		$aData = [];

		try{

			// services injection
			$oGitHubManager  = GitHubManager::GetInstance();
			$oGitHubApiAuthenticationService = GitHubAPIAuthenticationService::GetInstance();

			// retrieve webhook
			/** @var \VCSWebhook $oWebhook */
			$oWebhook = $oGitHubManager->ExtractWebhookFromRequestParam();
			$oConnector = $oWebhook->GetConnector();

			// revoke token
			$oGitHubApiAuthenticationService->RegenerateAccessToken($oConnector);
		}
		catch(Exception $e){

			$aData['errors'][] = $e->getMessage();
		}


		return $oPage->SetData($aData);
	}

}
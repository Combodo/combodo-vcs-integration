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

			// retrieve application
			$oApplication = $oGitHubManager->ExtractApplicationFromRequestParam();

			// synchronize webhook
			$aSynchronizationResult = $oGitHubManager->SynchronizeWebhook($oApplication);
			foreach($aSynchronizationResult['errors'] as $sError){
				$aData['errors'][] = $sError;
			}
			$oApplication->DBUpdate();

			// append webhook status field html
			$oGitHubManager->AppendWebhookStatusFieldHtml($oApplication, $aData);
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
			$oApplication = $oGitHubManager->ExtractApplicationFromRequestParam();

			// test GitHub webhook existence
			$aCheckWebhookWebhookSynchroResult = $oGitHubManager->UpdateWebhookStatus($oApplication);
			foreach($aCheckWebhookWebhookSynchroResult['errors'] as $sError){
				$aData['errors'][] = $sError;
			}
			$oApplication->DBUpdate();

			// append webhook status field html
			$oGitHubManager->AppendWebhookStatusFieldHtml($oApplication, $aData);
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
			$oApplication = $oGitHubManager->ExtractApplicationFromRequestParam();

			// revoke token
			$oGitHubApiAuthenticationService->RegenerateAccessToken($oApplication);
		}
		catch(Exception $e){

			$aData['errors'][] = $e->getMessage();
		}


		return $oPage->SetData($aData);
	}

}
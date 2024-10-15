<?php

/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\BackgroundProcess;

use Combodo\iTop\VCSManagement\Helper\ModuleHelper;
use Combodo\iTop\VCSManagement\Service\GitHubManager;
use DBObjectSet;
use DBSearch;
use Exception;
use ExceptionLog;
use iBackgroundProcess;

/**
 * Background task for GitHub integration.
 *
 * - check synchronization state
 * - synchronize and get webhook metrics if synchro auto
 *
 */
class VCSWebhookSynchroProcess implements iBackgroundProcess
{
	// task periodicity
	private static int $iPERIODICITY = 60 * 60 * 24;

	/** @var GitHubManager $oGitHubManager */
	private GitHubManager $oGitHubManager;

	/**
	 * Constructor.
	 *
	 * @throws \Exception
	 */
	public function __construct()
	{
		// Retrieve service dependencies
		$this->oGitHubManager = GitHubManager::GetInstance();
	}

	/** @inheritDoc * */
	public function GetPeriodicity() : int
	{
		// periodicity from module configuration
		$sSynchroAUtoInterval = ModuleHelper::GetModuleSetting(ModuleHelper::$PARAM_SYNCHRO_AUTO_INTERVAL);
		if($sSynchroAUtoInterval !== null){
			try{
				return intval($sSynchroAUtoInterval);
			}
			catch(Exception){}
		}

		return self::$iPERIODICITY;
	}

	/** @inheritDoc *
	 * @throws \Exception
	 */
	public function Process($iUnixTimeLimit) : void
	{
		// log
		ModuleHelper::LogDebug('Background task execution');

		// search webhooks
		$oDbObjectSearch = DBSearch::FromOQL('SELECT VCSWebhook');
		$oDbObjectSearch->SetShowObsoleteData(false);
		$oDbObjectSet = new DBObjectSet($oDbObjectSearch);

		// iterate throw webhooks...
		while ((time() < $iUnixTimeLimit) && ($oWebhook = $oDbObjectSet->Fetch())) {

			try{

				// ignore webhook without connector
				if($oWebhook->Get('connector_id') === 0){
					continue;
				}

				// check webhook status
				$this->oGitHubManager->UpdateWebhookStatus($oWebhook);
				$oWebhook->DBUpdate();

				// auto synchronize
				$this->oGitHubManager->PerformWebhookAutoSynchronization($oWebhook);
			}
			catch(Exception $e){

				// trace
				ExceptionLog::LogException($e, [
					'happened_on' => 'Process in VCSBackgroundProcess.php',
					'webhook' => $oWebhook->GetKey(),
					'error_msg' => $e->getMessage(),
				]);
			}
		}
	}
}
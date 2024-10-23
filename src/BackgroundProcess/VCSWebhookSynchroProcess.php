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
		// search applications
		$oDbObjectSearch = DBSearch::FromOQL('SELECT VCSApplication');
		$oDbObjectSearch->SetShowObsoleteData(false);
		$oDbObjectSet = new DBObjectSet($oDbObjectSearch);

		// iterate throw webhooks...
		while ((time() < $iUnixTimeLimit) && ($oApplication = $oDbObjectSet->Fetch())) {

			try{

				// check webhook status
				$this->oGitHubManager->UpdateWebhookStatus($oApplication);
				$oApplication->DBUpdate();

				// auto synchronize
				$this->oGitHubManager->PerformWebhookAutoSynchronization($oApplication);
			}
			catch(Exception $e){

				// trace
				ExceptionLog::LogException($e, [
					'happened on' => 'Process in VCSBackgroundProcess.php',
					'VCSWebhook' => $oApplication->GetKey(),
					'error message' => $e->getMessage(),
				]);
			}
		}
	}
}
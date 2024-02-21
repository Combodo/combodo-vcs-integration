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
 * - get repository metrics
 *
 */
class VCSBackgroundProcess implements iBackgroundProcess
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
		return self::$iPERIODICITY;
	}

	/** @inheritDoc *
	 * @throws \Exception
	 */
	public function Process($iUnixTimeLimit) : void
	{
		// log
		ModuleHelper::LogDebug('Background task execution');

		// Create db search
		$oDbObjectSearch = DBSearch::FromOQL('SELECT VCSRepository');
		$oDbObjectSearch->SetShowObsoleteData(false);

		// Create db set from db search
		$oDbObjectSet = new DBObjectSet($oDbObjectSearch);

		// iterate throw repositories...
		while ($oRepository = $oDbObjectSet->Fetch()) {

			try{

				// ignore repository without connector
				if($oRepository->Get('connector_id') === 0){
					continue;
				}

				// ignore if synchro mode manual
				if($oRepository->Get('synchro_mode') === 'manual'){
					continue;
				}

				// check repository
				$this->oGitHubManager->UpdateWebhookStatus($oRepository);
				$oRepository->DBUpdate();
			}
			catch(Exception $e){

				// trace
				ExceptionLog::LogException($e);
			}
		}
	}
}
<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Service;

use DBObject;
use Exception;
use ExceptionLog;
use GithubRepository;
use MetaModel;
use utils;
use VCSRepository;

/**
 * Automation manager;
 *
 */
class AutomationManager
{
	/** @var AutomationManager|null Singleton */
	static private ?AutomationManager $oSingletonInstance = null;

	/**
	 * GetInstance.
	 *
	 * @return AutomationManager
	 */
	public static function GetInstance(): AutomationManager
	{
		if (is_null(self::$oSingletonInstance)) {
			self::$oSingletonInstance = new AutomationManager();
		}

		return self::$oSingletonInstance;
	}

	/**
	 * Handle received webhook.
	 *
	 * @param string $sType
	 * @param \VCSRepository $oRepository
	 * @param array $aPayload
	 *
	 * @throws \Exception
	 *
	 * @return int
	 */
	public function HandleWebhook(string $sType, VCSRepository $oRepository, array $aPayload) : int
	{
		// variables
		$iAutomationTriggeredCount = 0;

		// iterate through automations...
		foreach($oRepository->Get('automations') as $oLnk){

			// retrieve automation
			$oAutomation = MetaModel::GetObject('VCSAutomation', $oLnk->Get('automation_id'));

			// handle event
			if(in_array($sType, $oAutomation->Get('events')->GetValues())){

				// automation inactive
				if($oLnk->Get('status') === 'inactive'){
					continue;
				}

				// automation condition
				if(!$oLnk->IsConditionUnsetOrMet($aPayload)){
					continue;
				}

				// compute scope and run automation
				$sScopeVar = $oAutomation->Get('scope_var');
				if(!utils::IsNullOrEmptyString($sScopeVar)){

					$aData = $aPayload[$sScopeVar];
					if(!is_array($aData)){
						throw new Exception($sScopeVar . ' is not an array');
					}

					foreach($aData as $aBatchData){
						self::LaunchAutomation($oAutomation, $sType, $aBatchData, $aPayload);
						$iAutomationTriggeredCount++;
					}
				}
				else{
					self::LaunchAutomation($oAutomation, $sType, $aPayload);
					$iAutomationTriggeredCount++;
				}

			}
		}

		return $iAutomationTriggeredCount;
	}

	/**
	 * Launch automation.
	 *
	 * @param DBObject $oAutomation
	 * @param string $sType
	 * @param array $aPayload
	 * @param array $aContext
	 *
	 * @return void
	 */
	private static function LaunchAutomation(DBObject $oAutomation, string $sType, array $aPayload, array $aContext = []) : void
	{
		try{
			$oAutomation->HandleEvent($sType, $aPayload, $aContext);
		}
		catch(Exception $e){
			ExceptionLog::LogException($e);
		}
}

}
<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Service;

use Combodo\iTop\VCSManagement\Helper\ModuleHelper;
use DBObject;
use Exception;
use ExceptionLog;
use MetaModel;
use utils;
use VCSAutomation;
use VCSWebhook;

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
	 * @param \VCSWebhook $oWebhook
	 * @param array $aPayload
	 *
	 * @throws \Exception
	 *
	 * @return int
	 */
	public function HandleWebhook(string $sType, VCSWebhook $oWebhook, array $aPayload) : int
	{
		// variables
		$iAutomationTriggeredCount = 0;

		// iterate through automations...
		foreach($oWebhook->Get('automations_list') as $oLnk){

			// retrieve automation
			$oAutomation = MetaModel::GetObject('VCSAutomation', $oLnk->Get('automation_id'));

			// handle event
			$aAutomationEvents = [];
			$oLnkAutomationToEventSet = $oAutomation->Get('events_list');
			while ($oLnkAutomationToEvent = $oLnkAutomationToEventSet->Fetch()) {
				$aAutomationEvents[] = $oLnkAutomationToEvent->Get('event_name');
			}
			if(in_array($sType, $aAutomationEvents)){

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
						throw new Exception('scope var ' . $sScopeVar . ' is not an array');
					}

					$AutomationGroupData = [];
					foreach($aData as $ScopeData){
						$ScopeData['context'] = $aPayload;
						self::LaunchAutomationHandleEvent($oAutomation, $sType, $aPayload, $ScopeData, $AutomationGroupData);
					}
					self::LaunchAutomationHandleScopeEnd($oAutomation, $sType, $aPayload, $AutomationGroupData);
				}
				else{
					self::LaunchAutomationHandleEvent($oAutomation, $sType, $aPayload);
				}

				$iAutomationTriggeredCount++;
			}
		}

		return $iAutomationTriggeredCount;
	}

	/**
	 * Launch automation handle data.
	 *
	 * @param DBObject $oAutomation
	 * @param string $sType
	 * @param array $aPayload
	 * @param array $aScopeData
	 * @param array $AutomationData
	 *
	 * @return void
	 */
	private static function LaunchAutomationHandleEvent(DBObject $oAutomation, string $sType, array $aPayload, array $aScopeData = [], array &$AutomationData = []) : void
	{
		try{
			$oAutomation->HandleEvent($sType, $aPayload, $aScopeData, $AutomationData);
		}
		catch(Exception $e){
			ExceptionLog::LogException($e, [
				'happened on' => 'LaunchAutomationHandleEvent in AutomationManager.php',
				'error message' => $e->getMessage(),
			]);
		}
	}

	/**
	 * Launch automation handle scope end.
	 *
	 * @param DBObject $oAutomation
	 * @param string $sType
	 * @param array $aPayload
	 * @param array $aAutomationData
	 *
	 * @return void
	 */
	private static function LaunchAutomationHandleScopeEnd(DBObject $oAutomation, string $sType, array $aPayload, array $aAutomationData = []) : void
	{
		try{
			$oAutomation->HandleScopeEnd($sType, $aPayload, $aAutomationData);
		}
		catch(Exception $e){
			ExceptionLog::LogException($e, [
				'happened on' => 'LaunchAutomationHandleScopeEnd in AutomationManager.php',
				'error message' => $e->getMessage(),
			]);
		}
	}

	/**
	 * @param \DBObject $oLnkAutomationToRepository
	 * @param int $iConditionNumber
	 * @param array $aPayload
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function IsConditionUnsetOrMet(DBObject $oLnkAutomationToRepository, int $iConditionNumber, array $aPayload) : bool
	{
		// check condition number
		if($iConditionNumber <= 0 || $iConditionNumber > 3){
			throw new Exception("Condition number `$iConditionNumber` is invalid");
		}

		// check condition
		$sCondition = $oLnkAutomationToRepository->Get('condition_' . $iConditionNumber);
		if(!utils::IsNullOrEmptyString($sCondition)){
			$aMatch = [];

            $res = preg_match('/NOT_NULL\((.*)\)/', $sCondition, $aMatch);
            if($res === 1){
                $val = ModuleHelper::ExtractDataFromArray($aPayload, $aMatch[1]);
                if($val === 'null'){
                    return false;
                }
            }

			$res = preg_match('/([>\w-]+)=(.*)/', $sCondition, $aMatch);
			if($res === 1){
				$val = ModuleHelper::ExtractDataFromArray($aPayload, $aMatch[1]);
				if(!preg_match("#$aMatch[2]#", $val)){

					$sAutomationRef = $oLnkAutomationToRepository->Get('automation_id');
					$oAutomation = MetaModel::GetObject(VCSAutomation::class, $sAutomationRef);

					ModuleHelper::LogDebug("Unmet condition for automation", [
						'VCSAutomation' => $oAutomation->GetKey(),
						'VCSLnkAutomationToRepository' => $oLnkAutomationToRepository->GetKey(),
						'automation name' => $oAutomation->Get('name'),
						'condition number' => $iConditionNumber,
						'condition' => $sCondition,
						'payload value' => $val
					]);

					return false;
				}
			}
		}

		return true;
	}
}
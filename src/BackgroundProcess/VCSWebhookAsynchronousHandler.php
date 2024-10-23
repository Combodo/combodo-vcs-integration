<?php

/*
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\BackgroundProcess;

use Combodo\iTop\VCSManagement\Helper\ModuleHelper;
use Combodo\iTop\VCSManagement\Service\AutomationManager;
use DBObjectSet;
use DBSearch;
use Exception;
use ExceptionLog;
use iBackgroundProcess;
use MetaModel;
use VCSWebhook;

/**
 * Asynchronous handing of webhook payloads
 *
 */
class VCSWebhookAsynchronousHandler implements iBackgroundProcess
{
	// task periodicity
	private static int $iPERIODICITY = 300;

	/** @inheritDoc * */
	public function GetPeriodicity() : int
	{
		// periodicity from module configuration
		$sInterval = ModuleHelper::GetModuleSetting(ModuleHelper::$PARAM_ASYNCHRONOUS_HANDLER_INTERVAL);
		if ($sInterval !== null){
			try{
				return intval($sInterval);
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
        // get automation instance
        $oAutomationInstance = AutomationManager::GetInstance();

        // search webhooks
        $oDbObjectSet = new DBObjectSet(DBSearch::FromOQL('SELECT VCSWebhookPayload'));
		$aDbObjectSet = $oDbObjectSet->ToArray();
        ksort($aDbObjectSet);

		// log
		ModuleHelper::LogDebug('Asynchronous webhook handler execution', [
			'payloads count' => count($aDbObjectSet)
		]);

		// iterate through payloads
        foreach ($aDbObjectSet as $iKey => $oWebhookPayload) {

			try {
                if ($oWebhookPayload->Get('provider') == 'github') {
                    /** @var VCSWebhook $oWebhook */
                    $oWebhook = MetaModel::GetObject('VCSWebhook', $oWebhookPayload->Get('webhook_id'));
	                $iAutomationsTriggeredCount = $oAutomationInstance->HandleWebhook($oWebhookPayload->Get('type'), $oWebhook, json_decode($oWebhookPayload->Get('payload'), true));
                    $oWebhookPayload->DBDelete();

	                // increment events count and last date
	                $oWebhook->DBIncrement('event_count');
	                $oWebhook->Set('last_event_date', time());
	                $oWebhook->DBUpdate();

	                // log
	                ModuleHelper::LogDebug('Processing payload Ref:' . $iKey, [
						'VCSWebhookPayload' => $iKey,
		                'VCSWebhook' => $oWebhookPayload->Get('webhook_id'),
		                'provider' => $oWebhookPayload->Get('provider'),
		                'event type' => $oWebhookPayload->Get('type'),
		                'automations triggered count' => $iAutomationsTriggeredCount,
	                ]);
                }
            } catch (Exception $e) {
				// trace
				ExceptionLog::LogException($e, [
					'happened on' => 'Process in VCSWebhookAsynchronousHandler.php',
					'VCSWebhookPayload' => $iKey,
					'error message' => $e->getMessage(),
				]);
			}
            if (time() >= $iUnixTimeLimit) {
	            // log
	            ModuleHelper::LogDebug('Asynchronous webhook handler stopped (execution time limit)', [
		            'time limit' => $iUnixTimeLimit,
	            ]);
                break;
            }
		}
	}
}
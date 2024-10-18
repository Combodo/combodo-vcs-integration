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
	private static int $iPERIODICITY = 10;

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
		// log
		ModuleHelper::LogDebug('Asynchronous handler execution');

        // get automation instance
        $oAutomationInstance = AutomationManager::GetInstance();

        // search webhooks
        $oDbObjectSet = new DBObjectSet(DBSearch::FromOQL('SELECT VCSWebhookPayload'));
		$aDbObjectSet = $oDbObjectSet->ToArray();
        ksort($aDbObjectSet);

		// iterate through payloads
        foreach ($aDbObjectSet as $iKey => $oWebhookPayload) {
            try {
                if ($oWebhookPayload->Get('provider') == 'github') {
                    /** @var VCSWebhook $oWebhook */
                    $oWebhook = MetaModel::GetObject('VCSWebhook', $oWebhookPayload->Get('webhook_id'));
                    $oAutomationInstance->HandleWebhook($oWebhookPayload->Get('type'), $oWebhook, json_decode($oWebhookPayload->Get('payload'), true));
                    $oWebhookPayload->DBDelete();
                }
            } catch (Exception $e) {
				// trace
				ExceptionLog::LogException($e, [
					'happened_on' => 'Process in VCSWebhookAsynchronousHandler.php',
					'webhook' => $oWebhook->GetKey(),
					'error_msg' => $e->getMessage(),
				]);
			}
            if (time() >= $iUnixTimeLimit) {
                break;
            }
		}
	}
}
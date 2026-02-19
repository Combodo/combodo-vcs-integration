<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Helper;

use Exception;
use IssueLog;
use MetaModel;
use utils;

/**
 * Module facilities.
 *
 */
class ModuleHelper
{
	// module name
    public const MODULE_NAME = "combodo-vcs-integration";

	// module parameters
	public static string $PARAM_WEBHOOK_USER_ID = 'webhook_user_id';
	public static string $PARAM_SYNCHRO_AUTO_INTERVAL = 'synchro_auto_interval';
    public static string $PARAM_ASYNCHRONOUS_HANDLER_INTERVAL = 'asynchronous_handler_interval';
	public static string $PARAM_WEBHOOK_HOST_OVERLOAD = 'webhook_host_overload';
	public static string $PARAM_WEBHOOK_SCHEME_OVERLOAD = 'webhook_scheme_overload';

	/**
	 * Get module absolute url.
	 *
	 * @return string
	 * @throws Exception
	 * @noinspection PhpUnused
	 */
	static public function GetModuleAbsoluteUrl() : string
	{
		return utils::GetAbsoluteUrlModulesRoot() . ModuleHelper::MODULE_NAME;
	}

	/**
     * Get module templates paths.
     *
     * @return string templates path
     */
    static public function GetTemplatePath() : string
    {
        return MODULESROOT . Self::MODULE_NAME . '/templates';
    }

	/**
	 * Get a module setting.
	 *
	 * @param string $sProperty
	 * @param mixed $defaultValue
	 * @return mixed
	 */
	static public function GetModuleSetting(string $sProperty, mixed $defaultValue = null) : mixed
	{
		return MetaModel::GetModuleSetting(Self::MODULE_NAME, $sProperty, $defaultValue);
	}


	/**
	 * Log debug message.
	 *
	 * @param string $sMessage
	 * @param array|null $aContext
	 *
	 * @return void
	 */
	static public function LogDebug(string $sMessage, ?array $aContext = null) : void
	{
		IssueLog::Debug(ModuleHelper::MODULE_NAME . ' ' . $sMessage, null, $aContext);
	}

	/**
	 * Log info message.
	 *
	 * @param string $sMessage
	 * @param array|null $aContext
	 *
	 * @return void
	 */
	static public function LogInfo(string $sMessage, ?array $aContext = null) : void
	{
		IssueLog::Info(ModuleHelper::MODULE_NAME . ' ' . $sMessage, null, $aContext);
	}

	/**
	 * @param callable $oFunction
	 *
	 * @return mixed
	 */
	static public function CallFunctionWithoutDisplayingPHPErrors(callable $oFunction) : mixed
	{
		$ini = ini_get('display_errors');
		ini_set('display_errors', 0);
		$return = $oFunction();
		ini_set('display_errors', $ini);
		return $return;
	}

	/**
	 * Extract data form an array.
	 *
	 * @param array $aArray The array
	 * @param string $sData Data to extract (e.g. 'data->data->data')
	 *
	 * @return mixed
	 */
	public static function ExtractDataFromArray(array $aArray, string $sData) : mixed
	{
		// explode expression
		$aElements = explode('->', $sData);

		$aSearch = $aArray;

		// search expression data...
		foreach ($aElements as $sElement){
			if(!array_key_exists($sElement, $aSearch)) return $sElement;
			$aSearch =  $aSearch[$sElement];
		}

		// convert bool & null
		if(is_bool($aSearch)){
			$aSearch = $aSearch ? 'true' : 'false';
		}
		if($aSearch === null){
			$aSearch = 'null';
		}

		return $aSearch;
	}
}
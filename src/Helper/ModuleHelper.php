<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Helper;

use Combodo\iTop\Application\TwigBase\Twig\TwigHelper;
use DBObject;
use Exception;
use ExceptionLog;
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
    public const MODULE_NAME = "combodo-github-integration";

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
	 * Check if a connector is installed.
	 *
	 * @param \DBObject $oRepository
	 *
	 * @return bool
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	static public function IsConnectorInstalled(DBObject $oRepository) : bool
	{
		$sConnectorRef = $oRepository->Get('connector_id');
		$oConnector = MetaModel::GetObject('VCSConnector', $sConnectorRef);
		return $oConnector->Get('mode') !== 'none';
	}

	/**
	 * Render a template.
	 *
	 * @param string $sTemplate
	 * @param array $aContext
	 *
	 * @return string
	 */
	static public function RenderTemplate(string $sTemplate, array $aContext = []) : string
	{
		try{
			$oTwig = TwigHelper::GetTwigEnvironment(self::GetTemplatePath());
			return $oTwig->render($sTemplate, $aContext);
		}
		catch(Exception $e){
			ExceptionLog::LogException($e);
			return 'template error';
		}
	}

	/**
	 * RenderGitHubInfoTemplate
	 *
	 * @param DBObject $oRepository The repository
	 * @param array|null $aData The data containing repository information
	 *
	 * @return string the HTML template string for displaying repository information
	 */
	static public function RenderGitHubInfoTemplate(DBObject $oRepository, ?array $aData) : string
	{
		if(empty($aData)){
			return '';
		}

		return self::RenderTemplate('github_info.html.twig', [
			'url' => $aData['github']['clone_url'],
			'watchers_count' => $aData['github']['watchers_count'],
			'forks_count' => $aData['github']['forks'],
			'issues_count' => $aData['github']['open_issues'],
			'description' => $aData['github']['description'],
			'date' => $aData['date'],
			'user_login' => $aData['github']['owner']['login'],
			'user_avatar' => $aData['github']['owner']['avatar_url'],
		]);

	}

	/**
	 * Log debug message.
	 *
	 * @param string $sMessage
	 * @param array|null $aContext
	 *
	 * @return void
	 */
	static public function LogDebug(string $sMessage, array $aContext = null) : void
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
	static public function LogInfo(string $sMessage, array $aContext = null) : void
	{
		IssueLog::Info(ModuleHelper::MODULE_NAME . ' ' . $sMessage, null, $aContext);
	}
}
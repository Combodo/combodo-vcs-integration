<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Helper;

use Combodo\iTop\Application\Helper\Session;
use DateTime;
use DateTimeZone;
use Exception;

/**
 * Session helper.
 *
 * Share the application instance access token.
 */
class SessionHelper
{
	public static string $SESSION_APP_INSTALLATION_ID = 'github_app_installation_id';
	public static string $SESSION_APP_INSTALLATION_ACCESS_TOKEN = 'github_app_installation_access_token';
	public static string $SESSION_APP_INSTALLATION_ACCESS_TOKEN_EXPIRATION_DATE = 'github_app_installation_access_token_expiration_date';

	/**
	 * Get a session variable name.
	 *
	 * @param string $sSessionVar
	 * @param string $sRepository
	 *
	 * @return string
	 */
	static private function GetVarName(string $sSessionVar, string $sRepository) : string
	{
		return  $sSessionVar . "[$sRepository]";
	}

	/**
	 * Get a session variable value.
	 *
	 * @param string $sSessionVar
	 * @param string $sRepository
	 *
	 * @return mixed
	 */
	static public function GetVar(string $sSessionVar, string $sRepository) : mixed
	{
		$sVarName = self::GetVarName($sSessionVar, $sRepository);
		return Session::Get($sVarName);
	}

	/**
	 * Set a session variable value.
	 *
	 * @param string $sSessionVar
	 * @param string $sRepository
	 * @param mixed $oValue
	 *
	 * @return void
	 */
	static public function SetVar(string $sSessionVar, string $sRepository, mixed $oValue) : void
	{
		$sVarName = self::GetVarName($sSessionVar, $sRepository);
		Session::Set($sVarName, $oValue);
	}

	/**
	 * Test a session variable existence.
	 *
	 * @param string $sSessionVar
	 * @param string $sRepository
	 *
	 * @return bool
	 */
	static public function IsSetVar(string $sSessionVar, string $sRepository) : bool
	{
		$sVarName = self::GetVarName($sSessionVar, $sRepository);
		return Session::IsSet($sVarName);
	}

	/**
	 * Unset a session variable.
	 *
	 * @param string $sSessionVar
	 * @param string $sRepository
	 *
	 * @return void
	 */
	static public function UnsetVar(string $sSessionVar, string $sRepository) : void
	{
		$sVarName = self::GetVarName($sSessionVar, $sRepository);
		Session::Unset($sVarName);
	}

	/**
	 * Clear session variables.
	 *
	 * @param string $sRepository
	 *
	 * @return void
	 */
	static public function ClearVars(string $sRepository) : void
	{
		self::UnsetVar(self::$SESSION_APP_INSTALLATION_ID, $sRepository);
		self::UnsetVar(self::$SESSION_APP_INSTALLATION_ACCESS_TOKEN_EXPIRATION_DATE, $sRepository);
		self::UnsetVar(self::$SESSION_APP_INSTALLATION_ACCESS_TOKEN, $sRepository);
	}

	/**
	 * Check if current app installation access token is expired.
	 *
	 * @param string $sRepository
	 *
	 * @return bool
	 */
	static public function IsCurrentAppInstallationTokenExpired(string $sRepository): bool
	{
		try{
			// no session var
			if(!SessionHelper::IsSetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN_EXPIRATION_DATE, $sRepository))
				return true;

			// compute dates
			$oDateNow = new DateTime('now',  new DateTimeZone('Z'));
			$oDateExpiration = new DateTime(SessionHelper::GetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN_EXPIRATION_DATE, $sRepository));

			// now > expiration_date
			return $oDateNow->getTimestamp() > $oDateExpiration->getTimestamp();
		}
		catch(Exception){

			return true;
		}
	}
}
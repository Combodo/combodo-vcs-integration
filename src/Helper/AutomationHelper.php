<?php

/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Helper;

use DBObject;
use Exception;
use MetaModel;
use User;
use utils;

class AutomationHelper
{
	public static function SearchObjectFromRegexp($oAutomation, $sObjectClass, $bScopeData, $sRefRegexPattern, $sRefRegexSubjectData, $sObjectRefAttCode, $aPayload, $aScopeData): ?DBObject
	{
		// retrieve payload subject data value
		$sRefSubjectDataValue = ModuleHelper::ExtractDataFromArray($bScopeData ? $aScopeData : $aPayload, $sRefRegexSubjectData);

		// retrieve destination object
		if (!utils::IsNullOrEmptyString($sRefRegexPattern)) {
			$aMatch = [];
			preg_match("/$sRefRegexPattern/", $sRefSubjectDataValue, $aMatch);
			if (!empty($aMatch)) {
				$sOQL = "SELECT $sObjectClass WHERE $sObjectRefAttCode=\"$aMatch[1]\"";
				return MetaModel::GetObjectFromOQL($sOQL, [], true);
			}
		}

		// output information (maybe normal)
		ModuleHelper::LogInfo('HandleEvent error no object found', [
			'VCSAutomation' => $oAutomation->GetKey(),
			'object class' => $sObjectClass,
			'object reference attribute code' => $sObjectRefAttCode,
			'reference regex pattern' => $sRefRegexPattern,
			'reference regex subject data' => $sRefRegexSubjectData,
			'reference subject data value' => $sRefSubjectDataValue,
		]);

		return null;
	}

	/**
	 * @return User|null
	 */
	public static function SearchUserFromContactNickname($sNicknameVarValue, $sPlatform): ?DBObject
	{
		$oUserResolverClassName = ModuleHelper::GetModuleSetting(ModuleHelper::$PARAM_USER_RESOLVER_CLASS_NAME);
		if ($oUserResolverClassName === null) {
			ModuleHelper::LogInfo('User resolver class name is not set in module settings');
			return null;
		}

		try {
			$oUserResolver = new $oUserResolverClassName();
			return $oUserResolver->resolveUserFromNickname($sNicknameVarValue, $sPlatform);
		} catch (Exception $e) {
			ModuleHelper::LogInfo('Error while resolving user from nickname', [
				'nickname' => $sNicknameVarValue,
				'platform' => $sPlatform,
				'error' => $e->getMessage(),
			]);
			return null;
		}
	}

	public static function SearchContactFromNickname($sNicknameVarValue, $sPlatform): ?DBObject
	{
		$oUser = self::SearchUserFromContactNickname($sNicknameVarValue, $sPlatform);
		return  MetaModel::GetObject('Person', $oUser->Get('contactid'));
	}

	public static function AddAutomationSubscribedEvents($oAutomation, $aEvents): void
	{
		foreach ($aEvents as $sEvent) {

			// Create link to event
			$oEvent = MetaModel::GetObjectByName('VCSEvent', $sEvent);
			$oLink = MetaModel::NewObject('lnkVCSAutomationToVCSEvent');
			$oLink->Set('automation_id', $oAutomation->GetKey());
			$oLink->Set('event_id', $oEvent->GetKey());
			$oLink->DBInsert();

			// Append the event to the list of events in the automation
			$oValue = $oAutomation->Get('events_list');
			$oValue->AddItem($oLink);
			$oAutomation->DBUpdate();
		}
	}
}

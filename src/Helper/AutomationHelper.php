<?php

namespace Combodo\iTop\VCSManagement\Helper;

use DBObject;
use DBObjectSet;
use DBSearch;
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
		$oSearch = DBSearch::FromOQL("SELECT User AS u
		    JOIN Person AS p ON u.contactid = p.id
		    JOIN PersonPseudo AS pp ON pp.person_id = p.id
		    WHERE pp.pseudo = '$sNicknameVarValue' AND pp.plateform = '$sPlatform'");
		$oSet = new DBObjectSet($oSearch, iLimitCount: 1);
		return $oSet->Fetch();
	}

	public static function SearchContactFromNickname($sNicknameVar, $bScopeData, $aPayload, $aScopeData): ?DBObject
	{
		$sNicknameVarValue = ModuleHelper::ExtractDataFromArray($bScopeData ? $aScopeData : $aPayload, $sNicknameVar);

		$sNicknameAttribute = ModuleHelper::GetModuleSetting(ModuleHelper::$PARAM_CONTACT_ATTRIBUTE_FOR_GITHUB_NICKNAME);
		if (empty($sNicknameAttribute)) {
			return null;
		}

		$oSearch = DBSearch::FromOQL("SELECT Person WHERE $sNicknameAttribute = '$sNicknameVarValue'");
		$oSet = new DBObjectSet($oSearch, iLimitCount: 1);
		return $oSet->Fetch();
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

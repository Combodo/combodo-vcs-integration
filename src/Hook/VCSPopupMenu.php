<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Hook;

use Dict;
use Exception;
use iPopupMenuExtension;
use JSPopupMenuItem;
use SeparatorPopupMenuItem;
use UserRights;
use utils;

/**
 * VCS menus.
 *
 */
class VCSPopupMenu implements iPopupMenuExtension
{
	/** @inheritdoc  */
	public static function EnumItems($iMenuId, $param) : array
	{
		$aResult = array();
		switch($iMenuId) // type of menu in which to add menu items
		{

			case iPopupMenuExtension::MENU_OBJDETAILS_ACTIONS:

				// allowed profiles for github actions
				$bAllowedProfile = UserRights::HasProfile('Administrator') || UserRights::HasProfile('VCS Manager');

				if(get_class($param) ===  'VCSApplication' && $bAllowedProfile)
				{
					// add separator
					$oSeparator = new SeparatorPopupMenuItem();
					$aResult[] = $oSeparator;

					// synchronize webhook
					$oItem = new JSPopupMenuItem('GitHubSynchronizeWebhook',
						Dict::S('Class:VCSApplication/UI:Button:synchronize_configuration'),
						'iTopGithubWorker.SynchronizeWebhook("'.$param->GetKey().'");',
						['env-' . utils::GetCurrentEnvironment() . '/combodo-vcs-integration/assets/js/github.js']);
					$oItem->SetIconClass('fab fa-github-alt');
					$aResult[] = $oItem;

					// check webhook configuration
					$oItem = new JSPopupMenuItem('GitHubCheckWebhookSynchro',
						Dict::S('Class:VCSApplication/UI:Button:check_configuration'),
						'iTopGithubWorker.CheckWebhookConfigurationSynchro("'.$param->GetKey().'");',
						['env-' . utils::GetCurrentEnvironment() . '/combodo-vcs-integration/assets/js/github.js']);
					$oItem->SetIconClass('fab fa-github-alt');
					$aResult[] = $oItem;

					// revoke token
					$oItem = new JSPopupMenuItem('GitHubRevokeToken',
						Dict::S('Class:VCSApplication/UI:Button:revoke_token'),
						'iTopGithubWorker.RegenerateAccessToken("'.$param->GetKey().'");',
						['env-' . utils::GetCurrentEnvironment() . '/combodo-vcs-integration/assets/js/github.js']);
					$oItem->SetIconClass('fab fa-github-alt');
					$aResult[] = $oItem;
				}
				break;

			case iPopupMenuExtension::MENU_OBJLIST_ACTIONS:
			case iPopupMenuExtension::MENU_OBJLIST_TOOLKIT:
			case iPopupMenuExtension::MENU_DASHBOARD_ACTIONS:
			case iPopupMenuExtension::MENU_USER_ACTIONS:
				break;

		}
		return $aResult;
	}
}
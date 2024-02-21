<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Hook;

use Dict;
use iPopupMenuExtension;
use JSPopupMenuItem;
use SeparatorPopupMenuItem;
use utils;

/**
 * VCS Repository menus.
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


				if(get_class($param) ===  'VCSRepository')
				{
					if($param->Get('synchro_mode') !== 'none')
					{
						// add separator
						$oSeparator = new SeparatorPopupMenuItem();
						$aResult[] = $oSeparator;

						// synchronize repository webhook
						$oItem = new JSPopupMenuItem('GitHubSynchronizeRepositoryWebhook',
							Dict::S('Class:VCSRepository/UI:Button:synchronize_configuration'),
							'iTopGithubWorker.SynchronizeRepository("'.$param->GetKey().'");',
							[utils::GetCurrentEnvironment() . '/combodo-github-integration/assets/js/github.js']);
						$oItem->SetIconClass('fab fa-github-alt');
						$aResult[] = $oItem;

						// check webhook configuration
						$oItem = new JSPopupMenuItem('GitHubCheckRepositoryWebhookSynchro',
							Dict::S('Class:VCSRepository/UI:Button:check_configuration'),
							'iTopGithubWorker.CheckRepositoryWebhookSynchro("'.$param->GetKey().'");',
							[utils::GetCurrentEnvironment() . '/combodo-github-integration/assets/js/github.js']);
						$oItem->SetIconClass('fab fa-github-alt');
						$aResult[] = $oItem;
					}

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
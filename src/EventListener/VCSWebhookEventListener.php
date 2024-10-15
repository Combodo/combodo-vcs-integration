<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\EventListener;

use Combodo\iTop\Service\Events\EventData;
use Combodo\iTop\Service\Events\EventService;
use Combodo\iTop\Service\Events\iEventServiceSetup;
use Combodo\iTop\VCSManagement\Service\GitHubManager;
use Exception;
use ExceptionLog;

/**
 * CRUD event listener.
 *
 * - update webhook url
 * - update synchronization status
 * - synchronize webhook
 */
class VCSWebhookEventListener implements iEventServiceSetup
{
	/** @var \Combodo\iTop\VCSManagement\Service\GitHubManager $oGitHubManager */
	private GitHubManager $oGitHubManager;

	/**
	 * Constructor.
	 *
	 * @throws \Exception
	 */
	public function __construct()
	{
		// service injection
		$this->oGitHubManager = GitHubManager::GetInstance();
	}

	/** @inheritdoc  */
	public function RegisterEventsAndListeners() : void
	{
		// EVENT_DB_AFTER_WRITE
		EventService::RegisterListener(
			EVENT_DB_AFTER_WRITE,
			[$this, 'OnDBAfterWrite'],
			'VCSWebhook'
		);

		// EVENT_DB_LINKS_CHANGED
		EventService::RegisterListener(
			EVENT_DB_LINKS_CHANGED,
			[$this, 'OnDBLinksChanged'],
			'VCSWebhook'
		);

		// EVENT_DB_AFTER_DELETE
		EventService::RegisterListener(
			EVENT_DB_AFTER_DELETE,
			[$this, 'OnDBAfterDelete'],
			'VCSWebhook'
		);
    }

	/**
	 * OnDBAfterWrite.
	 *
	 * @param \Combodo\iTop\Service\Events\EventData $oEventData
	 *
	 * @return void
	 */
	public function OnDBAfterWrite(EventData $oEventData): void
	{
		try{

			// retrieve webhook
			$oWebhook = $oEventData->GetEventData()['object'];

			// changes
			$aChanges = $oEventData->GetEventData()['changes'];

			// update web hook url (may have changed with module configuration)
			$this->oGitHubManager->UpdateVCSWebhook($oWebhook, array_key_exists('secret', $aChanges));
		}
		catch(Exception $e){

			// log
			ExceptionLog::LogException($e, [
				'happened_on' => 'OnDBAfterWrite in VCSWebhookEventListener.php',
				'error_msg' => $e->getMessage(),
			]);
		}
	}

	/**
	 * OnDBLinksChanged.
	 *
	 * @param \Combodo\iTop\Service\Events\EventData $oEventData
	 *
	 * @return void
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
	public function OnDBLinksChanged(EventData $oEventData): void
	{
		try{

			// retrieve webhook
			$oWebhook = $oEventData->GetEventData()['object'];

			// update synchro state
			$this->oGitHubManager->UpdateWebhookStatus($oWebhook);

			// auto synchronize
			$this->oGitHubManager->PerformWebhookAutoSynchronization($oWebhook);
		}
		catch(Exception $e){

			// log exception
			ExceptionLog::LogException($e, [
				'happened_on' => 'OnDBLinksChanged in VCSWebhookEventListener.php',
				'error_msg' => $e->getMessage(),
			]);
		}
	}

	/**
	 * OnDBAfterDelete.
	 *
	 * @param \Combodo\iTop\Service\Events\EventData $oEventData
	 *
	 * @return void
	 */
	public function OnDBAfterDelete(EventData $oEventData): void
	{
		try{

			// retrieve webhook
			$oWebhook = $oEventData->GetEventData()['object'];

			// delete synchronization
			$this->oGitHubManager->DeleteWebhookSynchronization($oWebhook);
		}
		catch(Exception $e){

			// log exception
			ExceptionLog::LogException($e, [
				'happened_on' => 'OnDBAfterDelete in VCSWebhookEventListener.php',
				'error_msg' => $e->getMessage(),
			]);
		}
	}

}
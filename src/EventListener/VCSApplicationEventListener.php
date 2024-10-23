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
class VCSApplicationEventListener implements iEventServiceSetup
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
			'VCSApplication'
		);

		// EVENT_DB_LINKS_CHANGED
		EventService::RegisterListener(
			EVENT_DB_LINKS_CHANGED,
			[$this, 'OnDBLinksChanged'],
			'VCSApplication'
		);

		// EVENT_DB_AFTER_DELETE
		EventService::RegisterListener(
			EVENT_DB_AFTER_DELETE,
			[$this, 'OnDBAfterDelete'],
			'VCSApplication'
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

			// retrieve application
			$oApplication = $oEventData->GetEventData()['object'];

			// changes
			$aChanges = $oEventData->GetEventData()['changes'];

			// update web hook url (may have changed with module configuration)
			$this->oGitHubManager->UpdateVCSApplication($oApplication, array_key_exists('secret', $aChanges));
		}
		catch(Exception $e){

			// log
			ExceptionLog::LogException($e, [
				'happened on' => 'OnDBAfterWrite in VCSWebhookEventListener.php',
				'error message' => $e->getMessage(),
			]);
		}
	}

	/**
	 * OnDBLinksChanged.
	 *
	 * @param \Combodo\iTop\Service\Events\EventData $oEventData
	 *
	 * @return void
	 */
	public function OnDBLinksChanged(EventData $oEventData): void
	{
		try{

			// retrieve application
			$oApplication = $oEventData->GetEventData()['object'];

			// update synchro state
			$this->oGitHubManager->UpdateWebhookStatus($oApplication);

			// auto synchronize
			$this->oGitHubManager->PerformWebhookAutoSynchronization($oApplication);
		}
		catch(Exception $e){

			// log exception
			ExceptionLog::LogException($e, [
				'happened on' => 'OnDBLinksChanged in VCSWebhookEventListener.php',
				'error message' => $e->getMessage(),
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

			// retrieve application
			$oApplication = $oEventData->GetEventData()['object'];

			// delete synchronization
			$this->oGitHubManager->DeleteWebhookSynchronization($oApplication);
		}
		catch(Exception $e){

			// log exception
			ExceptionLog::LogException($e, [
				'happened on' => 'OnDBAfterDelete in VCSWebhookEventListener.php',
				'error message' => $e->getMessage(),
			]);
		}
	}

}
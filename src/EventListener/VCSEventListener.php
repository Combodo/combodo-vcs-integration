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
 * - synchronize repository
 */
class VCSEventListener implements iEventServiceSetup
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
			'VCSRepository'
		);

		// EVENT_DB_LINKS_CHANGED
		EventService::RegisterListener(
			EVENT_DB_LINKS_CHANGED,
			[$this, 'OnDBLinksChanged'],
			'VCSRepository'
		);

		// EVENT_DB_AFTER_DELETE
		EventService::RegisterListener(
			EVENT_DB_AFTER_DELETE,
			[$this, 'OnDBAfterDelete'],
			'VCSRepository'
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

			// retrieve repository
			$oRepository = $oEventData->GetEventData()['object'];

			// changes
			$aChanges = $oEventData->GetEventData()['changes'];

			// cannot detect change with UpdateWebhookStatus (secret isn't visible entirely)
			if(array_key_exists('secret', $aChanges)){
				$oRepository->Set('webhook_status', 'unsynchronized');
			}

			// if synchro mode change to none
			if(array_key_exists('synchro_mode', $aChanges)){
				if($oRepository->Get('synchro_mode') === 'none'){
					try{
						$this->oGitHubManager->DeleteWebhookSynchronization($oRepository);
					}
					catch(Exception $e){
						// log exception
						ExceptionLog::LogException($e);
					}
				}
			}

			// if not synchro and synchro auto
			if($oRepository->Get('synchro_mode') === 'auto'
			&& $oRepository->Get('webhook_status') !== 'synchronized'){
				if($this->oGitHubManager->SynchronizeRepository($oRepository)['github_data'] !== null){
					$this->oGitHubManager->UpdateExternalData($oRepository);
				}
			}
			else if($oRepository->Get('synchro_mode') === 'manual'
			&& !empty($oRepository->Get('webhook_configuration') )){
				$this->oGitHubManager->UpdateWebhookStatus($oRepository);
			}

			// update webhook url
			$sOldUrl = $oRepository->Get('webhook_url');
			$this->oGitHubManager->UpdateWebhookURL($oRepository);
			if($sOldUrl !== $oRepository->Get('webhook_url')){
				$oRepository->DBUpdate();
			}
		}
		catch(Exception $e){

			// log
			ExceptionLog::LogException($e);
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

			// retrieve repository
			$oRepository = $oEventData->GetEventData()['object'];

			// update synchro state
			$this->oGitHubManager->UpdateWebhookStatus($oRepository);

			// auto synchronize
			if($oRepository->Get('webhook_status') === 'unsynchronized'
			&& $oRepository->Get('synchro_mode') === 'auto'){
				$this->oGitHubManager->SynchronizeRepository($oRepository);
				$oRepository->DBUpdate();
			}
		}
		catch(Exception $e){

			// log exception
			ExceptionLog::LogException($e);
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

			// retrieve repository
			$oRepository = $oEventData->GetEventData()['object'];

			// delete synchronization
			$this->oGitHubManager->DeleteWebhookSynchronization($oRepository);
		}
		catch(Exception $e){

			// log exception
			ExceptionLog::LogException($e);
		}
	}

}
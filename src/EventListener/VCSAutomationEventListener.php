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
use MetaModel;

/**
 * CRUD event listener.
 *
 * - update webhook url
 * - update synchronization status
 * - synchronize webhook
 */
class VCSAutomationEventListener implements iEventServiceSetup
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
            'VCSAutomation'
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
            // retrieve Automation
            $oAutomation = $oEventData->GetEventData()['object'];
            $olnkVCSAutomationToVCSApplicationSet = $oAutomation->Get('applications_list');
            while ($olnkVCSAutomationToVCSApplication = $olnkVCSAutomationToVCSApplicationSet->Fetch()) {
	            $oApplication = MetaModel::GetObject('VCSApplication', $olnkVCSAutomationToVCSApplication->Get('vcsapplication_id'));
                if (!is_null($oApplication)) {
                    $this->oGitHubManager->UpdateVCSApplication($oApplication);
                }
            }
        }
        catch(Exception $e){

            // log
            ExceptionLog::LogException($e, [
                'happened on' => 'OnDBAfterWrite in VCSAutomationEventListener.php',
                'error message' => $e->getMessage(),
            ]);
        }
    }


}
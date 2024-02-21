/*
 * @copyright   Copyright (C) 2010-2021 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

/**
 * GitHub integration endpoints.
 *
 */
const iTopGithubWorker = new function(){

    // endpoint
    const ROUTER_BASE_URL = '../pages/ajax.render.php';

    // routes
    const ROUTE_GET_REPOSITORY_INFO = 'github.get_repository_info';
    const ROUTE_SYNCHRONIZE_REPOSITORY_WEBHOOK = 'github.synchronize_repository_webhook';
    const ROUTE_CHECK_REPOSITORY_WEBHOOK_SYNCHRO = 'github.check_repository_webhook_synchro';
    const ROUTE_GET_REPOSITORY_APP_INSTALLATION = 'github.get_repository_app_installation';
    const ROUTE_CLEAR_SESSION = 'github.clear_session';
    const ROUTE_STOP_SYNCHRONIZATION = 'github.stop_repository_synchronization';

    /**
     * Synchronize repository.
     *
     * @param repository_reference
     */
    async function SynchronizeRepository(repository_reference){
        await iTopGithubWorker.SynchronizeRepositoryWebhook(repository_reference);
        await iTopGithubWorker.GetRepositoryInfo(repository_reference);
    }

    /**
     * Get repository information.
     *
     * @param repository_reference
     */
    async function GetRepositoryInfo(repository_reference)
    {
        try{

            // reset each metrics values...
            document.querySelectorAll('[data-role="ibo-pill"][data-github-data]').forEach(function(e){
                e.querySelector('.ibo-dashlet-header-dynamic--count').innerHTML = '-';
            });

            // endpoint call
            const response = await fetch(`${ROUTER_BASE_URL}?route=${ROUTE_GET_REPOSITORY_INFO}&repository_id=` + repository_reference);
            const data = await response.json();

            // check errors
            if(CheckErrors('Unable to get repository information', data)){

                // update template
                const oGitHubInfo = document.querySelector('#github_info');
                oGitHubInfo.innerHTML = data['data']['template'];
            }

        }
        catch(error){

            // log
            console.error(error);
        }

    }

    /**
     * Synchronize a repository webhook.
     *
     * @param repository_reference
     */
    async function SynchronizeRepositoryWebhook(repository_reference){

        try{

            // endpoint call
            const response = await fetch(`${ROUTER_BASE_URL}?route=${ROUTE_SYNCHRONIZE_REPOSITORY_WEBHOOK}&repository_id=` + repository_reference);
            const data = await response.json();

            // check errors
            if(CheckErrors('Unable to synchronize repository webhook', data)) {

                // update webhook_status
                const oGitHubInfo = document.querySelector('[data-role="ibo-field"][data-attribute-code="webhook_status"] .ibo-field--value');
                oGitHubInfo.innerHTML = data.data.webhook_status_field_html;

            }

        }
        catch(error){

            // log
            console.error(error);
        }

    }

    /**
     * Check a repository webhook synchronization.
     *
     * @param repository_reference
     */
    async function CheckRepositoryWebhookSynchro(repository_reference){

        try{

            // endpoint call
            const response = await fetch(`${ROUTER_BASE_URL}?route=${ROUTE_CHECK_REPOSITORY_WEBHOOK_SYNCHRO}&repository_id=` + repository_reference);
            const data = await response.json();

            // check errors
            if(CheckErrors('Check repository webhook synchro', data)) {

                // update webhook_status
                const oGitHubInfo = document.querySelector('[data-role="ibo-field"][data-attribute-code="webhook_status"] .ibo-field--value');
                oGitHubInfo.innerHTML = data.data.webhook_status_field_html;
            }

        }
        catch(error){

            // log
            console.error(error);
        }


    }


    /**
     * Get repository app installation.
     *
     * @param repository_reference
     */
    async function GetRepositoryAppInstallation(repository_reference){

        try{

            // endpoint call
            const response = await fetch(`${ROUTER_BASE_URL}?route=${ROUTE_GET_REPOSITORY_APP_INSTALLATION}&repository_id=` + repository_reference);
            const data = await response.json();

            // check errors
            if(CheckErrors('Unable to get app installation', data)){
                console.log(data.data.installation);
            }

        }
        catch(error){

            // log
            console.error(error);
        }


    }

    /**
     * Clear session.
     *
     * @param repository_reference
     */
    async function ClearSession(repository_reference){

        try{

            // endpoint call
            const response = await fetch(`${ROUTER_BASE_URL}?route=${ROUTE_CLEAR_SESSION}&repository_id=` + repository_reference);
            const data = await response.json();

            // check errors
            if(CheckErrors('Unable to clear session', data)){
                console.log(data.data);
            }

        }
        catch(error){

            // log
            console.error(error);
        }


    }

    /**
     * Stop synchronization.
     *
     * @param repository_reference
     */
    async function StopSynchronization(repository_reference){

        try{

            // endpoint call
            const response = await fetch(`${ROUTER_BASE_URL}?route=${ROUTE_STOP_SYNCHRONIZATION}&repository_id=` + repository_reference);
            const data = await response.json();

            // check errors
            if(CheckErrors('Unable to stop synchronization', data)){

                // update webhook_status
                const oStatusElement = document.querySelector('[data-role="ibo-field"][data-attribute-code="webhook_status"] .ibo-field--value');
                oStatusElement.innerHTML = data.data.webhook_status_field_html;

                // update template
                const oGitHubInfoElement = document.querySelector('#github_info');
                oGitHubInfoElement.innerHTML = '';
            }

        }
        catch(error){

            // log
            console.error(error);
        }


    }

    /**
     * Check errors.
     *
     * @param title
     * @param data
     * @returns {boolean}
     * @constructor
     */
    function CheckErrors(title, data){
        // handle errors
        if(data.data.errors !== undefined){
            ShowErrors(title, data.data.errors)
            return false;
        }
        return true;
    }

    /**
     * Show errors in modal.
     *
     * @param title
     * @param errors
     */
    function ShowErrors(title, errors){

        // prepare message
        let sErrorMessage = title + '\n\n';
        sErrorMessage += 'Error(s):\n';
        errors.forEach((error) => {
            sErrorMessage += '- ' + error + '\n';
        });
        sErrorMessage = sErrorMessage.replace(/(?:\r\n|\r|\n)/g, '<br>');

        // informative modal
        CombodoModal.OpenInformativeModal(sErrorMessage, CombodoModal.INFORMATIVE_MODAL_SEVERITY_ERROR, {});
    }

    /**
     * Open url.
     *
     * @param url
     * @constructor
     */
    function OpenUrl(url){
        window.open(url, '_blank');
    }

    return {
        GetRepositoryInfo,
        SynchronizeRepositoryWebhook,
        CheckRepositoryWebhookSynchro,
        GetRepositoryAppInstallation,
        ClearSession,
        StopSynchronization,
        OpenUrl,
        SynchronizeRepository,
    }
};
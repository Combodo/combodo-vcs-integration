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
    const ROUTE_SYNCHRONIZE_WEBHOOK_CONFIGURATION = 'github.synchronize_webhook_configuration';
    const ROUTE_CHECK_WEBHOOK_CONFIGURATION_SYNCHRO = 'github.check_webhook_configuration_synchro';
    const ROUTE_REGENERATE_ACCESS_TOKEN = 'github.regenerate_access_token';

    /**
     * Synchronize webhook
     *
     * @param webhook_reference
     */
    function SynchronizeWebhook(webhook_reference){
        iTopGithubWorker.SynchronizeWebhookConfiguration(webhook_reference);
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
            const response = await CombodoHTTP.Fetch(`${ROUTER_BASE_URL}?route=${ROUTE_GET_REPOSITORY_INFO}&webhook_id=` + repository_reference);
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
     * Synchronize a webhook configuration.
     *
     * @param webhook_reference
     */
    async function SynchronizeWebhookConfiguration(webhook_reference){

        try{

            // endpoint call
            const response = await CombodoHTTP.Fetch(`${ROUTER_BASE_URL}?route=${ROUTE_SYNCHRONIZE_WEBHOOK_CONFIGURATION}&webhook_id=` + webhook_reference);
            const data = await response.json();

            // check errors
            if(CheckErrors('Unable to synchronize webhook configuration', data)) {

                // update webhook status
                const oGitHubInfo = document.querySelector('[data-role="ibo-field"][data-attribute-code="status"] .ibo-field--value');
                oGitHubInfo.innerHTML = data.data['status_field_html'];

                CombodoToast.OpenSuccessToast('Webhook configuration synchronized successfully');
            }

            return data.data.errors === undefined;
        }
        catch(error){

            // log
            console.error(error);

            return false;
        }

    }

    /**
     * Check a webhook configuration synchronization.
     *
     * @param webhook_reference
     */
    async function CheckWebhookConfigurationSynchro(webhook_reference){

        try{

            // endpoint call
            const response = await CombodoHTTP.Fetch(`${ROUTER_BASE_URL}?route=${ROUTE_CHECK_WEBHOOK_CONFIGURATION_SYNCHRO}&webhook_id=` + webhook_reference);
            const data = await response.json();

            // check errors
            if(CheckErrors('Unable to synchronize webhook configuration', data)) {

                // update webhook status
                const oGitHubInfo = document.querySelector('[data-role="ibo-field"][data-attribute-code="status"] .ibo-field--value');
                oGitHubInfo.innerHTML = data.data.status_field_html;

                CombodoToast.OpenSuccessToast('Webhook configuration&nbsp;&nbsp;' + data.data.status_field_html, {escapeMarkup: false});
            }

            return data.data.errors === undefined;
        }
        catch(error){

            // log
            console.error(error);

            return false;
        }


    }

    /**
     * Regenerate an access token
     *
     * @param webhook_reference
     */
    async function RegenerateAccessToken(webhook_reference){

        try{

            // endpoint call
            const response = await CombodoHTTP.Fetch(`${ROUTER_BASE_URL}?route=${ROUTE_REGENERATE_ACCESS_TOKEN}&webhook_id=` + webhook_reference);
            const data = await response.json();

            // check errors
            if(CheckErrors('Unable to revoke token', data)) {

                CombodoToast.OpenSuccessToast('Token successfully revoked');
            }

            return data.data.errors === undefined;
        }
        catch(error){

            // log
            console.error(error);

            return false;
        }

    }


    /**
     * Check errors.
     *
     * @param title modal title
     * @param data ajax data
     * @returns {boolean}
     */
    function CheckErrors(title, data){
        // handle errors
        if(data.data.errors !== undefined){
            ShowErrors(title, data.data.errors)
            return false
        }
        return true;
    }

    /**
     * Show errors in a modal.
     *
     * @param title modal title
     * @param errors errors array
     */
    function ShowErrors(title, errors){

        // prepare message
        let sErrorMessage = '<div class="combodo-vcs-integration--informative-modal">';

        // title
        sErrorMessage += '<strong>' + title + '</strong><br><br>';

        // multiple errors
        if(errors.length > 1){
            sErrorMessage += `${errors.length} Errors:<br>`;
        }

        // print errors
        sErrorMessage += '<ul>';
        errors.forEach((error) => {
            sErrorMessage += '<li>' + error + '</li>';
        });
        sErrorMessage += '</ul>';

        // end message
        sErrorMessage += '</div>';

        // show informative error modal
        CombodoModal.OpenInformativeModal(sErrorMessage, CombodoModal.INFORMATIVE_MODAL_SEVERITY_ERROR, {});
    }

    /**
     * Open url.
     *
     * @param url
     */
    function OpenUrl(url){
        window.open(url, '_blank');
    }

    return {
        GetRepositoryInfo,
        SynchronizeWebhookConfiguration,
        CheckWebhookConfigurationSynchro,
        OpenUrl,
        SynchronizeWebhook,
        RegenerateAccessToken,
    }
};
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

    /**
     * Synchronize repository.
     *
     * @param repository_reference
     */
    function SynchronizeRepository(repository_reference){
        iTopGithubWorker.SynchronizeRepositoryWebhook(repository_reference).then(function(res){
            if(res === true){
                iTopGithubWorker.GetRepositoryInfo(repository_reference).then(function(res){
                    if(CombodoToast !== undefined){
                        CombodoToast.OpenSuccessToast('Repository synchronized successfully');
                    }
                });
            }
        });
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
     * Synchronize a repository webhook.
     *
     * @param repository_reference
     */
    async function SynchronizeRepositoryWebhook(repository_reference){

        try{

            // endpoint call
            const response = await CombodoHTTP.Fetch(`${ROUTER_BASE_URL}?route=${ROUTE_SYNCHRONIZE_REPOSITORY_WEBHOOK}&webhook_id=` + repository_reference);
            const data = await response.json();

            // check errors
            if(CheckErrors('Unable to synchronize repository webhook', data)) {

                // update webhook status
                const oGitHubInfo = document.querySelector('[data-role="ibo-field"][data-attribute-code="status"] .ibo-field--value');
                oGitHubInfo.innerHTML = data.data.status_field_html;
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
     * Check a repository webhook synchronization.
     *
     * @param repository_reference
     */
    async function CheckRepositoryWebhookSynchro(repository_reference){

        try{

            // endpoint call
            const response = await CombodoHTTP.Fetch(`${ROUTER_BASE_URL}?route=${ROUTE_CHECK_REPOSITORY_WEBHOOK_SYNCHRO}&webhook_id=` + repository_reference);
            const data = await response.json();

            // check errors
            if(CheckErrors('Check repository webhook synchro', data)) {

                // update webhook status
                const oGitHubInfo = document.querySelector('[data-role="ibo-field"][data-attribute-code="status"] .ibo-field--value');
                oGitHubInfo.innerHTML = data.data.status_field_html;
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
        }
        return data.data.fatal === undefined;
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
        SynchronizeRepositoryWebhook,
        CheckRepositoryWebhookSynchro,
        OpenUrl,
        SynchronizeRepository,
    }
};
<?php

namespace Combodo\iTop\VCSManagement\Hook;

use iBackofficeLinkedStylesheetsExtension;
use utils;

class VCSBackofficeLinkedStylesheetsExtension implements iBackofficeLinkedStylesheetsExtension, \iBackofficeLinkedScriptsExtension
{

    public function GetLinkedStylesheetsAbsUrls(): array
    {
        return [
            utils::GetAbsoluteUrlAppRoot() . 'env-production/combodo-vcs-integration/assets/css/vcs-backoffice.css',
        ];
    }

    public function GetLinkedScriptsAbsUrls(): array
    {
        return [
            utils::GetAbsoluteUrlAppRoot() . 'env-production/combodo-vcs-integration/assets/js/vcs-backoffice.js',
        ];
    }
}
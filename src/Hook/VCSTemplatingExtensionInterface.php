<?php

namespace Combodo\iTop\VCSManagement\Hook;

interface VCSTemplatingExtensionInterface
{
	public function ParseTemplate($sTemplate, $aPayload): string;
}

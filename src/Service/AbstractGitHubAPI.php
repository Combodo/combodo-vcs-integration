<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Service;

class AbstractGitHubAPI
{
	// global
	private static string $BASE_URL = 'https://api.github.com';

	/**
	 * Create a resource URI.
	 *
	 * @param string $sResource
	 *
	 * @return string
	 */
	protected function GetAPIUri(string $sResource) : string
	{
		return static::$BASE_URL . $sResource;
	}

}
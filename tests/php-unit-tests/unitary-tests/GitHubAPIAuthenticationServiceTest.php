<?php

/**
 * @copyright   Copyright (C) 2010-2026 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Test;

use Combodo\iTop\Test\UnitTest\ItopTestCase;
use Combodo\iTop\VCSManagement\Helper\SessionHelper;
use Combodo\iTop\VCSManagement\Service\GitHubAPIAuthenticationService;

class GitHubAPIAuthenticationServiceTest extends ItopTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		require_once dirname(__DIR__, 3).'/vendor/autoload.php';
	}

	public function testIsCurrentAppInstallationTokenExpiredReturnsTrueWhenMissing(): void
	{
		$sRepository = 'repo-missing-'.uniqid('', true);
		SessionHelper::UnsetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN_EXPIRATION_DATE, $sRepository);

		$this->assertTrue(GitHubAPIAuthenticationService::IsCurrentAppInstallationTokenExpired($sRepository));
	}

	public function testIsCurrentAppInstallationTokenExpiredReturnsFalseForFutureDate(): void
	{
		$sRepository = 'repo-future-'.uniqid('', true);
		$sFutureDate = gmdate('Y-m-d\\TH:i:s\\Z', time() + 3600);
		SessionHelper::SetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN_EXPIRATION_DATE, $sRepository, $sFutureDate);

		$this->assertFalse(GitHubAPIAuthenticationService::IsCurrentAppInstallationTokenExpired($sRepository));

		SessionHelper::UnsetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN_EXPIRATION_DATE, $sRepository);
	}

	public function testIsCurrentAppInstallationTokenExpiredReturnsTrueForPastDate(): void
	{
		$sRepository = 'repo-past-'.uniqid('', true);
		$sPastDate = gmdate('Y-m-d\\TH:i:s\\Z', time() - 3600);
		SessionHelper::SetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN_EXPIRATION_DATE, $sRepository, $sPastDate);

		$this->assertTrue(GitHubAPIAuthenticationService::IsCurrentAppInstallationTokenExpired($sRepository));

		SessionHelper::UnsetVar(SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN_EXPIRATION_DATE, $sRepository);
	}

}

<?php

/**
 * @copyright   Copyright (C) 2010-2026 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Test;

use Combodo\iTop\Test\UnitTest\ItopTestCase;
use Combodo\iTop\VCSManagement\Helper\SessionHelper;

class SessionHelperTest extends ItopTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		require_once dirname(__DIR__, 3).'/vendor/autoload.php';
	}

	public function testSetGetUnsetLifecycle(): void
	{
		$sRepository = 'repo-lifecycle-'.uniqid('', true);
		$sSessionVar = SessionHelper::$SESSION_APP_INSTALLATION_ACCESS_TOKEN;

		$this->assertFalse(SessionHelper::IsSetVar($sSessionVar, $sRepository));

		SessionHelper::SetVar($sSessionVar, $sRepository, 'token-123');
		$this->assertTrue(SessionHelper::IsSetVar($sSessionVar, $sRepository));
		$this->assertSame('token-123', SessionHelper::GetVar($sSessionVar, $sRepository));

		SessionHelper::UnsetVar($sSessionVar, $sRepository);
		$this->assertFalse(SessionHelper::IsSetVar($sSessionVar, $sRepository));
	}

	public function testVariablesAreIsolatedByRepository(): void
	{
		$sSessionVar = SessionHelper::$SESSION_APP_INSTALLATION_ID;
		$sRepo1 = 'repo-a-'.uniqid('', true);
		$sRepo2 = 'repo-b-'.uniqid('', true);

		SessionHelper::SetVar($sSessionVar, $sRepo1, 'inst-1');
		SessionHelper::SetVar($sSessionVar, $sRepo2, 'inst-2');

		$this->assertSame('inst-1', SessionHelper::GetVar($sSessionVar, $sRepo1));
		$this->assertSame('inst-2', SessionHelper::GetVar($sSessionVar, $sRepo2));

		SessionHelper::UnsetVar($sSessionVar, $sRepo1);
		SessionHelper::UnsetVar($sSessionVar, $sRepo2);
	}
}

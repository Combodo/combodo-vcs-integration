<?php

/**
 * @copyright   Copyright (C) 2010-2026 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Test;

use Combodo\iTop\Test\UnitTest\ItopTestCase;
use Combodo\iTop\VCSManagement\Helper\ModuleHelper;

class ModuleHelperTest extends ItopTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/combodo-vcs-integration/vendor/autoload.php');
	}

	public function testExtractDataFromArrayReturnsNestedValue(): void
	{
		$aPayload = [
			'action' => [
				'author' => [
					'login' => 'jdoe',
				],
			],
		];

		$this->assertSame('jdoe', ModuleHelper::ExtractDataFromArray($aPayload, 'action->author->login'));
	}

	public function testExtractDataFromArrayConvertsBooleanAndNull(): void
	{
		$aPayload = [
			'flags' => [
				'is_active' => true,
				'is_archived' => false,
				'comment' => null,
			],
		];

		$this->assertSame('true', ModuleHelper::ExtractDataFromArray($aPayload, 'flags->is_active'));
		$this->assertSame('false', ModuleHelper::ExtractDataFromArray($aPayload, 'flags->is_archived'));
		$this->assertSame('null', ModuleHelper::ExtractDataFromArray($aPayload, 'flags->comment'));
	}

	public function testExtractDataFromArrayReturnsMissingSegmentWhenPathDoesNotExist(): void
	{
		$aPayload = [
			'action' => [
				'name' => 'opened',
			],
		];

		$this->assertSame('status', ModuleHelper::ExtractDataFromArray($aPayload, 'action->status->label'));
	}
}

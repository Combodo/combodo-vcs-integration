<?php

/**
 * @copyright   Copyright (C) 2010-2026 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Test;

use Combodo\iTop\Test\UnitTest\ItopTestCase;
use Combodo\iTop\VCSManagement\Service\TemplatingService;

class TemplatingServiceTest extends ItopTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/combodo-vcs-integration/vendor/autoload.php');
		$this->RequireOnceItopFile('/setup/setuputils.class.inc.php');
	}

	public function testParseTemplateReplacesEventAndDataInIfBranch(): void
	{
		$oService = new TemplatingService();
		$sTemplate = 'Event:[[event]]; State:[[@if action->name == opened]]OK-[[action->name]][[@else]]KO[[@endif]]';
		$aPayload = [
			'action' => [
				'name' => 'opened',
			],
		];

		$this->assertSame('Event:push; State:OK-opened', $oService->ParseTemplate($sTemplate, 'push', $aPayload));
	}

	public function testParseTemplateUsesElseBranchWhenConditionIsFalse(): void
	{
		$oService = new TemplatingService();
		$sTemplate = '[[@if action->name == opened]]opened[[@else]]closed[[@endif]]';
		$aPayload = [
			'action' => [
				'name' => 'closed',
			],
		];

		$this->assertSame('closed', $oService->ParseTemplate($sTemplate, 'push', $aPayload));
	}

	public function testParseTemplateSupportsForLoop(): void
	{
		$oService = new TemplatingService();
		$sTemplate = "[[@for commits]]- [[id]]\n[[@endfor]]";
		$aPayload = [
			'commits' => [
				['id' => 1],
				['id' => 2],
			],
		];

		$this->assertSame("- 1\n- 2", $oService->ParseTemplate($sTemplate, 'push', $aPayload));
	}

	public function testParseTemplateSupportsCountAndSubstring(): void
	{
		$oService = new TemplatingService();
		$sTemplate = 'Count:[[@count commits one many]]; Short:[[@substring sha 0 4]]';
		$aPayload = [
			'commits' => [1, 2],
			'sha' => 'abcdef',
		];

		$this->assertSame('Count:2  many; Short:abcd', $oService->ParseTemplate($sTemplate, 'push', $aPayload));
	}

	public function testParseTemplateFormatsDate(): void
	{
		$oService = new TemplatingService();
		$sTemplate = 'At:[[@date created_at]]';
		$aPayload = [
			'created_at' => '2026-09-30T14:05:00Z',
		];

		$this->assertSame('At:30 Sep 14:05', $oService->ParseTemplate($sTemplate, 'push', $aPayload));
	}

	public function testParseTemplateSupportsHyperlinkWithDynamicLabel(): void
	{
		$oService = new TemplatingService();
		$sTemplate = 'See:[[@hyperlink url as label]]';
		$aPayload = [
			'url' => 'https://github.com/combodo/itop',
			'label' => 'Repository link',
		];

		$this->assertSame(
			'See:<a href="https://github.com/combodo/itop" target='."'\"_blank\"'".'>Repository link</a>',
			$oService->ParseTemplate($sTemplate, 'push', $aPayload)
		);
	}

	public function testParseTemplateSupportsMailToWithDefaultLabel(): void
	{
		$oService = new TemplatingService();
		$sTemplate = 'Mail:[[@mailto author->email]]';
		$aPayload = [
			'author' => [
				'email' => 'john.doe@example.org',
			],
		];

		$this->assertSame(
			'Mail:<a href="mailto:john.doe@example.org" target='."'\"_blank\"'".'>john.doe@example.org</a>',
			$oService->ParseTemplate($sTemplate, 'push', $aPayload)
		);
	}

	public function testParseTemplateSupportsImageWithoutWidth(): void
	{
		$oService = new TemplatingService();
		$sTemplate = 'Avatar:[[@image sender->avatar_url]]';
		$aPayload = [
			'sender' => [
				'avatar_url' => 'https://img.example.org/u.png',
			],
		];

		$this->assertSame(
			'Avatar:<img style="width: px;vertical-align: middle;" alt="sender->avatar_url" src="https://img.example.org/u.png"/>',
			$oService->ParseTemplate($sTemplate, 'push', $aPayload)
		);
	}

	public function testParseTemplateSupportsTextWithCustomColorAndRawHtml(): void
	{
		$oService = new TemplatingService();
		$sTemplate = 'Repo:[[@text repository->name #112233]]';
		$aPayload = [
			'repository' => [
				'name' => '<b>RAW</b>',
			],
		];

		$this->assertSame(
			'Repo:<span style="color:#112233"><b>RAW</b></span>',
			$oService->ParseTemplate($sTemplate, 'push', $aPayload)
		);
	}

	public function testParseTemplateKeepsImageStatementWhenWidthUsesParentheses(): void
	{
		$oService = new TemplatingService();
		$sTemplate = 'Avatar:[[@image sender->avatar_url (24)]]';
		$aPayload = [
			'sender' => [
				'avatar_url' => 'https://img.example.org/u.png',
			],
		];

		$this->assertSame('Avatar:[[@image sender->avatar_url (24)]]', $oService->ParseTemplate($sTemplate, 'push', $aPayload));
	}
}

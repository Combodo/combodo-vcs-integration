<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Service;

use Combodo\iTop\Application\TwigBase\Twig\TwigHelper;
use Combodo\iTop\VCSManagement\Helper\ModuleHelper;
use DBObject;
use Exception;

/**
 * Message templating service.
 *
 */
class TemplatingService
{
	private static string $DEFAULT_SEPARATOR_COLOR = '#91618e42';
	private static string $DEFAULT_TEXT_COLOR = '#b83280';

	/** @var string regex */
	private static string $REGEX_FOR_STATEMENT = "/\[\[@for\s+(\w+)\]\]([.\s\S]*)\[\[@endfor\]\]/";
	private static string $REGEX_EVENT_STATEMENT = "/\[\[event\]\]/";
	private static string $REGEX_HYPERLINK_STATEMENT = "/\[\[@hyperlink\s+([\>\w-]+)(\s+as\s+([\>\w\s-]+))?\]\]/";
	private static string $REGEX_BUTTON_STATEMENT = "/\[\[@button\s+([\>\w-]+)\s+as\s+([\>\w\s-]+)\]\]/";
	private static string $REGEX_MAILTO_STATEMENT = "/\[\[@mailto\s+([\>\w-]+)(\s+as\s+([\>\w\s-]+))?\]\]/";
	private static string $REGEX_IMAGE_STATEMENT = "/\[\[@image\s+([\>\w-]+)(\s+(\d+))?\]\]/";
    private static string $REGEX_SUBSTRING_STATEMENT = "/\[\[@substring\s+([\>\w-]+)\s+(\d+)(\s+(\d+))?\]\]/";
	private static string $REGEX_COUNT_STATEMENT = "/\[\[@count\s+([\>\w-]+)\s+(\w+)\s+(\w+)\]\]/";
	private static string $REGEX_SEPARATOR_STATEMENT = "/\[\[@separator(\s+([#|\w]+))?\]\]/";
	private static string $REGEX_TEXT_STATEMENT = "/\[\[@text\s+([\>\w-]+)(\s+([#|\w]+))?\]\]/";
	private static string $REGEX_DATA = "/\[\[([\>\w-]+)\]\]/";

	/** @var TemplatingService|null Singleton */
	static private ?TemplatingService $oSingletonInstance = null;

	/**
	 * GetInstance.
	 *
	 * @return TemplatingService
	 * @throws \Exception
	 */
	public static function GetInstance(): TemplatingService
	{
		if (is_null(self::$oSingletonInstance)) {
			self::$oSingletonInstance = new TemplatingService();
		}

		return self::$oSingletonInstance;
	}

	/**
	 * Parse a template.
	 *
	 * @param string $sTemplate
	 * @param string $sEvent
	 * @param array $aPayload
	 *
	 * @return string
	 * @noinspection PhpUnused
	 */
	public function ParseTemplate(string $sTemplate, string $sEvent, array $aPayload) : string
	{
		// parse @for
		$sTemplate = preg_replace_callback(
			self::$REGEX_FOR_STATEMENT,
			fn ($matches) => $this->CallBackFor($aPayload, $sEvent, $matches),
			$sTemplate);

		// parse @event
		$sTemplate = preg_replace_callback(
			self::$REGEX_EVENT_STATEMENT,
			fn ($matches) => $sEvent,
			$sTemplate);

		// parse @hyperlink
		$sTemplate = preg_replace_callback(
			self::$REGEX_HYPERLINK_STATEMENT,
			fn ($matches) => $this->CallBackHyperlink($aPayload, $matches),
			$sTemplate);

		// parse @button
		$sTemplate = preg_replace_callback(
			self::$REGEX_BUTTON_STATEMENT,
			fn ($matches) => $this->CallBackButton($aPayload, $matches),
			$sTemplate);

		// parse @mailto
		$sTemplate = preg_replace_callback(
			self::$REGEX_MAILTO_STATEMENT,
			fn ($matches) => $this->CallBackMailTo($aPayload, $matches),
			$sTemplate);

		// parse @image
		$sTemplate = preg_replace_callback(
			self::$REGEX_IMAGE_STATEMENT,
			fn ($matches) => $this->CallBackImage($aPayload, $matches),
			$sTemplate);

		// parse @substring
		$sTemplate = preg_replace_callback(
			self::$REGEX_SUBSTRING_STATEMENT,
			fn ($matches) => $this->CallBackSubstring($aPayload, $matches),
			$sTemplate);

		// parse @text
		$sTemplate = preg_replace_callback(
			self::$REGEX_TEXT_STATEMENT,
			fn ($matches) => $this->CallBackText($aPayload, $matches),
			$sTemplate);

		// parse @separator
		$sTemplate = preg_replace_callback(
			self::$REGEX_SEPARATOR_STATEMENT,
			fn ($matches) => $this->CallBackSeparator($aPayload, $matches),
			$sTemplate);

		// parse @count
		$sTemplate = preg_replace_callback(
			self::$REGEX_COUNT_STATEMENT,
			fn ($matches) => $this->CallBackCount($aPayload, $matches),
			$sTemplate);

		// finally parse data
		return preg_replace_callback(
			self::$REGEX_DATA,
			fn ($matches) => ModuleHelper::ExtractDataFromArray($aPayload, $matches[1]),
			$sTemplate);
	}

	/**
	 * Parse @for statement.
	 *
	 * @param array $aPayload
	 * @param string $sEvent
	 * @param array $aMatch
	 *
	 * @return string
	 */
	private function CallBackFor(array $aPayload, string $sEvent, array $aMatch) : string
	{
		// data
		$data = $aMatch[1];
		$template = $aMatch[2];

		// prepare template
		$template = ltrim($template);
		$sLoopText = '';

		$oData = ModuleHelper::ExtractDataFromArray($aPayload, $data);
		foreach($oData as $oElement){
			$sLoopText .= $this->ParseTemplate($template, $sEvent, $oElement);
		}

		return rtrim($sLoopText);
	}

	/**
	 * Parse @hyperlink statement.
	 *
	 * @param array $aPayload
	 * @param array $aMatch
	 *
	 * @return string
	 */
	private function CallBackHyperlink(array $aPayload, array $aMatch) : string
	{
		// data
		$sDataUrl = $aMatch[1];
		$sUrlLabel = array_key_exists(3, $aMatch) ? $aMatch[3] : $sDataUrl;

		// prepare template
		$data = ModuleHelper::ExtractDataFromArray($aPayload, $sDataUrl);
		$dataLabel = ModuleHelper::ExtractDataFromArray($aPayload, $sUrlLabel);

		return "<a href=\"$data\" target='\"_blank\"'>$dataLabel</a>";
	}

	/**
	 * Parse @button statement.
	 *
	 * @param array $aPayload
	 * @param array $aMatch
	 *
	 * @return string
	 */
	function CallBackButton(array $aPayload, array $aMatch) : string
	{
		// data
		$sDataUrl = $aMatch[1];
		$sUrlLabel = $aMatch[2];

		// prepare template
		$data = ModuleHelper::ExtractDataFromArray($aPayload, $sDataUrl);
		$dataLabel = ModuleHelper::ExtractDataFromArray($aPayload, $sUrlLabel);

		return <<<HTML
			<a href="$data" target="_blank" class="ibo-button ibo-is-alternative ibo-is-secondary">
				<i class="fas fa-external-link-alt"></i>&nbsp;&nbsp;<span class="ibo-button--label">$dataLabel</span>
			</a>
		HTML;
	}

	/**
	 * Parse @mailto statement.
	 *
	 * @param array $aPayload
	 * @param array $aMatch
	 *
	 * @return string
	 */
	private function CallBackMailTo(array $aPayload, array $aMatch) : string
	{
		// data
		$sDataUrl = $aMatch[1];
		$sUrlLabel = array_key_exists(3, $aMatch) ? $aMatch[3] : $sDataUrl;

		// prepare template
		$data = ModuleHelper::ExtractDataFromArray($aPayload, $sDataUrl);
		$dataLabel = ModuleHelper::ExtractDataFromArray($aPayload, $sUrlLabel);
		return "<a href=\"mailto:$data\" target='\"_blank\"'>$dataLabel</a>";
	}

	/**
	 * Parse @substring statement.
	 *
	 * @param array $aPayload
	 * @param array $aMatch
	 *
	 * @return string
	 */
	private function CallBackSubstring(array $aPayload, array $aMatch) : string
	{
		// data
		$sDataUrl = $aMatch[1];
		$iOffset = intval($aMatch[2]);
        $iLength = null;
        if(array_key_exists(3, $aMatch)) {
            $iLength = intval($aMatch[3]);
        }

		// prepare template
		$data = ModuleHelper::ExtractDataFromArray($aPayload, $sDataUrl);

		return substr($data, $iOffset, $iLength);
	}

	/**
	 * Parse @text statement.
	 *
	 * @param array $aPayload
	 * @param array $aMatch
	 *
	 * @return string
	 */
	private function CallBackText(array $aPayload, array $aMatch) : string
	{
		// data
		$sDataText = $aMatch[1];
		$sTextColor = array_key_exists(3, $aMatch) ? $aMatch[3] : self::$DEFAULT_TEXT_COLOR;

		// prepare template
		$data = ModuleHelper::ExtractDataFromArray($aPayload, $sDataText);

		return "<span style=\"color:$sTextColor\">$data</span>";
	}

	/**
	 * Parse @separator statement.
	 *
	 * @param array $aPayload
	 * @param array $aMatch
	 *
	 * @return string
	 */
	private function CallBackSeparator(array $aPayload, array $aMatch) : string
	{
		$sColor = array_key_exists(2, $aMatch) ? $aMatch[2] : self::$DEFAULT_SEPARATOR_COLOR;

		return "<hr style=\"background-color:$sColor;height:1px;\">";
	}

	/**
	 * Parse @count statement.
	 *
	 * @param array $aPayload
	 * @param array $aMatch
	 *
	 * @return string
	 */
	private function CallBackCount(array $aPayload, array $aMatch) : string
	{
		// data
		$sDataUrl = $aMatch[1];
		$sText = $aMatch[2];
		$sTextPluralized = $aMatch[3];

		// prepare template
		$data = ModuleHelper::ExtractDataFromArray($aPayload, $sDataUrl);

		return count($data) . '  ' . (count($data) > 1 ? $sTextPluralized : $sText);
	}

	/**
	 * Parse @image statement.
	 *
	 * @param array $aPayload
	 * @param array $aMatch
	 *
	 * @return string
	 */
	private function CallBackImage(array $aPayload, array $aMatch) : string
	{
		// data
		$sDataUrl = $aMatch[1];
		$sWidth = array_key_exists(3, $aMatch) ? $aMatch[3] : '';

		// prepare template
		$data = ModuleHelper::ExtractDataFromArray($aPayload, $sDataUrl);
		return "<img style=\"width: {$sWidth}px;vertical-align: middle;\" alt=\"$sDataUrl\" src=\"$data\"/>";
	}

	/**
	 * Render a template.
	 *
	 * @param string $sTemplate
	 * @param array $aData
	 *
	 * @return string
	 */
	public function RenderTemplate(string $sTemplate, array $aData = []) : string
	{
		try{
			$oTwig = TwigHelper::GetTwigEnvironment(ModuleHelper::GetTemplatePath());
			return $oTwig->render($sTemplate, $aData);
		}
		catch(Exception $e){
			ExceptionLog::LogException($e, [
				'happened on' => 'RenderTemplate in TemplatingService.php',
				'error message' => $e->getMessage(),
			]);
			return 'template error';
		}
	}

	/**
	 * RenderGitHubInfoTemplate
	 *
	 * @param DBObject $oRepository The repository
	 * @param array|null $aData The data containing repository information
	 *
	 * @return string the HTML template string for displaying repository information
	 */
	public function RenderGitHubInfoTemplate(DBObject $oRepository, ?array $aData) : string
	{
		if(empty($aData)){
			return '';
		}

		return $this->RenderTemplate('github_info.html.twig', [
			'url' => $aData['github']['clone_url'],
			'watchers_count' => $aData['github']['watchers_count'],
			'forks_count' => $aData['github']['forks'],
			'issues_count' => $aData['github']['open_issues'],
			'description' => $aData['github']['description'],
			'date' => $aData['date'],
			'owner_login' => $aData['github']['owner']['login'],
			'owner_avatar' => $aData['github']['owner']['avatar_url'],
		]);

	}
}
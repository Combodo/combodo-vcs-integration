<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Service;

/**
 * Message templating service.
 *
 */
class TemplatingService
{
	/** @var string regex */
	private static string $REGEX_FOR_STATEMENT = "/\[\[@for\s+(\w+)\s+as\s+(\w+)\]\]([.\s\S]*)\[\[@endfor\]\]/";
	private static string $REGEX_EVENT_STATEMENT = "/\[\[event\]\]/";
	private static string $REGEX_URL_STATEMENT = "/\[\[@hyperlink\s+([\>\w-]+)(\s+as\s+([\>\w\s-]+))?\]\]/";
	private static string $REGEX_MAILTO_STATEMENT = "/\[\[@mailto\s+([\>\w-]+)(\s+as\s+([\>\w\s-]+))?\]\]/";
	private static string $REGEX_IMAGE_STATEMENT = "/\[\[@image\s+([\>\w-]+)(\s+(\d+))?\]\]/";
	private static string $REGEX_SUBSTRING_STATEMENT = "/\[\[@substring\s+([\>\w-]+)\s+(\d+)\s+(\d+)\]\]/";
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
	 * @param array|null $aContext
	 *
	 * @return string
	 */
	public function ParseTemplate(string $sTemplate, string $sEvent, array $aPayload, array $aContext = []) : string
	{
		// parse @event
		$sTemplate = preg_replace_callback(
			self::$REGEX_EVENT_STATEMENT,
			fn ($matches) => $sEvent,
			$sTemplate);

		// parse @hyperlink
		$sTemplate = preg_replace_callback(
			self::$REGEX_URL_STATEMENT,
			fn ($matches) => $this->CallBackUrl($aPayload, $aContext, $matches),
			$sTemplate);

		// parse @mailto
		$sTemplate = preg_replace_callback(
			self::$REGEX_MAILTO_STATEMENT,
			fn ($matches) => $this->CallBackMailTo($aPayload, $aContext, $matches),
			$sTemplate);

		// parse @image
		$sTemplate = preg_replace_callback(
			self::$REGEX_IMAGE_STATEMENT,
			fn ($matches) => $this->CallBackImage($aPayload, $aContext, $matches),
			$sTemplate);

		// parse @substring
		$sTemplate = preg_replace_callback(
			self::$REGEX_SUBSTRING_STATEMENT,
			fn ($matches) => $this->CallBackSubstring($aPayload, $aContext, $matches),
			$sTemplate);

		// parse @for
		$sTemplate = preg_replace_callback(
			self::$REGEX_FOR_STATEMENT,
			fn ($matches) => $this->CallBackFor($aPayload, $aContext, $matches),
			$sTemplate);

		// finally parse data
		$sTemplate = preg_replace_callback(
			self::$REGEX_DATA,
			fn ($matches) => $this->ExtractDataFromPayload($aPayload, $aContext, $matches[1]),
			$sTemplate);

		return nl2br($sTemplate);
	}

	/**
	 * Extract data form payload object.
	 *
	 * @param array $aPayload The payload object
	 * @param array $aContext Optional context
	 * @param string $sData Data to extract
	 *
	 * @return mixed
	 */
	public function ExtractDataFromPayload(array $aPayload, array $aContext, string $sData) : string
	{
		// explode expression
		$aElements = explode('->', $sData);

		// start search by payload
		if($aElements[0] === 'context'){
			$aSearch = $aContext;
			array_shift($aElements);
		}
		else{
			$aSearch = $aPayload;
		}

		// search expression data...
		foreach ($aElements as $sElement){
			if(!array_key_exists($sElement, $aSearch)) return $sElement;
			$aSearch =  $aSearch[$sElement];
		}

		// convert bool & null
		if(is_bool($aSearch)){
			$aSearch = $aSearch ? 'true' : 'false';
		}
		if($aSearch === null){
			$aSearch = 'null';
		}

		return $aSearch;
	}

	/**
	 * Parse @for statement.
	 *
	 * @param array $aPayload
	 * @param array $aContext
	 * @param array $aMatch
	 *
	 * @return string
	 */
	private function CallBackFor(array $aPayload, array $aContext, array $aMatch) : string
	{
		// data
		$data = $aMatch[1];
		$as = $aMatch[2];
		$template = $aMatch[3];

		// prepare template
		$template = ltrim($template);
		$sLoopText = '';
		$oData = $this->ExtractDataFromPayload($aPayload, $aContext, $data);
		foreach($oData as $iIterator => $oElement){
			$sLoopText .= str_replace('[[' . $as, '[['. $data . '->' . $iIterator, $template);
		}

		return $sLoopText;
	}

	/**
	 * Parse @url statement.
	 *
	 * @param array $aPayload
	 * @param array $aContext
	 * @param array $aMatch
	 *
	 * @return string
	 */
	private function CallBackUrl(array $aPayload, array $aContext, array $aMatch) : string
	{
		// data
		$sDataUrl = $aMatch[1];
		$sUrlLabel = array_key_exists(3, $aMatch) ? $aMatch[3] : $sDataUrl;

		// prepare template
		$data = $this->ExtractDataFromPayload($aPayload, $aContext, $sDataUrl);
		$dataLabel = $this->ExtractDataFromPayload($aPayload, $aContext, $sUrlLabel);
		return "<a href=\"$data\" target='\"_blank\"'>$dataLabel</a>";
	}

	/**
	 * Parse @mailto statement.
	 *
	 * @param array $aPayload
	 * @param array $aContext
	 * @param array $aMatch
	 *
	 * @return string
	 */
	private function CallBackMailTo(array $aPayload, array $aContext, array $aMatch) : string
	{
		// data
		$sDataUrl = $aMatch[1];
		$sUrlLabel = array_key_exists(3, $aMatch) ? $aMatch[3] : $sDataUrl;

		// prepare template
		$data = $this->ExtractDataFromPayload($aPayload, $aContext, $sDataUrl);
		$dataLabel = $this->ExtractDataFromPayload($aPayload, $aContext, $sUrlLabel);
		return "<a href=\"mailto:$data\" target='\"_blank\"'>$dataLabel</a>";
	}

	/**
	 * Parse @substring statement.
	 *
	 * @param array $aPayload
	 * @param array $aContext
	 * @param array $aMatch
	 *
	 * @return string
	 */
	private function CallBackSubstring(array $aPayload, array $aContext, array $aMatch) : string
	{
		// data
		$sDataUrl = $aMatch[1];
		$iOffset = intval($aMatch[2]);
		$iLength = intval($aMatch[3]);

		// prepare template
		$data = $this->ExtractDataFromPayload($aPayload, $aContext, $sDataUrl);

		return substr($data, $iOffset, $iLength);
	}

	/**
	 * Parse @image statement.
	 *
	 * @param array $aPayload
	 * @param array $aContext
	 * @param array $aMatch
	 *
	 * @return string
	 */
	private function CallBackImage(array $aPayload, array $aContext, array $aMatch) : string
	{
		// data
		$sDataUrl = $aMatch[1];
		$sWidth = array_key_exists(3, $aMatch) ? $aMatch[3] : '';

		// prepare template
		$data = $this->ExtractDataFromPayload($aPayload, $aContext, $sDataUrl);
		return "<img style=\"width: {$sWidth}px;vertical-align: middle;\" alt=\"$sDataUrl\" src=\"$data\"/>";
	}


}
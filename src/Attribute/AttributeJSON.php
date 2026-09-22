<?php

/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Attribute;

use Combodo\iTop\Application\TwigBase\Twig\Extension;
use Combodo\iTop\Core\AttributeDefinition\AttributeText;
use Throwable;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use utils;

/**
 * Attribute JSON
 *
 * Attribute to store JSON data in a text field and render it as HTML using an inline Twig template.
 *
 */
class AttributeJSON extends AttributeText
{
	/** @inheritDoc */
	public function GetAsHTML($sValue, $oHostObject = null, $bLocalize = true): string
	{
		// empty
		$sEmptyMessage = (string) $this->GetOptional('empty_message', '');
		if ($sValue === null || $sValue === '') {
			return \Dict::S($sEmptyMessage);
		}

		// decoding JSON
		$aDecoded = json_decode((string) $sValue, true);
		if (!is_array($aDecoded)) {
			return parent::GetAsHTML($sValue, $oHostObject, $bLocalize);
		}

		// inline template
		$sTemplateInline = (string) $this->GetOptional('template_inline', '');
		if (trim($sTemplateInline) === '') {
			return $this->RenderFallback($aDecoded);
		}
		try {
			return $this->RenderInlineTemplate($sTemplateInline, [
				'data' => $aDecoded,
			]);
		} catch (Throwable $oException) {
			\IssueLog::Warning('Unable to render inline twig template for AttributeJSON', null, [
				'attribute_code' => $this->GetCode(),
				'message' => $oException->getMessage(),
			]);

			return $this->RenderFallback($aDecoded);
		}
	}

	/**
	 * @throws SyntaxError
	 * @throws RuntimeError
	 * @throws LoaderError
	 */
	private function RenderInlineTemplate(string $sTemplate, array $aParams): string
	{
		$oTwig = new Environment(new ArrayLoader([
			'inline_template' => $sTemplate,
		]), [
			'debug' => utils::IsDevelopmentEnvironment(),
		]);

		Extension::RegisterTwigExtensions($oTwig);

		return $oTwig->render('inline_template', $aParams);
	}

	/**
	 * Fallback rendering.
	 *
	 * @param array $aDecoded
	 * @return string
	 */
	private function RenderFallback(array $aDecoded): string
	{
		return '<pre class="vcs-attribute-json">'.utils::EscapeHtml(json_encode($aDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)).'</pre>';
	}

}

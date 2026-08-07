<?php

/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\VCSManagement\Attribute;

use CMDBSource;
use Combodo\iTop\Core\AttributeDefinition\AttributeDBField;
use DBObject;

class AttributeJSON extends AttributeDBField
{
	/**
	 * @return null
	 *
	 * persist data as json
	 *
	 * PRID => [
	 *
	 * number
	 * base->repo->fullname
	 * base->ref
	 * html_url
	 * state
	 * merged_at
	 * requested_reviewers
	 * assignee->login
	 * ],
	 * PRID => [
 *
	 *
	 */

	public function GetEditClass()
	{
		return "HTML";
	}

	protected function GetSQLCol($bFullSpec = false)
	{
		return "TEXT".CMDBSource::GetSqlStringColumnDefinition();
	}

	public function FromSQLToValue($aCols, $sPrefix = '')
	{
		return $aCols[$sPrefix.''];
	}

	public function GetDefaultValue(?DBObject $oHostObject = null)
	{
		return json_encode([]);
	}

	public function GetSize(?string $sValue)
	{
		// If the value is null, we return 0
		if ($sValue === null) {
			return 0;
		}

		return strlen($sValue);
	}

	public function GetMaxSize()
	{
		// Is there a way to know the current limitation for mysql?
		// See mysql_field_len()
		return 65535;
	}

	public function GetAsHTML($sValue, $oHostObject = null, $bLocalize = true)
	{
		$sHtml = '<table class="ibo-datatable ibo-content-block ibo-block dataTable no-footer">';
		$sHtml .= '<thead>';
		$sHtml .= '<tr><th>Target</th><th>Status</th><th>At</th><th>Reviewers</th><th>Assignee</th></tr>';
		$sHtml .= '</thead>';
		$sHtml .= '<tbody>';

		if ($sValue !== null) {
			$aPR = json_decode($sValue, true);
			foreach ($aPR as $prId => $prData) {
				$sHtml .= '<tr>';
				$sHtml .= '<td>'.htmlspecialchars($prData['base.repo.full_name'] ?? '').'<br>'.htmlspecialchars($prData['base.ref'] ?? '').'</td>';
				$sHtml .= '<td>'.htmlspecialchars($prData['state'] ?? '').'</td>';
				$sHtml .= '<td>'.htmlspecialchars($prData['merged_at'] ?? '').'</td>';
				$sHtml .= '<td>'.count($prData['requested_reviewers'] ?? []).'</td>';
				$sHtml .= '<td>'.htmlspecialchars($prData['assignee.login'] ?? '').'</td>';
				$sHtml .= '</tr>';
			}
		}

		$sHtml .= '</tbody>';
		$sHtml .= '</table>';
		return $sHtml;
	}

	public static function IsScalar()
	{
		return true;
	}

	public function GetEditValue($sValue, $oHostObj = null)
	{
		return $sValue ?? '';
	}

	public function GetSQLColumns($bFullSpec = false)
	{
		$aColumns = [];
		$aColumns[$this->Get('sql')] = $this->GetSQLCol($bFullSpec);
		return $aColumns;
	}

	public function GetBasicFilterOperators()
	{
		return null;
	}

	public function GetBasicFilterLooseOperator()
	{
		return null;
	}

	public function GetBasicFilterSQLExpr($sOpCode, $value)
	{
		return null;
	}
}

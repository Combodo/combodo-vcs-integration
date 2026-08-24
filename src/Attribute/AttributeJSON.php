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
	public function GetEditClass()
	{
		return "HTML";
	}

	protected function GetSQLCol($bFullSpec = false)
	{
		return "TEXT".CMDBSource::GetSqlStringColumnDefinition();
	}

	public function GetDefaultValue(?DBObject $oHostObject = null)
	{
		return json_encode([]);
	}

	public function GetMaxSize()
	{
		return 65535;
	}

	public function GetAsHTML($sValue, $oHostObject = null, $bLocalize = true): string
	{
		$sHtml = '<table class="ibo-datatable ibo-content-block ibo-block dataTable no-footer combodo-vcs-integration--pr--table">';
		$sHtml .= '<thead>';
		$sHtml .= '<tr><th colspan="2"><input type="checkbox" checked style="display: none;"><label style="display: none;">Hide cancelled</label></th><th class="reviewers_requested"></th><th class="reviewers_changes"></th><th class="reviewers_approved"></th><th class="merged"></th></tr>';
		$sHtml .= '</thead>';
		$sHtml .= '<tbody>';

		if ($sValue !== null) {
			$aPR = json_decode($sValue, true);
			$aPR = array_reverse($aPR);
			foreach ($aPR as $prId => $prData) {

				$sRowStyle = '';
				if (!$prData['merged'] && $prData['state'] === 'closed') {
					$sRowStyle = ' style="display: none;"';
				}

				$sHtml .= '<tr data-role="vcs-pr-row" data-url="'.$prData['html_url'].'" data-state="'.$prData['state'].'" data-merged="'.$prData['merged'].'" '.$sRowStyle.'>';
				$sHtml .= '<td style="width:60px"><span class="ibo-field-badge" data-pr-state="'.$prData['state'].'"></span>'.htmlspecialchars($prData['number']).'</td>';
				$sHtml .= '<td><span class="repo">'.htmlspecialchars($prData['base.repo.name'] ?? '').'</span> <span class="branch"><i class="fas fa-code-branch"></i> '.htmlspecialchars($prData['base.ref'] ?? '').'</span></td>';
				$sHtml .= '<td class="reviewers">'.count($prData['requested_reviewers'] ?? []).'</td>';
				$sHtml .= '<td class="reviewers">'.count($prData['requested_reviewers'] ?? []).'</td>';
				$sHtml .= '<td class="reviewers">'.count($prData['requested_reviewers'] ?? []).'</td>';
				$sHtml .= '<td class="merged">'.($prData['merged'] ? '<i class="fas fa-check-square"></i>' : '<i class="far fa-square"></i>').'</td>';
				$sHtml .= '</tr>';
			}

			if (count($aPR) === 0) {
				$sHtml .= '<tr><td colspan="6" style="text-align: center;">No pull requests found</td></tr>';
			}
		}

		$sHtml .= '</tbody>';
		$sHtml .= '</table>';
		return $sHtml;
	}

	public function GetWidth()
	{
		return $this->GetOptional('width', '');
	}

	public function GetHeight()
	{
		return $this->GetOptional('height', '');
	}

}

<?php
//FileManager1
if (!defined('sugarEntry') || !sugarEntry) {
	die('Not A Valid Entry Point');
}

use Sugarcrm\Sugarcrm\Security\InputValidation\InputValidation;

/**
 * A custom field maintenance utility
 *
 * It allows you to see wasted, unused, underused, unneeded and stale custom fields
 * in your database.
 *
 * PHP version 5.6+
 *
 * LICENSE: Permission is hereby granted, free of charge, to any person obtaining a copy of this
 * software and associated documentation files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom
 * the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or
 * substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING
 * BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
 * DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @category   studio
 * @package    Field Manager
 * @author     Kenneth Brill <ken.brill@gmail.com>
 * @copyright  2017-2018 Kenneth Brill
 * @license    https://opensource.org/licenses/MIT  MIT License
 * @version    1.0
 * @link       https://wallencreeksoftware.blogspot.com/
 */

$sql = "SHOW TABLES LIKE '%cstm'";
$result = $GLOBALS['db']->query($sql);
$customTables = array();
while ($hash = $GLOBALS['db']->fetchByAssoc($result)) {
	$customTables[] = array_shift($hash);
}
$modules = array();
if (!is_array($_GET['FM_Module'])) {
	$modules = array($_GET['FM_Module']);
} else {
	$modules = $_GET['FM_Module'];
}

foreach ($GLOBALS['moduleList'] as $index => $moduleName) {
	$currentModule = BeanFactory::newBean($moduleName);
	if ($currentModule != null) {
		$customTableName = $currentModule->table_name . "_cstm";
		if (in_array($customTableName, $customTables)) {
			if (in_array($moduleName, $modules)) {
				$selected = ' SELECTED';
			} else {
				$selected = '';
			}
			$output .= "<option value='{$moduleName}'{$selected}>{$moduleName}</option>\n";
		}
	}
}
$smarty = new Sugar_Smarty();
$smarty->assign('OPTIONS', $output);
if (isset($_GET['skipReports']) && $_GET['skipReports'] == 'on') {
	$smarty->assign('REPORTS_CHECKED', ' CHECKED');
} else {
	$smarty->assign('REPORTS_CHECKED', '');
}
if (isset($_GET['skipWorkflow']) && $_GET['skipWorkflow'] == 'on') {
	$smarty->assign('WORKFLOW_CHECKED', ' CHECKED');
} else {
	$smarty->assign('WORKFLOW_CHECKED', '');
}

if (!isset($_GET['FM_Module'])) {
	echo $smarty->fetch("custom/modules/Administration/FieldManager.tpl");
	sugar_die(null);
}

$fieldManager = new fieldManager();
$smarty->assign('VERSION', $fieldManager->version);
$fieldManager->tableContents = "<table border='1' width='100%'>";
$fieldManager->tableContents .= "<thead><tr bgcolor='#d3d3d3'><td width='5%'><b>Module Name</b></td>
										 <td width='20%'><b>Field name</b></td>
										 <td width='30%'><b>Message</b></td>
										 <td width='5%'><b>Views</b></td>
										 <td width='20%'><b>Reports</b></td>
										 <td width='20%'><b>Automation</b></td>
									 </tr></thead><tbody>";

foreach ($modules as $moduleName) {
	$currentModule = BeanFactory::newBean($moduleName);
	if ($currentModule != null) {
		$fieldManager->customTableName = $currentModule->table_name . "_cstm";
		if (in_array($fieldManager->customTableName, $customTables)) {
			//There is a custom table
			$fieldManager->output = false;
			$columnsInTable = $GLOBALS['db']->get_columns($fieldManager->customTableName);
			$columnsInTable = $fieldManager->findOrphanFields($currentModule, $columnsInTable);
			$columnsInTable = $fieldManager->findNonDB($currentModule, $columnsInTable);
			$columnsInTable = $fieldManager->testFields($currentModule, $columnsInTable);
			if ($fieldManager->output) {
				$fieldManager->tableContents .= "<tr bgcolor='#d3d3d3'><td><b>Module Name</b></td>
													 <td><b>Field name</b></td>
													 <td><b>Message</b></td>
													 <td><b>Views</b></td>
													 <td><b>Reports</b></td>
													 <td><b>Automation</b></td>
					  							 </tr>";
			}
		}
	}
}
$fieldManager->tableContents .= "</tbody></table>";
$smarty->assign('TABLE', $fieldManager->tableContents);

echo $smarty->fetch("custom/modules/Administration/FieldManager.tpl");


class fieldManager
{
	protected $moduleData = array();
	public $output;
	public $tableContents;
	public $customTableName;
	public $skipReports = false;
	public $skipWorkflow = false;
	public $version = '1.0';

	public function __construct()
	{
		if (isset($_GET['skipReports']) && $_GET['skipReports'] == 'on') {
			$this->skipReports = true;
		}
		if (isset($_GET['skipWorkflow']) && $_GET['skipWorkflow'] == 'on') {
			$this->skipWorkflow = true;
		}
		$MetaDataManager = new MetaDataManager();
		$this->moduleData = $MetaDataManager->getModulesData();
		if ($this->skipReports == false) {
			$this->indexReports();
		}
	}

	/**
	 * @param object $currentModule
	 * @param array  $columnsInTable
	 * @return array
	 */
	public function findOrphanFields($currentModule, $columnsInTable)
	{
		$message = "<span style='color:red'>Field is in the database but does not exist in SugarCRM</span>";
		foreach ($columnsInTable as $index => $field) {
			$fieldName = $field['name'];
			if ($fieldName != 'id_c' && !isset($currentModule->field_defs[$fieldName])) {
				$this->output($currentModule, $fieldName, $message);
				unset($columnsInTable[$index]);
			}
		}
		return $columnsInTable;
	}

	public function testFields($currentModule, $columnsInTable)
	{
		foreach ($columnsInTable as $index => $field) {
			$fieldName = $field['name'];
			$fieldSettings = $currentModule->field_defs[$fieldName];
			$field['type'] = $fieldSettings['type'];
			$message = '';
			$table = $currentModule->table_name . "_cstm";
			if ($field['type'] == 'datetime' || $field['type'] == 'date') {
				$sql = "SELECT COUNT({$fieldName}) FROM {$table} WHERE COALESCE({$fieldName},'')!=''";
				$totalFilled = $GLOBALS['db']->getOne($sql);
				if ($totalFilled == 0) {
					$message .= "<span style='color:red'>{$field['type']}: This field contains no data</span>";
				}
				$sql = "SELECT COUNT({$fieldName}) 
						FROM {$table} 
						WHERE {$fieldName} LIKE '1970%' OR {$fieldName} LIKE '0000%'";
				$totalbad = $GLOBALS['db']->getOne($sql);
				if ($totalbad > 0) {
					$message .= "<br><span style='color:red'>{$field['type']}: {$totalbad} out of {$totalFilled} dates are bad.</span>";
				}
				$this->output($currentModule, $fieldName, $message);
			}
			if ($field['type'] == 'bool') {
				$sql = "SELECT COUNT({$fieldName}) 
						FROM {$table} 
						WHERE {$fieldName}=1";
				$totalChecked = $GLOBALS['db']->getOne($sql);
				if ($totalChecked == 0) {
					$message = "<span style='color:red'>{$field['type']}(1): This checkbox is never checked.  All data is NULL or '0'.</span>";
					$this->output($currentModule, $fieldName, $message);
				}
				//How many times has it been unchecked
				$sql = "SELECT COUNT({$fieldName}) 
						FROM {$table} 
						WHERE {$fieldName}=0 AND COALESCE({$fieldName},'')!=''";
				$totalChecked = $GLOBALS['db']->getOne($sql);
				if ($totalChecked == 0) {
					$message = "<span style='color:red'>{$field['type']}(1): This checkbox is unchecked.  All data is NULL or '1'.</span>";
					$this->output($currentModule, $fieldName, $message);
				}
			}
			if ($field['type'] == 'int') {
				$sql = "SELECT COUNT({$fieldName}) FROM {$table} WHERE {$fieldName}>0";
				$totalData = $GLOBALS['db']->getOne($sql);
				if ($totalData == 0) {
					$message = "<span style='color:red'>INT({$field['len']}): This field contains no data</span>";
					$this->output($currentModule, $fieldName, $message);
				}
			}
			if ($field['type'] == 'varchar' || $field['type'] == 'enum') {
				$sql = "SELECT MAX(LENGTH({$fieldName})) AS maxLength FROM {$table}";
				$maxLength = $GLOBALS['db']->getOne($sql);
				if (empty($maxLength)) {
					$maxLength = 0;
				}
				if ($field['len'] - $maxLength > 50) {
					$sql = "SELECT COUNT(id_c) AS totalBlank FROM {$table} WHERE COALESCE({$fieldName}, '')=''";
					$totalBlank = $GLOBALS['db']->getOne($sql);
					$sql = "SELECT COUNT(1) AS totalRecords FROM {$table}";
					$totalRecords = $GLOBALS['db']->getOne($sql);
					$sql = "SELECT count({$fieldName}) AS totalAlpha 
                            FROM {$table} 
                            WHERE {$fieldName} NOT REGEXP '^[0-9]+$' AND 
                                  COALESCE({$fieldName}, '')!=''";
					$totalAlpha = $GLOBALS['db']->getOne($sql);
					$dateTimeRegex = "[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9] [0-9][0-9]:[0-9][0-9]:[0-9][0-9]";
					$dateRegex = "[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]";
					$sql = "SELECT count({$fieldName}) AS totalDates 
							FROM {$table} 
							WHERE {$fieldName} REGEXP '{$dateRegex}' OR 
								  {$fieldName} REGEXP '{$dateTimeRegex}'";
					$totalDates = $GLOBALS['db']->getOne($sql);
					if ($totalBlank == $totalRecords) {
						$start = "<span style='color:red'>";
						$end = "</span>";
					} else {
						$start = "";
						$end = "";
					}
					$totalFilled = $totalRecords - $totalBlank;
					$message = "{$field['type']}({$field['len']}) : Data <b>MAX:</b>{$maxLength}{$start} 
					  											<b>CNT:</b>{$totalRecords}
					  											<b>FILLED:</b>{$totalFilled}
					  											<b>BLANK:</b>{$totalBlank}{$end}";
					if ($totalAlpha == 0 && $totalRecords != $totalBlank) {
						$message .= "&nbsp;<b>NUMERIC:</b>ALL";
					} else {
						if ($totalAlpha > 0 && $totalFilled > 0 && $totalAlpha != $totalFilled) {
							$totalNumbers = $totalFilled - $totalAlpha;
							$percentNumeric = intval(($totalNumbers / $totalFilled) * 100);
							if ($percentNumeric > 50) {
								$message .= "&nbsp;<b>NUMERIC:</b>{$percentNumeric}%";
							}
						}
					}
					if ($totalDates > 0 && $totalRecords != $totalBlank) {
						$message .= "&nbsp;<b>DATES:</b>{$totalDates}";
					}

					if ($totalFilled > 0) {
						$distinctValues = $this->distinctValues($fieldName);
						$trCount = substr_count($distinctValues, "<tr>") - 1;
						if (!empty($distinctValues)) {
							$idString = $currentModule->module_name . $fieldName . '_distinct';
							$distinctValues = "<button id='{$idString}_button' onclick=\"toggleList('{$idString}')\">See {$trCount} distinct values</button>
											   <div class='postit' style='display: none' id='{$idString}'><span class='hidden'><br>" . $distinctValues . "</span></div>";
							$message = $message . "<br>" . $distinctValues;
						}
					}

					$this->output($currentModule, $fieldName, $message);
				}
			}
			if ($field['type'] == 'text') {
				$sql = "SELECT MAX(LENGTH({$fieldName})) AS maxLength FROM {$table}";
				$maxLength = $GLOBALS['db']->getOne($sql);
				if ($maxLength < 256) {
					$sql = "SELECT COUNT(id_c) AS totalBlank FROM {$table} WHERE COALESCE({$fieldName}, '')=''";
					$totalBlank = $GLOBALS['db']->getOne($sql);
					$sql = "SELECT COUNT(1) AS totalRecords FROM {$table}";
					$totalRecords = $GLOBALS['db']->getOne($sql);
					$sql = "SELECT count({$fieldName}) AS totalAlpha 
                            FROM {$table} 
                            WHERE {$fieldName} NOT REGEXP '^[0-9]+$' AND 
                                  COALESCE({$fieldName}, '')!=''";
					$totalAlpha = $GLOBALS['db']->getOne($sql);
					if ($totalBlank == $totalRecords) {
						$start = "<span style='color:red'>";
						$end = "</span>";
					} else {
						$start = "";
						$end = "";
					}
					$totalFilled = $totalRecords - $totalBlank;
					$message = "{$field['type']}: Data <b>MAX:</b>{$maxLength}{$start} 
											<b>CNT:</b>{$totalRecords}
											<b>FILLED:</b>{$totalFilled}
											<b>BLANK:</b>{$totalBlank}{$end}";
					if ($totalAlpha == 0 && $totalRecords != $totalBlank) {
						$message .= "&nbsp;<b>NUMERIC:</b>ALL";
					} else {
						if ($totalAlpha > 0 && $totalFilled > 0 && $totalAlpha != $totalFilled) {
							$totalNumbers = $totalFilled - $totalAlpha;
							$percentNumeric = intval(($totalNumbers / $totalFilled) * 100);
							if ($percentNumeric > 50) {
								$message .= "&nbsp;<b>NUMERIC:</b>{$percentNumeric}%";
							}
						}
					}
					$this->output($currentModule, $fieldName, $message);
				}
			}
		}
		return $columnsInTable;
	}

	/**
	 * @param object $currentModule
	 * @param array  $columnsInTable
	 * @return array
	 */
	public function findNonDB($currentModule, $columnsInTable)
	{
		$sugarLogic = $this->findSugarLogic();
		if (isset($sugarLogic[$currentModule->module_name])) {
			foreach ($columnsInTable as $index => $field) {
				$fieldName = $field['name'];
				if ($fieldName != 'id_c' && isset($sugarLogic[$currentModule->module_name][$fieldName]['formula'])) {
					$views = $this->findViews($fieldName, $currentModule->module_name);
					if (empty($views)) {
						$formula = wordwrap($sugarLogic[$currentModule->module_name][$fieldName]['formula'], 80, "<BR>",
							true);
						$message = "{$field['type']}({$field['len']}): Field is populated by SugarLogic but not on any views<br><i><span style='color:blue'>{$formula}</span></i>";
					} else {
						$reports = $this->findRelatedReports($currentModule->module_name, $fieldName);
						if (empty($reports)) {
							$formula = wordwrap($sugarLogic[$currentModule->module_name][$fieldName]['formula'], 80,
								"<BR>", true);
							$message = "{$field['type']}({$field['len']}): Field is populated by SugarLogic and might be changed to a non-db type and removed from the database<br><i><span style='color:blue'>{$formula}</span></i>";
						} else {
							$message = '';
						}
					}
					if (!empty($message)) {
						$this->output($currentModule, $fieldName, $message);
						unset($columnsInTable[$index]);
					}
				}
			}
		}
		return $columnsInTable;
	}

	private function output($currentModule, $fieldName, $message)
	{
		$moduleName = $currentModule->module_name;
		$field_defs = $currentModule->field_defs[$fieldName];
		$views = $this->findViews($fieldName, $moduleName);

		//Gather the information about Reports
		$reports = $this->findRelatedReports($moduleName, $fieldName);
		$brCount = substr_count($reports, "<br>");
		if ($brCount > 5) {
			$idString = $currentModule->module_name . $fieldName . '_reports';
			$reports = "<button id='{$idString}_button' onclick=\"toggleList('{$idString}')\">See all {$brCount} Reports</button>
						<div class='postit' style='display: none' id='{$idString}'><span class='hidden'><br>" . $reports . "</span></div>";
		}

		//Gather information about Workflow, PDFs, Process Author and the like
		$workflowItems = $this->findWorkFlowItems($moduleName, $fieldName);
		$brCount = substr_count($workflowItems, "<br>");
		if ($brCount > 5) {
			$idString = $currentModule->module_name . $fieldName . '_workflow';
			$workflowItems = "<button id='{$idString}_button' onclick=\"toggleList('{$idString}')\">See all {$brCount} WorkFlow Items</button>
							  <div class='postit' style='display: none' id='{$idString}'><span class='hidden'><br>" . $workflowItems . "</span></div>";
		}

		//enum Info
		$sql = "SELECT date_modified 
					FROM fields_meta_data 
					WHERE name='{$fieldName}' AND 
						  custom_module='{$currentModule->module_name}' AND 
					      deleted=0";
		$date_modified = substr($GLOBALS['db']->getOne($sql), 0, 10);
		$moreInfo = "<br><b>Last Modified:</b> {$date_modified}";
		if ($field_defs['type'] == 'enum') {
			$options = $GLOBALS['app_list_strings'][$field_defs['options']];
			$optionString = implode("','", array_keys($options));
			$sql = "SELECT COUNT({$fieldName}) AS invalidOption
					FROM {$this->customTableName} 
					WHERE {$fieldName} NOT IN ('$optionString') AND 
					      {$fieldName} IS NOT NULL";
			$invalidOptions = $GLOBALS['db']->getOne($sql);
			$maxlen = max(array_map('strlen', array_values($options)));

			$moreInfo .= "<br><b>MAX:</b>{$maxlen}";
			if ($invalidOptions > 0) {
				$moreInfo .= "&nbsp;<span style='color:red'><b>INVALID:</b>{$invalidOptions}</span>";
			}
			$spaces = str_repeat('&nbsp;', 10);
			$optionList = str_replace("',", "',<br>{$spaces}", var_export($options, true));
			$idString = $currentModule->module_name . $fieldName . '_option';
			$moreInfo .= "<br><button id='{$idString}_button' onclick=\"toggleList('{$idString}')\">See all Options</button>
						  <div class='postit' style='display: none' id='{$idString}'><span class='hidden'><br>" . $optionList . "</span></div>";
		}

		$this->tableContents .= "<tr class='oddListRowS1'>";
		$this->tableContents .= "<td scope='row' valign='top'>{$moduleName}</td>
				<td valign='top'>{$fieldName}{$moreInfo}</td>
				<td valign='top'>{$message}</td>
				<td valign='top'>{$views}</td>
				<td valign='top'>{$reports}</td>
				<td valign='top'>{$workflowItems}</td>";
		$this->tableContents .= "</tr>";
		$this->output = true;
	}

	private function distinctValues($fieldName)
	{
		$header = false;
		$sql = "SELECT COUNT(*) AS Rows, {$fieldName} FROM {$this->customTableName} GROUP BY {$fieldName} ORDER BY Rows DESC";
		$result = $GLOBALS['db']->query($sql, true);
		$returnTable = "<table>";
		$count = 0;
		while ($hash = $GLOBALS['db']->fetchByAssoc($result)) {
			$count++;
			if (!$header) {
				$returnTable .= "<tr>";
				$dataRow = "<tr bgcolor='aqua'>";
				foreach ($hash as $header => $value) {
					if ($value === null) {
						$value = 'NULL';
					}
					$returnTable .= "<td>{$header}</td>";
					$dataRow .= "<td>{$value}</td>";
				}
				$dataRow .= "</tr>";
				$returnTable .= "</tr>" . $dataRow;
				$header = true;
			} else {
				$returnTable .= "<tr>";
				foreach ($hash as $header => $value) {
					if ($value === null) {
						$value = 'NULL';
					}
					$returnTable .= "<td>{$value}</td>";
				}
				$returnTable .= "</tr>";
			}
			if ($count > 100) {
				break;
			}
		}
		$returnTable .= "</table>";
		if ($count == 0 OR $count > 99) {
			return null;
		} else {
			return $returnTable;
		}
	}

	/**
	 * Finds any SugarLogic that is attached to a field
	 * @return array
	 */
	private function findSugarLogic()
	{
		$sugarLogic = array();
		foreach ($this->moduleData as $moduleName => $moduleInfo) {
			if (isset($moduleInfo['fields'])) {
				foreach ($moduleInfo['fields'] as $fieldName => $fieldData) {
					if (!isset($sugarLogic[$moduleName])) {
						$sugarLogic[$moduleName] = array();
						if (!isset($sugarLogic[$moduleName][$fieldName])) {
							$sugarLogic[$moduleName][$fieldName] = array();
						}
					}
					if (isset($fieldData['formula'])) {
						$sugarLogic[$moduleName][$fieldName]['formula'] = $fieldData['formula'];
					}
				}
			}
			if (isset($moduleInfo['dependencies'])) {
				foreach ($moduleInfo['dependencies'] as $index => $data) {
					if (isset($data['actions'])) {
						foreach ($data['actions'] as $actions) {
							if (isset($actions['params']['target'])) {
								$target = $actions['params']['target'];
								if (!isset($sugarLogic[$moduleName])) {
									$sugarLogic[$moduleName] = array();
									if (!isset($sugarLogic[$moduleName][$target])) {
										$sugarLogic[$moduleName][$target] = array();
									}
								}
								$sugarLogic[$moduleName][$target]['actions'] = $actions;
							}
						}
					}
					if (isset($data['triggerFields'])) {
						if (!isset($sugarLogic[$moduleName])) {
							$sugarLogic[$moduleName] = array();
						}
						foreach ($data['triggerFields'] as $triggerFields) {
							if (!isset($sugarLogic[$moduleName][$triggerFields])) {
								$sugarLogic[$moduleName][$triggerFields] = array();
							}
							$sugarLogic[$moduleName][$triggerFields]['triggerField'] = true;
						}
					}
				}
			}
		}
		return $sugarLogic;
	}

	/**
	 * Works out what views any particular field is on
	 * @return bool|string
	 */
	private function findViews($fieldName, $moduleName)
	{
		$result = '';
		if ($fieldName != 'id_c') {
			$key = $this->recursive_array_search($fieldName, $this->moduleData[$moduleName]['views']);
			if ($key == false) {
				$result = '';
			} else {
				$result = $key;
			}
		}
		return $result;
	}

	/**
	 * @param string $needle
	 * @param array  $haystack
	 * @return bool|string
	 */
	private function recursive_array_search($needle, $haystack)
	{
		$views = array();
		if (is_array($haystack)) {
			foreach ($haystack as $key => $value) {
				$current_key = $key;
				if ($needle === $value OR (is_array($value) && $this->recursive_array_search($needle,
							$value) !== false)) {
					if (substr($current_key, 0, 8) == 'subpanel') {
						$current_key = 'subpanel';
					}
					//This makes it so that 'Subpanel' only shows up on the list once.
					$views[$current_key] = str_replace('-', '_', $current_key);
				}
			}
			if (empty($views)) {
				return false;
			} else {
				return implode('<br>', array_values($views));
			}
		} else {
			return false;
		}
	}

	private function findRelatedReports($moduleName, $fieldName)
	{
		$reportArray = array();
		if ($this->skipReports == true) {
			return '';
		}
		$sql = "SELECT name,parent_id FROM kab_fieldManager_xref
				WHERE type = 'rep' AND
					  module = '{$moduleName}' AND
					  parent_type = 'Reports' AND
				      (field_name = '{$fieldName}' OR
				       rel_name = '{$fieldName}')";
		$result = $GLOBALS['db']->query($sql, true);

		while ($hash = $GLOBALS['db']->fetchByAssoc($result)) {
			$reportArray[$hash['parent_id']] = "<a href='index.php?action=ReportCriteriaResults&module=Reports&page=report&id={$hash['parent_id']}'>{$hash['name']}</a>";
		}
		if (empty($reportArray)) {
			return null;
		} else {
			return implode('<br>', array_values($reportArray));
		}
	}

	private function findWorkFlowItems($moduleName, $fieldName)
	{
		$list = array();
		if ($this->skipWorkflow == true) {
			return '';
		}
		$matchString = '"expModule":"' . $moduleName . '","expField":"' . $fieldName . '"';
		$sql = "SELECT pbed.pro_id, pbp.name
				FROM pmse_bpm_event_definition pbed
				JOIN pmse_bpmn_process pbp ON pbp.id=pbed.pro_id AND pbp.deleted=0
				WHERE pbed.evn_criteria LIKE '%{$matchString}%' AND
					  pbed.deleted=0";
		$result = $GLOBALS['db']->query($sql, true);
		while ($hash = $GLOBALS['db']->fetchByAssoc($result)) {
			$list[$hash['id']] = "<a href='#pmse_Project/{$hash['pro_id']}'>{$hash['name']}</a> (PMSE)";
		}

		$matchString1 = "{\"module\":\"{$moduleName}\",\"field\":\"{$fieldName}\"}";
		$matchString2 = "\"expValue\":\"{$fieldName}\",\"expModule\":\"{$moduleName}\"";
		$sql = "SELECT id, name FROM pmse_business_rules
				WHERE (rst_source_definition LIKE '%{$matchString1}%' OR 
				      rst_source_definition LIKE '%{$matchString2}%') AND 
				      deleted=0";
		$result = $GLOBALS['db']->query($sql, true);
		while ($hash = $GLOBALS['db']->fetchByAssoc($result)) {
			$list[$hash['id']] = "<a href='#pmse_Business_Rules/{$hash['id']}'>{$hash['name']}</a> (BR)";
		}

		$matchString = "{::{$moduleName}::{$fieldName}::}";
		$sql = "SELECT id, name FROM pmse_emails_templates
				WHERE body_html LIKE '%{$matchString}%' AND
				      deleted=0";
		$result = $GLOBALS['db']->query($sql, true);
		while ($hash = $GLOBALS['db']->fetchByAssoc($result)) {
			$list[$hash['id']] = "<a href='#pmse_Emails_Templates/{$hash['id']}'>{$hash['name']}</a> (TEMP)";
		}

		$sql = "SELECT id, name FROM pdfmanager 
			   WHERE base_module='{$moduleName}' AND 
			         body_html LIKE '%\$fields.{$fieldName}%'";
		$result = $GLOBALS['db']->query($sql, true);
		while ($hash = $GLOBALS['db']->fetchByAssoc($result)) {
			$list[$hash['id']] = "<a href='index.php?module=PdfManager&offset=1&action=DetailView&record={$hash['id']}'>{$hash['name']}</a> (PDF)";
		}

		$sql = "SELECT w.id, w.name
				FROM workflow w
				LEFT JOIN workflow_triggershells wt ON wt.parent_id=w.id AND wt.deleted=0
				LEFT JOIN workflow_alertshells was ON was.parent_id=w.id AND was.deleted=0
				LEFT JOIN workflow_actions wa ON wa.parent_id=w.id AND wa.deleted=0
				LEFT JOIN email_templates et ON et.id=was.custom_template_id
				WHERE w.deleted=0 AND 
				      (
				        (wt.field='{$fieldName}' AND w.base_module='{$moduleName}') OR 
				        (wt.field='{$fieldName}' AND wt.rel_module=LOWER('{$moduleName}')) OR 
				        (wa.field='{$fieldName}' AND w.base_module='{$moduleName}') OR
				        (et.subject LIKE '%::{$moduleName}::{$fieldName}::%') OR 
				        (et.body_html LIKE '%::{$moduleName}::{$fieldName}::%')
				      )";
		$result = $GLOBALS['db']->query($sql, true);
		while ($hash = $GLOBALS['db']->fetchByAssoc($result)) {
			$list[$hash['id']] = "<a href='index.php?module=WorkFlow&offset=1&action=DetailView&record={$hash['id']}'>{$hash['name']}</a> (WF)";
		}

		if (empty($list)) {
			return null;
		} else {
			return implode('<br>', array_values($list));
		}
	}

	private function indexReports()
	{
		$sql = "CREATE TEMPORARY TABLE kab_fieldManager_xref ( id CHAR(36) NOT NULL , name VARCHAR(100) NOT NULL, type CHAR(3) NOT NULL , parent_id CHAR(36) NOT NULL , parent_type VARCHAR(100) NOT NULL , module VARCHAR(100) NOT NULL, field_name VARCHAR(100) NOT NULL, rel_name VARCHAR(100) NOT NULL ) ENGINE = InnoDB CHARSET=utf8 COLLATE utf8_general_ci;";
		$GLOBALS['db']->query($sql);

		$sql = "SELECT id, name, content FROM saved_reports WHERE deleted=0";

		$result = $GLOBALS['db']->query($sql);
		$insertArray = array();
		$count = 0;
		while ($report = $GLOBALS['db']->fetchByAssoc($result)) {
			//Fill in the new data
			$content = JSON::decode(htmlspecialchars_decode($report['content']), false);
			if (isset($content['display_columns'])) {
				foreach ($content['display_columns'] as $display_columns) {
					$tableKey = $display_columns['table_key'];
					if (isset($content['full_table_list'][$tableKey])) {
						$module = $content['full_table_list'][$tableKey]['module'];
						$fieldName = $display_columns['name'];
					}
					if ($tableKey == 'self') {
						$relName = '';
					} else {
						$relName = substr($tableKey, strpos($tableKey, ':') + 1);
					}
					$guid = Sugarcrm\Sugarcrm\Util\Uuid::uuid1();
					$insertArray[] = "('" . implode("','", array_values(
							array('id'          => $guid,
								  'name'        => $report['name'],
								  'type'        => 'rep',
								  'parent_id'   => $report['id'],
								  'parent_type' => 'Reports',
								  'module'      => $module,
								  'field_name'  => $fieldName,
								  'rel_name'    => $relName))) . "')";
					$count++;
					if ($count > 999) {
						$sql = "INSERT INTO kab_fieldManager_xref (id,name,type,parent_id,parent_type,module,field_name,rel_name) ";
						$insertValues = implode(',', $insertArray);
						$sql .= $insertValues;
						$GLOBALS['db']->query($sql, true);
						$insertArray = array();
						$count = 0;
					}
				}
				if ($count > 0) {
					$sql = "INSERT INTO kab_fieldManager_xref (id,name,type,parent_id,parent_type,module,field_name,rel_name) ";
					$insertValues = implode(',', $insertArray);
					$sql .= 'VALUES' . $insertValues;
					$GLOBALS['db']->query($sql, true);
					$insertArray = array();
					$count = 0;
				}
			}
			if (isset($content['filters_def'])) {
				foreach ($content['filters_def'] as $filterBlock) {
					unset($filterBlock['operator']);
					foreach ($filterBlock as $fgi => $filterGroup) {
						if (isset($filterGroup['operator'])) {
							unset($filterGroup['operator']);
						}
						if (isset($filterGroup['table_key'])) {
							$tableKey = $filterGroup['table_key'];
							if (isset($content['full_table_list'][$tableKey])) {
								$module = $content['full_table_list'][$tableKey]['module'];
								$fieldName = $filterGroup['name'];
							}
							if ($tableKey == 'self') {
								$relName = '';
							} else {
								$relName = substr($tableKey, strpos($tableKey, ':') + 1);
							}
							$guid = Sugarcrm\Sugarcrm\Util\Uuid::uuid1();
							$insertArray[] = "('" . implode("','", array_values(
									array('id'          => $guid,
										  'name'        => $report['name'],
										  'type'        => 'rep',
										  'parent_id'   => $report['id'],
										  'parent_type' => 'Reports',
										  'module'      => $module,
										  'field_name'  => $fieldName,
										  'rel_name'    => $relName))) . "')";
							$count++;
						} elseif (is_array($filterGroup)) {
							foreach ($filterGroup as $filter) {
								if (isset($filter['operator'])) {
									unset($filter['operator']);
								}
								if (isset($filter['table_key'])) {
									$tableKey = $filter['table_key'];
									if (isset($content['full_table_list'][$tableKey])) {
										$module = $content['full_table_list'][$tableKey]['module'];
										$fieldName = $filter['name'];
									}
									if ($tableKey == 'self') {
										$relName = '';
									} else {
										$relName = substr($tableKey, strpos($tableKey, ':') + 1);
									}
									$guid = Sugarcrm\Sugarcrm\Util\Uuid::uuid1();
									$insertArray[] = "('" . implode("','", array_values(
											array('id'          => $guid,
												  'name'        => $report['name'],
												  'type'        => 'rep',
												  'parent_id'   => $report['id'],
												  'parent_type' => 'Reports',
												  'module'      => $module,
												  'field_name'  => $fieldName,
												  'rel_name'    => $relName))) . "')";
									$count++;
								} elseif (is_array($filter)) {
									foreach ($filter as $subFilter) {
										if (isset($subFilter['operator'])) {
											unset($subFilter['operator']);
										}
										$tableKey = $subFilter['table_key'];
										if (isset($content['full_table_list'][$tableKey])) {
											$module = $content['full_table_list'][$tableKey]['module'];
											$fieldName = $subFilter['name'];
										}
										if ($tableKey == 'self') {
											$relName = '';
										} else {
											$relName = substr($tableKey, strpos($tableKey, ':') + 1);
										}
										$guid = Sugarcrm\Sugarcrm\Util\Uuid::uuid1();
										$insertArray[] = "('" . implode("','", array_values(
												array('id'          => $guid,
													  'name'        => $report['name'],
													  'type'        => 'rep',
													  'parent_id'   => $report['id'],
													  'parent_type' => 'Reports',
													  'module'      => $module,
													  'field_name'  => $fieldName,
													  'rel_name'    => $relName))) . "')";
										$count++;
									}
								}
							}
						}
						if ($count > 999) {
							$sql = "INSERT INTO kab_fieldManager_xref (id,name,type,parent_id,parent_type,module,field_name,rel_name) ";
							$insertValues = implode(',', $insertArray);
							$sql .= 'VALUES' . $insertValues;
							$GLOBALS['db']->query($sql, true);
							$insertArray = array();
							$count = 0;
						}
					}
				}
				if ($count > 0) {
					$sql = "INSERT INTO kab_fieldManager_xref (id,name,type,parent_id,parent_type,module,field_name,rel_name) ";
					$insertValues = implode(',', $insertArray);
					$sql .= 'VALUES' . $insertValues;
					$GLOBALS['db']->query($sql, true);
					$insertArray = array();
					$count = 0;
				}
			}
		}
	}
}

<?php
//FileManager1
$admin_option_defs = array();
$admin_option_defs['Administration']['fieldManager'] = array(
	'Administration',
	'LBL_FIELDMANAGER_LINK_NAME',
	'LBL_FIELDMANAGER_LINK_DESCRIPTION',
	'./index.php?module=Administration&action=FieldManager'
);
$admin_group_header[] = array(
	//Section header label
	'LBL_SUGARQL2_SECTION_HEADER',
	//$other_text parameter for get_form_header()
	'',
	//$show_help parameter for get_form_header()
	false,
	//Section links
	$admin_option_defs,
	//Section description label
	'LBL_SUGARQL2_SECTION_DESCRIPTION'
);

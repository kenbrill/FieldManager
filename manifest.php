<?php
$manifest = array(
    'acceptable_sugar_flavors' => array('CE','PRO','CORP','ENT','ULT'),
    'acceptable_sugar_versions' => array(
        'exact_matches' => array(),
        'regex_matches' => array('(.*?)\.(.*?)\.(.*?)$'),
    ),
    'author' => 'Kenneth Brill (ken.brill@gmail.com)',
    'description' => 'A Custom Field Manager for optimizing SugarCRM',
    'icon' => '',
    'is_uninstallable' => true,
    'name' => 'FileManager',
    'published_date' => '2018-03-10 20:38:03',
    'type' => 'module',
    'version' => '1.0'
);

$installdefs =array (
  'id' => 'FileManager517',
  'copy' =>
  array (
    0 =>
    array (
      'from' => '<basepath>/files/custom/modules/Administration/FieldManager.php',
      'to' => 'custom/modules/Administration/FieldManager.php',
      'timestamp' => '2018-03-10 20:37:00',
    ),
    1 =>
    array (
      'from' => '<basepath>/files/custom/modules/Administration/FieldManager.tpl',
      'to' => 'custom/modules/Administration/FieldManager.tpl',
      'timestamp' => '2018-03-10 20:37:00',
    ),
    2 =>
    array (
      'from' => '<basepath>/files/custom/Extension/modules/Administration/Ext/Administration/fileManager.php',
      'to' => 'custom/Extension/modules/Administration/Ext/Administration/fileManager.php',
      'timestamp' => '2018-03-10 20:37:00',
    ),
    3 =>
    array (
      'from' => '<basepath>/files/custom/Extension/modules/Administration/Ext/Language/en_us.fileManager.php',
      'to' => 'custom/Extension/modules/Administration/Ext/Language/en_us.fileManager.php',
      'timestamp' => '2018-03-10 20:37:00',
    ),
  ),
);

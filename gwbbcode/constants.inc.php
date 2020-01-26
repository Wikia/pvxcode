<?php
//gwBBCode's version
$gwbbcode_version = '1.8.0.2';

// DATABASE PATHS
//  Skill ids to details
define('SKILLS_PATH', GWBBCODE_ROOT.'/skill_db.php');
//  Skill names into ids
define('SKILLNAMES_PATH', GWBBCODE_ROOT.'/skillname_db.php');
//  Skill shorthand names into full names
define('SKILLABBRS_PATH', GWBBCODE_ROOT.'/abbr_db.php');
//  Skill pvp ids into pve ids
define('SKILLIDSPVP_PATH', GWBBCODE_ROOT.'/skill_pvpids_db.php');

// INCLUDE PATHS
//  Used in common.inc.php
define('CONFIG_PATH', GWBBCODE_ROOT.'/config.inc.php');

//  Used in gwbbcode.inc.php
define('GWBBCODE_IMG_PATH', GWBBCODE_ROOT);
define('TEMPLATE_PATH', GWBBCODE_ROOT.'/gwbbcode.tpl');
define('GWBB_DYNAMIC_BODY', GWBBCODE_ROOT.'/dynamic_body.tpl');
?>

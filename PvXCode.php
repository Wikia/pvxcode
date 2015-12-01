<?php
/**
 * Curse Inc.
 * PvX Code
 * Guildwiki / Guild Wars Template to PvXCode Handling
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2015 Curse Inc.
 * @license		All Rights Reserved
 * @package		PvXCode
 * @link		http://www.curse.com/
 *
**/

/******************************************/
/* Credits                                */
/******************************************/
$wgExtensionCredits['specialpage'][] = [
	'path'           => __FILE__,
	'name'           => 'PvX Code',
	'author'         => 'Curse Wiki Team',
	'descriptionmsg' => 'pvxcode_description',
	'version'        => '1.0' //Must be a string or Mediawiki will turn it into an integer.
];

//$wgAvailableRights[] = 'pvxcode';

/******************************************/
/* Language Strings, Page Aliases, Hooks  */
/******************************************/
$wgMessagesDirs['PvXCode'] = __DIR__.'/i18n';


define('GWBBCODE_ROOT', __DIR__.'/vendor/gwbbcode');
require_once(GWBBCODE_ROOT.'/gwbbcode.inc.php');

// Classes
$wgAutoloadClasses['PvXCode'] = __DIR__.'/classes/PvXCode.php';
$wgAutoloadClasses['PvXCodeHooks'] = __DIR__.'/PvXCode.hooks.php';
$wgAutoloadClasses['SpecialPvXConvert'] = __DIR__."/specials/SpecialPvXConvert.php";
$wgAutoloadClasses['SpecialPvXDecode'] = __DIR__."/specials/SpecialPvXDecode.php";
$wgAutoloadClasses["SpecialPvXDownloadTemplate"] = __DIR__."/specials/SpecialPvXDownloadTemplate.php";


// Special Pages
$wgSpecialPages['PvXConvert'] = 'SpecialPvXConvert';
$wgSpecialPages['PvXDecode'] = 'SpecialPvXDecode';
$wgSpecialPages['DownloadTemplate'] = "SpecialPvXDownloadTemplate";

// Hooks
$wgHooks['ParserFirstCallInit'][]			= 'PvXCodeHooks::onParserFirstCallInit';

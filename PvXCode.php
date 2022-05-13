<?php
/**
 * Curse Inc.
 * PvX Code
 * Guildwiki / Guild Wars Template to PvXCode Handling
 *
 * @author        Cameron Chunn
 * @copyright    (c) 2015 Curse Inc.
 * @license        GPL-2.0-or-later
 * @package        PvXCode
 * @link        https://gitlab.com/hydrawiki
 *
 * Purpose of this file:
 *  Allows the extension to be loaded from localSettings.php
 */
if ( function_exists( 'wfLoadExtension' ) ) {
	// The following line seeks the PvXCode/extension.json file
	wfLoadExtension( 'PvXCode' );

	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['PvXCode'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for PvX Vote extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);

	return;
} else {
	die( 'This version of the PvXCode extension requires MediaWiki 1.25+' );
}

<?php
/**
 * Curse Inc.
 * PvX Code
 * Guild Wars Template to PvXCode Handling
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2015 Curse Inc.
 * @license		GNU General Public License v2.0 or later
 * @package		PvXCode
 * @link		https://gitlab.com/hydrawiki
 *
 * Purpose of this file:
 *  Declares how the extension will interpret pvxbig tag
 *  Loads the gwbbcode.inc.php file for builds and tooltips
**/
class PvXCodeHooks {
	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @param Parser $parser
	 * @return true
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		// Calls PvXCode.php within the classes folder
		$parser->setHook('pvxbig', 'PvXCode::ParserRender');
		return true;
	}

	/**
	 * Include the third party gwbbcode library on extension regiation.
	 * @return void
	 */
	public static function onRegistration() {
		// Retrieve config settings from localSettings.php - probably a better way of doing this via "use MediaWiki\MediaWikiServices;"
		global $wgServer;
		global $wgScriptPath;

		// Local file path for includes
		define('GWBBCODE_ROOT', __DIR__.'/gwbbcode');

		// Website URL for the image folder within extension/PvXCode folder, e.g. '/extensions/PvXCode/images'/img_skills/83.jpg
		define('GWBBCODE_IMAGES_FOLDER_URL', $wgServer.$wgScriptPath.'/extensions/PvXCode/images');

		// Website URL for the page prefix, e.g. 'https://gwpvx.gamepedia.com/index.php?title='/Archive:Team_-_Frostmaw_Searing_Spike
		define('PVX_WIKI_PAGE_URL', $wgServer.$wgScriptPath );

		// Website URL for the page prefix on the prefered wiki database
		define('GW_WIKI_PAGE_URL', 'https://wiki.guildwars.com/wiki');

		// Load main script
		require_once(GWBBCODE_ROOT.'/gwbbcode.inc.php');
	}

}

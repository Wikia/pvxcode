<?php
/**
 * Curse Inc.
 * PvX Code
 * Guildwiki / Guild Wars Template to PvXCode Handling
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
		$parser->setHook('pvxbig', 'PvXCode::ParserRender');
	    return true;
	}

	/**
	 * Include the third party gwbbcode library on extension regiation.
	 * @return void
	 */
	public static function onRegistration() {
		define('GWBBCODE_ROOT', __DIR__.'/gwbbcode');
		require_once(GWBBCODE_ROOT.'/gwbbcode.inc.php');
	}

}

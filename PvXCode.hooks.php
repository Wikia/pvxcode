<?php
/**
 * Curse Inc.
 * PvX Code
 * Instruct wiki to parse anything in pvxbig tags
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2015 Curse Inc.
 * @license		GNU General Public License v2.0 or later
 * @package		PvXCode
 * @link		https://gitlab.com/hydrawiki
 *
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

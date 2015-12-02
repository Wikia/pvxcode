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

	public static function onRegistration() {
		define('GWBBCODE_ROOT', __DIR__.'/vendor/gwbbcode');
		require_once(GWBBCODE_ROOT.'/gwbbcode.inc.php');
	}

}

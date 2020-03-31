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
**/

class PvXCode {
	/**
	 * Parser Renderer
	 * @param string $input	 inpuit into parser
	 * @param array $args  parsing arguements, not used in this function, but neaded by mediawiki.
	 * @param Parser $parser reference to the parser
	 * @param string $frame	 not used in this function, but needed by mediawiki.
	 */
	public static function ParserRender($input, $args, $parser, $frame) {
		$parser->getOutput()->addModuleStyles('ext.PvXCode.css');
		$parser->getOutput()->addModules('ext.PvXCode.js');
		$parsed_input = $parser->parse($input, $parser->getTitle(), $parser->getOptions(), true, false);
		$title = $parser->getTitle()->getText();
		$results = parse_gwbbcode($parsed_input->getText(), $title);
		return $results;
	}
}

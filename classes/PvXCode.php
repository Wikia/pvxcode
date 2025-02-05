<?php

use MediaWiki\Parser\Parser;
use MediaWiki\Title\TitleValue;

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
 */
class PvXCode {
	/**
	 * Parser Renderer
	 * @param string $input text input into parser
	 * @param array $args parsing arguements, not used in this function, but neaded by mediawiki.
	 * @param Parser $parser reference to the parser
	 * @param string $frame not used in this function, but needed by mediawiki.
	 * @return array|string|string[]|null
	 */
	public static function parserRender( string $input, array $args, Parser $parser, string $frame ) {
		$parser->getOutput()->addModuleStyles( [ 'ext.PvXCode.css' ] );
		$parser->getOutput()->addModules( [ 'ext.PvXCode.js' ] );
		$title = TitleValue::newFromPage( $parser->getPage() );
		$text = $title->getText();

		// Using recursiveTagParse() instead of parse() to avoid wrapping result
		// in a div and the associated processing time hidden HTML comment
		$parsed_input = $parser->recursiveTagParse( $input, $frame = false );

		return parseGwbbcode( $parsed_input, $text );
	}
}

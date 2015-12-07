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

class PvXCode {
    /**
     * Parser Renderer
     * @param string $input  inpuit into parser
     * @param array $args  parsing arguements, not used in this function, but neaded by mediawiki.
     * @param Parser $parser reference to the parser
     * @param string $frame  not used in this function, but needed by mediawiki.
     */
    public static function ParserRender($input, $args, $parser, $frame) {
        $parsed_input = $parser->parse($input, $parser->getTitle(), $parser->getOptions(), true, false);
        $title = $parser->getTitle()->getText();
        $results = parse_gwbbcode($parsed_input->getText(), $title);
        return str_replace('/template.php', Title::newFromText('DownloadTemplate', NS_SPECIAL)->getFullUrl(), $results);
    }

}

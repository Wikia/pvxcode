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
     * @param [type] $input  [description]
     * @param [type] $args   [description]
     * @param [type] $parser [description]
     * @param [type] $frame  [description]
     */
    public function ParserRender($input, $args, $parser, $frame) {
        $parsed_input = $parser->parse($input, $parser->getTitle(), $parser->getOptions(), true, false);
        $title = $parser->getTitle()->getText();
        $results = parse_gwbbcode($parsed_input->getText(), $title);
        return str_replace('/template.php', Title::newFromText('DownloadTemplate', NS_SPECIAL)->getFullUrl(), $results);
    }

}

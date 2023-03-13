<?php


/**
 * Curse Inc.
 * PvX Code
 * Guildwiki / Guild Wars Template to PvXCode Handling
 *
 * @author        Cameron Chunn
 * @copyright    (c) 2015 Curse Inc.
 * @license        GPL-2.0-or-later
 * @package        PvXDownloadTemplate
 * @link        https://gitlab.com/hydrawiki
 *
 * Purpose of this file:
 *  Provide a dedicated special page for converting an ingame skill template into valid pvxbig tags.
 */
class SpecialPvXDecode extends SpecialPage {
	/**
	 * Main Constructor
	 *
	 * @return    void
	 */
	public function __construct() {
		parent::__construct( 'PvXDecode' );

		require_once( GWBBCODE_ROOT . '/gwbbcode.inc.php' );
	}

	/**
	 * Main Execute for the Specil Page
	 * @param $par - Not used, but expected to be there by mediawiki.
	 * @return void - echos to page.
	 * @throws MWException
	 */
	public function execute( $par = null ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$name = $request->getText( 'wpName' );
		$build = $request->getText( 'wpBuild' );

		if ( $build ) {
			if ( strlen( $name ) >= 1 ) {
				$rout = template_to_gwbbcode( $name . ";" . $build );
			} else {
				$rout = template_to_gwbbcode( $build );
			}

			$output->addWikiTextAsContent( "== Preview ==" );
			$output->addWikiTextAsContent( "<pvxbig>" . $rout . "</pvxbig>" );
			$output->addHtml( "<br>" );
			$output->addWikiTextAsContent( "== PvXcode ==" );
			$out =
				"<p><textarea cols='80' rows='10' wrap='virtual'>\n<pvxbig>\n" . $rout . "\n</pvxbig>\n</textarea></p>";

		} else {
			$out = '<p>
					This decoder can process Guild Wars template and return PvXcode style template. Sample input:<br>
					<code>
					Hard Mode Farming;OQMU0QnEZpKpF4rUQl/MSik8AA
					<br>-- OR --<br>
					OANWQiiYkD3yXG1DkdJPqRkyTfA
					</code>
					<p>Enter Guild Wars template code:</p>
					<form action="" method="get">
					<input name="title" type="hidden" value="Special:PvXDecode" />
					<p><input name="wpBuild" type="text" size="80" maxlength="60" /></p>
					<p>Give new build template a name (optional):</p>
					<p><input name="wpName" type="text" size="80" maxlength="60" /></p>
					<p><input name="Go" type="submit" /></p>
					</form>';
		}
		$this->setHeaders();

		$output->addHTML( $out );
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @return string
	 */
	protected function getGroupName(): string {
		// Change to display in a different category on Special:SpecialPages.
		return 'pvx';
	}
}
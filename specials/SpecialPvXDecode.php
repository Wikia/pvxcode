<?php
/**
 * Curse Inc.
 * PvX Download Template
 * Dedicated special page for downloading build templates.
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2015 Curse Inc.
 * @license		All Rights Reserved
 * @package		PvXDownloadTemplate
 * @link		http://www.curse.com/
 *
**/

class SpecialPvXDecode extends SpecialPage {
	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		global $wgRequest, $wgUser, $wgOut;

		parent::__construct('PvXDecode');

		$this->wgRequest	= $wgRequest;
		$this->wgUser		= $wgUser;
		$this->output		= $this->getOutput();

		$this->DB = wfGetDB(DB_MASTER);


		require_once(GWBBCODE_ROOT.'/gwbbcode.inc.php');
	}

	/**
	 * Main Execute for the Specil Page
	 * @param  $par Not used, but expected to be there by mediawiki.
	 * @return void, echos to page.
	 */
	function execute($par = null) {
		$name  = $this->wgRequest->getText('wpName');
		$build = $this->wgRequest->getText('wpBuild');

		if ($build) {
			if (strlen($name) > 1) {
				$rout = template_to_gwbbcode($name . ";" . $build);
			} else {
				$rout = template_to_gwbbcode($build);
			}

			$this->output->addWikiText("== Preview ==");
			$this->output->addWikiText("<pvxbig>" . $rout . "</pvxbig>");
			$this->output->addHtml("<br>");
			$this->output->addWikiText("== PvXcode ==");
			$out = "<p><textarea cols='80' rows='10' wrap='virtual'>\n<pvxbig>\n" . $rout . "\n</pvxbig>\n</textarea></p>";

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

		$this->output->addHtml($out);
	}
	
	/**
	 * Return the group name for this special page.
	 *
	 * @access protected
	 * @return string
	 */
	protected function getGroupName() {
		return 'pvx'; //Change to display in a different category on Special:SpecialPages.
	}
}

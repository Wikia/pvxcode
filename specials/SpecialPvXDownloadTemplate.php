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

class SpecialPvXDownloadTemplate extends SpecialPage {
	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct(
			'DownloadTemplate', // name
			'downloadtemplate', // required user right
			true // display on Special:Specialpages
		);
	}

	/**
	 * Main Executor
	 *
	 * @access	public
	 * @param	string	Sub page passed in the URL.
	 * @return	void	[Outputs to screen]
	 */
	public function execute( $path ) {

		$name = $_GET["name"];
		# hack -- gwbbcode passes unencoded build hash
		$build = str_replace( " ", "+", $_GET["build"] );


		//Begin writing headers
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header('Content-Type: text/force-download');

		//Sending the content...
		header('Content-Disposition: attachment; filename="' . $name . '.txt"');
		if (strlen($build) < 100) echo $build;
		exit;
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @access protected
	 * @return string
	 */
	protected function getGroupName() {
		return 'other'; //Change to display in a different category on Special:SpecialPages.
	}
}

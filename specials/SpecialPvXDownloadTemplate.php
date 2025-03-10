<?php

use MediaWiki\SpecialPage\SpecialPage;

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
 *  Provide a dedicated special page for downloading build templates.
 */
class SpecialPvXDownloadTemplate extends SpecialPage {
	/**
	 * Main Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct(
			'DownloadTemplate',
			'downloadtemplate',
			true
		);
	}

	/**
	 * Main Executor
	 *
	 * @param string|null $subPage - Sub page passed in the URL.
	 * @return void - [Outputs to screen]
	 */
	public function execute( $subPage ) {
		// $_GET retrieves functions from the URL.
		// If the parameter hasn't been specified we don't want
		// to put php errors in the download file.
		$name = 'Unnamed template';
		if ( isset( $_GET['name'] ) ) {
			$name = $_GET['name'];
		}
		$build = '(template parameter was blank)';
		if ( isset( $_GET['build'] ) ) {
			$build = $_GET['build'];
		}
		// FIXME - gwbbcode passes unencoded build hash
		$build = str_replace( " ", "+", $build );

		// Begin writing headers
		header( "Pragma: public" );
		header( "Expires: 0" );
		header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
		header( 'Content-Type: text/force-download' );

		// Sending the content...
		header( 'Content-Disposition: attachment; filename="' . $name . '.txt"' );
		if ( strlen( $build ) < 100 ) {
			echo $build;
		}
		exit;
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @return string
	 */
	protected function getGroupName(): string {
		// Change to display in a different category on Special:SpecialPages.
		return 'other';
	}
}

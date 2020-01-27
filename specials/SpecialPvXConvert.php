<?php
/**
 * Curse Inc.
 * PvX Code
 * Guildwiki / Guild Wars Template to PvXCode Handling
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2015 Curse Inc.
 * @license		GNU General Public License v2.0 or later
 * @package		PvXDownloadTemplate
 * @link		https://gitlab.com/hydrawiki
 *
 * Purpose of this file:
 *  Provide a dedicated special page for converting old GuildWiki templates into valid pvxbig tags.
**/
class SpecialPvXConvert extends SpecialPage {
	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct('PvXConvert');

		$this->wgRequest	= $this->getRequest();
		$this->wgUser		= $this->getUser();
		$this->output		= $this->getOutput();

		$this->DB = wfGetDB(DB_MASTER);
	}

	/**
	 * Main Execute for the Special Page
	 *
	 * @param  $par Not used, but expected to be there by mediawiki.
	 * @return void, echos to page.
	 */
	function execute($par = null) {
		$name  = $this->wgRequest->getText('wpName');
		$build = $this->wgRequest->getText('wpBuild');

		if ($build) {
			$rout = $this->formatBuild($build, $name);
			$this->output->addWikiText("== Preview ==");
			$this->output->addWikiText($rout);
			$this->output->addHtml("<br>");
			$this->output->addWikiText("== PvXcode ==");
			$out = "<p><textarea cols='80' rows='10' wrap='virtual'>" . $rout . "</textarea></p>";
		} else {
			$out = '<p>
					This converter can process single template (attribute & skill) as well as a whole wiki page. It will replace old style template with new pvxcode. <br>
					However manual control is required. Sample input:<br><code>
					{{attributes&nbsp;|&nbsp;Ranger&nbsp;|&nbsp;Mesmer<br>
					&nbsp;&nbsp;|&nbsp;Wilderness&nbsp;Survival&nbsp;|&nbsp;12&nbsp;+&nbsp;1&nbsp;+&nbsp;3<br>
					&nbsp;&nbsp;|&nbsp;Expertise&nbsp;|&nbsp;12&nbsp;+&nbsp;1<br>
					}}<br>
					{{skill bar|Echo|Dust Trap|Barbed Trap|Flame Trap|Trappers Speed|Troll Unguent|Whirling Defense|Resurrection Signet}}</code>
					<p>Enter old style GuildWiki &quot;Attributes and Skills&quot; template:</p>
					<form action="' . $_SERVER["PHP_SELF"] . '" method="get">
					<input name="title" type="hidden" value="Special:PvXconvert" />
					<p><textarea name="wpBuild" cols="80" rows="10" wrap="virtual"></textarea></p>
					<p>Give new build template a name (optional):</p>
					<p><input name="wpName" type="text" size="80" maxlength="60" /></p>
					<p><input name="Go" type="submit" /></p>
					</form>';
		}
		$this->setHeaders();

		$this->output->addHtml($out);
	}

	/**
	 * Takes gw code and parses it into pvx code.
	 * @param  string $build
	 * @param  string $name
	 * @return string
	 */
	function formatBuild($build, $name) {
		// Explode always returns at least one array element
		$build = explode("skill bar", $build);
		$att = $build[0];

		// Check if second element exists
		$skl = '';
		if ( isset($build[1]) ) {
			$skl = $build[1];
		}

		return ('<pvxbig>' . PHP_EOL . '[build' . $this->cnv_attributes($att, $name) . ']' . $this->cnv_skils($skl) . '[/build]' . PHP_EOL . '</pvxbig>');
	}

	/**
	 * Converts Attributes section of gw code into pvx code.
	 * @param  string $att gw code for attributes
	 * @param  string $name build name
	 * @return string
	 */
	function cnv_attributes($att, $name) {
		$var = preg_replace("/\r\n|\n|\r/", "", $att);
		$var = str_replace(" ", "", $var);
		$var = str_replace("{{attributes|", "", $var);
		$var = str_replace("{{", "", $var);
		$var = str_replace("}}", "", $var);

		// Quit early if no attributes (would have been equal to "{{" before removal).
		if ($var == '') {
			return ' prof=""'; // the converter doesn't like if the build attribute is empty.
		}

		// Add build name (if it exists)
		$out = array();
		if ($name) {
			$out[0] = ' name="' . $name . '"';
		} else {
			$out[0] = '';
		}
		
		// For the unnamed parameters:
		//  the first two will be the professions
		//  followed by pairs of attributes
		$attributes = explode("|", $var);
		
		// Add build profession
		if ( isset($attributes[0]) && isset($attributes[1]) ) {
			$out[0] .= ' prof="' . $attributes[0] . '/' . $attributes[1] . '"';
		
			// Add build attributes
			$i = 2; // offset due to first two being professions, per above
			$y = 1; // first position is already occupied by the profession
			while ($i <= count($attributes)) {
				if ( isset($attributes[$i]) and isset($attributes[$i + 1]) ) {
					// $out[$y] = substr($attributes[$i], 0, 6) . "=" . substr($attributes[$i + 1], 0, 12);
					$out[$y] = $attributes[$i] . "=" . $attributes[$i + 1];
				}
				$y++;
				$i = $i + 2;
			}
		}
		$attributes = strtolower(implode(" ", $out));
		return $attributes;
	}

	/**
	 * Converts Skills section of gw code into pvx code.
	 * @param  $skl gw code for skills
	 * @return string
	 */
	function cnv_skils($skl) {
		$var = preg_replace("/\r\n|\n|\r/", "", $skl);
		$var = str_replace("'", "", $var);
		$var = str_replace("\"", "", $var);
		$var = str_replace("!", "", $var); // We're removing exclamation marks for shouts here.
		$var = str_replace("{{", "", $var);
		$var = str_replace("}}", "", $var);

		// Quit early if no skill bar (would have been equal to "" before pattern removal).
		if ($var == '') {
			return '';
		}

		$skills = explode("|", $var);
		$out = array();
		$i = 0;
		while ($i <= count($out)) {
			if (isset($skills[$i + 1])) {
				$out[$i] = '[' . $skills[$i + 1] . ']';
			}
			$i++;
		}
		$skills = implode("", $out);
		return $skills;
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

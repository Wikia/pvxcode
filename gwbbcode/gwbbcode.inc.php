<?php
/***************************************************************************
 *                             gwbbcode.inc.php
 *                            -------------------
 *   begin                : Tuesday, Apr 21, 2005
 *   copyright            : (C) 2006-2007 Pierre 'pikiou' Scelles
 *   email                : liu.pi.vipi@gmail.com
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *   All images, skill names and descriptions are (C) ArenaNet.
 ***************************************************************************/

require_once(GWBBCODE_ROOT . '/constants.inc.php');

/***************************************************************************
 * GLOBALS
 ***************************************************************************/

// Load the gwbbcode smarty syntax template (*.TPL)
global $gwbbcode_tpl;
if (!isset($gwbbcode_tpl))
	$gwbbcode_tpl = load_gwbbcode_smarty_template();

// FIXME - SUSPECT MEMORY INTENSIVE - NOT SURE HOW TO ONLY LOAD THE IDS ONCE, AND NOT ATTEMPT TO LOAD MULTIPLE TIMES.
// Array of PvP -> PvE IDs
//  @ sign just means it suppresses errors if they occur
static $pvpSkillIds = Array(); // previously "global" not "static"
if (empty($pvpSkillIds)) {
	if (($pvpSkillIds = @load(SKILLIDSPVP_PATH)) === false) {
		die("Missing pvp skill id database.");
	}
}
// Array of PvE -> PvP IDs (only if there is a PvP id)
$pveSkillIds = array_flip($pvpSkillIds); // FIXME - VERIFY: PREVIOUSLY WROTE STATIC IN FRONT OF THIS - THINK IT JUST TAKES THE TYPE OF THE ARRAY_FLIP?

/***************************************************************************
 * MAIN FUNCTION
 ***************************************************************************/

// Prepares the page and then replaces gwBBCode with HTML
//  This function is directly called by: extension\classes\PvXCode.php
function parse_gwbbcode($text, $build_name = false) {
	// If a build name is provided, assign it to all builds
	//  This is so that any save build button has a decent title
	if (!empty($build_name)) {
		$text = preg_replace('#(\[build[^\]]*?) name="[^\]"]+"#isS', "\\1", $text); // Remove all build names
		$text = preg_replace('#(\[build )#isS', "\\1name=\"$build_name\" ", $text); // Add build names
	}

	// Timer for the actual gwBBCode parse duration
	$start = microtime(true);

	// 1: Replace all '['s inside [pre]..[/pre], or [nobb]..[/nobb] by '&#91;'
	$text = preg_replace_callback('#\[pre\](.*?)\[\/pre\]#isS', 'pre_replace', $text);
	$text = preg_replace_callback('#\[nobb\](.*?)\[\/nobb\]#isS', 'pre_replace', $text);

	// 2: Replace all [Random Skill] by [some random skill name] each time the post is rendered
	$text = preg_replace_callback('#\[Random Skill(.*?)\]#is', 'random_skill_replace', $text);

	// 3: [rand seed=465468 players=2]
	$text = preg_replace_callback('#\[rand([^\]]*)\]#isS', 'rand_replace', $text);

	// 4: Manage [build=...], or [build_name;template_code]
	$text = preg_replace_callback('#\[build=([^\]]*)\]\]?(\[/build\])?\r?\n?#isS', 'build_id_replace', $text);
	$text = preg_replace_callback('#\[(([^]\r\n]+)(;)([^];\r\n]+))\]\r?\n?#isS', 'build_id_replace', $text);

	// 5: Replace all [skill_name] by [skill]skill_name[/skill] if skill_name is a valid skill name
	$text = preg_replace_callback('#\[(.+)\]#isSU', 'skill_name_replace', $text);

	// 6: Manage [build]...[/build]
	$text = preg_replace_callback('#\[build ([^\]]*)\](.*?)\[/build\]\r?\n?#isS', 'build_replace', $text);

	// 7: Replace all [skillset=prof_short_name@attr_level]
	$text = preg_replace_callback('#\[(\[?)skillset=(.*?)\]#isS', 'skillset_replace', $text);

	// 8: Manage [skill]...[/skill]
	$text = preg_replace_callback('#\[skill([^\]]*)\](.*?)\[/skill\][ ]?#isS', 'skill_replace', $text);

	// FIXME - SEEMS TO ALWAYS RETURN ZERO?
	// 9: [gwbbcode runtime]
	if (preg_match('@\[gwbbcode runtime\]@i', $text) !== false) {
		$text = preg_replace('@\[gwbbcode runtime\]@i', 'Runtime = ' . round(microtime(true) - $start, 3) . ' seconds', $text); // Precise enough
	}

	return $text;
}




/***************************************************************************
 * HELPER REPLACEMENT FUNCTIONS
 ***************************************************************************/

// Replacement function 1:
// Changes any '[' by '&#91;', hence disabling bbcode
function pre_replace($reg) {
	list($all, $content) = $reg;
	return str_replace('[', '&#91;', $content);
}

// Replacement function 2:
// Convert [Random Skill] tags to [a random skill name]
function random_skill_replace($reg) {
	list($all, $att) = $reg;
	$skill_list = gws_skill_id_list();
	$small_name = array_rand($skill_list);
	$details    = gws_details($skill_list[$small_name]);
	return '[' . $details['name'] . "$att]";
}

// Replacement function 3:
// Process [rand seed=465468 players=2]
function rand_replace($reg) {
	list($all, $att) = $reg;
	$att = html_safe_decode($att);

	// Get the seed
	if (preg_match('|seed=([0-9]+)|', $att, $reg)) {
		$seed = intval($reg[1]);
	} else {
		$seed = rand(1000, 100000);
	}
	srand($seed);

	// Get the number of players
	if (preg_match('|players=([0-9]+)|', $att, $reg)) {
		$players = intval($reg[1]);
	} else {
		$players = 1;
	}

	// Generate random skills
	$skills     = Array();
	$elite      = 0;
	$max_elite  = ($players + 1) * 3;
	$normal     = 0;
	$max_normal = ($players + 1) * 14;
	$total      = 0;
	$max_total  = $max_elite + $max_normal;
	while ($total < $max_total) {
		$id = rand(0, 2000);
		if ($skill = gws_details($id)) {
			// Skill exists!
			if ($skill['elite'] == 1 && $elite < $max_elite) {
				$elite++;
				$total++;
				$skills[] = $skill;
			} else if ($skill['elite'] == 0 && $normal < $max_normal) {
				$normal++;
				$total++;
				$skills[] = $skill;
			}
		}
	}

	// Sort and output the list of skills
	uasort($skills, "skill_sort_cmp");
	$skill_list = '';
	foreach ($skills as $skill) {
		$skill_list .= '[' . $skill['name'] . ']';
	}

	return "Random skill set (seed=$seed):\n$skill_list";
}

// Replacement function 4:
// Process the [build=id] element
function build_id_replace($reg) {
	list($all, $id) = $reg;
	$new_code = template_to_gwbbcode($id);
	return (strpos($new_code, '[') === 0) ? $new_code : $all;
}

// Replacement function 5:
// Convert [Potential Skill Name] tags to [skill]Potential Skill Name[/skill]
function skill_name_replace($reg) {
	global $gwbbcode_tpl;
	list($all, $name) = $reg;
	$name = html_safe_decode($name);

	$all = preg_replace('@\[gwbbcode version\]@i', GWBBCODE_VERSION, $all);

	//'[[Skill Name]' => no icon
	if ($name{0} == '[') {
		$noicon = true;
		$name   = substr($name, 1);
	} else
		$noicon = false;

	//"name@attr_value|shown_name" -> $attr_val, $shown_name and $name
	if (preg_match('/@([^\]:\|]+)/', $name, $reg)) {
		$attr_val = preg_replace('@[^0-9+-]@', '', $reg[1]);
	}
	if (preg_match('/\|([^\]@:]+)/', $name, $reg)) {
		$shown_name = html_safe_decode($reg[1]); // Play it safe
	}
	if (preg_match('/^([^@\]:\|]+)/', $name, $reg)) {
		$name = $reg[1];
	}

	if (($id = gws_skill_id($name)) !== false) {
		// Handle [shock@8]
		$attr = '';
		if (isset($attr_val)) {
			$skill = gws_details($id);
			$attr  = gws_attribute_name($skill['attribute']);
			$attr  = " $attr=$attr_val";
		}

		// Handle [shock|a knockdown]
		$show = '';
		if (!empty($shown_name)) {
			$show   = " show=\"$shown_name\"";
			$noicon = true;
		}

		// Handle the difference between [[shock] and [shock]
		if ($noicon)
			return "[skill noicon$attr$show]" . $name . '[/skill]';
		else
			return "[skill$attr]" . $name . '[/skill]';
	} else
		return $all;
}

// Replacement function 6:
// Process the [build] element
function build_replace($reg) {
	global $gwbbcode_tpl;
	global $userdata;
	$gwbbcode_root_path = GWBBCODE_ROOT;
	$gwbbcode_img_path  = GWBBCODE_IMG_PATH;
	list($all, $att, $skills) = $reg;
	$att           = str_replace("\n", "<br/>\n", html_safe_decode($att));
	$attr_list_raw = attribute_list_raw($att);
	$attr_list     = attribute_list($att);
	$load          = rand();

	// Build name
	$build_name = gws_build_name($att);
	$build_name = str_replace('{br}', '<br/>', str_replace('{br/}', '<br/>', str_replace('{BR}', '<br/>', str_replace('{BR/}', '<br/>', $build_name))));

	// Professions
	$prof      = gws_build_profession($att);
	$prof_imgs = '';
	$cursor    = 'help';
	if ($prof !== false) {
		$primary   = gws_prof_name($prof['primary']);
		$secondary = gws_prof_name($prof['secondary']);
		if ($secondary == $primary) {
			$secondary = 'No profession';
		}

		$showimg = '';
		$prof_imgs .= str_replace('{profession}', $primary, $gwbbcode_tpl['prof_icon']);
		if ($secondary != 'No profession') {
			$prof_imgs .= str_replace('{profession}', $secondary, $gwbbcode_tpl['prof_icon']);
			// Remove secondary profession main attribute, or set it to 0 for skill description coherence
			unset($attr_list_raw[gws_main_attribute($secondary)]);
			$att .= ' ' . gws_attribute_name(gws_main_attribute($secondary)) . '=0';
		}
		unset($matches);
		preg_match_all("#\{(.*?)\}#is", $prof_imgs, $matches);
		foreach ($matches[0] as $r => $find) {
			$replace   = ${$matches[1][$r]};
			$prof_imgs = str_replace($find, $replace, $prof_imgs);
		}
	} else {
		$showimg   = ' ;display: none';
		$primary   = 'Any';
		$secondary = 'Any';
	}


	// Attributes
	$attributes = '';
	foreach ($attr_list_raw as $attribute_name => $attribute_value) {
		//$attributes .= preg_replace("#\{(.*?)\}#ise", "isset($\\1)?$\\1:'\\0'", $gwbbcode_tpl['attribute']);
		unset($matches);
		$attr = $gwbbcode_tpl['attribute'];
		preg_match_all("#\{(.*?)\}#is", $attr, $matches);
		foreach ($matches[0] as $r => $find) {
			$replace = ${$matches[1][$r]};
			$attr    = str_replace($find, $replace, $attr);
		}
		$attributes .= $attr;
	}
	$attributes = preg_replace('/\s*\\+\s*/', ' + ', $attributes);
	$skills     = str_replace('[skill', '[skill ' . $att, $skills);

	// Build description
	$desc = preg_match('|desc=\\"([^"]+)\\"|', $att, $reg) ? $reg[1] : '';
	$desc = empty($desc) ? '' : ($desc . '<br/>');
	$desc = str_replace('{br}', '<br/>', str_replace('{br/}', '<br/>', str_replace('{BR}', '<br/>', str_replace('{BR/}', '<br/>', $desc))));

	// Primary attribute effect on build
	if (!empty($attr_list['Energy Storage'])) {
		$desc .= '<span class="expert">Your maximum Energy is raised by <b>' . (3 * $attr_list['Energy Storage']) . '</b>.</span><br/>';
	} else if (!empty($attr_list['Soul Reaping'])) {
		$desc .= '<span class="expert">When they die near you, you gain <b>' . $attr_list['Soul Reaping'] . '</b> Energy per creature, or <b>' . floor($attr_list['Soul Reaping'] / 2) . '</b> Energy per Spirit under your control, up to 3 times every 15 seconds.</span><br/>';
	} else if (!empty($attr_list['Critical Strikes'])) {
		$desc .= '<span class="expert">You have an additonal <b>' . $attr_list['Critical Strikes'] . '</b>% chance to critical hit. Whenever you critical hit, you get <b>' . round($attr_list['Critical Strikes'] / 5) . '</b> Energy.</span><br/>';
	} else if (!empty($attr_list['Mysticism'])) {
		$desc .= '<span class="expert">Whenever an Enchantment ends, you gain <b>' . $attr_list['Mysticism'] . '</b> Health and <b>' . floor($attr_list['Mysticism'] / 3) . '</b> Energy.</span><br/>';
	}


	// Template: professions
	$invalid_template = false;
	static $prof_ids = Array('No profession', 'Warrior', 'Ranger', 'Monk', 'Necromancer', 'Mesmer', 'Elementalist', 'Assassin', 'Ritualist', 'Paragon', 'Dervish');
	$template = '0111000000';
	$template .= int2bin(array_search($primary, $prof_ids), 4);
	$template .= int2bin(array_search($secondary, $prof_ids), 4);

	// Template: attributes
	static $attr_ids = Array('fas', 'ill', 'dom', 'ins', 'blo', 'dea', 'sou', 'cur', 'air', 'ear', 'fir', 'wat', 'ene', 'hea', 'smi', 'pro', 'div', 'str', 'axe', 'ham', 'swo', 'tac', 'bea', 'exp', 'wil', 'mar', 29 => 'dag', 'dead', 'sha', 'com', 'res', 'cha', 'cri', 'spa', 'spe', 'comma', 'mot', 'lea', 'scy', 'win', 'earthp', 'mys');
	static $prof_attr_list = array('Air Magic' => 'Elementalist', 'Earth Magic' => 'Elementalist', 'Energy Storage' => 'Elementalist', 'Fire Magic' => 'Elementalist', 'Water Magic' => 'Elementalist', 'Domination Magic' => 'Mesmer', 'Fast Casting' => 'Mesmer', 'Illusion Magic' => 'Mesmer', 'Inspiration Magic' => 'Mesmer', 'Divine Favor' => 'Monk', 'Healing Prayers' => 'Monk', 'Protection Prayers' => 'Monk', 'Smiting Prayers' => 'Monk', 'Blood Magic' => 'Necromancer', 'Curses' => 'Necromancer', 'Death Magic' => 'Necromancer', 'Soul Reaping' => 'Necromancer', 'Beast Mastery' => 'Ranger', 'Expertise' => 'Ranger', 'Marksmanship' => 'Ranger', 'Wilderness Survival' => 'Ranger', 'Axe Mastery' => 'Warrior', 'Hammer Mastery' => 'Warrior', 'Strength' => 'Warrior', 'Swordsmanship' => 'Warrior', 'Tactics' => 'Warrior', 'Critical Strikes' => 'Assassin', 'Dagger Mastery' => 'Assassin', 'Deadly Arts' => 'Assassin', 'Shadow Arts' => 'Assassin', 'Spawning Power' => 'Ritualist', 'Channeling Magic' => 'Ritualist', 'Communing' => 'Ritualist', 'Restoration Magic' => 'Ritualist', 'Spear Mastery' => 'Paragon', 'Command' => 'Paragon', 'Motivation' => 'Paragon', 'Leadership' => 'Paragon', 'Scythe Mastery' => 'Dervish', 'Wind Prayers' => 'Dervish', 'Earth Prayers' => 'Dervish', 'Mysticism' => 'Dervish');
	arsort($attr_list_raw); //=> First attribute is attribute of highest level


	// Prepare a base attribute level and rune bonus list for primary and secondary attributes
	$attr_primary     = Array();
	$attr_secondary   = Array();
	$attr_runes       = Array();
	$available_helmet = true;
	// Ignore secondary profession main attribute
	if ($secondary != 'No profession') {
		unset($attr_list_raw[gws_main_attribute($secondary)]);
	}

	foreach ($attr_list_raw as $attr => $raw_level) {
		// Separate base level and bonus
		preg_match('@^([0-9]+)(\\+[+0-9]+)?@', $raw_level, $reg);
		$base_level = $reg[1];
		$bonus      = isset($reg[2]) ? $reg[2] : '0';

		// Primary attributes
		if ($prof_attr_list[$attr] == $primary) {
			$bonus_level = array_sum(explode('+', $bonus));
			// Invalid attribute level bonus
			if ($bonus_level > 4 || $bonus_level < 0 || ($bonus_level == 4 && !$available_helmet)) {
				$invalid_template = "Invalid attribute level bonus";
			}
			// Does attribute level bonus include a helmet?
			else if ($available_helmet && ($bonus_level == 4 || (substr_count($bonus, '+') > 1 && strpos($bonus, '+1') !== false))) {
				$available_helmet    = false;
				$attr_primary[$attr] = $base_level;
				$attr_runes[$attr]   = $bonus_level - 1;
			}
			// No helmet, but runes maybe
			else {
				$attr_primary[$attr] = $base_level;
				$attr_runes[$attr]   = $bonus_level;
			}
		}

		// Secondary attributes
		else if ($prof_attr_list[$attr] == $secondary) {
			$attr_secondary[$attr] = $base_level;
		}
	}

	// Manage primary attribute levels
	$points_secondary = attr_points($attr_secondary);
	if (!empty($attr_primary)) {
		// Set helmet if needed
		if ($available_helmet && (max($attr_primary) > 12 || (attr_points($attr_primary) + $points_secondary > 200))) {
			reset($attr_primary);
			$attr_primary[key($attr_primary)]--; // First attribute is attribute of highest level, the one needing a helmet the most
		}
		// Assign runes
		while ((max($attr_primary) > 12 || (attr_points($attr_primary) + $points_secondary > 200)) && min($attr_runes) < 3) {
			arsort($attr_primary);
			foreach ($attr_runes as $attr => $rune) {
				if ($rune < 3) {
					$attr_primary[$attr]--;
					$attr_runes[$attr]++;
					break;
				}
			}
		}
	}
	$attr_list_raw = array_merge($attr_primary, $attr_secondary);
	$template .= int2bin(count($attr_list_raw), 4);
	if (!empty($attr_list_raw) && (max($attr_list_raw) > 12 || min($attr_list_raw) < 0 || attr_points($attr_list_raw) > 200)) {
		$invalid_template = "More attribute levels than attribute points can handle";
	}
	$attr_bit_size  = 5;
	$template_attrs = Array();
	foreach ($attr_list_raw as $attr => $level) {
		$id                  = array_search(gws_attribute_name($attr), $attr_ids);
		$template_attrs[$id] = $level;
		if ($id >= 32) {
			$attr_bit_size = 6;
		}
	}
	$template .= int2bin($attr_bit_size - 4, 4);
	ksort($template_attrs);
	foreach ($template_attrs as $id => $level) {
		$template .= int2bin($id, $attr_bit_size);
		$template .= int2bin($level, 4);
	}


	// Template: skills
	// and Kurzick/Luxon adaptation
	$skill_bit_size  = 9;
	$skill_id_max    = pow(2, $skill_bit_size);
	$template_skills = Array();
	if (preg_match_all('#\[skill[^\]]*\](.*?)\[/skill\][ ]?#isS', $skills, $regs, PREG_SET_ORDER)) {
		foreach ($regs as $reg) {
			$skill_name = $reg[1];
			$id         = gws_skill_id($skill_name);
			if ($id !== false) {
				$skill = gws_details($id);
				// Handle faction-less skills
				if ($skill['attr'] == 'kur' && strpos($skill_name, '(') === false) { // By default, unaligned skills are kurzick
					// Change to default allegiance
					$default_id = $id;
					if (strpos(strtolower(GWBBCODE_ALLEGIANCE), 'kurzick') === false) {
						$id         = gws_skill_id($skill_name . ' (luxon)');
						$skill      = gws_details($id);
						$default_id = $id;
					}
					// Try the other allegiance if default one is already used
					if (array_search($id, $template_skills) !== false) {
						$id = gws_skill_id($skill_name . ($skill['attr'] == 'kur' ? ' (Luxon)' : ' (Kurzick)'));
						// Change back to default allegiance if both allegiance skills were used
						if (array_search($id, $template_skills) !== false) {
							$id = $default_id;
						}
						$skill = gws_details($id);
					}
					// Update skill names in $skills
					$new_skill_str = str_replace($skill_name, $skill_name . ($skill['attr'] == 'kur' ? ' (Kurzick)' : ' (Luxon)'), $reg[0]);
					$new_skill_str = str_replace('$', '\\$', $new_skill_str); // you never know..
					$skills        = preg_replace('@' . preg_quote($reg[0]) . '@', $new_skill_str, $skills, 1);
				}

				if (($skill['profession'] == $primary || $skill['profession'] == $secondary || $skill['prof'] == '?') && (array_search($id, $template_skills) === false || $id == 0)) {
					if ($id >= $skill_id_max) {
						$skill_bit_size = floor(log($id, 2)) + 1;
						$skill_id_max   = pow(2, $skill_bit_size);
					}
				} else {
					if (array_search($id, $template_skills) !== false) {
						$invalid_template = "Skill $skill_name can't be specified twice in the same build";
					} else {
						$invalid_template = "Skill $skill_name is not from one of the build professions";
					}
				}
				$template_skills[] = $id; // Keep the list of skills independently from their build validity
			}
		}
	}
	$template .= int2bin($skill_bit_size - 8, 4);
	if (count($template_skills) > 8) {
		$invalid_template = "You specified more skills than a build can handle";
	} else {
		foreach ($template_skills as $id) {
			$template .= int2bin(getSkillIdPvE($id), $skill_bit_size);
		}
		// Complete with 0s
		$template .= str_repeat(str_repeat('0', $skill_bit_size), 8 - count($template_skills));
	}

	// Manage template save icons
	if (preg_match("/ nosave/i", $att)) {
		$template_html = '';
	} else {
		if ($invalid_template === false) {
			// Prepare template bbcode
			$template_code = bin_to_template($template . '0'); // Added a 0 since that's what GW does
			$template_name = preg_replace('@[];]@', '', preg_replace('@<br/>.*@', '', $build_name));
			if (empty($template_name)) {
				$template_name = gws_profession_abbr($primary);
				if ($secondary != 'No profession' && $secondary != $primary) {
					$template_name .= '/' . gws_profession_abbr($secondary);
				}
			}
			$template_bbcode = "&#91;$template_name;$template_code]"; // Used &#91; to prevent double parsing anomalies
		} else {
			$template_error_msg = htmlspecialchars($invalid_template);
			// FIXME - No idea where the error should be routed to.
		}
	}

	$tpl = $gwbbcode_tpl['build'];
	// Replace all "{var_name}" by $var_name till there is none to replace (i.e a tag replacement can contain other tags)
	do {
		$prev_tpl = $tpl;
		// "{{skill_description}}" is replaced by $gwbbcode_tpl['skill_description']
		$tpl = preg_replace_callback("#\{\{(.*?)\}\}#is", function($m) {
			return isset($gwbbcode_tpl[$m[1]]) ? $gwbbcode_tpl[$m[1]] : $m[0];
		}, $tpl);
	
		// "{desc}" is replaced by $desc
		unset($matches);
		preg_match_all("#\{(.*?)\}#is", $tpl, $matches);
		foreach ($matches[0] as $r => $find) {
			if ( isset(${$matches[1][$r]}) ) {
				$replace = ${$matches[1][$r]};
			} else {
				$replace = '';
			}
			$tpl = str_replace($find, $replace, $tpl);
		}
	} while ($prev_tpl != $tpl);
	return $tpl;
}

// Replacement function 7:
// Convert [skillset=prof_short_name@attr_level] tags to a list of skills of the appropriate attribute
function skillset_replace($reg) {
	list($all, $noicon, $name) = $reg;
	$name = html_safe_decode($name);

	//'[[...]' => no icon
	$noicon       = empty($noicon) ? '' : ' noicon';
	$noicon_comma = empty($noicon) ? '' : ', ';

	//"name@attr_value" -> $name and $attr_val
	$attr = '';
	if (preg_match('|([^@\]]+)@([^\]]+)|', $name, $reg)) {
		$name     = $reg[1];
		$attr_val = preg_replace('@[^0-9+-]@', '', $reg[2]);
	}


	// Get the list of skills
	$bbcode = Array();
	if ($attr = gws_attribute_name($name)) {
		$attr_bbcode = " $attr=$attr_val";
		$skill_list  = gws_skill_id_list();
		foreach ($skill_list as $name_id => $id) {
			$skill = gws_details($id);
			if ($skill['attr'] == $attr) {
				$bbcode[] = "[skill$noicon$attr_bbcode]{$skill['name']}[/skill]";
			}
		}
	}

	return implode($noicon_comma, $bbcode);
}

// Replacement function 8:
// Process the [skill] elements
function skill_replace($reg) {
	global $gwbbcode_tpl;
	$gwbbcode_root_path = GWBBCODE_ROOT;
	$gwbbcode_img_path  = GWBBCODE_IMG_PATH;

	list($all, $att, $name) = $reg;
	$att  = html_safe_decode($att);
	$name = html_safe_decode($name);

	// Handle alternative text
	$shown_name = $name;
	if (preg_match('/show="([^\]]*)"/', $att, $reg)) {
		$shown_name = html_safe_decode($reg[1]); // Play it safe
		$att        = preg_replace('/[ ]*db="[^\]]*"/', '', $att);
	}

	// Exit if skill doesn't exist
	if (($id = gws_skill_id($name)) === false) {
		return $all;
	}

	$details = gws_details($id);
	if ($details === false)
		die("Missing skill. Id=$id; Name=$name");

	// Handle faction-less skills
	if ($details['attr'] == 'kur' && strpos($name, '(') === false) { // By default, unaligned skills are kurzick
		// Change to default allegiance
		if (strtolower(GWBBCODE_ALLEGIANCE) !== 'kurzick') {
			$id      = gws_skill_id($name . ' (luxon)');
			$details = gws_details($id);
		}
	}

	extract($details, EXTR_OVERWRITE);
	$load = rand();

	// Blank skill slot
	if ($name == 'No Skill') {
		$tpl = $gwbbcode_tpl['blank_icon'];
	}

	// Skill slot
	else {
		// Get the skill attribute level
		$attr_list  = attribute_list($att);
		$attr_value = isset($attr_list[$attribute]) ? $attr_list[$attribute] : '';

		// Skill name or image on which to move cursor
		$name_link = str_replace("\"", "&quot;", $name);
		if (gws_noicon($att)) {
			$tpl = $gwbbcode_tpl['noicon'];
		} else {
			$tpl = $gwbbcode_tpl['icon'];
		}
		$tpl .= $gwbbcode_tpl['skill'];

		// What adrenaline/energy/energyregen is required?
		$required = Array();
		if ($recharge != 0)
			$required[] = infuse_values($gwbbcode_tpl['requirement'], Array(
				'type' => 'rech',
				'value' => $recharge
			));

		// Format casting time
		if ($casting != 0) {
			switch ($casting) {
				case 0.25:
					$casting_frac = '&frac14;';
					break;
				case 0.50:
					$casting_frac = '&frac12;';
					break;
				case 0.75:
					$casting_frac = '&frac34;';
					break;
				default:
					$casting_frac = $casting;
			}
			// Handle fast casting
			$fast_casting = calc_fastcasting($attr_list, $type, $casting, $profession);
			if ($fast_casting && $fast_casting != $casting) {
				$fast_casting = sprintf('%.1f', $fast_casting);
				$casting_html = infuse_values($gwbbcode_tpl['modified_requirement_value'], Array(
					'initial_value' => $casting_frac,
					'modified_value' => $fast_casting
				));
			} else {
				$casting_html = $casting_frac;
			}
			$required[] = infuse_values($gwbbcode_tpl['requirement'], Array(
				'type' => 'cast',
				'value' => $casting_html
			));
		}

		// Format energy, adrenaline and upkeep
		if ($adrenaline != 0) {
			$required[]    = infuse_values($gwbbcode_tpl['requirement'], Array(
				'type' => 'adre',
				'value' => $adrenaline
			));
			// Handle leadership
			$leader_energy = false; // FIXME: dummied out for now // calc_leadership($name, $attr_list, $type, 0, $pve_only);
			if ($leader_energy) {
				$energy_html = infuse_values($gwbbcode_tpl['modified_requirement_value'], Array(
					'initial_value' => '',
					'modified_value' => $leader_energy
				));
				$required[]  = infuse_values($gwbbcode_tpl['requirement'], Array(
					'type' => 'ener',
					'value' => $energy_html
				));
			} else {
				$energy_html = $energy;
			}
		} else if ($energy != 0) {
			// Handle expertise
			$expert_energy = calc_expertise($name, $attr_list, $type, $energy, $profession, $desc);
			if ($expert_energy && $expert_energy != $energy) {
				$energy_html = infuse_values($gwbbcode_tpl['modified_requirement_value'], Array(
					'initial_value' => $energy,
					'modified_value' => $expert_energy
				));
			}
			// Handle mysticism
			$mystic_energy = calc_mysticism($attr_list, $type, $energy, $profession);
			if ($mystic_energy && $mystic_energy != $energy) {
				$energy_html = infuse_values($gwbbcode_tpl['modified_requirement_value'], Array(
					'initial_value' => $energy,
					'modified_value' => $mystic_energy
				));
			}
			// Handle leadership
			else {
				$leader_energy = false; // FIXME: dummied out for now // calc_leadership($name, $attr_list, $type, $energy, $pve_only);
				if ($leader_energy) {
					$energy_html = infuse_values($gwbbcode_tpl['modified_requirement_value'], Array(
						'initial_value' => $energy,
						'modified_value' => $energy
					));
				} else {
					$energy_html = $energy;
				}
			}
			$required[] = infuse_values($gwbbcode_tpl['requirement'], Array(
				'type' => 'ener',
				'value' => $energy_html
			));
		}
		if ($eregen != 0)
			$required[] = infuse_values($gwbbcode_tpl['requirement'], Array(
				'type' => 'eregen',
				'value' => -$eregen
			));
		$required = implode('', $required);

		// Campaign names
		$campaign_names = Array(
			'Core',
			'Prophecies',
			'Factions',
			'Nightfall',
			'EotN'
		);
		if (isset($campaign_names[$chapter])) {
			$chapter = $campaign_names[$chapter];
		}

		// PvE only
		$pve_only = $pve_only ? '<br/>PvE only' : '';

		// Descriptions variables -> green and adapted to their attribute. (0..12..16 -> green 8)
		$extra_desc = '';
		gws_adapt_description($desc, $extra_desc, $name, $attribute, $attr_list, $type, $pve_only);
		$attr_html  = $attribute == 'No Attribute' ? $gwbbcode_tpl['tpl_skill_no_attr'] : $gwbbcode_tpl['tpl_skill_attr'];
		$extra_desc = empty($extra_desc) ? '' : infuse_values($gwbbcode_tpl['tpl_extra_desc'], Array(
			'extra_desc' => $extra_desc
		));

		// Change the skill aspect if skill is elite
		if (isset($elite) && $elite) {
			$elite_or_normal = 'elite_skill';
			$type            = 'Elite ' . $type;
		} else {
			$elite_or_normal = 'normal_skill';
		}

		// Profession icon
		if ($prof == '?')
			$prof_img = "$gwbbcode_img_path/img_interface/void2.gif";
		else
			$prof_img = "$gwbbcode_img_path/img_interface/$profession.gif";
	}

	// Replace all "{var_name}" by $var_name till there is none to replace (i.e a tag replacement can contain other tags)
	do {
		$prev_tpl = $tpl;

		//"{{skill_description}}" is replaced by $gwbbcode_tpl['skill_description']
		unset($matches);
		preg_match_all("#\{\{(.*?)\}\}#is", $tpl, $matches);
		foreach ($matches[0] as $r => $find) {
			if (isset($gwbbcode_tpl[$matches[1][$r]])) {
				$replace = $gwbbcode_tpl[$matches[1][$r]];
				$tpl     = str_replace($find, $replace, $tpl);
			}
		}
		
		//"{desc}" is replaced by $desc
		unset($matches);
		preg_match_all("#\{(.*?)\}#is", $tpl, $matches);
		foreach ($matches[0] as $r => $find) {
			$replace = ${$matches[1][$r]};
			$tpl     = str_replace($find, $replace, $tpl);
		}
	} while ($prev_tpl != $tpl);

	return $tpl;
}




/***************************************************************************
 * HELPER DATABASE FUNCTIONS
 ***************************************************************************/

// Database function 1:
// Returns either the id of a $skill_name, or false
// Also works if $skill_name is an abbreviation (within reason)
function gws_skill_id($skill_name) {
	$ret = false;
	// Handle abbreviations
	static $abbr_db = Array();
	$name_id = preg_replace('|[\'"!]|', '', strtolower($skill_name));
	if (empty($abbr_db)) {
		if (($abbr_db = @load(SKILLABBRS_PATH)) === false) {
			die("Missing abbreviation database.");
		}
	}
	if (isset($abbr_db[$name_id])) {
		$name_id = preg_replace('|[\'"!]|', '', strtolower($abbr_db[$name_id]));
	}

	// Load the name to id listing from a file (only once)
	static $list = Array();
	if (empty($list)) {
		if (($list = @load(SKILLNAMES_PATH)) === false) {
			die("Missing skillname database.");
		}
	}

	$name_id = preg_replace('|[\'"!]|', '', strtolower($name_id));
	if (isset($list[$name_id])) {
		$ret = $list[$name_id];
	}
	// Check if name could be a partial match
	else if (strlen($name_id) >= 4) {
		$name_id_length = strlen($name_id);
		foreach ($list as $name => $id) {
			if ($name_id == substr($name, 0, $name_id_length)) {
				$ret = $id;
				break;
			}
		}
	}
	return $ret;
}

// Database function 2:
// Return a list of skill names and corresponding id
function gws_skill_id_list() {
	// Load the name to id listing from a file (only once)
	static $list = Array();
	if (empty($list)) {
		if (($list = @load(SKILLNAMES_PATH)) === false) {
			die("Missing skillname database.");
		}
	}
	return $list;
}

// Database function 3:
// Returns either the skill information array of a $skill_id, or false
function gws_details($skill_id) {
		//(Re)load skill list (can't have two in memory, it'd be too big)
		static $skill_list = Array();
		if ( !file_exists(SKILLS_PATH) ) {
			return false;
		}
		$skill_list = load(SKILLS_PATH);
		
		$ret = isset($skill_list[$skill_id]) ? $skill_list[$skill_id] : false;
	return $ret;
}




/***************************************************************************
 * HELPER CALCULATION FUNCTIONS - PROFESSIONS
 ***************************************************************************/

// Calculation function 1:
// Returns values of the prof attribute of an attribute list (string $att), or false
// gws_build_profession(' prof=W/E tactics=16')  ===  Array('primary' => 'W', 'secondary' => 'E', 'professions => 'W/E')
function gws_build_profession($att) {
	if (preg_match('|prof=(([^/ ]+)/([^ ]+))|', $att, $reg)) {
		return Array(
			'primary' => $reg[2],
			'secondary' => $reg[3],
			'professions' => $reg[1]
		);
	} else if (preg_match('|prof=([^/ ]+)|', $att, $reg)) {
		return Array(
			'primary' => $reg[1],
			'secondary' => '?',
			'professions' => $reg[1]
		);
	} else {
		return false;
	}
}

// FIXME - RITUALIST EXCEPTION LOOKS IDIOTIC. WONDER IF IT TRIES TO MATCH RANGER INTO THE RITUALIST BIT? SURELY WE CAN CODE THIS BETTER.
// Calculation function 2:
// Returns the full profession name of a partial one. Returns 'No profession' if no match is found
function gws_prof_name($profession) {
	// Look for a profession name corresponding to $profession
	static $p = Array('E' => 'Elementalist', 'Me' => 'Mesmer', 'Mo' => 'Monk', 'N' => 'Necromancer', 'R' => 'Ranger', 'W' => 'Warrior', 'A' => 'Assassin', 'Rt' => 'Ritualist', 'D' => 'Dervish', 'P' => 'Paragon', '?' => 'No profession');
	$profession = strtolower($profession);

	// Ritualist exception
	if ($profession == 'rt')
		return $p['Rt'];

	foreach ($p as $prof)
		if (strpos(strtolower($prof), $profession) === 0)
			return $prof;

	// No corresponding profession was found
	return 'No profession';
}

// Calculation function 3:
// Returns the profession abbreviation of a partial one. Returns '?' if no match is found
function gws_profession_abbr($profession) {
	static $p = Array('E' => 'Elementalist', 'Me' => 'Mesmer', 'Mo' => 'Monk', 'N' => 'Necromancer', 'R' => 'Ranger', 'W' => 'Warrior', 'A' => 'Assassin', 'Rt' => 'Ritualist', 'D' => 'Dervish', 'P' => 'Paragon', '?' => 'No profession');
	return array_search(gws_prof_name($profession), $p);
}




/***************************************************************************
 * HELPER CALCULATION FUNCTIONS - BUILD NAMES
 ***************************************************************************/

// FIXME - THINK THIS IS REFERRED TO FROM SMARTY TEMPLATE GWBBCODE.TPL, BUT NOT SURE IT WORKS AS INTENDED.
// Calculation function 4:
// Returns value of the name attribute of an attribute list (string $att), or ''
// gws_build_name(' prof=W/E name="Shock Warrior"')  ===  'Shock Warrior'
function gws_build_name($att) {
	return preg_match('|name=\\"([^\"]+)\\"|', $att, $reg) ? $reg[1] : '';
}




/***************************************************************************
 * HELPER CALCULATION FUNCTIONS - ATTRIBUTES
 ***************************************************************************/

// Calculation function 5:
// Returns the full attribute name of a partial or full one. Returns false if no match is found
function gws_attr_name($attr) {
	static $attribute_list = array('airmagic' => 'Air Magic', 'earthmagic' => 'Earth Magic', 'energystorage' => 'Energy Storage', 'firemagic' => 'Fire Magic', 'watermagic' => 'Water Magic', 'dominationmagic' => 'Domination Magic', 'fastcasting' => 'Fast Casting', 'illusionmagic' => 'Illusion Magic', 'inspirationmagic' => 'Inspiration Magic', 'divinefavor' => 'Divine Favor', 'healingprayers' => 'Healing Prayers', 'protectionprayers' => 'Protection Prayers', 'smitingprayers' => 'Smiting Prayers', 'bloodmagic' => 'Blood Magic', 'curses' => 'Curses', 'deathmagic' => 'Death Magic', 'soulreaping' => 'Soul Reaping', 'beastmastery' => 'Beast Mastery', 'expertise' => 'Expertise', 'marksmanship' => 'Marksmanship', 'wildernesssurvival' => 'Wilderness Survival', 'axemastery' => 'Axe Mastery', 'hammermastery' => 'Hammer Mastery', 'strength' => 'Strength', 'swordsmanship' => 'Swordsmanship', 'tactics' => 'Tactics', 'criticalstrikes' => 'Critical Strikes', 'daggermastery' => 'Dagger Mastery', 'deadlyarts' => 'Deadly Arts', 'shadowarts' => 'Shadow Arts', 'spawningpower' => 'Spawning Power', 'channelingmagic' => 'Channeling Magic', 'communing' => 'Communing', 'restorationmagic' => 'Restoration Magic', 'spearmastery' => 'Spear Mastery', 'command' => 'Command', 'motivation' => 'Motivation', 'leadership' => 'Leadership', 'scythemastery' => 'Scythe Mastery', 'windprayers' => 'Wind Prayers', 'earthprayers' => 'Earth Prayers', 'mysticism' => 'Mysticism', 'kurzickrank' => 'Kurzick rank', 'luxonrank' => 'Luxon rank', 'sunspearrank' => 'Sunspear rank', 'lightbringerrank' => 'Lightbringer rank', 'asurarank' => 'Asura rank', 'deldrimorrank' => 'Deldrimor rank', 'ebonvanguardrank' => 'Ebon Vanguard rank', 'nornrank' => 'Norn rank');

	$attr = strtolower(str_replace('_', '', $attr));
	if (empty($attr)) {
		return false;
	}

	foreach ($attribute_list as $long_attr => $attribute) {
		if (strpos($long_attr, $attr) === 0) {
			return $attribute;
		}
	}
	// Otherwise
	return false;
}

// Calculation function 6:
// Returns the attribute name abbreviation of a partial one. Returns false if no match is found
function gws_attribute_name($attribute) {
	static $attribute_list = Array('air' => 'airmagic', 'ear' => 'earthmagic', 'ene' => 'energystorage', 'fir' => 'firemagic', 'wat' => 'watermagic', 'dom' => 'dominationmagic', 'fas' => 'fastcasting', 'ill' => 'illusionmagic', 'ins' => 'inspirationmagic', 'div' => 'divinefavor', 'hea' => 'healingprayers', 'pro' => 'protectionprayers', 'smi' => 'smitingprayers', 'blo' => 'bloodmagic', 'cur' => 'curses', 'dea' => 'deathmagic', 'sou' => 'soulreaping', 'bea' => 'beastmastery', 'exp' => 'expertise', 'mar' => 'marksmanship', 'wil' => 'wildernesssurvival', 'axe' => 'axemastery', 'ham' => 'hammermastery', 'str' => 'strength', 'swo' => 'swordsmanship', 'tac' => 'tactics', 'cri' => 'criticalstrikes', 'dag' => 'daggermastery', 'dead' => 'deadlyarts', 'sha' => 'shadowarts', 'spa' => 'spawningpower', 'cha' => 'channelingmagic', 'com' => 'communing', 'res' => 'restorationmagic', 'spe' => 'spearmastery', 'comma' => 'command', 'mot' => 'motivation', 'lea' => 'leadership', 'scy' => 'scythemastery', 'win' => 'windprayers', 'earthp' => 'earthprayers', 'mys' => 'mysticism', 'kur' => 'kurzickrank', 'lux' => 'luxonrank', 'sun' => 'sunspearrank', 'lig' => 'lightbringerrank', 'asu' => 'asurarank', 'del' => 'deldrimorrank', 'ebo' => 'ebonvanguardrank', 'nor' => 'nornrank');

	$attribute = strtolower(str_replace(' ', '', $attribute));
	if (!empty($attribute)) {
		foreach ($attribute_list as $attr => $long_attr) {
			if (strpos($long_attr, $attribute) === 0) {
				return $attr;
			}
		}
	}
	// Otherwise
	return false;
}

// Calculation function 7:
// Returns the main attribute of a full profession name; false in case of 'No profession' or erroneous profession name
function gws_main_attribute($profession) {
	static $main_attributes = Array('Elementalist' => 'Energy Storage', 'Mesmer' => 'Fast Casting', 'Monk' => 'Divine Favor', 'Necromancer' => 'Soul Reaping', 'Ranger' => 'Expertise', 'Warrior' => 'Strength', 'Assassin' => 'Critical Strikes', 'Ritualist' => 'Spawning Power', 'Paragon' => 'Leadership', 'Dervish' => 'Mysticism');
	return isset($main_attributes[$profession]) ? $main_attributes[$profession] : false;
}

// Calculation function 8:
// Returns true only if the partial attribute is a pve attribute, else false
function gws_pve_attr($attribute) {
	static $pve_attr_list = Array('kur', 'lux', 'sun', 'lig', 'asu', 'del', 'ebo', 'nor');
	return in_array(gws_attribute_name($attribute), $pve_attr_list);
}

// Calculation function 9:
// Returns an attribute list (string) cleaned of the prof, name and desc attributes
function gws_attributes_clean($att) {
	$att = preg_replace('|prof=[^ \]]*|', '', $att);
	$att = preg_replace('|name=\\"[^"]+\\"|', '', $att);
	$att = preg_replace('|desc=\\"[^"]+\\"|', '', $att);
	return trim($att);
}

// Calculation function 10:
// Returns a list of attributes and their string values (e.g "12+1+3")
function attribute_list_raw($att) {
	$list = Array();
	$clean_attr = explode(' ', gws_attributes_clean($att));
	foreach ($clean_attr as $value) {
		$value = explode('=', $value);
		// Only one '='?
		if (count($value) == 2) {
			$attr_name = gws_attr_name($value[0]);
			// Valid attribute name?
			if ($attr_name !== false) {
				$attr_value = preg_replace('@[^0-9+-]@', '', $value[1]);
				// Valid attribute value?
				if (isset($attr_value) && $attr_value !== false) {
					// Alright record it
					$list[$attr_name] = $attr_value;
				}
			}
		}
	}
	return $list;
}

// Calculation function 11:
// Returns a list of attributes and their numeric values (e.g "12+1+3" will return 16)
function attribute_list($att) {
	$list = attribute_list_raw($att);
	foreach ($list as $attr_name => $attr_lvl) {
		$list[$attr_name] = array_sum(explode('+', $attr_lvl));
	}
	return $list;
}

// Calculation function 12:
// Return the number of necessary attribute points for given attribute levels, or false
function attr_points($attr_list) {
	static $point_list = Array(0, 1, 2, 3, 4, 5, 6, 7, 9, 11, 13, 16, 20);
	$points = 0;
	foreach ($attr_list as $level) {
		if (!isset($point_list[$level])) {
			return false;
		}
		$points += $point_list[$level];
	}
	return $points;
}




/***************************************************************************
 * HELPER CALCULATION FUNCTIONS - DESCRIPTIONS
 ***************************************************************************/

// Calculation function 13:
// Returns a description after adapting it's variables to an attribute value
function gws_adapt_description(&$desc, &$extra_desc, $name, $attribute, $attr_list, $type, $pve_only) {
	// Take into account Divine Favor
	add_divine_favor($desc, $extra_desc, $attr_list, $name);

	// Take into account Strength
	if (!empty($attr_list['Strength']) && strpos($type, 'Attack') !== false) {
		$extra_desc = 'This attack skill has <b>' . $attr_list['Strength'] . '</b>% armor penetration.';
	}

	// Put some green around the fork
	$desc = preg_replace_callback('|([0-9]+\.\.[0-9]+)|', 'fork_replace', $desc);

	// For PvP skills,
	if (!$pve_only) {
		// Adapt the fork to the build's attribute level..
		if (isset($attr_list[$attribute])) {
			$attr_lvl = $attr_list[$attribute];
			if (preg_match_all('|([0-9]+)\.\.([0-9]+)|', $desc, $regs, PREG_SET_ORDER)) {
				foreach ($regs as $fork) {
					list($all, $val_0, $val_15) = $fork;
					$pos = strpos($desc, $all);
					$desc = substr_replace($desc, fork_val($val_0, $val_15, $attr_lvl), $pos, strlen($all));
				}
			}
		}
		// or show its 0..12..16 values
		else {
			$desc = preg_replace_callback('|([0-9]+)\.\.([0-9]+)|', 'desc_replace', $desc);
		}
	}
	// For PvE skills
	else {
		// Adapt the fork to the build's attribute level..
		if (isset($attr_list[$attribute])) {
			$attr_lvl = $attr_list[$attribute];
			if (preg_match_all('|([0-9]+)\.\.([0-9]+)|', $desc, $regs, PREG_SET_ORDER)) {
				foreach ($regs as $fork) {
					list($all, $val_0, $val_10) = $fork;
					$pos = strpos($desc, $all);
					$desc = substr_replace($desc, fork_val_pveonly($val_0, $val_10, $attr_lvl), $pos, strlen($all));
				}
			}
		}
		// or show its 0..10 values (no action required)
	}

	// Specify a Spirit health and armor
	$desc = add_spirit_health($desc, $attr_list);

	// Specify additional weapon spell duration
	$desc = calc_weapon_duration($desc, $attr_list, $type);
}

// Calculation function 14:
// Put some green around the fork
function fork_replace($reg) {
	list($all, $fork) = $reg;
	return "<span class=\"variable\">$fork</span>"; // Make sure to adapt add_spirit_health() if you change this
}

// Calculation function 15:
// Return value at a given attribute level
function fork_val($val_0, $val_15, $attr_lvl) {
	return $val_0 + round(($val_15 - $val_0) * $attr_lvl / 15);
}

// Calculation function 16:
// Replace a 0..15 fork by a 0..12..16 one
function desc_replace($reg) {
	list($all, $val_0, $val_15) = $reg;
	return $val_0 . '..' . fork_val($val_0, $val_15, 12) . '..' . fork_val($val_0, $val_15, 16);
}

// Calculation function 17:
// Return value at a given attribute level for a pve only skill
function fork_val_pveonly($val_0, $val_10, $attr_lvl) {
	return $val_0 + round(($val_10 - $val_0) * $attr_lvl / 10);
}

// Calculation function 18:
// Return the real energy cost of a skill depending on its type and level of Expertise
//  Note: There is not a field in databases/skill_db.php containing whether the skill is a touch skill or not.
function calc_expertise($name, $attr_list, $type, $energy, $profession, $desc) {
	if (isset($attr_list['Expertise']) && $attr_list['Expertise'] > 0 && ($profession == 'Ranger' || strpos($type, 'Attack') !== false || strpos($type, 'Ritual') !== false || $name == 'Lift Enchantment' || (preg_match('@touch@i', $desc) && !preg_match('@touch skills@i', $desc)))) {
		return round($energy * (1.0 - 0.04 * $attr_list['Expertise']));
	}

	// Otherwise
	return false;
}

// Calculation function 19:
// Return the real energy cost of a skill depending on its type, profession and level of Mysticism
function calc_mysticism($attr_list, $type, $energy, $profession) {
	if (isset($attr_list['Mysticism']) && $attr_list['Mysticism'] > 0 && $profession == 'Dervish' && strpos($type, 'Enchantment') !== false ) {
		return round($energy * (1.0 - 0.04 * $attr_list['Mysticism']));
	}

	// Otherwise
	return false;
}

// Calculation function 20:
// Return the energy fork gained from using a skill depending on its type and level of Leadership
function calc_leadership($name, $attr_list, $type, $energy, $pve_only) {
	static $leadership_skills = Array(0 => Array('"It\'s just a flesh wound."', '"Make Your Time!"', '"Never Give Up!"', '"The Power Is Yours!"', '"Fear Me!"', '"I Will Avenge You!"', '"None Shall Pass!"', '"Retreat!"', '"On Your Knees!"', '"Coward!"', '"To the Limit!"', '"You Will Die!"', '"Victory is Mine!"', '"You\'re All Alone!"'), 1 => Array('"Brace Yourself!"', '"Can\'t Touch This!"', '"Find Their Weakness!"', '"Help Me!"', '"For Great Justice!"', '"I Will Survive!"', 'Call of Haste', 'Call of Protection', 'Otyugh\'s Cry', 'Predatory Bond', 'Symbiotic Bond'), 2 => Array('Strike as One'));
	// FIXME - Till we know more about PvE Chants and Shouts, ignore them
	if ($pve_only) {
		return false;
	}
	if (isset($attr_list['Leadership']) && $attr_list['Leadership'] > 1 && ($type == 'Chant' || $type == 'Shout')) {
		$max_energy = 8;
		foreach ($leadership_skills as $skill_energy => $skill_list) {
			if (in_array($name, $skill_list)) {
				$max_energy = $skill_energy;
				break;
			}
		}
		$max_energy = min($max_energy, floor($attr_list['Leadership'] / 2));
		if ($max_energy > 1) {
			return '+1..' . $max_energy;
		} else {
			return '+' . $max_energy;
		}
	}

	// Otherwise
	return false;
}

// Calculation function 21:
// Return the real cast time of a skill depending on its type and level of Fast Casting
function calc_fastcasting($attr_list, $type, $casting, $profession) {
	if ((isset($attr_list['Fast Casting']) && $attr_list['Fast Casting'] > 0) && ($profession == 'Mesmer' || $casting >= 2)) {
		if (strpos($type, 'Spell') !== false) {
			return $casting * pow(2.0, (($attr_list['Fast Casting'] * -1.0) / 15.0));
		} else if ($type == 'Signet') {
			return $casting * (1.0 - ($attr_list['Fast Casting'] * 0.03));
		}
	}

	// Otherwise
	return false;
}

// Calculation function 22:
// Return the additional weapon spell duration depending on level of Spawning Power
function calc_weapon_duration($desc, $attr_list, $type) {
	if ($type == 'Weapon Spell' && isset($attr_list['Spawning Power']) && $attr_list['Spawning Power'] > 0) {
		if (preg_match('@(for (?:<span class="variable">)?)([0-9]+)((?:</span>)? second)@i', $desc, $reg)) {
			$base_duration       = $reg[2];
			$additional_duration = round($reg[2] * $attr_list['Spawning Power'] * 0.04, 1);
			$desc                = str_replace($reg[0], $reg[1] . $reg[2] . '<span class="expert">(+' . $additional_duration . '&#041;</span>' . $reg[3], $desc);
		}
	}
	return $desc;
}

// Calculation function 23:
// Return a description of the real effect of a skill taking into account Divine Favor
function add_divine_favor(&$desc, &$extra_desc, $attr_list, $name) {
	if (isset($attr_list['Divine Favor']) && $attr_list['Divine Favor'] > 0) {
		if (preg_match('/\{((div)|(target)|(self))\}/', $desc, $reg)) {
			$heal_type = $reg[1];
			$div_heal  = round(3.2 * $attr_list['Divine Favor']);
			switch ($heal_type) {
				case 'div':
					if ($name === 'Healing Touch')
						$desc = str_replace('{div}', '<span class="expert">&nbsp;<b>(+' . (2 * $div_heal) . '&#041;</b></span>', $desc);
					else
						$desc = str_replace('{div}', '<span class="expert">&nbsp;<b>(+' . $div_heal . '&#041;</b></span>', $desc);
					break;
				case 'target':
					$extra_desc = 'Target ally gets healed for <b>' . $div_heal . '</b> Health.';
					break;
				case 'self':
					$extra_desc = 'You get healed for <b>' . $div_heal . '</b> Health.';
					break;
			}
			$desc = preg_replace('/\{((target)|(self))\}/', '', $desc);
		}
	}

	// Remove remaining unused tags
	else {
		$desc = str_replace('{div}', '', $desc);
		$desc = str_replace('{target}', '', $desc);
		$desc = str_replace('{self}', '', $desc);
	}
}

// Calculation function 24:
// Return a description specifying how much health and armor does a Spirit have
function add_spirit_health($desc, $attr_list) {

	// Get Spirit's level
	if (preg_match('@Create a level <span class="variable">([0-9]+)</span> Spirit@', $desc, $reg)) {
		$spirit_level = $reg[1];

		// Get Binding Ritual level
		$spawning_level = isset($attr_list['Spawning Power']) ? $attr_list['Spawning Power'] : 0;

		// Compute the Spirit's Health and armor
		$spirit_health  = $spirit_level * 20; // Thanks to GuildWiki.org for this equation
		$spawning_bonus = '';
		if ($spawning_level > 0) {
			$spawning_health = round($spirit_health * ($spawning_level * 0.04));
			$spawning_bonus  = ' (+' . $spawning_health . '&#041;';
		}
		$spirit_armor = round((88 / 15 * $spirit_level) + 3);

		// Add Spirit's health to description
		$desc = preg_replace('@Create a level <span class="variable">[0-9]+</span> Spirit@', '${0} <span class="expert"> with <b>' . $spirit_health . $spawning_bonus . '</b> Health and <b>' . $spirit_armor . '</b> armor</span>', $desc);
	}

	return $desc;
}

// Calculation function 25:
// Return a skill description enriched with a div tag depending on the specified effect ('{target}', '{self}' or '{div}')
function add_div_tag($effect, $desc) {
	if ($effect == 'target' || $effect == 'self') {
		$desc .= '{' . $effect . '}';
	} else {
		$new_desc = preg_replace('@([0-9]+\\.\\.[0-9]+)( Health)@', '$1{div}$2', $desc, 1);
		if ($desc != $new_desc) {
			$desc = $new_desc;
		} else {
			//'Health' isn't specified :(
			$desc = preg_replace('@([0-9]+\\.\\.[0-9]+)@', '$1{div}', $desc, 1);
		}
	}
	return $desc;
}

// Calculation function 26:
// Return the skill description enriched with a divine favor tags ('{target}', '{self}' or '{div}')
function desc_with_div_tag($skill) {
	$desc = $skill['desc'];
	// Clean up the description
	$desc = str_replace('{target}', '', str_replace('{self}', '', str_replace('{div}', '', $desc)));

	// Add the appropriate div tag
	if ($skill['prof'] != 'Mo' || strpos($skill['type'], 'Spell') === false) {
		// No div fav bonus for you
	} else if (in_array($skill['name'], Array(
			'Kirin\'s Wrath',
			'Symbol of Wrath',
			'Amity',
			'Light of Dwayna',
			'Divine Spirit',
			'Healer\'s Boon',
			'Spell Shield',
			'Healer\'s Covenant',
			'Martyr',
			'Smiter\'s Boon'
		))) {
		$desc = add_div_tag('self', $desc);
	} else if ($skill['name'] == 'Infuse Health') {
		$desc = add_div_tag('target', $desc);
	} else if ($skill['name'] == 'Pacifism' || strpos($desc, 'to life') !== false || preg_match('@^Resurrect@', $desc)) {
		// No div fav bonus for you
	} else if (preg_match('@target ally for [0-9]+\\.\\.[0-9]+ Health@i', $desc)) {
		$desc = add_div_tag('div', $desc);
	} else if (preg_match('@target (other )?ally is healed for [0-9]+\\.\\.[0-9]+ Health@i', $desc)) {
		$desc = add_div_tag('div', $desc);
	} else if (strpos($desc, 'target ally') !== false) {
		$desc = add_div_tag('target', $desc);
	} else if (preg_match('@party|adjacent creatures|you use|you cast@', $desc)) {
		$desc = add_div_tag('self', $desc);
	} else if ($skill['attribute'] == 'Smiting Prayers') {
		// No div fav bonus for you
	} else if (preg_match('@ally for [0-9]+\\.\\.[0-9]+ Health@', $desc)) {
		$desc = add_div_tag('div', $desc);
	} else if (strpos($desc, 'adjacent') !== false) {
		$desc = add_div_tag('target', $desc);
	} else if ($skill['attribute'] == 'Healing Prayers' && preg_match('@healed for [0-9]+\\.\\.[0-9]+@', $desc)) {
		$desc = add_div_tag('div', $desc);
	} else {
		$desc = add_div_tag('target', $desc);
	}
	return $desc;
}

// Calculation function 27:
// Return $text with each "{var_name}" replaced by $values['var_name']
function infuse_values($text, $values) {
	foreach ($values as $name => $value) {
		$text = str_replace('{' . $name . '}', $value, $text);
	}
	return $text;
}

// Calculation function 28:
function getSkillIdPvP($pve) {
	global $pveSkillIds;
	return (isset($pveSkillIds[$pve]) ? $pveSkillIds[$pve] : $pve);
}

// Calculation function 29:
function getSkillIdPvE($pvp) {
	global $pvpSkillIds;
	return (isset($pvpSkillIds[$pvp]) ? $pvpSkillIds[$pvp] : $pvp);
}



/***************************************************************************
 * HELPER CALCULATION FUNCTIONS - MISCELLANEOUS
 ***************************************************************************/

// Calculation function 30:
// Returns true if $att specifies that the skill should be rendered as text
function gws_noicon($att) {
	return strstr($att, 'noicon') !== false;
}

// Calculation function 31:
// Restores html entities to characters, except for '<'
function html_safe_decode($text) {
	return str_replace('<', '&lt;', html_entity_decode($text));
}

// Calculation function 32:
// Int to bin on $bit_size bits
function int2bin($int, $bit_size) {
	$bin = strrev(base_convert($int, 10, 2));
	if ($bit_size < strlen($bin)) {
		return false;
	}
	// Otherwise
	return $bin . str_repeat('0', $bit_size - strlen($bin));
}

// Calculation function 33:
// Load a var from a file
function load($filename) {
	if (!file_exists($filename))
		return false;
	else
		return require($filename);
}

// Calculation function 34:
// Organize skill by elite, profession, attribute and name (used by [rand seed="x"])
function skill_sort_cmp($a, $b) {
	static $prof_ids = Array('Warrior', 'Ranger', 'Monk', 'Necromancer', 'Mesmer', 'Elementalist', 'Assassin', 'Ritualist', 'Paragon', 'Dervish', 'No profession');
	if ($a['elite'] == $b['elite']) {
		$a['prof_id'] = array_search($a['profession'], $prof_ids);
		$b['prof_id'] = array_search($b['profession'], $prof_ids);
		if ($a['prof_id'] == $b['prof_id']) {
			if ($a['attribute'] == 'No Attribute') {
				$a['attribute'] = 'ZZZ';
			}
			if ($b['attribute'] == 'No Attribute') {
				$b['attribute'] = 'ZZZ';
			}

			// FIXME - Investigate why this is present. Probably minimal impact if removed, but would be nice to understand it.
			// Special case: Dervish
			$d_attributes = Array(
				'Earth Prayers',
				'Wind Prayers',
				'Mysticism',
				'Scythe Mastery',
				'ZZZ'
			);
			if ($a['prof'] == 'D') {
				$a['attribute'] = array_search($a['attribute'], $d_attributes);
				$b['attribute'] = array_search($b['attribute'], $d_attributes);
			}

			if (preg_replace('|[\'! ]|', '', strtolower($a['attribute'])) == preg_replace('|[\'! ]|', '', strtolower($b['attribute']))) {
				if (preg_replace('|[\'!]|', '', strtolower($a['name'])) == preg_replace('|[\'!]|', '', strtolower($b['name']))) {
					return 0;
				}
				return (preg_replace('|[\'!]|', '', strtolower($a['name'])) < preg_replace('|[\'!]|', '', strtolower($b['name']))) ? -1 : 1;
			}
			return (preg_replace('|[\'! ]|', '', strtolower($a['attribute'])) < preg_replace('|[\'! ]|', '', strtolower($b['attribute']))) ? -1 : 1;
		}
		return ($a['prof_id'] < $b['prof_id']) ? -1 : 1;
	}
	return ($a['elite'] > $b['elite']) ? -1 : 1;
}




/***************************************************************************
 * SMARTY TEMPLATE FUNCTIONS
 ***************************************************************************/
/*
 * Modification of Nathan Codding's load_bbcode_template.
 **/

// Smarty template function 1:
// Loads gwbbcode templates from the gwbbcode.tpl file
// Creates an array, keys are bbcode names like "b_open" or "url", values are the associated template.
// Probably pukes all over the place if there's something really screwed with the gwbbcode.tpl file.
function load_gwbbcode_smarty_template() {
	$tpl_array = file(TEMPLATE_PATH);

	// Trim each line
	$tpl = '';
	foreach ($tpl_array as $line) {
		$tpl .= trim($line);
	}

	// Replace \ with \\ and then ' with \'.
	$tpl = str_replace('\\', '\\\\', $tpl);
	$tpl = str_replace('\'', '\\\'', $tpl);

	// Strip newlines.
	$tpl = str_replace("\n", '', $tpl);

	// Turn template blocks into PHP assignment statements for the values of $gwbbcode_tpls..
	$tpl = preg_replace('#<!-- BEGIN (.*?) -->(.*?)<!-- END (.*?) -->#', "\n" . '$gwbbcode_tpls[\'\\1\'] = clean_tpl(\'\\2\');', $tpl);

	$gwbbcode_tpls = array();

	eval($tpl);

	return $gwbbcode_tpls;
}

// Smarty template function 2:
// Return a string after removing all its whitespaces at begining of lines, and its newlines
function clean_tpl($tpl) {
	return preg_replace('@[\r\n][ ]*@', '', $tpl);
}




/***************************************************************************
 * GW TEMPLATE CONVERSION FUNCTIONS
 ***************************************************************************/

// GW template conversion function 1:
function binval($bin) {
	return intval(base_convert(strrev($bin), 2, 10));
}

// GW template conversion function 2:
// Return a binary string based on the $text template_id
function template_to_bin($text) {
	static $conv_table = Array('A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15, 'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23, 'Y' => 24, 'Z' => 25, 'a' => 26, 'b' => 27, 'c' => 28, 'd' => 29, 'e' => 30, 'f' => 31, 'g' => 32, 'h' => 33, 'i' => 34, 'j' => 35, 'k' => 36, 'l' => 37, 'm' => 38, 'n' => 39, 'o' => 40, 'p' => 41, 'q' => 42, 'r' => 43, 's' => 44, 't' => 45, 'u' => 46, 'v' => 47, 'w' => 48, 'x' => 49, 'y' => 50, 'z' => 51, '0' => 52, '1' => 53, '2' => 54, '3' => 55, '4' => 56, '5' => 57, '6' => 58, '7' => 59, '8' => 60, '9' => 61, '+' => 62, '/' => 63);
	$ret = '';
	foreach (preg_split('//', trim($text), -1, PREG_SPLIT_NO_EMPTY) as $char) {
		// Handle invalid characters
		if (!isset($conv_table[$char])) {
			return false;
		}
		$bin = strrev(base_convert($conv_table[$char], 10, 2));
		$ret .= $bin . str_repeat('0', 6 - strlen($bin));
	}
	return $ret;
}

// GW template conversion function 3:
// Return gwbbcode based on the $text template id
function template_to_gwbbcode($text) {
	static $prof_ids = Array('?', 'W', 'R', 'Mo', 'N', 'Me', 'E', 'A', 'Rt', 'P', 'D');
	static $attr_ids = Array('fas', 'ill', 'dom', 'ins', 'blo', 'death', 'sou', 'cur', 'air', 'ear', 'fir', 'wat', 'ene', 'hea', 'smi', 'pro', 'div', 'str', 'axe', 'ham', 'swo', 'tac', 'bea', 'exp', 'wil', 'mar', 29 => 'dag', 'dead', 'sha', 'com', 'res', 'cha', 'cri', 'spa', 'spe', 'comma', 'mot', 'lea', 'scy', 'win', 'earthp', 'mys');

	// Handle the [build name;build code] syntaxe
	$build_name = '';
	if (preg_match('@([^][]+);([^];]+)@', $text, $reg)) {
		$build_name = $reg[1];
		$text       = $reg[2];
	}

	$bin = template_to_bin($text);
	if ($bin === false) {
		return 'Invalid characters found';
	}

	// Handle the new format (i.e leading '0111')
	if (preg_match('@^0111@', $bin)) {
		$bin = substr($bin, 4);
	}

	$ret = '';
	if (!preg_match('@^([01]{6})([01]{4})([01]{4})([01]{4})([01]{4})@', $bin, $reg)) {
		return 'Couldn\'t read professions nor attribute count and size';
	}
	$bin = preg_replace('@^([01]{6})([01]{4})([01]{4})([01]{4})([01]{4})@', '', $bin);

	// Make sure template begins with '000000'
	if ($reg[1] != '000000') {
		return 'First 6 bits are invalid';
	}

	// Primary profession
	$primary_id = binval($reg[2]);
	if (!isset($prof_ids[$primary_id])) {
		return 'Invalid primary profession';
	}
	$primary = $prof_ids[$primary_id];

	// Secondary profession
	$secondary_id = binval($reg[3]);
	if (!isset($prof_ids[$secondary_id])) {
		return 'Invalid secondary profession';
	}
	$secondary = $prof_ids[$secondary_id];

	// Create prof=?/?
	$ret .= "[build prof=$primary/$secondary";

	// Add clean build name if any
	//  Fix by KillsLess to move syntax options out of the name
	if (!empty($build_name)) {
		$syntax_options = array(
			"nosave",
			"box"
		);
		foreach ($syntax_options as $option) {
			if (preg_match("/ $option/i", $build_name, $option_reg)) {
				$ret .= $option_reg[0];
				$build_name = preg_replace("/ $option/i", '', $build_name);
			}
		}
		$ret .= ' name="' . str_replace('"', "''", $build_name) . '"';
	}

	// Manage attributes
	$attr_count = binval($reg[4]);
	$attr_size  = 4 + binval($reg[5]);
	for ($i = 0; $i < $attr_count; $i++) {
		if (!preg_match('@^([01]{' . $attr_size . '})([01]{4})@', $bin, $reg2)) {
			return 'Couldn\'t read attribute id and value';
		}
		$bin = preg_replace('@^([01]{' . $attr_size . '})([01]{4})@', '', $bin);

		// Attribute name
		$attr_id = binval($reg2[1]);
		if (!isset($attr_ids[$attr_id])) {
			return "Invalid attribute id: $attr_id";
		}
		$attr_name = $attr_ids[$attr_id];

		// Attribute value
		$attr_value = binval($reg2[2]);
		if ($attr_value > 12) {
			return 'An attribute value can\'t be higher than 12';
		}

		// Create attr=10
		$ret .= " $attr_name=$attr_value";
	}
	$ret .= ']';

	// Skills
	if (!preg_match('@^([01]{4})@', $bin, $reg2)) {
		return 'Couldn\'t get skill id size';
	}
	$bin = preg_replace('@^([01]{4})@', '', $bin);

	// Skill size
	$skill_size = 8 + binval($reg2[1]);
	for ($i = 0; $i < 8; $i++) {
		if (!preg_match('@^([01]{' . $skill_size . '})@', $bin, $reg2)) {
			return 'Couldn\'t read skill id';
		}
		$bin = preg_replace('@^([01]{' . $skill_size . '})@', '', $bin);

		// Skill name
		$skill_id   = getSkillIdPvP(binval($reg2[1]));
		$skill      = gws_details($skill_id);
		$skill_name = $skill['name'];
		if ($skill === false) {
			$ret .= "[Unknown skill id $skill_id]";
		} else {
			$ret .= '[' . $skill['name'] . ']';
		}
	}
	return $ret . '[/build]';
}

// GW template conversion function 4:
// Return a template string based on the $bin binary string
function bin_to_template($bin) {
	static $conv_table = Array('A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15, 'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23, 'Y' => 24, 'Z' => 25, 'a' => 26, 'b' => 27, 'c' => 28, 'd' => 29, 'e' => 30, 'f' => 31, 'g' => 32, 'h' => 33, 'i' => 34, 'j' => 35, 'k' => 36, 'l' => 37, 'm' => 38, 'n' => 39, 'o' => 40, 'p' => 41, 'q' => 42, 'r' => 43, 's' => 44, 't' => 45, 'u' => 46, 'v' => 47, 'w' => 48, 'x' => 49, 'y' => 50, 'z' => 51, '0' => 52, '1' => 53, '2' => 54, '3' => 55, '4' => 56, '5' => 57, '6' => 58, '7' => 59, '8' => 60, '9' => 61, '+' => 62, '/' => 63);
	$ret = '';
	$bin .= str_repeat('0', 6 - (strlen($bin) % 6));
	while (!empty($bin)) {
		$digit = substr($bin, 0, 6);
		$bin   = substr($bin, 6);
		$ret .= array_search(base_convert(strrev($digit), 2, 10), $conv_table);
	}
	return $ret;
}

?>

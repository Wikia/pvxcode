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

require_once GWBBCODE_ROOT . '/constants.inc.php';

/***************************************************************************
 * GLOBALS
 ***************************************************************************/

// Load the gwbbcode smarty syntax template (*.TPL)
global $gwbbcode_tpl;
if ( !isset( $gwbbcode_tpl ) ) {

	$gwbbcode_tpl = load_gwbbcode_smarty_template();
}

// Load the pvp to pve id conversion array
// Ingame templates must not contain pvp skill ids to be valid.
global $pvp_to_pve_skill_list;
if ( !file_exists( SKILLIDSPVP_PATH ) ) {
	die( "Missing pvp skill id database." );
}
$pvp_to_pve_skill_list = load( SKILLIDSPVP_PATH );

/***************************************************************************
 * MAIN FUNCTION
 ***************************************************************************/

/**
 * Prepares the page and then replaces gwBBCode with HTML
 * This function is directly called by: extension\classes\PvXCode.php
 * @param $text
 * @param bool $build_name
 * @return array|string|string[]|null
 */
function parseGwbbcode( $text, $build_name = false ) {
	// Timer for the gwBBCode parse duration
	$start = microtime( true );

	// 1. Add a build name if not specified. Default to the name provided by PvXCode.php.
	//  This is used in the template download link as the file title.
	if ( !empty( $build_name ) && !preg_match( '#(\[build[^\]]*?) name="[^\]"]+"#isS', $text ) ) {
		$text = preg_replace( '#(\[build )#is', "\\1name=\"$build_name\" ", $text );
	}

	// 2: Replace all '['s inside [pre]..[/pre], or [nobb]..[/nobb] by '&#91;'
	$text = preg_replace_callback( '#\[pre\](.*?)\[\/pre\]#isS', 'pre_replace', $text );
	$text = preg_replace_callback( '#\[nobb\](.*?)\[\/nobb\]#isS', 'pre_replace', $text );

	// 3: Replace all [Random Skill] by [some random skill name] each time the post is rendered
	$text = preg_replace_callback( '#\[Random Skill(.*?)\]#is', 'random_skill_replace', $text );

	// 4: [rand seed=465468 players=2]
	$text = preg_replace_callback( '#\[rand([^\]]*)\]#isS', 'rand_replace', $text );

	// 5: Manage [build=...], or [build_name;template_code]
	$text = preg_replace_callback( '#\[build=([^\]]*)\]\]?(\[/build\])?\r?\n?#isS', 'build_id_replace', $text );
	$text = preg_replace_callback( '#\[(([^]\r\n]+)(;)([^];\r\n]+))\]\r?\n?#isS', 'build_id_replace', $text );

	// 6: Replace all [skill_name] by [skill]skill_name[/skill] if skill_name is a valid skill name
	$text = preg_replace_callback( '#\[(.+)\]#isSU', 'skill_name_replace', $text );

	// 7: Manage [build]...[/build]
	$text = preg_replace_callback( '#\[build ([^\]]*)\](.*?)\[/build\]\r?\n?#isS', 'build_replace', $text );

	// 8: Replace all [skillset=attribute_name@attr_level]
	$text = preg_replace_callback( '#\[(\[?)skillset=(.*?)\]#isS', 'skillset_replace', $text );

	// 9: Manage [skill]...[/skill]
	$text = preg_replace_callback( '#\[skill([^\]]*)\](.*?)\[/skill\]#isS', 'skill_replace', $text );

	// 10: Manage [gwbbcode version]
	$text = preg_replace( '@\[gwbbcode version\]@i', GWBBCODE_VERSION, $text );

	// 11: Manage [gwbbcode runtime]
	if ( preg_match( '@\[gwbbcode runtime\]@i', $text ) !== false ) {
		$text =
			preg_replace(
				'@\[gwbbcode runtime\]@i',
				'Runtime = ' . round( microtime( true ) - $start, 3 ) . ' seconds',
				$text
			);
	}

	return $text;
}


/***************************************************************************
 * HELPER REPLACEMENT FUNCTIONS
 ***************************************************************************/

// Replacement function 1:
// Changes any '[' by '&#91;', hence disabling bbcode
function pre_replace( $reg ) {
	[ $all, $content ] = $reg;

	return str_replace( '[', '&#91;', $content );
}

// Replacement function 2:
// Convert [Random Skill] tags to [a random skill name]
function random_skill_replace( $reg ) {
	[ $all, $att ] = $reg;
	$skill_list = gws_skill_id_list();
	$random_skill_name = array_rand( $skill_list );
	$details = gws_details( $skill_list[$random_skill_name] );

	return '[' . $details['name'] . "$att]";
}

// Replacement function 3:
// Process [rand seed=465468 players=2]
function rand_replace( $reg ) {
	[ $all, $att ] = $reg;
	$att = html_safe_decode( $att );

	// Get the seed
	if ( preg_match( '|seed=([0-9]+)|', $att, $reg ) ) {
		$seed = intval( $reg[1] );
	} else {
		$seed = mt_rand( 1000, 100000 );
	}
	mt_srand( $seed );

	// Get the number of players
	if ( preg_match( '|players=([0-9]+)|', $att, $reg ) ) {
		$players = intval( $reg[1] );
	} else {
		$players = 1;
	}

	// Get PvP ids so we can ignore them
	static $pvpSkillIds = [];
	if ( !file_exists( SKILLIDSPVP_PATH ) ) {
		die( "Missing pvp skill id database." );
	}
	$pvpSkillIds = load( SKILLIDSPVP_PATH );

	// Generate random skills
	// Note: Can't use array_filter then array_rand, because array_rand uses an algorithm that doesn't respect
	// setting the random seed with mt_srand().
	$skills = [];
	$used_ids = [];

	// Initialise variables
	$normal = 0;
	$elite = 0;
	$total = 0;
	$max_normal = ( $players + 1 ) * 14;
	$max_elite = ( $players + 1 ) * 3;
	$max_total = $max_elite + $max_normal;

	// Reject if the limits are excessive.
	if ( $max_total > 200 ) {
		return "Selected input parameters to &#91;rand ...] requested too many skills ($max_total skills requested).";
	}

	// Initialise
	while ( $total < $max_total ) {
		// 2423 is the id of the highest non-pvp only skill.
		$id = mt_rand( 0, 2423 );
		$skill = gws_details( $id );
		// Check skill details exist, that it is not a pvp skill id, and that the id hasn't been stored before
		if ( $skill && !isset( $pvpSkillIds[$id] ) && !in_array( $id, $used_ids ) ) {
			// Skill exists!
			if ( $skill['elite'] == 1 && $elite < $max_elite ) {
				$elite++;
				$total++;
				$skills[] = $skill;
				$used_ids[] = $id;
			} else {
				if ( $skill['elite'] == 0 && $normal < $max_normal ) {
					$normal++;
					$total++;
					$skills[] = $skill;
					$used_ids[] = $id;
				}
			}
		}
	}

	// Sort and output the list of skills
	uasort( $skills, "skill_sort_cmp" );
	$skill_list = '';
	foreach ( $skills as $skill ) {
		$skill_list .= '[' . $skill['name'] . ']';
	}

	return "Random skill set (seed=$seed):\n$skill_list";
}

// Replacement function 4:
// Process the [build=id] element
function build_id_replace( $reg ) {
	[ $all, $id ] = $reg;
	$new_code = template_to_gwbbcode( $id );

	return ( strpos( $new_code, '[' ) === 0 ) ? $new_code : $all;
}

// Replacement function 5:
// Convert [Potential Skill Name] tags to [skill]Potential Skill Name[/skill]
function skill_name_replace( $reg ) {
	[ $all, $name ] = $reg;
	$name = html_safe_decode( $name );

	// '[[Skill Name]' => no icon
	if ( $name[0] == '[' ) {
		$noicon = true;
		$name = substr( $name, 1 );
	} else {
		$noicon = false;
	}

	// "name@attr_value|shown_name" -> $attr_val, $shown_name and $name
	if ( preg_match( '/@([^\]:\|]+)/', $name, $reg ) ) {
		$attr_val = preg_replace( '@[^0-9+-]@', '', $reg[1] );
	}
	if ( preg_match( '/\|([^\]@:]+)/', $name, $reg ) ) {
		// Play it safe
		$shown_name = html_safe_decode( $reg[1] );
	}
	if ( preg_match( '/^([^@\]:\|]+)/', $name, $reg ) ) {
		$name = $reg[1];
	}

	$id = gws_skill_id( $name );
	if ( $id !== false ) {
		// Handle [shock@8]
		$attr = '';
		if ( isset( $attr_val ) ) {
			$skill = gws_details( $id );
			$attr = gws_attribute_name( $skill['attribute'] );
			$attr = " $attr=$attr_val";
		}

		// Handle [shock|a knockdown]
		$show = '';
		if ( !empty( $shown_name ) ) {
			$show = " show=\"$shown_name\"";
			$noicon = true;
		}

		// Handle the difference between [[shock] and [shock]
		if ( $noicon ) {
			// PHP note: Use of double quotes replaces variables inside with their values
			// PHP note: Use of single quotes shows the variable names like JS would.
			return "[skill noicon$attr$show]" . $name . '[/skill]';
		} else {
			return "[skill$attr]" . $name . '[/skill]';
		}
	} else {
		return $all;
	}
}

// Replacement function 6:
// Process the [build] element
function build_replace( $reg ) {
	global $gwbbcode_tpl;
	$gwbbcode_images_folder_url = GWBBCODE_IMAGES_FOLDER_URL;
	$pvx_wiki_page_url = PVX_WIKI_PAGE_URL;
	$gw_wiki_page_url = GW_WIKI_PAGE_URL;

	[ $all, $att, $skills ] = $reg;
	$att = str_replace( "\n", "<br/>\n", html_safe_decode( $att ) );
	$attr_list_raw = attribute_list_raw( $att );
	$attr_list = attribute_list( $att );
	$load = mt_rand();

	// Professions
	$prof = gws_build_profession( $att );
	$cursor = 'help';
	if ( $prof !== false ) {
		$primary = gws_prof_name( $prof['primary'] );
		$secondary = gws_prof_name( $prof['secondary'] );
		if ( $secondary == $primary ) {
			$secondary = 'No profession';
		}

		// If the secondary profession is specified, remove impossible secondary profession primary attributes,
		// or set them to 0 for skill description coherence
		if ( $secondary != 'No profession' ) {
			unset( $attr_list_raw[gws_main_attribute( $secondary )] );
			$att .= ' ' . gws_attribute_name( gws_main_attribute( $secondary ) ) . '=0';
		}
	} else {
		$primary = 'No profession';
		$secondary = 'No profession';
	}
	// After validating profession names, use "Any" as the display name rather than "No profession" if not specified
	$primary_display_name = $primary;
	$secondary_display_name = $secondary;
	if ( $primary_display_name == 'No profession' ) {
		$primary_display_name = 'Any';
	}
	if ( $secondary_display_name == 'No profession' ) {
		$secondary_display_name = 'Any';
	}

	// Attributes
	$attributes = '';
	foreach ( $attr_list_raw as $attribute_name => $attribute_value ) {
		unset( $matches );
		$attr = $gwbbcode_tpl['attribute'];
		preg_match_all( "#\{(.*?)\}#is", $attr, $matches );
		foreach ( $matches[0] as $r => $find ) {
			$replace = ${$matches[1][$r]};
			$attr = str_replace( $find, $replace, $attr );
		}
		$attributes .= $attr;
	}
	$attributes = preg_replace( '/\s*\\+\s*/', ' + ', $attributes );
	$skills = str_replace( '[skill', '[skill ' . $att, $skills );

	// Build description
	$desc = preg_match( '|desc=\\"([^"]+)\\"|', $att, $reg ) ? $reg[1] : '';
	$desc = empty( $desc ) ? '' : ( $desc . '<br/>' );
	$desc =
		str_replace(
			'{br}',
			'<br/>',
			str_replace( '{br/}', '<br/>', str_replace( '{BR}', '<br/>', str_replace( '{BR/}', '<br/>', $desc ) ) )
		);

	// Primary attribute effect on build
	if ( !empty( $attr_list['Strength'] ) ) {
		$desc .= '<span class="expert">Your attack skills gain <b>' . $attr_list['Strength'] .
				 '%</b> armor penetration.</span><br/>';
	} else {
		if ( !empty( $attr_list['Expertise'] ) ) {
			$desc .= '<span class="expert">The energy cost of attack skills, rituals, touch skills and all Ranger skills is reduced by <b>' .
					 ( 4 * $attr_list['Expertise'] ) . '%</b>.</span><br/>';
		} else {
			if ( !empty( $attr_list['Divine Favor'] ) ) {
				$desc .= '<span class="expert">Your allies are healed for ' .
						 round( 3.2 * $attr_list['Divine Favor'] ) .
						 ' Health whenever you cast Monk spells on them.</span><br/>';
			} else {
				if ( !empty( $attr_list['Soul Reaping'] ) ) {
					$desc .= '<span class="expert">Gain <b>' . $attr_list['Soul Reaping'] .
							 '</b> Energy whenever a non-Spirit creature near you dies, up to 3 times every 15 seconds.</span><br/>';
				} else {
					if ( !empty( $attr_list['Fast Casting'] ) ) {
						$desc .= '<span class="expert">You activate Spells and Signets <b>' .
								 ( 100 - floor( pow( 0.955, $attr_list['Fast Casting'] ) * 100 ) ) .
								 '%</b> faster. (No effect for non-Mesmer skills with a cast time less than 2 seconds.)</span><br/>';
						$desc .= '<span class="expert">In PvE, the recharge time of your Mesmer Spells is reduced by <b>' .
								 ( 3 * $attr_list['Fast Casting'] ) . '%</b>.</span><br/>';
					} else {
						if ( !empty( $attr_list['Energy Storage'] ) ) {
							$desc .= '<span class="expert">Your maximum Energy is raised by <b>' .
									 ( 3 * $attr_list['Energy Storage'] ) . '</b>.</span><br/>';
						} else {
							if ( !empty( $attr_list['Critical Strikes'] ) ) {
								$desc .= '<span class="expert">You have an additional <b>' .
										 $attr_list['Critical Strikes'] .
										 '</b>% chance to critical hit. Whenever you critical hit, you get <b>' .
										 round( $attr_list['Critical Strikes'] / 5 ) . '</b> Energy.</span><br/>';
							} else {
								if ( !empty( $attr_list['Spawning Power'] ) ) {
									$desc .= '<span class="expert">Creatures you create have <b>' .
											 ( 4 * $attr_list['Spawning Power'] ) .
											 '%</b> more Health, and weapon spells you cast last <b>' .
											 ( 4 * $attr_list['Spawning Power'] ) . '%</b> longer.</span><br/>';
								} else {
									if ( !empty( $attr_list['Mysticism'] ) ) {
										$desc .= '<span class="expert">The energy cost of Dervish enchantments is reduced by <b>' .
												 ( 4 * $attr_list['Mysticism'] ) . '%</b>.</span><br/>';
										$desc .= '<span class="expert">In PvE, gain <b>+' . $attr_list['Mysticism'] .
												 '</b> armor rating while enchanted.</span><br/>';
									} else {
										if ( !empty( $attr_list['Leadership'] ) ) {
											$desc .= '<span class="expert">You gain 2 Energy for each ally affected by one of your Shouts or Chants (maximum <b>' .
													 floor( $attr_list['Leadership'] / 2 ) .
													 '</b> Energy).</span><br/>';
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}


	// Template: professions
	$invalid_template = false;
	static $prof_ids =
		[
			'No profession',
			'Warrior',
			'Ranger',
			'Monk',
			'Necromancer',
			'Mesmer',
			'Elementalist',
			'Assassin',
			'Ritualist',
			'Paragon',
			'Dervish',
		];
	$template = '0111000000';
	$template .= int2bin( array_search( $primary, $prof_ids ), 4 );
	$template .= int2bin( array_search( $secondary, $prof_ids ), 4 );

	// Template: attributes
	static $attr_ids =
		[
			'fas',
			'ill',
			'dom',
			'ins',
			'blo',
			'dea',
			'sou',
			'cur',
			'air',
			'ear',
			'fir',
			'wat',
			'ene',
			'hea',
			'smi',
			'pro',
			'div',
			'str',
			'axe',
			'ham',
			'swo',
			'tac',
			'bea',
			'exp',
			'wil',
			'mar',
			29 => 'dag',
			'dead',
			'sha',
			'com',
			'res',
			'cha',
			'cri',
			'spa',
			'spe',
			'comma',
			'mot',
			'lea',
			'scy',
			'win',
			'earthp',
			'mys',
		];
	static $prof_attr_list = [
		'Air Magic' => 'Elementalist',
		'Earth Magic' => 'Elementalist',
		'Energy Storage' => 'Elementalist',
		'Fire Magic' => 'Elementalist',
		'Water Magic' => 'Elementalist',
		'Domination Magic' => 'Mesmer',
		'Fast Casting' => 'Mesmer',
		'Illusion Magic' => 'Mesmer',
		'Inspiration Magic' => 'Mesmer',
		'Divine Favor' => 'Monk',
		'Healing Prayers' => 'Monk',
		'Protection Prayers' => 'Monk',
		'Smiting Prayers' => 'Monk',
		'Blood Magic' => 'Necromancer',
		'Curses' => 'Necromancer',
		'Death Magic' => 'Necromancer',
		'Soul Reaping' => 'Necromancer',
		'Beast Mastery' => 'Ranger',
		'Expertise' => 'Ranger',
		'Marksmanship' => 'Ranger',
		'Wilderness Survival' => 'Ranger',
		'Axe Mastery' => 'Warrior',
		'Hammer Mastery' => 'Warrior',
		'Strength' => 'Warrior',
		'Swordsmanship' => 'Warrior',
		'Tactics' => 'Warrior',
		'Critical Strikes' => 'Assassin',
		'Dagger Mastery' => 'Assassin',
		'Deadly Arts' => 'Assassin',
		'Shadow Arts' => 'Assassin',
		'Spawning Power' => 'Ritualist',
		'Channeling Magic' => 'Ritualist',
		'Communing' => 'Ritualist',
		'Restoration Magic' => 'Ritualist',
		'Spear Mastery' => 'Paragon',
		'Command' => 'Paragon',
		'Motivation' => 'Paragon',
		'Leadership' => 'Paragon',
		'Scythe Mastery' => 'Dervish',
		'Wind Prayers' => 'Dervish',
		'Earth Prayers' => 'Dervish',
		'Mysticism' => 'Dervish',
	];
	// => First attribute is attribute of the highest level
	arsort( $attr_list_raw );

	// Prepare a base attribute level and rune bonus list for primary and secondary attributes
	$attr_primary = [];
	$attr_secondary = [];
	$attr_runes = [];
	$available_helmet = true;
	// Ignore secondary profession main attribute
	if ( $secondary != 'No profession' ) {
		unset( $attr_list_raw[gws_main_attribute( $secondary )] );
	}

	foreach ( $attr_list_raw as $attr => $raw_level ) {
		// Separate base level and bonus
		preg_match( '@^([0-9]+)(\\+[+0-9]+)?@', $raw_level, $reg );
		$base_level = $reg[1];
		$bonus = $reg[2] ?? '0';

		// Primary attributes
		if ( $prof_attr_list[$attr] == $primary ) {
			$bonus_level = @array_sum( explode( '+', $bonus ) );
			// Invalid attribute level bonus
			if ( $bonus_level > 4 || $bonus_level < 0 || ( $bonus_level == 4 && !$available_helmet ) ) {
				$invalid_template = "Invalid attribute level bonus";
			} else {
				// Does attribute level bonus include a helmet?
				if ( $available_helmet && ( $bonus_level == 4 || ( substr_count( $bonus, '+' ) > 1 &&
																   strpos( $bonus, '+1' ) !== false ) ) ) {
					$available_helmet = false;
					$attr_primary[$attr] = $base_level;
					$attr_runes[$attr] = $bonus_level - 1;
				} else {
					// No helmet, but runes maybe
					$attr_primary[$attr] = $base_level;
					$attr_runes[$attr] = $bonus_level;
				}
			}
		} else {
			// Secondary attributes
			if ( $prof_attr_list[$attr] == $secondary ) {
				$attr_secondary[$attr] = $base_level;
			}
		}
	}

	// Manage primary attribute levels
	$points_secondary = attr_points( $attr_secondary );
	if ( !empty( $attr_primary ) ) {
		// Set helmet if needed
		if ( $available_helmet &&
			 ( max( $attr_primary ) > 12 || ( attr_points( $attr_primary ) + $points_secondary > 200 ) ) ) {
			// First attribute is attribute of the highest level, the one needing a helmet the most
			$attr_primary[key( $attr_primary )]--;
		}
		// Assign runes
		while ( ( max( $attr_primary ) > 12 || ( attr_points( $attr_primary ) + $points_secondary > 200 ) ) &&
				min( $attr_runes ) < 3 ) {
			arsort( $attr_primary );
			foreach ( $attr_runes as $attr => $rune ) {
				if ( $rune < 3 ) {
					$attr_primary[$attr]--;
					$attr_runes[$attr]++;
					break;
				}
			}
		}
	}
	$attr_list_raw = array_merge( $attr_primary, $attr_secondary );
	$template .= int2bin( count( $attr_list_raw ), 4 );
	if ( !empty( $attr_list_raw ) &&
		 ( max( $attr_list_raw ) > 12 || min( $attr_list_raw ) < 0 || attr_points( $attr_list_raw ) > 200 ) ) {
		$invalid_template = "More attribute levels than attribute points can handle";
	}
	$attr_bit_size = 5;
	$template_attrs = [];
	foreach ( $attr_list_raw as $attr => $level ) {
		$id = array_search( gws_attribute_name( $attr ), $attr_ids );
		$template_attrs[$id] = $level;
		if ( $id >= 32 ) {
			$attr_bit_size = 6;
		}
	}
	$template .= int2bin( $attr_bit_size - 4, 4 );
	ksort( $template_attrs );
	foreach ( $template_attrs as $id => $level ) {
		$template .= int2bin( $id, $attr_bit_size );
		$template .= int2bin( $level, 4 );
	}

	// Template: Skills
	$skill_bit_size = 9;
	$skill_id_max = pow( 2, $skill_bit_size );
	$template_skills = [];
	if ( preg_match_all( '#\[skill[^\]]*\](.*?)\[/skill\][ ]?#isS', $skills, $regs, PREG_SET_ORDER ) ) {
		foreach ( $regs as $reg ) {
			$skill_name = $reg[1];
			$id = gws_skill_id( $skill_name );
			if ( $id !== false ) {
				$skill = gws_details( $id );
				if ( ( $skill['profession'] == $primary || $skill['profession'] == $secondary ||
					   $skill['prof'] == '?' ) && ( array_search( $id, $template_skills ) === false || $id == 0 ) ) {
					if ( $id >= $skill_id_max ) {
						$skill_bit_size = floor( log( $id, 2 ) ) + 1;
						$skill_id_max = pow( 2, $skill_bit_size );
					}
				} else {
					if ( in_array( $id, $template_skills ) ) {
						$invalid_template = "Skill $skill_name can't be specified twice in the same build";
					} else {
						$invalid_template = "Skill $skill_name is not from one of the build professions";
					}
				}
				// Keep the list of skills independently of their build validity
				$template_skills[] = $id;
			}
		}
	}
	$template .= int2bin( $skill_bit_size - 8, 4 );
	if ( count( $template_skills ) > 8 ) {
		$invalid_template = "You specified more skills than a build can handle";
	} else {
		foreach ( $template_skills as $id ) {
			$template .= int2bin( getSkillIdPvE( $id ), $skill_bit_size );
		}
		// Complete with 0s
		$template .= str_repeat( str_repeat( '0', $skill_bit_size ), 8 - count( $template_skills ) );
	}

	// Template: Build name as populated by template_to_gwbbcode()
	$build_name = gws_build_name( $att );

	// Template: Manage template save link
	if ( preg_match( "/ nosave/i", $att ) ) {
		$template_html = '';
	} else {
		if ( $invalid_template === false ) {
			// Prepare template bbcode
			// Added a 0 since that's what GW does
			$template_code = bin_to_template( $template . '0' );

			// Prepare template name
			$template_name = $build_name;
		} else {
			// Display error message beneath build template
			$template_error_msg = htmlspecialchars( $invalid_template );
		}
	}

	// Replace all "{var_name}" with $var_name until there are none to replace =
	// (i.e a tag replacement can contain other tags)
	$tpl = $gwbbcode_tpl['build'];
	do {
		$prev_tpl = $tpl;
		// "{{skill_description}}" is replaced by $gwbbcode_tpl['skill_description']
		$tpl = preg_replace_callback( "#\{\{(.*?)\}\}#is", static function ( $m ) {
			return $gwbbcode_tpl[$m[1]] ?? $m[0];
		}, $tpl );

		// "{desc}" is replaced by $desc
		unset( $matches );
		preg_match_all( "#\{(.*?)\}#is", $tpl, $matches );
		foreach ( $matches[0] as $r => $find ) {
			if ( isset( ${$matches[1][$r]} ) ) {
				$replace = ${$matches[1][$r]};
			} else {
				$replace = '';
			}
			$tpl = str_replace( $find, $replace, $tpl );
		}
	} while ( $prev_tpl != $tpl );

	// Ensure there is a carriage return after [build][/build] tags so that bullets are always respected
	return $tpl . "\r\n";
}

// Replacement function 7:
// Convert [skillset=attribute@attr_level] tags to a list of skills of the appropriate attribute
function skillset_replace( $reg ) {
	global $gwbbcode_tpl;
	$tpl = $gwbbcode_tpl['skillset'];

	[ $all, $noicon, $name ] = $reg;
	$name = html_safe_decode( $name );

	// '[[...]' => no icon
	$noicon = empty( $noicon ) ? '' : ' noicon';
	$noicon_comma = empty( $noicon ) ? '' : ', ';

	// "name@attr_value" -> $name and $attr_val
	$attr = '';
	if ( preg_match( '|([^@\]]+)@([^\]]+)|', $name, $reg ) ) {
		$name = $reg[1];
		$attr_val = preg_replace( '@[^0-9+-]@', '', $reg[2] );
	}

	// Get the list of skills
	$bbcode = [];
	$attr = gws_attribute_name( $name );
	if ( $attr ) {
		// Load all skills
		static $skill_list = [];
		if ( !file_exists( SKILLS_PATH ) ) {
			die( "Missing skill details database." );
		}

		// Prepare bbcode prefix
		$skill_list = load( SKILLS_PATH );
		$attr_bbcode = " $attr=$attr_val";

		// Native php filter function is more effective than a loop
		$skill_list_filtered = array_filter( $skill_list, static function ( $var ) use ( $attr ) {
			return ( $var['attr'] == $attr );
		} );
		foreach ( $skill_list_filtered as $id ) {
			$bbcode[] = "[skill$noicon$attr_bbcode]{$id['name']}[/skill]";
		}
	}

	return infuse_values( $gwbbcode_tpl['skillset'], [
		'skillset_value' => implode( $noicon_comma, $bbcode ),
	] );
}

// Replacement function 8:
// Process the [skill] elements
function skill_replace( $reg ) {
	global $gwbbcode_tpl;
	$gwbbcode_images_folder_url = GWBBCODE_IMAGES_FOLDER_URL;
	$pvx_wiki_page_url = PVX_WIKI_PAGE_URL;
	$gw_wiki_page_url = GW_WIKI_PAGE_URL;

	[ $all, $att, $name ] = $reg;
	$att = html_safe_decode( $att );
	$name = html_safe_decode( $name );

	// Handle alternative text
	$shown_name = $name;
	if ( preg_match( '/show="([^\]]*)"/', $att, $reg ) ) {
		// Play it safe
		$shown_name = html_safe_decode( $reg[1] );
		$att = preg_replace( '/[ ]*show="[^\]]*"/', '', $att );
	}

	// Exit if skill doesn't exist
	if ( ( $id = gws_skill_id( $name ) ) === false ) {
		return $all;
	}

	$details = gws_details( $id );
	if ( $details === false ) {
		die( "Missing skill. Id=$id; Name=$name" );
	}

	extract( $details, EXTR_OVERWRITE );
	$load = mt_rand();

	// Blank/Optional skill slot
	if ( $name == 'No Skill' ) {
		$tpl = $gwbbcode_tpl['blank_icon'];
	} else {
		// Skill slot
		// Get the skill attribute level
		$attr_list = attribute_list( $att );
		$attr_value = $attr_list[$attribute] ?? '';

		// Skill name or image on which to move cursor
		$name_link = str_replace( "\"", "&quot;", $name );
		if ( strstr( $att, 'noicon' ) !== false ) {
			if ( $shown_name !== $name ) {
				$tpl = $gwbbcode_tpl['noicon_showname'];
			} else {
				$tpl = $gwbbcode_tpl['noicon'];
			}
		} else {
			$tpl = $gwbbcode_tpl['icon'];
		}

		// Append the hidden tooltip element
		$tpl .= $gwbbcode_tpl['skill'];

		// Initialise array of requirements
		$required = [];

		// Format adrenaline
		if ( $adrenaline != 0 ) {
			$required[] = infuse_values( $gwbbcode_tpl['requirement'], [
				'type' => 'adre',
				'value' => $adrenaline,
			] );
		}

		// Format sacrifice
		if ( $sacrifice != 0 ) {
			$required[] = infuse_values( $gwbbcode_tpl['requirement'], [
				'type' => 'sacr',
				'value' => $sacrifice . '%',
			] );
		}

		// Format upkeep
		if ( $eregen != 0 ) {
			$required[] = infuse_values( $gwbbcode_tpl['requirement'], [
				'type' => 'eregen',
				'value' => -$eregen,
			] );
		}

		// Format overcast
		if ( $overcast != 0 ) {
			$required[] = infuse_values( $gwbbcode_tpl['requirement'], [
				'type' => 'over',
				'value' => $overcast,
			] );
		}

		// Format energy
		if ( $energy != 0 ) {
			$energy_change = false;

			// Handle expertise
			$expert_energy = calc_expertise( $name, $attr_list, $type, $energy, $profession, $desc );
			if ( $expert_energy && $expert_energy != $energy ) {
				$energy_html = infuse_values( $gwbbcode_tpl['modified_requirement_value'], [
					'initial_value' => $energy,
					'modified_value' => $expert_energy,
				] );
				$energy_change = true;
			}

			// Handle mysticism
			$mystic_energy = calc_mysticism( $attr_list, $type, $energy, $profession );
			if ( $mystic_energy && $mystic_energy != $energy ) {
				$energy_html = infuse_values( $gwbbcode_tpl['modified_requirement_value'], [
					'initial_value' => $energy,
					'modified_value' => $mystic_energy,
				] );
				$energy_change = true;
			}

			// Default
			if ( $energy_change == false ) {
				$energy_html = $energy;
			}

			// Insert values into template
			$required[] = infuse_values( $gwbbcode_tpl['requirement'], [
				'type' => 'ener',
				'value' => $energy_html,
			] );
		}

		// Format casting time
		if ( $casting != 0 ) {
			switch ( $casting ) {
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
			$fast_casting = calc_fastcasting( $attr_list, $type, $casting, $profession );
			if ( $fast_casting && $fast_casting != $casting ) {
				$fast_casting = sprintf( '%.1f', $fast_casting );
				$casting_html = infuse_values( $gwbbcode_tpl['modified_requirement_value'], [
					'initial_value' => $casting_frac,
					'modified_value' => $fast_casting,
				] );
			} else {
				$casting_html = $casting_frac;
			}
			$required[] = infuse_values( $gwbbcode_tpl['requirement'], [
				'type' => 'cast',
				'value' => $casting_html,
			] );
		}

		// Format recharge time
		if ( $recharge != 0 ) {
			$required[] = infuse_values( $gwbbcode_tpl['requirement'], [
				'type' => 'rech',
				'value' => $recharge,
			] );
		}

		// Concatenate requirements
		$required = implode( '', $required );

		// Campaign names
		$campaign_names = [
			'Core',
			'Prophecies',
			'Factions',
			'Nightfall',
			'EotN',
		];
		if ( isset( $campaign_names[$chapter] ) ) {
			$chapter = $campaign_names[$chapter];
		}

		// PvE only
		$pve_only = $pve_only ? 'PvE only.' : '';

		// Descriptions variables -> green and adapted to their attribute. (0..12..16 -> green 8)
		$extra_desc = '';
		gws_adapt_description( $desc, $extra_desc, $name, $attribute, $profession, $attr_list, $type, $pve_only );
		$attr_html =
			$attribute == 'No Attribute' ? $gwbbcode_tpl['tpl_skill_no_attr'] : $gwbbcode_tpl['tpl_skill_attr'];
		$extra_desc = empty( $extra_desc )
			? ''
			: infuse_values( $gwbbcode_tpl['tpl_extra_desc'], [
				'extra_desc' => $extra_desc,
			] );

		// Change the skill aspect if skill is elite
		if ( isset( $elite ) && $elite ) {
			$elite_or_normal = 'elite_skill';
			$type = 'Elite ' . $type;
		} else {
			$elite_or_normal = 'normal_skill';
		}

		// Profession icon - used on the faded tooltip background image
		if ( $prof == '?' ) {
			$prof_img = "No_profession-faded";
		} else {
			$prof_img = "$profession-faded";
		}
	}

	// Replace all "{var_name}" by $var_name till there is none to replace
	// (i.e a tag replacement can contain other tags)
	do {
		$prev_tpl = $tpl;

		// "{{skill_description}}" is replaced by $gwbbcode_tpl['skill_description']
		unset( $matches );
		preg_match_all( "#\{\{(.*?)\}\}#is", $tpl, $matches );
		foreach ( $matches[0] as $r => $find ) {
			if ( isset( $gwbbcode_tpl[$matches[1][$r]] ) ) {
				$replace = $gwbbcode_tpl[$matches[1][$r]];
				$tpl = str_replace( $find, $replace, $tpl );
			}
		}

		// "{desc}" is replaced by $desc
		unset( $matches );
		preg_match_all( "#\{(.*?)\}#is", $tpl, $matches );
		foreach ( $matches[0] as $r => $find ) {
			$replace = ${$matches[1][$r]};
			$tpl = str_replace( $find, $replace, $tpl );
		}
	} while ( $prev_tpl != $tpl );

	return $tpl;
}


/***************************************************************************
 * HELPER DATABASE FUNCTIONS
 ***************************************************************************/

// Database function 1:
// Returns either the id of a $skill_name, or false
// Also works if $skill_name is an abbreviation (within reason)
function gws_skill_id( $skill_name ) {
	$ret = false;
	// Handle abbreviations
	static $abbr_db = [];
	$name_id = preg_replace( '|[\'"!]|', '', strtolower( $skill_name ) );
	if ( empty( $abbr_db ) ) {
		if ( ( $abbr_db = @load( SKILLABBRS_PATH ) ) === false ) {
			die( "Missing abbreviation database." );
		}
	}
	if ( isset( $abbr_db[$name_id] ) ) {
		$name_id = preg_replace( '|[\'"!]|', '', strtolower( $abbr_db[$name_id] ) );
	}

	// Load the name to id listing from a file (only once)
	static $list = [];
	if ( empty( $list ) ) {
		if ( ( $list = @load( SKILLNAMES_PATH ) ) === false ) {
			die( "Missing skillname database." );
		}
	}

	$name_id = preg_replace( '|[\'"!]|', '', strtolower( $name_id ) );
	if ( isset( $list[$name_id] ) ) {
		$ret = $list[$name_id];
	} else {
		// Check if name could be a partial match
		if ( strlen( $name_id ) >= 4 ) {
			$name_id_length = strlen( $name_id );
			foreach ( $list as $name => $id ) {
				if ( $name_id == substr( $name, 0, $name_id_length ) ) {
					$ret = $id;
					break;
				}
			}
		}
	}

	return $ret;
}

// Database function 2:
// Return a list of skill names and corresponding id
function gws_skill_id_list() {
	// Load the name to id listing from a file (only once)
	static $list = [];
	if ( empty( $list ) ) {
		if ( ( $list = @load( SKILLNAMES_PATH ) ) === false ) {
			die( "Missing skillname database." );
		}
	}

	return $list;
}

// Database function 3:
// Returns either the skill information array of a $skill_id, or false
function gws_details( $skill_id ) {
	// (Re)load skill list (can't have two in memory, it'd be too big)
	static $skill_list = [];
	if ( !file_exists( SKILLS_PATH ) ) {
		return false;
	}
	$skill_list = load( SKILLS_PATH );

	return ( isset( $skill_list[$skill_id] ) ? $skill_list[$skill_id] : false );
}


/***************************************************************************
 * HELPER CALCULATION FUNCTIONS - PROFESSIONS
 ***************************************************************************/

// Calculation function 1:
// Returns values of the prof attribute of an attribute list (string $att), or false
// gws_build_profession(' prof=W/E tactics=16')  ===  Array('primary' => 'W', 'secondary' => 'E', 'professions => 'W/E')
function gws_build_profession( $att ) {
	if ( preg_match( '|prof=(([^/ ]+)/([^ ]+))|', $att, $reg ) ) {
		return [
			'primary' => $reg[2],
			'secondary' => $reg[3],
			'professions' => $reg[1],
		];
	} else {
		if ( preg_match( '|prof=([^/ ]+)|', $att, $reg ) ) {
			return [
				'primary' => $reg[1],
				'secondary' => '?',
				'professions' => $reg[1],
			];
		} else {
			return false;
		}
	}
}

// Calculation function 2:
// Returns the full profession name of a partial one. Returns 'No profession' if no match is found
function gws_prof_name( $profession ) {
	// Look for a $p profession name corresponding to $profession
	static $p = [
		'E' => 'Elementalist',
		'Me' => 'Mesmer',
		'Mo' => 'Monk',
		'N' => 'Necromancer',
		'R' => 'Ranger',
		'W' => 'Warrior',
		'A' => 'Assassin',
		'Rt' => 'Ritualist',
		'D' => 'Dervish',
		'P' => 'Paragon',
		'?' => 'No profession',
	];
	$profession = strtolower( $profession );

	// Ritualist exception ("rt" is the only abbreviation that does not reflect
	// the first one/two letters of the full name)
	if ( $profession == 'rt' ) {
		return $p['Rt'];
	}

	foreach ( $p as $prof ) {
		if ( strpos( strtolower( $prof ), $profession ) === 0 ) {
			return $prof;
		}
	}

	// No corresponding profession was found
	return 'No profession';
}

// Calculation function 3:
// Returns the profession abbreviation of a partial one. Returns '?' if no match is found
function gws_profession_abbr( $profession ) {
	static $p =
		[
			'E' => 'Elementalist',
			'Me' => 'Mesmer',
			'Mo' => 'Monk',
			'N' => 'Necromancer',
			'R' => 'Ranger',
			'W' => 'Warrior',
			'A' => 'Assassin',
			'Rt' => 'Ritualist',
			'D' => 'Dervish',
			'P' => 'Paragon',
			'?' => 'No profession',
		];

	return array_search( gws_prof_name( $profession ), $p );
}


/***************************************************************************
 * HELPER CALCULATION FUNCTIONS - BUILD NAMES
 ***************************************************************************/

// Calculation function 4:
// Returns value of the name attribute of an attribute list (string $att), or ''
// gws_build_name(' prof=W/E name="Shock Warrior"')  ===  'Shock Warrior'
function gws_build_name( $att ) {
	return preg_match( '|name=\\"([^\"]+)\\"|', $att, $reg ) ? $reg[1] : '';
}


/***************************************************************************
 * HELPER CALCULATION FUNCTIONS - ATTRIBUTES
 ***************************************************************************/

// Calculation function 5:
// Returns the full attribute name of a partial or full one. Returns false if no match is found
function gws_attr_name( $attr ) {
	static $attribute_list = [
		'airmagic' => 'Air Magic',
		'earthmagic' => 'Earth Magic',
		'energystorage' => 'Energy Storage',
		'firemagic' => 'Fire Magic',
		'watermagic' => 'Water Magic',
		'dominationmagic' => 'Domination Magic',
		'fastcasting' => 'Fast Casting',
		'illusionmagic' => 'Illusion Magic',
		'inspirationmagic' => 'Inspiration Magic',
		'divinefavor' => 'Divine Favor',
		'healingprayers' => 'Healing Prayers',
		'protectionprayers' => 'Protection Prayers',
		'smitingprayers' => 'Smiting Prayers',
		'bloodmagic' => 'Blood Magic',
		'curses' => 'Curses',
		'deathmagic' => 'Death Magic',
		'soulreaping' => 'Soul Reaping',
		'beastmastery' => 'Beast Mastery',
		'expertise' => 'Expertise',
		'marksmanship' => 'Marksmanship',
		'wildernesssurvival' => 'Wilderness Survival',
		'axemastery' => 'Axe Mastery',
		'hammermastery' => 'Hammer Mastery',
		'strength' => 'Strength',
		'swordsmanship' => 'Swordsmanship',
		'tactics' => 'Tactics',
		'criticalstrikes' => 'Critical Strikes',
		'daggermastery' => 'Dagger Mastery',
		'deadlyarts' => 'Deadly Arts',
		'shadowarts' => 'Shadow Arts',
		'spawningpower' => 'Spawning Power',
		'channelingmagic' => 'Channeling Magic',
		'communing' => 'Communing',
		'restorationmagic' => 'Restoration Magic',
		'spearmastery' => 'Spear Mastery',
		'command' => 'Command',
		'motivation' => 'Motivation',
		'leadership' => 'Leadership',
		'scythemastery' => 'Scythe Mastery',
		'windprayers' => 'Wind Prayers',
		'earthprayers' => 'Earth Prayers',
		'mysticism' => 'Mysticism',
		'kurzickrank' => 'Kurzick rank',
		'luxonrank' => 'Luxon rank',
		'sunspearrank' => 'Sunspear rank',
		'lightbringerrank' => 'Lightbringer rank',
		'asurarank' => 'Asura rank',
		'deldrimorrank' => 'Deldrimor rank',
		'ebonvanguardrank' => 'Ebon Vanguard rank',
		'nornrank' => 'Norn rank',
	];

	$attr = strtolower( str_replace( '_', '', $attr ) );
	if ( empty( $attr ) ) {
		return false;
	}

	foreach ( $attribute_list as $long_attr => $attribute ) {
		if ( strpos( $long_attr, $attr ) === 0 ) {
			return $attribute;
		}
	}

	// Otherwise
	return false;
}

// Calculation function 6:
// Returns the attribute name abbreviation of a partial one. Returns false if no match is found
function gws_attribute_name( $attribute ) {
	static $attribute_list = [
		'air' => 'airmagic',
		'ear' => 'earthmagic',
		'ene' => 'energystorage',
		'fir' => 'firemagic',
		'wat' => 'watermagic',
		'dom' => 'dominationmagic',
		'fas' => 'fastcasting',
		'ill' => 'illusionmagic',
		'ins' => 'inspirationmagic',
		'div' => 'divinefavor',
		'hea' => 'healingprayers',
		'pro' => 'protectionprayers',
		'smi' => 'smitingprayers',
		'blo' => 'bloodmagic',
		'cur' => 'curses',
		'dea' => 'deathmagic',
		'sou' => 'soulreaping',
		'bea' => 'beastmastery',
		'exp' => 'expertise',
		'mar' => 'marksmanship',
		'wil' => 'wildernesssurvival',
		'axe' => 'axemastery',
		'ham' => 'hammermastery',
		'str' => 'strength',
		'swo' => 'swordsmanship',
		'tac' => 'tactics',
		'cri' => 'criticalstrikes',
		'dag' => 'daggermastery',
		'dead' => 'deadlyarts',
		'sha' => 'shadowarts',
		'spa' => 'spawningpower',
		'cha' => 'channelingmagic',
		'com' => 'communing',
		'res' => 'restorationmagic',
		'spe' => 'spearmastery',
		'comma' => 'command',
		'mot' => 'motivation',
		'lea' => 'leadership',
		'scy' => 'scythemastery',
		'win' => 'windprayers',
		'earthp' => 'earthprayers',
		'mys' => 'mysticism',
		'kur' => 'kurzickrank',
		'lux' => 'luxonrank',
		'sun' => 'sunspearrank',
		'lig' => 'lightbringerrank',
		'asu' => 'asurarank',
		'del' => 'deldrimorrank',
		'ebo' => 'ebonvanguardrank',
		'nor' => 'nornrank',
	];

	$attribute = strtolower( str_replace( ' ', '', $attribute ) );
	if ( !empty( $attribute ) ) {
		foreach ( $attribute_list as $attr => $long_attr ) {
			if ( strpos( $long_attr, $attribute ) === 0 ) {
				return $attr;
			}
		}
	}

	// Otherwise
	return false;
}

// Calculation function 7:
// Returns the main attribute of a full profession name; false in case of 'No profession' or erroneous profession name
function gws_main_attribute( $profession ) {
	static $main_attributes =
		[
			'Elementalist' => 'Energy Storage',
			'Mesmer' => 'Fast Casting',
			'Monk' => 'Divine Favor',
			'Necromancer' => 'Soul Reaping',
			'Ranger' => 'Expertise',
			'Warrior' => 'Strength',
			'Assassin' => 'Critical Strikes',
			'Ritualist' => 'Spawning Power',
			'Paragon' => 'Leadership',
			'Dervish' => 'Mysticism',
		];

	return isset( $main_attributes[$profession] ) ? $main_attributes[$profession] : false;
}

// Calculation function 8:
// Returns true only if the partial attribute is a pve attribute, else false
function gws_pve_attr( $attribute ) {
	static $pve_attr_list = [ 'kur', 'lux', 'sun', 'lig', 'asu', 'del', 'ebo', 'nor' ];

	return in_array( gws_attribute_name( $attribute ), $pve_attr_list );
}

// Calculation function 9:
// Returns an attribute list (string) cleaned of the prof, name and desc attributes
function gws_attributes_clean( $att ) {
	$att = preg_replace( '|prof=[^ \]]*|', '', $att );
	$att = preg_replace( '|name=\\"[^"]+\\"|', '', $att );
	$att = preg_replace( '|desc=\\"[^"]+\\"|', '', $att );

	return trim( $att );
}

// Calculation function 10:
// Returns a list of attributes and their string values (e.g "12+1+3")
function attribute_list_raw( $att ) {
	$list = [];
	$clean_attr = explode( ' ', gws_attributes_clean( $att ) );
	foreach ( $clean_attr as $value ) {
		$value = explode( '=', $value );
		// Only one '='?
		if ( count( $value ) == 2 ) {
			$attr_name = gws_attr_name( $value[0] );
			// Valid attribute name?
			if ( $attr_name !== false ) {
				$attr_value = preg_replace( '@[^0-9+-]@', '', $value[1] );
				// Valid attribute value?
				if ( isset( $attr_value ) && $attr_value !== false ) {
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
function attribute_list( $att ) {
	$list = attribute_list_raw( $att );
	foreach ( $list as $attr_name => $attr_lvl ) {
		$list[$attr_name] = array_sum( explode( '+', $attr_lvl ) );
	}

	return $list;
}

// Calculation function 12:
// Return the number of necessary attribute points for given attribute levels, or false
function attr_points( $attr_list ) {
	static $point_list = [ 0, 1, 2, 3, 4, 5, 6, 7, 9, 11, 13, 16, 20 ];
	$points = 0;
	foreach ( $attr_list as $level ) {
		if ( !isset( $point_list[$level] ) ) {
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
// PHP note: Using the ampersand before desc and extra_desc means the same variables get passed back to the source
function gws_adapt_description( &$desc, &$extra_desc, $name, $attribute, $profession, $attr_list, $type, $pve_only ) {
	// Put some green around the fork
	$desc = preg_replace_callback( '|([0-9]+\.\.[0-9]+)|', 'fork_replace', $desc );

	// For skill which are not PvE only skills, adapt the 0..15 fork
	// to the build's attribute level if the attribute level has been specified
	if ( !$pve_only ) {
		if ( isset( $attr_list[$attribute] ) ) {
			$attr_lvl = $attr_list[$attribute];
			if ( preg_match_all( '|([0-9]+)\.\.([0-9]+)|', $desc, $regs, PREG_SET_ORDER ) ) {
				foreach ( $regs as $fork ) {
					[ $all, $val_0, $val_15 ] = $fork;
					$pos = strpos( $desc, $all );
					$desc = substr_replace( $desc, fork_val( $val_0, $val_15, $attr_lvl ), $pos, strlen( $all ) );
				}
			}
		} else {
			// or adapt the 0..15 form to 0..12..16 values if the attribute level has not been specified
			$desc = preg_replace_callback( '|([0-9]+)\.\.([0-9]+)|', 'desc_replace', $desc );
		}
	} else {
		// For PvE only skills, adapt the fork to the rank. Rank limits vary with tracks.
		// Suggest an array of attribute shorthands and their max ranks for effects.
		// Then lookup the attribute.
		if ( $attribute == 'Kurzick rank' || $attribute == 'Luxon rank' ) {
			// Kurzick/Luxon ranks are 0-12, but have no effect above rank 6.
			if ( isset( $attr_list[$attribute] ) ) {
				$attr_lvl = $attr_list[$attribute];
				if ( preg_match_all( '|([0-9]+)\.\.([0-9]+)|', $desc, $regs, PREG_SET_ORDER ) ) {
					foreach ( $regs as $fork ) {
						[ $all, $val_0, $val_max ] = $fork;
						$pos = strpos( $desc, $all );
						$desc =
							substr_replace(
								$desc,
								fork_val_pve_only( $val_0, $val_max, $attr_lvl, 6 ),
								$pos,
								strlen( $all )
							);
					}
				}
			}
		} else {
			if ( $profession !== 'No Profession' && gws_main_attribute( $profession ) == $attribute ) {
				// 2020 anniversary elite skills use profession primary attributes
				if ( isset( $attr_list[$attribute] ) ) {
					$attr_lvl = $attr_list[$attribute];
					if ( preg_match_all( '|([0-9]+)\.\.([0-9]+)|', $desc, $regs, PREG_SET_ORDER ) ) {
						foreach ( $regs as $fork ) {
							[ $all, $val_0, $val_15 ] = $fork;
							$pos = strpos( $desc, $all );
							$desc =
								substr_replace( $desc, fork_val( $val_0, $val_15, $attr_lvl ), $pos, strlen( $all ) );
						}
					}
				} else {
					// or adapt the 0..15 form to 0..12..16 values if the attribute level has not been specified
					$desc = preg_replace_callback( '|([0-9]+)\.\.([0-9]+)|', 'desc_replace', $desc );
				}
			} else {
				// Lightbringer, Sunspear, Asura, Dwarf, Ebon, Norn ranks are 0-10, but have no effect above rank 5.
				if ( isset( $attr_list[$attribute] ) ) {
					$attr_lvl = $attr_list[$attribute];
					if ( preg_match_all( '|([0-9]+)\.\.([0-9]+)|', $desc, $regs, PREG_SET_ORDER ) ) {
						foreach ( $regs as $fork ) {
							[ $all, $val_0, $val_max ] = $fork;
							$pos = strpos( $desc, $all );
							$desc =
								substr_replace(
									$desc,
									fork_val_pve_only( $val_0, $val_max, $attr_lvl, 5 ),
									$pos,
									strlen( $all )
								);
						}
					}
				}
			}
		}
		// or show its 0..10 values (no action required)
	}

	// Warrior: Take into account Strength
	add_strength( $desc, $extra_desc, $attr_list, $type, $name );

	// Paragon: Take into account Leadership
	add_leadership( $extra_desc, $attr_list, $type, $name );

	// Monk: Take into account Divine Favor
	add_divine_favor( $desc, $extra_desc, $attr_list, $name );

	// Spirits created by anyone: Specify a Spirit health and armor
	// Ritualist: Take into account Spawning Power
	add_spirit_health( $desc, $attr_list );

	// Ritualist: Specify additional weapon spell duration
	add_weapon_duration( $desc, $attr_list, $type );

	// PvE only: Append a note saying PvE only
	if ( $pve_only !== '' ) {
		if ( $extra_desc !== '' ) {
			$extra_desc .= '<br>';
		}
		$extra_desc .= $pve_only;
	}
}

// Calculation function 14:
// Put some green around the fork
function fork_replace( $reg ) {
	[ $all, $fork ] = $reg;
	// Make sure to adapt add_spirit_health() if you change this
	return "<span class=\"variable\">$fork</span>";
}

// Calculation function 15:
// Return value at a given attribute level
function fork_val( $val_0, $val_15, $attr_lvl ) {
	return $val_0 + round( ( $val_15 - $val_0 ) * $attr_lvl / 15 );
}

// Calculation function 16:
// Replace a 0..15 fork by a 0..12..16 one
function desc_replace( $reg ) {
	[ $all, $val_0, $val_15 ] = $reg;

	return $val_0 . '..' . fork_val( $val_0, $val_15, 12 ) . '..' . fork_val( $val_0, $val_15, 16 );
}

// Calculation function 17:
// Return value at a given attribute level, using a non-linear conversion.
function fork_val_pve_only( $val_0, $val_15, $attr_lvl, $rank_limit ) {
	// Sunspear, Lightbringer and EoTN ranks have an improved effect up to rank 5
	$track10_limit5 = [ 0 => 0, 1 => 3, 2 => 6, 3 => 9, 4 => 12, 5 => 15 ];
	// Kurzick and Luxon ranks have an improved effect up to rank 6
	$track12_limit6 = [ 0 => 0, 1 => 2, 2 => 5, 3 => 7, 4 => 10, 5 => 12, 6 => 15 ];

	$factor = 0;
	if ( $attr_lvl > $rank_limit ) {
		$factor = 15;
	} else {
		if ( $attr_lvl >= 0 ) {
			if ( $rank_limit == 6 ) {
				$conversion_array = $track12_limit6;
			} else {
				$conversion_array = $track10_limit5;
			}
			$factor = $conversion_array[$attr_lvl];
		}
	}

	return $val_0 + round( ( $val_15 - $val_0 ) * $factor / 15 );
}

// Calculation function 18:
// Return a tooltip addition for the armor penetration gained from using an attack skill
// unless that skill already has armor penetration
function add_strength( $desc, &$extra_desc, $attr_list, $type ) {
	if ( isset( $attr_list['Strength'] ) && $attr_list['Strength'] > 0 && strpos( $type, 'Attack' ) !== false ) {
		// Strength does not stack with skills with inherent armor penetration
		if ( preg_match( '@[Tt]his (attack|axe attack) has ([0-9]+)% armor penetration@', $desc, $reg ) ) {
			if ( $reg[2] < $attr_list['Strength'] ) {
				$extra_desc =
					'This attack skill has <b>' . $attr_list['Strength'] .
					'</b>% armor penetration. Its inherent armor penetration is overwritten.';
			}
		} else {
			$extra_desc = 'This attack skill has <b>' . $attr_list['Strength'] . '</b>% armor penetration.';
		}
	}
}

// Calculation function 19:
// Return a tooltip addition for the energy gained from using a skill depending on its type and level of Leadership
function add_leadership( &$extra_desc, $attr_list, $type, $name ) {
	static $leadership_limited_gain_skills = [
		// Bugged - no energy
		'"By Ural\'s Hammer!"' => 0,
		'"Make Your Time!"' => 0,

		// Targets a foe only - no energy
		'"Coward!"' => 0,
		'"Dodge This!"' => 0,
		'"Finish Him!"' => 0,
		'"None Shall Pass!"' => 0,
		'"On Your Knees!"' => 0,
		'"You Are All Weaklings!"' => 0,
		'"You Move Like a Dwarf!"' => 0,
		'"You\'re All Alone!"' => 0,

		// Selfish shouts / Target only one ally / Target only pet - energy = 2.
		'"Brace Yourself!"' => 2,
		'"Fear Me!"' => 2,
		'"Find Their Weakness!"' => 2,
		'"For Great Justice!"' => 2,
		'"Help Me!"' => 2,
		'"I Am Unstoppable!"' => 2,
		'"I Am the Strongest!"' => 2,
		'"I Meant to Do That!"' => 2,
		'"I Will Avenge You!"' => 2,
		'"I Will Survive!"' => 2,
		'"It\'s just a flesh wound."' => 2,
		'"Lead the Way!"' => 2,
		'"To the Limit!"' => 2,
		'"Victory is Mine!"' => 2,
		'"You Will Die!"' => 2,
		'Call of Haste' => 2,
		'Call of Protection' => 2,
		'Otyugh\'s Cry' => 2,
		'Predatory Bond' => 2,
		'Symbiotic Bond' => 2,

		// Target yourself and pet - energy = 4
		'Strike as One' => 4,
	];

	// Check if leadership is set, if leadership is greater than 1, and if the skill is either a shout or chant
	if ( isset( $attr_list['Leadership'] ) && $attr_list['Leadership'] > 1 &&
		 ( $type == 'Chant' || $type == 'Shout' ) ) {

		// Check if it is one of the limited leadership gain skills.
		if ( isset( $leadership_limited_gain_skills[$name] ) ) {
			$max_energy = $leadership_limited_gain_skills[$name];

			// Check if is not one of the bugged shout skills with zero gain. If so, energy gain is fixed.
			if ( $max_energy !== 0 ) {
				$extra_desc = 'Gain <b>' . min( $max_energy, floor( $attr_list['Leadership'] / 2 ) ) . '</b> energy.';
			}
		} else {
			// Otherwise, for regular shouts/chants, energy increases with allies
			$extra_desc =
				'Gain 2 energy per affected ally (maximum of <b>' . floor( $attr_list['Leadership'] / 2 ) .
				'</b> energy).';
		}
	}
}

// Calculation function 20:
// Return a description of the real effect of a skill taking into account Divine Favor
function add_divine_favor( &$desc, &$extra_desc, $attr_list, $name ) {
	if ( isset( $attr_list['Divine Favor'] ) && $attr_list['Divine Favor'] > 0 ) {
		// This function relies on the descriptions in skill_db.php containing
		// a suffix of {div}, {target} or {self} which are manually added.
		if ( preg_match( '/\{((div)|(target)|(self))\}/', $desc, $reg ) ) {
			$heal_type = $reg[1];
			$div_heal = round( 3.2 * $attr_list['Divine Favor'] );
			switch ( $heal_type ) {
				case 'div':
					if ( $name === 'Healing Touch' ) {
						$desc =
							str_replace(
								'{div}',
								'<span class="expert">&nbsp;<b>(+' . ( 2 * $div_heal ) . '&#041;</b></span>',
								$desc
							);
					} else {
						$desc =
							str_replace(
								'{div}',
								'<span class="expert">&nbsp;<b>(+' . $div_heal . '&#041;</b></span>',
								$desc
							);
					}
					break;
				case 'target':
					$extra_desc = 'Target ally gets healed for <b>' . $div_heal . '</b> Health.';
					break;
				case 'self':
					$extra_desc = 'You get healed for <b>' . $div_heal . '</b> Health.';
					break;
			}
			$desc = preg_replace( '/\{((target)|(self))\}/', '', $desc );
		}
	} else {
		// Remove remaining unused tags
		$desc = str_replace( '{div}', '', $desc );
		$desc = str_replace( '{target}', '', $desc );
		$desc = str_replace( '{self}', '', $desc );
	}
}

// Calculation function 21:
// Return a description specifying how much health and armor does a Spirit, Minion or Summon have
function add_spirit_health( &$desc, $attr_list ) {
	// Get Spirit's level
	if ( preg_match( '@Create a level <span class="variable">([0-9]+)</span> Spirit@', $desc, $reg ) ) {
		$spirit_level = $reg[1];

		// Get Spawning Power level
		$spawning_level = isset( $attr_list['Spawning Power'] ) ? $attr_list['Spawning Power'] : 0;

		// Compute the Spirit's Health and armor
		$spirit_health = $spirit_level * 20;
		$spawning_bonus = '';
		if ( $spawning_level > 0 ) {
			$spawning_health = round( $spirit_health * ( $spawning_level * 0.04 ) );
			$spawning_bonus = ' (+' . $spawning_health . ')';
		}
		$spirit_armor = round( ( 88 / 15 * $spirit_level ) + 3 );

		// Add Spirit's health and armor to description
		$desc =
			preg_replace(
				'@Create a level <span class="variable">[0-9]+</span> Spirit@',
				'${0} <span class="expert"> with <b>' . $spirit_health . $spawning_bonus . '</b> Health and <b>' .
				$spirit_armor . '</b> armor</span>',
				$desc
			);
	}

	if ( isset( $attr_list['Death Magic'] ) ) {
		if ( preg_match(
			'@level <span class="variable">([0-9]+)</span> (bone fiend|bone horror|bone minions|bone minion|flesh golem|jagged horror|masterless bone horror|shambling horror|vampiric horror)@',
			$desc,
			$reg
		) ) {
			$spirit_level = $reg[1];

			// Get Spawning Power level
			$spawning_level = isset( $attr_list['Spawning Power'] ) ? $attr_list['Spawning Power'] : 0;

			// Compute the Minion's Health and armor
			// https://wiki.guildwars.com/wiki/Creature#Health : health = level*20+80
			$spirit_health = $spirit_level * 20 + 80;
			$spawning_bonus = '';
			if ( $spawning_level > 0 ) {
				$spawning_health = round( $spirit_health * ( $spawning_level * 0.04 ) );
				$spawning_bonus = ' (+' . $spawning_health . ')';
			}
			$spirit_armor = round( ( 88 / 15 * $spirit_level ) + 3 );

			// Add Minion's health and armor to description
			$desc =
				preg_replace(
					'@level <span class="variable">[0-9]+</span> (?:bone fiend|bone horror|bone minions|bone minion|flesh golem|jagged horror|masterless bone horror|shambling horror|vampiric horror)@',
					'${0} <span class="expert"> with <b>' . $spirit_health . $spawning_bonus . '</b> Health and <b>' .
					$spirit_armor . '</b> armor</span>',
					$desc
				);
		}
	}

	// https://wiki.guildwars.com/wiki/Creature#Health : health = level*20+80
	if ( isset( $attr_list['Asura rank'] ) || isset( $attr_list['Ebon Vanguard rank'] ) ) {
		if ( preg_match(
			'@Summon a level <span class="variable">([0-9]+)</span> (?:Ebon Vanguard Assassin|Ice Imp|Mursaat|Naga Shaman|Ruby Djinn)@',
			$desc,
			$reg
		) ) {
			$spirit_level = $reg[1];

			// Get Spawning Power level
			$spawning_level = isset( $attr_list['Spawning Power'] ) ? $attr_list['Spawning Power'] : 0;

			// Compute the Summon's Health and armor
			// https://wiki.guildwars.com/wiki/Creature#Health : health = level*20+80
			$spirit_health = $spirit_level * 20 + 80;
			$spawning_bonus = '';
			if ( $spawning_level > 0 ) {
				$spawning_health = round( $spirit_health * ( $spawning_level * 0.04 ) );
				$spawning_bonus = ' (+' . $spawning_health . ')';
			}
			$spirit_armor = round( ( 88 / 15 * $spirit_level ) + 3 );

			// Add Summon's health and armor to description
			$desc =
				preg_replace(
					'@Summon a level <span class="variable">[0-9]+</span> (?:Ebon Vanguard Assassin|Ice Imp|Mursaat|Naga Shaman|Ruby Djinn)@',
					'${0} <span class="expert"> with <b>' . $spirit_health . $spawning_bonus . '</b> Health and <b>' .
					$spirit_armor . '</b> armor</span>',
					$desc
				);
		}
	}
}

// Calculation function 22:
// Return the additional weapon spell duration depending on level of Spawning Power
function add_weapon_duration( &$desc, $attr_list, $type ) {
	if ( $type == 'Weapon Spell' && isset( $attr_list['Spawning Power'] ) && $attr_list['Spawning Power'] > 0 ) {
		if ( preg_match( '@(for (?:<span class="variable">)?)([0-9]+)((?:</span>)? second)@i', $desc, $reg ) ) {
			$base_duration = $reg[2];
			$additional_duration = round( $reg[2] * $attr_list['Spawning Power'] * 0.04, 1 );
			$desc =
				str_replace(
					$reg[0],
					$reg[1] . $reg[2] . ' <span class="expert">(+' . $additional_duration . '&#041;</span>' . $reg[3],
					$desc
				);
		}
	}
}


/***************************************************************************
 * HELPER CALCULATION FUNCTIONS - PROPERTIES
 ***************************************************************************/

// Calculation function 23:
// Return the real energy cost of a skill depending on its type and level of Expertise
//  Note: There is not a field in databases/skill_db.php containing whether the skill is a touch skill or not.
function calc_expertise( $name, $attr_list, $type, $energy, $profession, $desc ) {
	if ( isset( $attr_list['Expertise'] ) && $attr_list['Expertise'] > 0 &&
		 ( $profession == 'Ranger' || strpos( $type, 'Attack' ) !== false || strpos( $type, 'Ritual' ) !== false ||
		   $name == 'Lift Enchantment' ||
		   ( preg_match( '@touch@i', $desc ) && !preg_match( '@touch skills@i', $desc ) ) ) ) {
		return round( $energy * ( 1.0 - 0.04 * $attr_list['Expertise'] ) );
	}

	// Otherwise
	return false;
}

// Calculation function 24:
// Return the real energy cost of a skill depending on its type, profession and level of Mysticism
function calc_mysticism( $attr_list, $type, $energy, $profession ) {
	if ( isset( $attr_list['Mysticism'] ) && $attr_list['Mysticism'] > 0 && $profession == 'Dervish' &&
		 strpos( $type, 'Enchantment' ) !== false ) {
		return round( $energy * ( 1.0 - 0.04 * $attr_list['Mysticism'] ) );
	}

	// Otherwise
	return false;
}

// Calculation function 25:
// Return the real cast time of a skill depending on its type and level of Fast Casting
function calc_fastcasting( $attr_list, $type, $casting, $profession ) {
	if ( ( isset( $attr_list['Fast Casting'] ) && $attr_list['Fast Casting'] > 0 ) &&
		 ( $profession == 'Mesmer' || $casting >= 2 ) ) {
		if ( strpos( $type, 'Spell' ) !== false ) {
			return $casting * pow( 2.0, ( ( $attr_list['Fast Casting'] * -1.0 ) / 15.0 ) );
		} else {
			if ( $type == 'Signet' ) {
				return $casting * ( 1.0 - ( $attr_list['Fast Casting'] * 0.03 ) );
			}
		}
	}

	// Otherwise
	return false;
}

// Calculation function 26:
// Return a skill description enriched with a div tag depending on the specified
// effect ('{target}', '{self}' or '{div}')
function add_div_tag( $effect, $desc ) {
	if ( $effect == 'target' || $effect == 'self' ) {
		// Adds the effect to the end of the tooltip
		$desc .= '{' . $effect . '}';
	} else {
		$new_desc = preg_replace( '@([0-9]+\\.\\.[0-9]+)( Health)@', '$1{div}$2', $desc, 1 );
		if ( $desc != $new_desc ) {
			$desc = $new_desc;
		} else {
			// 'Health' isn't specified :(
			$desc = preg_replace( '@([0-9]+\\.\\.[0-9]+)@', '$1{div}', $desc, 1 );
		}
	}

	return $desc;
}

// Calculation function 27:
// Return $text with each "{var_name}" replaced by $values['var_name']
function infuse_values( $text, $values ) {
	foreach ( $values as $name => $value ) {
		$text = str_replace( '{' . $name . '}', $value, $text );
	}

	return $text;
}

// Calculation function 28:
// Can't have pvp skill ids in any download links since they cannot be loaded ingame.
function getSkillIdPvE( $pvp ) {
	global $pvp_to_pve_skill_list;

	return ( isset( $pvp_to_pve_skill_list[$pvp] ) ? $pvp_to_pve_skill_list[$pvp] : $pvp );
}


/***************************************************************************
 * HELPER CALCULATION FUNCTIONS - MISCELLANEOUS
 ***************************************************************************/

// Calculation function 29:
// Restores html entities to characters, except for '<'
function html_safe_decode( $text ) {
	return str_replace( '<', '&lt;', html_entity_decode( $text ) );
}

// Calculation function 30:
// Int to bin on $bit_size bits
function int2bin( $int, $bit_size ) {
	$bin = strrev( base_convert( $int, 10, 2 ) );
	if ( $bit_size < strlen( $bin ) ) {
		return false;
	}

	// Otherwise
	return $bin . str_repeat( '0', $bit_size - strlen( $bin ) );
}

// Calculation function 31:
// Load a var from a file
function load( $filename ) {
	if ( !file_exists( $filename ) ) {
		return false;
	} else {
		return require $filename;
	}
}

// Calculation function 32:
// Organize skill by elite, profession, attribute and name (used by [rand seed="x"])
function skill_sort_cmp( $a, $b ) {
	static $prof_ids =
		[
			'Warrior',
			'Ranger',
			'Monk',
			'Necromancer',
			'Mesmer',
			'Elementalist',
			'Assassin',
			'Ritualist',
			'Paragon',
			'Dervish',
			'No Profession',
		];
	if ( $a['elite'] == $b['elite'] ) {
		$a['prof_id'] = array_search( $a['profession'], $prof_ids );
		$b['prof_id'] = array_search( $b['profession'], $prof_ids );
		if ( $a['prof_id'] == $b['prof_id'] ) {
			if ( $a['attribute'] == 'No Attribute' ) {
				$a['attribute'] = 'ZZZ';
			}
			if ( $b['attribute'] == 'No Attribute' ) {
				$b['attribute'] = 'ZZZ';
			}

			if ( preg_replace( '|[\'! ]|', '', strtolower( $a['attribute'] ) ) ==
				 preg_replace( '|[\'! ]|', '', strtolower( $b['attribute'] ) ) ) {
				if ( preg_replace( '|[\'!]|', '', strtolower( $a['name'] ) ) ==
					 preg_replace( '|[\'!]|', '', strtolower( $b['name'] ) ) ) {
					return 0;
				}

				return ( preg_replace( '|[\'!]|', '', strtolower( $a['name'] ) ) <
						 preg_replace( '|[\'!]|', '', strtolower( $b['name'] ) ) ) ? -1 : 1;
			}

			return ( preg_replace( '|[\'! ]|', '', strtolower( $a['attribute'] ) ) <
					 preg_replace( '|[\'! ]|', '', strtolower( $b['attribute'] ) ) ) ? -1 : 1;
		}

		return ( $a['prof_id'] < $b['prof_id'] ) ? -1 : 1;
	}

	return ( $a['elite'] > $b['elite'] ) ? -1 : 1;
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
	$tpl_array = file( TEMPLATE_PATH );

	// Trim each line
	$tpl = '';
	foreach ( $tpl_array as $line ) {
		$tpl .= trim( $line );
	}

	// Replace \ with \\ and then ' with \'.
	$tpl = str_replace( '\\', '\\\\', $tpl );
	$tpl = str_replace( '\'', '\\\'', $tpl );

	// Strip newlines.
	$tpl = str_replace( "\n", '', $tpl );

	// Turn template blocks into PHP assignment statements for the values of $gwbbcode_tpls..
	$tpl =
		preg_replace(
			'#<!-- BEGIN (.*?) -->(.*?)<!-- END (.*?) -->#',
			"\n" . '$gwbbcode_tpls[\'\\1\'] = clean_tpl(\'\\2\');',
			$tpl
		);

	$gwbbcode_tpls = [];

	eval( $tpl );

	return $gwbbcode_tpls;
}

// Smarty template function 2:
// Return a string after removing all its whitespaces at begining of lines, and its newlines
function clean_tpl( $tpl ) {
	return preg_replace( '@[\r\n][ ]*@', '', $tpl );
}


/***************************************************************************
 * GW TEMPLATE CONVERSION FUNCTIONS
 ***************************************************************************/

// GW template conversion function 1:
function binval( $bin ) {
	return intval( base_convert( strrev( $bin ), 2, 10 ) );
}

// GW template conversion function 2:
// Return a binary string based on the $text template_id
function template_to_bin( $text ) {
	static $conv_table =
		[
			'A' => 0,
			'B' => 1,
			'C' => 2,
			'D' => 3,
			'E' => 4,
			'F' => 5,
			'G' => 6,
			'H' => 7,
			'I' => 8,
			'J' => 9,
			'K' => 10,
			'L' => 11,
			'M' => 12,
			'N' => 13,
			'O' => 14,
			'P' => 15,
			'Q' => 16,
			'R' => 17,
			'S' => 18,
			'T' => 19,
			'U' => 20,
			'V' => 21,
			'W' => 22,
			'X' => 23,
			'Y' => 24,
			'Z' => 25,
			'a' => 26,
			'b' => 27,
			'c' => 28,
			'd' => 29,
			'e' => 30,
			'f' => 31,
			'g' => 32,
			'h' => 33,
			'i' => 34,
			'j' => 35,
			'k' => 36,
			'l' => 37,
			'm' => 38,
			'n' => 39,
			'o' => 40,
			'p' => 41,
			'q' => 42,
			'r' => 43,
			's' => 44,
			't' => 45,
			'u' => 46,
			'v' => 47,
			'w' => 48,
			'x' => 49,
			'y' => 50,
			'z' => 51,
			'0' => 52,
			'1' => 53,
			'2' => 54,
			'3' => 55,
			'4' => 56,
			'5' => 57,
			'6' => 58,
			'7' => 59,
			'8' => 60,
			'9' => 61,
			'+' => 62,
			'/' => 63,
		];
	$ret = '';
	foreach ( preg_split( '//', trim( $text ), - 1, PREG_SPLIT_NO_EMPTY ) as $char ) {
		// Handle invalid characters
		if ( !isset( $conv_table[$char] ) ) {
			return false;
		}
		$bin = strrev( base_convert( $conv_table[$char], 10, 2 ) );
		$ret .= $bin . str_repeat( '0', 6 - strlen( $bin ) );
	}

	return $ret;
}

// GW template conversion function 3:
// Return gwbbcode based on the $text template id
function template_to_gwbbcode( $text ) {
	static $prof_ids = [ '?', 'W', 'R', 'Mo', 'N', 'Me', 'E', 'A', 'Rt', 'P', 'D' ];
	static $attr_ids =
		[
			'fas',
			'ill',
			'dom',
			'ins',
			'blo',
			'death',
			'sou',
			'cur',
			'air',
			'ear',
			'fir',
			'wat',
			'ene',
			'hea',
			'smi',
			'pro',
			'div',
			'str',
			'axe',
			'ham',
			'swo',
			'tac',
			'bea',
			'exp',
			'wil',
			'mar',
			29 => 'dag',
			'dead',
			'sha',
			'com',
			'res',
			'cha',
			'cri',
			'spa',
			'spe',
			'comma',
			'mot',
			'lea',
			'scy',
			'win',
			'earthp',
			'mys',
		];

	// Handle the [build name;build code] syntax
	$build_name = '';
	if ( preg_match( '@([^][]+);([^];]+)@', $text, $reg ) ) {
		$build_name = $reg[1];
		$text = $reg[2];
	}

	$bin = template_to_bin( $text );
	if ( $bin === false ) {
		return 'Invalid characters found';
	}

	// Handle the new format (i.e leading '0111')
	if ( preg_match( '@^0111@', $bin ) ) {
		$bin = substr( $bin, 4 );
	}

	$ret = '';
	if ( !preg_match( '@^([01]{6})([01]{4})([01]{4})([01]{4})([01]{4})@', $bin, $reg ) ) {
		return 'Couldn\'t read professions nor attribute count and size';
	}
	$bin = preg_replace( '@^([01]{6})([01]{4})([01]{4})([01]{4})([01]{4})@', '', $bin );

	// Make sure template begins with '000000'
	if ( $reg[1] != '000000' ) {
		return 'First 6 bits are invalid';
	}

	// Primary profession
	$primary_id = binval( $reg[2] );
	if ( !isset( $prof_ids[$primary_id] ) ) {
		return 'Invalid primary profession';
	}
	$primary = $prof_ids[$primary_id];

	// Secondary profession
	$secondary_id = binval( $reg[3] );
	if ( !isset( $prof_ids[$secondary_id] ) ) {
		return 'Invalid secondary profession';
	}
	$secondary = $prof_ids[$secondary_id];

	// Create prof=?/?
	$ret .= "[build prof=$primary/$secondary";

	// Add clean build name if any
	if ( !empty( $build_name ) ) {
		$ret .= ' name="' . str_replace( '"', "''", $build_name ) . '"';
	}

	// Manage attributes
	$attr_count = binval( $reg[4] );
	$attr_size = 4 + binval( $reg[5] );
	for ( $i = 0; $i < $attr_count; $i ++ ) {
		if ( !preg_match( '@^([01]{' . $attr_size . '})([01]{4})@', $bin, $reg2 ) ) {
			return 'Couldn\'t read attribute id and value';
		}
		$bin = preg_replace( '@^([01]{' . $attr_size . '})([01]{4})@', '', $bin );

		// Attribute name
		$attr_id = binval( $reg2[1] );
		if ( !isset( $attr_ids[$attr_id] ) ) {
			return "Invalid attribute id: $attr_id";
		}
		$attr_name = $attr_ids[$attr_id];

		// Attribute value
		$attr_value = binval( $reg2[2] );
		if ( $attr_value > 12 ) {
			return 'An attribute value can\'t be higher than 12';
		}

		// Create attr=10
		$ret .= " $attr_name=$attr_value";
	}
	$ret .= ']';

	// Skills
	if ( !preg_match( '@^([01]{4})@', $bin, $reg2 ) ) {
		return 'Couldn\'t get skill id size';
	}
	$bin = preg_replace( '@^([01]{4})@', '', $bin );

	// Skill size
	$skill_size = 8 + binval( $reg2[1] );
	for ( $i = 0; $i < 8; $i ++ ) {
		if ( !preg_match( '@^([01]{' . $skill_size . '})@', $bin, $reg2 ) ) {
			return 'Couldn\'t read skill id';
		}
		$bin = preg_replace( '@^([01]{' . $skill_size . '})@', '', $bin );

		// Skill name
		$skill_id = binval( $reg2[1] );
		$skill = gws_details( $skill_id );
		$skill_name = $skill['name'];
		if ( $skill === false ) {
			$ret .= "[Unknown skill id $skill_id]";
		} else {
			$ret .= '[' . $skill['name'] . ']';
		}
	}

	return $ret . '[/build]';
}

// GW template conversion function 4:
// Return a template string based on the $bin binary string
function bin_to_template( $bin ) {
	static $conv_table =
		[
			'A' => 0,
			'B' => 1,
			'C' => 2,
			'D' => 3,
			'E' => 4,
			'F' => 5,
			'G' => 6,
			'H' => 7,
			'I' => 8,
			'J' => 9,
			'K' => 10,
			'L' => 11,
			'M' => 12,
			'N' => 13,
			'O' => 14,
			'P' => 15,
			'Q' => 16,
			'R' => 17,
			'S' => 18,
			'T' => 19,
			'U' => 20,
			'V' => 21,
			'W' => 22,
			'X' => 23,
			'Y' => 24,
			'Z' => 25,
			'a' => 26,
			'b' => 27,
			'c' => 28,
			'd' => 29,
			'e' => 30,
			'f' => 31,
			'g' => 32,
			'h' => 33,
			'i' => 34,
			'j' => 35,
			'k' => 36,
			'l' => 37,
			'm' => 38,
			'n' => 39,
			'o' => 40,
			'p' => 41,
			'q' => 42,
			'r' => 43,
			's' => 44,
			't' => 45,
			'u' => 46,
			'v' => 47,
			'w' => 48,
			'x' => 49,
			'y' => 50,
			'z' => 51,
			'0' => 52,
			'1' => 53,
			'2' => 54,
			'3' => 55,
			'4' => 56,
			'5' => 57,
			'6' => 58,
			'7' => 59,
			'8' => 60,
			'9' => 61,
			'+' => 62,
			'/' => 63,
		];
	$ret = '';
	$bin .= str_repeat( '0', 6 - ( strlen( $bin ) % 6 ) );
	while ( !empty( $bin ) ) {
		$digit = substr( $bin, 0, 6 );
		$bin = substr( $bin, 6 );
		$ret .= array_search( base_convert( strrev( $digit ), 2, 10 ), $conv_table );
	}

	return $ret;
}

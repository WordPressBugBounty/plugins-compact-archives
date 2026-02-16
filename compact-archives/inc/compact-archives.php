<?php

/**
 * Bootstrap the plugin.
 */
function wpb_caw_bootstrap() {
	add_shortcode( 'compact_archive', 'compact_archives_shortcode' );

	// Create widget and then hide it from the WP 5.8 screen.
	add_action( 'widgets_init', 'wpb_caw_load_widget' );
	add_filter( 'widget_types_to_hide_from_legacy_widget_block', 'wpb_caw_hide_legacy_widget' );

	// Stop bootrapping of block editor does not exist.
	if ( ! has_action( 'enqueue_block_editor_assets' ) ) {
		return;
	}

	// Enqueue the scripts for the WPBeginner Compact Archive block.
	add_action( 'enqueue_block_editor_assets', 'load_wpbca_block_files' );

	// Register the functions for rendering the WPBeginner Compact Archive dynamic block.
	add_action( 'init', 'wpb_compact_archive_block' );
}

/**
 * Display the compact archive.
 *
 * Echos the compact archive HTML to the page. This is a wrapper of the
 * `get_compact_archive()` function.
 *
 * @param string $style  How to display the month name, one of three options.
 *                       * First letter of month, eg J, `initial` (default).
 *                       * Short name of month, eg Jan, `block`.
 *                       * Month number, eg 01, `numeric`.
 * @param string $before HTML to place before each item, default `<li>`.
 * @param string $after  HTML to place after each item, default `</li>`.
 */
function compact_archive( $style = 'initial', $before = '<li>', $after = '</li>' ) {
	// Escaping takes place in the getter function.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo get_compact_archive( $style, $before, $after );
}

/********************************************************************************************************
	Stuff below this point is not meant to be used directly
*********************************************************************************************************/

/**
 * Add the compact archives widget to those unavailable in the legacy block.
 *
 * @param array $unavailable_widget_types Widgets unavailable as a legacy block.
 * @return array Unavailable widgets with CAW included.
 */
function wpb_caw_hide_legacy_widget( $unavailable_widget_types ) {
	$unavailable_widget_types[] = 'wpb-caw-widget';
	return $unavailable_widget_types;
}

/**
 * Get the compact archive date data.
 *
 * @return array Compact archive date data.
 */
function wpbca_get_archive_data() {
	global $wpdb;
	$last_changed = wp_cache_get_last_changed( 'posts' );
	$key          = "dates::$last_changed";
	$group        = 'wpb_compact_archives';
	$found        = null;
	$dates        = wp_cache_get( $key, $group, false, $found );

	if ( ! $found ) {
		$results = $wpdb->get_results( 'SELECT DISTINCT YEAR(post_date) AS year, MONTH(post_date) AS month FROM ' . $wpdb->posts . " WHERE post_type='post' AND post_status='publish' AND post_password='' ORDER BY year DESC, month DESC" );
		$dates   = array();
		if ( $results ) {
			foreach ( $results as $result ) {
				$dates[ $result->year ][ $result->month ] = 1;
			}
			unset( $results, $result );
		}

		wp_cache_set( $key, $dates, $group );
	}

	return empty( $dates ) ? array() : $dates;
}

/**
 * Generate the HTML for the compact archive.
 *
 * @param string $style  How to display the month name, one of three options.
 *                       * First letter of month, eg J, `initial` (default).
 *                       * Short name of month, eg Jan, `block`.
 *                       * Month number, eg 01, `numeric`.
 * @param string $before HTML to place before each item, default `<li>`.
 * @param string $after  HTML to place after each item, default `</li>`.
 * @return string The HTML of the compact archive.
 */
function get_compact_archive( $style = 'initial', $before = '<li>', $after = '</li>' ) {
	$dates = wpbca_get_archive_data();

	if ( empty( $dates ) ) {
		return wp_kses_post( $before . __( 'Archive is empty' ) . $after );
	}
	$result = '';
	foreach ( $dates as $year => $months ) {
		$result .= $before . '<strong><a href="' . esc_url( get_year_link( $year ) ) . '">' . esc_html( $year ) . '</a>: </strong> ';
		for ( $month = 1; $month <= 12; $month++ ) {
			$month_has_posts = ( isset( $months[ $month ] ) );
			$dummydate       = strtotime( "2001-$month-1" );
			// get the month name; date_i18n() localizes
			$month_name = date_i18n( 'F', $dummydate );
			switch ( $style ) {
				case 'block':
					$month_abbrev = date_i18n( 'M', $dummydate ); // get the short month name; date_i18n() localizes
					break;
				case 'numeric':
					$month_abbrev = date_i18n( 'm', $dummydate ); // get the month number, e.g., '04'
					break;
				case 'initial':
				default:
					$month_abbrev = $month_name[0]; // the initial of the month
					break;
			}
			if ( $month_has_posts ) {
				$result .= '<a href="' . esc_url( get_month_link( $year, $month ) ) . '" title="' . esc_attr( date_i18n( 'F Y', $dummydate ) ) . '">' . esc_html( $month_abbrev ) . '</a> ';
			} else {
				$result .= '<span class="emptymonth">' . esc_html( $month_abbrev ) . '</span> ';
			}
		}
		$result .= $after . "\n";
	}
	return wp_kses_post( $result );
}

/**
 * Builds the compact archive shortcode.
 *
 * Builds the HTML for the `compact_archive` shortcode
 *
 * @param array $atts {
 *     Attributes of the compact archive shortcode.
 *
 *     @type string $style  How to display the month name, one of three options.
 *                       * First letter of month, eg J, `initial` (default).
 *                       * Short name of month, eg Jan, `block`.
 *                       * Month number, eg 01, `numeric`.
 *     @type string $before HTML to place before each item, default `<li>`.
 *     @type string $after  HTML to place after each item, default `</li>`.
 * }
 * @return string HTML to display the shortcode.
 */
function compact_archives_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'style'     => 'initial',
			'before'    => '<li>',
			'after'     => '</li>',
			'classname' => '',
		),
		$atts
	);

	// Validate style against allowlist.
	$allowed_styles = array( 'initial', 'block', 'numeric' );
	if ( ! in_array( $atts['style'], $allowed_styles, true ) ) {
		$atts['style'] = 'initial';
	}

	// Allowed wrapper tags.
	$allowed_tags = array( 'li', 'p', 'div', 'span' );

	// Normalize input: trim, lowercase, decode HTML entities, strip </p> from editor.
	$before_normalized = strtolower( trim( html_entity_decode( $atts['before'] ) ) );
	$before_normalized = trim( str_ireplace( '</p>', '', $before_normalized ) );

	// Extract tag name (strip < > / to support both "div" and "<div>").
	$tag_name = preg_replace( '/[<>\/]/', '', $before_normalized );

	if ( ! in_array( $tag_name, $allowed_tags, true ) ) {
		$tag_name = 'li';
	}

	// Sanitize classname: split by space, sanitize each, rejoin.
	$class_attr = '';
	if ( ! empty( $atts['classname'] ) ) {
		$classes = preg_split( '/\s+/', trim( $atts['classname'] ) );
		$sanitized_classes = array_map( 'sanitize_html_class', $classes );
		$sanitized_classes = array_filter( $sanitized_classes ); // Remove empty.
		if ( ! empty( $sanitized_classes ) ) {
			$class_attr = ' class="' . esc_attr( implode( ' ', $sanitized_classes ) ) . '"';
		}
	}

	$wrap     = '';
	$wrap_end = '';

	if ( 'li' === $tag_name ) {
		// For li, apply classname to the ul container.
		$atts['before'] = '<' . $tag_name . '>';
		$wrap           = '<ul' . $class_attr . ' style="list-style-type: none; margin-top: 10px; margin-bottom: 20px;">';
		$wrap_end       = '</ul>';
	} else {
		// For other tags, apply classname to the before tag.
		$atts['before'] = '<' . $tag_name . $class_attr . '>';
	}

	$atts['after'] = '</' . $tag_name . '>';

	return $wrap . get_compact_archive( $atts['style'], $atts['before'], $atts['after'] ) . $wrap_end;
}

/**
 * Register the compact archive widget.
 *
 * Props Aldo Latino http://www.aldolat.it/ for original widget.
 */
function wpb_caw_load_widget() {
	register_widget( 'WPBeginner_CAW_Widget' );
}

/**
 * Enqueue the block editor scripts and styles.
 */
function load_wpbca_block_files() {
	global $wp_version;
	if ( version_compare( $wp_version, '5.2.0', 'lt' ) ) {
		$shim_entry_point = 'wpFiveZero';
	} else {
		$shim_entry_point = 'wpFiveTwo';
	}

	/*
	 * Shim for items relocated from @wordpress/editor to @wordpress/block-editor
	 * in WordPress 5.2.
	 *
	 * Two core components used in the compact archives custom block were relocated in
	 * WordPress 5.2. This shim is used to allow the plugin to continue to support
	 * WordPress 5.0 and 5.1 through an intermediate JS file.
	 *
	 * The file loaded depends on the version of WordPress used.
	 * - WP 5.0 - 5.1 entry point: wpFiveZero
	 * - WP 5.2 + entry point: wpFiveTwo
	 */
	$shim_details_path = plugin_dir_path( __DIR__ ) . "assets/build/{$shim_entry_point}.asset.php";
	$shim_asset_data   = array(
		'dependencies' => array(),
		'version'      => microtime(),
	);
	// Production/after build.
	if ( file_exists( $shim_details_path ) ) {
		$shim_asset_data = include $shim_details_path;
	}
	$shim_script_deps = $shim_asset_data['dependencies'];
	$shim_version     = $shim_asset_data['version'];
	wp_register_script(
		'wpb-compact-archive-block-shims',
		plugins_url( "assets/build/{$shim_entry_point}.js", __DIR__ ),
		$shim_script_deps,
		$shim_version,
		true
	);

	/*
	 * Data for JavaScript and CSS files.
	 *
	 * This pulls in the data generated by @wordpress/dependency-extraction-webpack-plugin.
	 *
	 * The version hash takes in to account both the JavaScript and CSS files that are generated
	 * so can safely be used for both.
	 */
	$asset_details_path = plugin_dir_path( __DIR__ ) . 'assets/build/blocks.asset.php';
	// Fallback during development.
	$block_asset_data = array(
		'dependencies' => array(),
		'version'      => microtime(),
	);
	// Production/after build.
	if ( file_exists( $asset_details_path ) ) {
		$block_asset_data = include $asset_details_path;
	}
	$block_script_deps   = $block_asset_data['dependencies'];
	$block_script_deps[] = 'wpb-compact-archive-block-shims';
	$block_version       = $block_asset_data['version'];

	// Scripts.
	wp_enqueue_script(
		'wpb-compact-archive-block-script',
		plugins_url( 'assets/build/blocks.js', __DIR__ ),
		$block_script_deps,
		$block_version,
		true
	);
	wp_localize_script(
		'wpb-compact-archive-block-script',
		'wpbca_block_vars',
		array(
			'ca_initial' => get_compact_archive( 'initial' ),
			'ca_block'   => get_compact_archive( 'block' ),
			'ca_numeric' => get_compact_archive( 'numeric' ),
		)
	);

	// Styles.
	wp_enqueue_style(
		'wpb-compact-archive-block-style',
		plugins_url( '/assets/build/blocks.css', __DIR__ ),
		array(),
		$block_version
	);
}

/**
 * Register the dynamic callback for the block.
 *
 * PHP registration of the compact archive block.
 */
function wpb_compact_archive_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	register_block_type(
		'wpb-compact-archive/wpb-compact-archive-block',
		array(
			'editor_script'   => 'wpb-compact-archive-block-script',
			'render_callback' => 'wpb_compact_archive_block_render_callback',
		)
	);
}

/**
 * Render the gutenberg block content.
 *
 * @param array  $attributes The block attributes selected by the user.
 * @param string $content    The content from saving the function. For the compact
 *                           archives block the content in empty.
 * @return string The HTML for rendering the compact archive block.
 */
function wpb_compact_archive_block_render_callback( $attributes, $content ) {
	// Set defaults.
	if ( ! isset( $attributes['compact_archive_type'] ) ) {
		$attributes['compact_archive_type'] = 'block';
	}
	if ( ! isset( $attributes['compact_archive_text_case'] ) ) {
		$attributes['compact_archive_text_case'] = 'none';
	}
	if ( ! isset( $attributes['compact_archive_title'] ) ) {
		$attributes['compact_archive_title'] = '';
	}

	// Validate archive type against allowlist.
	$allowed_types = array( 'initial', 'block', 'numeric' );
	if ( ! in_array( $attributes['compact_archive_type'], $allowed_types, true ) ) {
		$attributes['compact_archive_type'] = 'block';
	}

	// Validate text case against allowlist.
	$allowed_text_cases = array( 'none', 'capitalize', 'uppercase' );
	if ( ! in_array( $attributes['compact_archive_text_case'], $allowed_text_cases, true ) ) {
		$attributes['compact_archive_text_case'] = 'none';
	}

	// Default styles.
	$style = ( ! empty( $attributes['compact_archive_title'] ) ) ? 'list-style-type: none; margin-top: 10px; margin-bottom 20px;' : 'list-style-type: none; margin-top: 20px; margin-bottom 20px;';

	// Chosen styles.
	if ( 'capitalize' === $attributes['compact_archive_text_case'] ) {
		$style .= 'text-transform: capitalize;';
	} elseif ( 'uppercase' === $attributes['compact_archive_text_case'] ) {
		$style .= 'text-transform: uppercase;';
	}

	// Sanitize title with restrictive allowlist matching RichText formatting controls.
	$title_html = '';
	if ( ! empty( $attributes['compact_archive_title'] ) ) {
		$allowed_title_html = array(
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'a'      => array(
				'href'  => true,
				'title' => true,
				'rel'   => true,
			),
		);
		$title_html = '<p>' . wp_kses( $attributes['compact_archive_title'], $allowed_title_html ) . '</p>';
	}

	return $title_html . '<ul style="' . esc_attr( $style ) . '">' . get_compact_archive( $attributes['compact_archive_type'] ) . '</ul>';
}

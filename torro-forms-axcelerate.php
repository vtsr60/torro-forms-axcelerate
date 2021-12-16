<?php
/**
 * Plugin initialization file
 *
 * @package TorroFormsaXcelerate
 * @since 1.0.0
 *
 * @wordpress-plugin
 * Plugin Name: Torro Forms Plugin aXcelerate
 * Plugin URI:  https://github.com/vtsr60/torro-forms-axcelerate
 * Description: Torro Forms Plugin create contact in aXcelerate SMS.
 * Version:     1.0.0
 * Author:      Raj Vivakaran
 * Author URI:  https://github.com/vtsr60
 * License:     GNU General Public License v2 (or later)
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: torro-forms-axcelerate
 * Domain Path: /languages/
 * Tags:        extension, torro forms, forms, form builder, api, aXcelerate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the extension.
 *
 * To retrieve the extension instance from the outside, third-party developers
 * have to call `torro()->extensions()->get( 'torro_forms_axcelerate' )`.
 *
 * @since 1.0.0
 *
 * @param Torro_Forms $torro Main plugin instance.
 * @return bool|WP_Error True on success, error object on failure.
 */
function torro_forms_axcelerate_load( $torro ) {
	// Require main extension class file. All other classes will be autoloaded.
	require_once dirname( __FILE__ ) . '/src/extension.php';
	require_once dirname( __FILE__ ) . '/src/actions/axcelerate_contact.php';
	require_once dirname( __FILE__ ) . '/src/title-choice-element-type-trait.php';
	require_once dirname( __FILE__ ) . '/src/element-types/dropdownwithtitle.php';
	require_once dirname( __FILE__ ) . '/src/element-types/onechoicewithtitle.php';
	require_once dirname( __FILE__ ) . '/src/element-types/multiplechoicewithtitle.php';


	// Use a string here for the extension class name so that this file can be parsed by PHP 5.2.
	$class_name = 'TFaXcelerate\TorroFormsaXcelerate\Extension';

	// Store the main extension file.
	$main_file = __FILE__;

	// Determine the relative basedir (will be empty unless a must-use plugin).
	$basedir_relative = '';
	$file             = wp_normalize_path( $main_file );
	$mu_plugin_dir    = wp_normalize_path( WPMU_PLUGIN_DIR );
	if ( preg_match( '#^' . preg_quote( $mu_plugin_dir, '#' ) . '/#', $file ) && file_exists( $mu_plugin_dir . '/torro-forms-axcelerate.php' ) ) {
		$basedir_relative = 'torro-forms-axcelerate/';
	}

	$result = $torro->extensions()->register( 'torro_forms_axcelerate', $class_name, $main_file, $basedir_relative );

	if ( is_wp_error( $result ) ) {
		$method = get_class( $torro->extensions() ) . '::register()';
		$torro->error_handler()->doing_it_wrong( $method, $result->get_error_message(), null );
	}

	return $result;
}

if ( function_exists( 'torro_load' ) ) {
	torro_load( 'torro_forms_axcelerate_load' );
} else {
	add_action( 'torro_loaded', 'torro_forms_axcelerate_load', 10, 1 );
}

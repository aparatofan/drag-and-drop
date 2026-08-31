<?php
/**
 * Plugin Name:       TBT Drag & Drop
 * Plugin URI:        https://github.com/aparatofan/drag-and-drop
 * Description:       Build gap-fill drag-and-drop exercises on the front end, publish them at their own URL, and embed them with a shortcode.
 * Version:           2.2.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Mariusz Mirecki
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tbt-drag-drop
 */

namespace TBT\DragDrop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TBTDD_VERSION', '2.2.0' );
define( 'TBTDD_FILE', __FILE__ );
define( 'TBTDD_DIR', plugin_dir_path( __FILE__ ) );
define( 'TBTDD_URL', plugin_dir_url( __FILE__ ) );

$tbtdd_includes = array(
	'class-activator.php',
	'class-access.php',
	'class-post-type.php',
	'class-exercise-validator.php',
	'class-exercise-repository.php',
	'class-assets.php',
	'class-renderer.php',
	'class-shortcode.php',
	'class-tools-shortcode.php',
	'class-template-loader.php',
	'class-exercises-controller.php',
	'class-admin.php',
	'class-plugin.php',
);

foreach ( $tbtdd_includes as $tbtdd_include ) {
	require_once TBTDD_DIR . 'includes/' . $tbtdd_include;
}

/**
 * Register this plugin on the TBT Hub overview page.
 *
 * Registered at file scope so it loads regardless of admin context.
 *
 * The card points at the front-end tool page, which is site content rather
 * than something the plugin owns, so its address is read from an option. With
 * no page recorded yet the card still has somewhere useful to go: the wp-admin
 * exercise list, which is where authoring lived before this release.
 *
 * @param array $items Existing hub items.
 * @return array
 */
function register_hub_item( $items ) {
	$url = (string) get_option( 'tbtdd_tool_page_url', '' );

	$items[] = array(
		'slug'        => 'tbt-drag-drop',
		'title'       => 'TBT Drag & Drop',
		'description' => 'Gap-fill drag-and-drop exercises: teachers build them on the front end and students play from a link.',
		'capability'  => Access::CAPABILITY,
		'url'         => '' !== $url ? $url : admin_url( 'edit.php?post_type=dd_exercise' ),
	);

	return $items;
}
add_filter( 'tbt_hub_items', __NAMESPACE__ . '\\register_hub_item' );

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Activator::class, 'deactivate' ) );

Plugin::instance()->boot();

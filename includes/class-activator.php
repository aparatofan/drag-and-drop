<?php
/**
 * Activation and deactivation hooks.
 *
 * @package TBT_Drag_Drop
 */

namespace TBT\DragDrop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {
	/**
	 * Option holding the version the rewrite rules were last flushed for.
	 */
	public const REWRITE_OPTION = 'tbtdd_rewrite_version';

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Post_Type::register();
		Access::add_caps();
		update_option( 'tbtdd_caps_version', Access::CAPS_VERSION );
		flush_rewrite_rules();
		update_option( self::REWRITE_OPTION, TBTDD_VERSION );
	}

	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Flush once when the plugin is updated in place.
	 *
	 * Activation does not fire for an already-active plugin, so an install
	 * updated over FTP would keep the old rules and every /drag-and-drop/ URL
	 * would 404 until permalinks were re-saved by hand. Runs on init, after
	 * Post_Type::register(), so the rules being written include this post type.
	 *
	 * @return void
	 */
	public static function maybe_flush_rewrites(): void {
		if ( get_option( self::REWRITE_OPTION ) === TBTDD_VERSION ) {
			return;
		}

		flush_rewrite_rules();
		update_option( self::REWRITE_OPTION, TBTDD_VERSION );
	}
}

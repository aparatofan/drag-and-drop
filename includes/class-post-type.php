<?php
/**
 * Custom post type registration.
 *
 * @package TBT_Drag_Drop
 */

namespace TBT\DragDrop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Post_Type {
	/**
	 * Unchanged since 1.0.0: this is stored data, not a name we are free to
	 * pick. Renaming it would orphan every exercise on the live site.
	 */
	public const POST_TYPE = 'dd_exercise';

	/**
	 * Public rewrite slug for the standalone exercise page.
	 */
	public const SLUG = 'drag-and-drop';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_block_editor' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
	}

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = array(
			'name'               => __( 'Drag & Drop', 'tbt-drag-drop' ),
			'singular_name'      => __( 'Drag & Drop Exercise', 'tbt-drag-drop' ),
			'menu_name'          => __( 'Drag & Drop', 'tbt-drag-drop' ),
			'name_admin_bar'     => __( 'Drag & Drop Exercise', 'tbt-drag-drop' ),
			'add_new'            => __( 'Add New', 'tbt-drag-drop' ),
			'add_new_item'       => __( 'Add New Exercise', 'tbt-drag-drop' ),
			'new_item'           => __( 'New Exercise', 'tbt-drag-drop' ),
			'edit_item'          => __( 'Edit Exercise', 'tbt-drag-drop' ),
			'view_item'          => __( 'View Exercise', 'tbt-drag-drop' ),
			'all_items'          => __( 'All Exercises', 'tbt-drag-drop' ),
			'search_items'       => __( 'Search Exercises', 'tbt-drag-drop' ),
			'not_found'          => __( 'No exercises found.', 'tbt-drag-drop' ),
			'not_found_in_trash' => __( 'No exercises found in Trash.', 'tbt-drag-drop' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				// Nest under the TBT hub menu when it is active; fall back to a
				// top-level menu of its own when the hub is deactivated.
				'show_in_menu'        => defined( 'TBT_HUB_SLUG' ) ? TBT_HUB_SLUG : true,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => array(
					'slug'       => self::SLUG,
					'with_front' => false,
				),
				'supports'            => array( 'title', 'author', 'revisions' ),
				'menu_icon'           => 'dashicons-editor-ol',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Disable the block editor for exercises.
	 *
	 * The exercise lives in meta and is edited by the meta box; a block canvas
	 * beside it would offer a second, meaningless place to type.
	 *
	 * @param bool   $use_block_editor Current choice.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public function disable_block_editor( bool $use_block_editor, string $post_type ): bool {
		return self::POST_TYPE === $post_type ? false : $use_block_editor;
	}

	/**
	 * Change the title placeholder.
	 *
	 * @param string   $placeholder Existing placeholder.
	 * @param \WP_Post $post Current post.
	 * @return string
	 */
	public function title_placeholder( string $placeholder, \WP_Post $post ): string {
		if ( self::POST_TYPE === $post->post_type ) {
			return __( 'Exercise title', 'tbt-drag-drop' );
		}

		return $placeholder;
	}
}

<?php
/**
 * Front-end access control for the teaching tools.
 *
 * The custom post type keeps its default capability model (edit_posts and
 * friends). Nothing here changes that: the front-end tools never rely on the
 * post type capabilities, they check this gate plus post_author ownership
 * explicitly, so wp-admin behaviour is untouched.
 *
 * @package TBT_Drag_Drop
 */

namespace TBT\DragDrop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Access {
	/**
	 * The capability that marks a user as a paying teacher.
	 *
	 * Shared across the TBT Teaching Tools rather than specific to this plugin,
	 * so granting it once opens every tool. Deliberately identical to the name
	 * TBT Matching Games uses: a teacher who has it must get this tool with
	 * nothing to configure.
	 */
	public const CAPABILITY = 'tbt_use_teaching_tools';

	/**
	 * TBT Swipe's own teacher capability.
	 *
	 * Swipe shipped first and its capability is what a teacher role on the live
	 * site already carries. Honouring it here means a teacher who can reach
	 * Swipe can reach this tool too, with nothing to configure — the tools are
	 * one product to the teacher, so one grant should open both.
	 */
	public const SWIPE_CAPABILITY = 'tbts_manage';

	/**
	 * The option Swipe stores its granted roles in.
	 */
	private const SWIPE_ROLES_OPTION = 'tbt_swipe_manager_roles';

	/**
	 * Bumped when the capability set changes so existing installs pick it up.
	 * register_activation_hook does not fire for an already-active plugin, so
	 * activation alone is not enough.
	 */
	public const CAPS_VERSION = '1';

	/**
	 * Option holding the granted capability version.
	 */
	private const CAPS_OPTION = 'tbtdd_caps_version';

	/**
	 * Can the current user use the front-end teaching tools?
	 *
	 * Logged-out visitors are always denied. For everyone else the capability
	 * decides, and the result passes through a filter so a membership or
	 * WooCommerce check can be wired in from a snippet without this plugin
	 * taking a dependency on either.
	 *
	 * @return bool
	 */
	public static function can_use_tools(): bool {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$allowed = current_user_can( self::CAPABILITY )
			|| current_user_can( self::SWIPE_CAPABILITY )
			|| current_user_can( 'manage_options' );

		/**
		 * Filter whether a user may use the front-end teaching tools.
		 *
		 * @param bool $allowed Capability result.
		 * @param int  $user_id Current user ID.
		 */
		return (bool) apply_filters( 'tbt_drag_drop_can_use_tools', $allowed, $user_id );
	}

	/**
	 * Does the current user own an exercise?
	 *
	 * @param int $post_id Exercise post ID.
	 * @return bool
	 */
	public static function owns( int $post_id ): bool {
		$user_id = get_current_user_id();
		if ( ! $user_id || ! $post_id ) {
			return false;
		}

		return (int) get_post_field( 'post_author', $post_id ) === $user_id;
	}

	/**
	 * Can the current user edit an exercise through the front end?
	 *
	 * @param int $post_id Exercise post ID.
	 * @return bool
	 */
	public static function can_edit( int $post_id ): bool {
		if ( ! self::can_use_tools() ) {
			return false;
		}

		return self::owns( $post_id ) || current_user_can( 'manage_options' );
	}

	/**
	 * Grant the capability to the administrator role and every Swipe role.
	 *
	 * @return void
	 */
	public static function add_caps(): void {
		$roles = array( 'administrator' );

		// Any role already trusted with Swipe is a teacher role here too.
		$swipe_roles = get_option( self::SWIPE_ROLES_OPTION, array() );
		if ( is_array( $swipe_roles ) ) {
			$roles = array_merge( $roles, array_map( 'strval', $swipe_roles ) );
		}

		$roles = array_unique( (array) apply_filters( 'tbt_drag_drop_tool_roles', $roles ) );
		foreach ( $roles as $role_slug ) {
			$role = get_role( (string) $role_slug );
			if ( $role && ! $role->has_cap( self::CAPABILITY ) ) {
				$role->add_cap( self::CAPABILITY );
			}
		}
	}

	/**
	 * Grant the caps once per capability version.
	 *
	 * @return void
	 */
	public static function maybe_add_caps(): void {
		if ( get_option( self::CAPS_OPTION ) === self::CAPS_VERSION ) {
			return;
		}

		self::add_caps();
		update_option( self::CAPS_OPTION, self::CAPS_VERSION );
	}
}

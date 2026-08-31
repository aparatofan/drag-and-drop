<?php
/**
 * Front-end teaching tool shortcodes.
 *
 * [tbt_drag_generator] builds and edits an exercise, [tbt_drag_exercises] lists
 * the teacher's own exercises. Both are gated the same way and share one asset
 * bundle, so they can live on one Divi page or two.
 *
 * @package TBT_Drag_Drop
 */

namespace TBT\DragDrop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Tools_Shortcode {
	public const GENERATOR_SHORTCODE = 'tbt_drag_generator';
	public const LIBRARY_SHORTCODE   = 'tbt_drag_exercises';

	/**
	 * The white TBT mark used in the hero, shared with the player's hero.
	 */
	public const LOGO_URL = 'https://thebluetree.pl/wp-content/uploads/2020/12/TBT-white-logo.png';

	/**
	 * Option holding the front-end tool page URL.
	 *
	 * That page is site content, not something the plugin owns, so the Hub card
	 * and the wp-admin row link read its address from here.
	 */
	public const TOOL_PAGE_OPTION = 'tbtdd_tool_page_url';

	private Exercise_Repository $repository;
	private Assets $assets;

	public function __construct( Exercise_Repository $repository, Assets $assets ) {
		$this->repository = $repository;
		$this->assets     = $assets;
	}

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_shortcode( self::GENERATOR_SHORTCODE, array( $this, 'render_generator' ) );
		add_shortcode( self::LIBRARY_SHORTCODE, array( $this, 'render_library' ) );
	}

	/**
	 * Render the generator tool.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_generator( $atts = array() ): string {
		$gate = $this->gate();
		if ( null !== $gate ) {
			return $gate;
		}

		$atts = shortcode_atts(
			array(
				'hero'    => 'yes',
				'library' => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::GENERATOR_SHORTCODE
		);

		$exercise_id = $this->requested_exercise_id();
		$data        = $exercise_id ? $this->repository->get( $exercise_id ) : Exercise_Repository::default_data();
		$post        = $exercise_id ? get_post( $exercise_id ) : null;
		$status      = $post instanceof \WP_Post ? $post->post_status : '';

		$this->assets->enqueue_tools( $exercise_id );
		$this->remember_tool_page();

		return $this->template(
			'generator.php',
			array(
				'exercise_id' => $exercise_id,
				'data'        => $data,
				'status'      => $status,
				'permalink'   => ( $exercise_id && 'publish' === $status ) ? (string) get_permalink( $exercise_id ) : '',
				'preview'     => $post instanceof \WP_Post ? (string) get_preview_post_link( $post ) : '',
				'denied'      => $this->requested_but_denied(),
				'hero'        => $this->hero( 'generator', (string) $atts['hero'] ),
				'library_url' => self::library_url( (string) $atts['library'] ),
			)
		);
	}

	/**
	 * Render the library tool.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_library( $atts = array() ): string {
		$gate = $this->gate();
		if ( null !== $gate ) {
			return $gate;
		}

		/*
		 * The library defaults to no hero: it normally shares a page with the
		 * generator, whose hero already owns the page identity, and a second one
		 * would just repeat it. hero="yes" brings it back for a library that
		 * lives on its own page.
		 */
		$atts = shortcode_atts(
			array(
				'hero'      => 'no',
				'generator' => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::LIBRARY_SHORTCODE
		);

		$this->assets->enqueue_tools();

		return $this->template(
			'library.php',
			array(
				'hero'          => $this->hero( 'library', (string) $atts['hero'] ),
				'generator_url' => self::generator_url( (string) $atts['generator'] ),
			)
		);
	}

	/**
	 * Hero copy for a tool page, or null when the page suppresses it.
	 *
	 * @param string $context Either 'generator' or 'library'.
	 * @param string $show    Resolved hero="…" attribute.
	 * @return array|null
	 */
	private function hero( string $context, string $show ): ?array {
		if ( 'yes' !== strtolower( $show ) ) {
			return null;
		}

		$defaults = 'library' === $context
			? array(
				'eyebrow' => __( 'The Blue Tree Teacher Tools', 'tbt-drag-drop' ),
				'title'   => __( 'MY DRAG & DROP EXERCISES', 'tbt-drag-drop' ),
				'support' => __( 'Everything you have created', 'tbt-drag-drop' ),
				'logo'    => self::LOGO_URL,
			)
			: array(
				'eyebrow' => __( 'The Blue Tree Teacher Tools', 'tbt-drag-drop' ),
				'title'   => __( 'DRAG & DROP', 'tbt-drag-drop' ),
				'support' => __( 'Build a gap-fill exercise from any text', 'tbt-drag-drop' ),
				'logo'    => self::LOGO_URL,
			);

		/**
		 * Filter the Tool Hero copy.
		 *
		 * @param array  $hero    Eyebrow, title, support line and logo URL.
		 * @param string $context Either 'generator' or 'library'.
		 */
		$hero = apply_filters( 'tbt_drag_drop_hero', $defaults, $context );
		$hero = is_array( $hero ) ? array_merge( $defaults, $hero ) : $defaults;

		return array(
			'eyebrow' => (string) $hero['eyebrow'],
			'title'   => (string) $hero['title'],
			'support' => (string) $hero['support'],
			'logo'    => (string) $hero['logo'],
		);
	}

	/**
	 * The generator page URL, used by the library's edit and create actions.
	 *
	 * @param string $attribute The library shortcode's generator="…" value.
	 * @return string
	 */
	public static function generator_url( string $attribute = '' ): string {
		$default = self::clean_url( $attribute );

		if ( '' === $default ) {
			$post = get_post();
			if ( $post instanceof \WP_Post ) {
				$default = (string) get_permalink( $post );
			}
		}

		/**
		 * Filter the URL of the page holding [tbt_drag_generator].
		 *
		 * Applied last, over whatever the generator="…" attribute resolved to,
		 * so a site that already overrides this keeps winning. With no
		 * attribute and no filter the default is the current page, which is
		 * right when both shortcodes share one.
		 *
		 * @param string $url Generator page URL.
		 */
		return (string) apply_filters( 'tbt_drag_drop_generator_url', $default );
	}

	/**
	 * The library page URL, used by the generator's back link and discard.
	 *
	 * There is deliberately no current-page default: on a shared page a link
	 * back to the page you are already on is noise, and after a discard it
	 * would return the teacher to the exercise they just deleted. Nothing
	 * resolved means neither control renders.
	 *
	 * @param string $attribute The generator shortcode's library="…" value.
	 * @return string
	 */
	public static function library_url( string $attribute = '' ): string {
		return self::clean_url( $attribute );
	}

	/**
	 * The recorded front-end tool page, for wp-admin and the Hub card.
	 *
	 * @return string
	 */
	public static function tool_page_url(): string {
		return (string) get_option( self::TOOL_PAGE_OPTION, '' );
	}

	/**
	 * Record where the generator lives, the first time it renders.
	 *
	 * The Hub card and the list-table row link both need an address for a page
	 * only the site owner can create, and neither has a way to discover it.
	 * Written once, when the option is still empty, so a generator that later
	 * appears on a second page cannot make the recorded address flip between
	 * them; clearing the option is how a site owner moves it.
	 *
	 * @return void
	 */
	private function remember_tool_page(): void {
		if ( ! is_singular() || ! in_the_loop() || '' !== self::tool_page_url() ) {
			return;
		}

		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$url = (string) get_permalink( $post );
		if ( '' !== $url ) {
			update_option( self::TOOL_PAGE_OPTION, esc_url_raw( $url ) );
		}
	}

	/**
	 * Sanitise a page URL attribute.
	 *
	 * Accepts an absolute URL or a site-root-relative path, because the site
	 * owner types these into a Divi page rather than into PHP. Anything else
	 * resolves to an empty string, which every caller reads as "not set".
	 *
	 * @param string $value Raw attribute value.
	 * @return string
	 */
	private static function clean_url( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		if ( 0 === strpos( $value, '/' ) && 0 !== strpos( $value, '//' ) ) {
			// esc_url_raw() judges absolute URLs, so resolve the path first.
			$value = home_url( $value );
		} elseif ( ! preg_match( '#^https?://#i', $value ) ) {
			/*
			 * A bare word is not a URL. Left alone, esc_url_raw() would promote
			 * "library" to http://library and send the teacher off the site.
			 */
			return '';
		}

		return (string) esc_url_raw( $value );
	}

	/**
	 * Access gate. Returns markup to show instead of the tool, or null to proceed.
	 *
	 * @return string|null
	 */
	private function gate(): ?string {
		if ( ! is_user_logged_in() ) {
			$login = wp_login_url( $this->current_url() );

			return sprintf(
				'<div class="tbt tbt-tool tbtdd-tool tbtdd-tool--gate"><p>%1$s</p><p><a class="tbtdd-button tbtdd-button--primary" href="%2$s">%3$s</a></p></div>',
				esc_html__( 'Log in to your teacher account to build drag-and-drop exercises.', 'tbt-drag-drop' ),
				esc_url( $login ),
				esc_html__( 'Log in', 'tbt-drag-drop' )
			);
		}

		if ( ! Access::can_use_tools() ) {
			$default = sprintf(
				'<div class="tbt tbt-tool tbtdd-tool tbtdd-tool--gate"><p>%s</p></div>',
				esc_html__( 'The TBT Teaching Tools are part of a teacher subscription. Your account does not include them yet.', 'tbt-drag-drop' )
			);

			/**
			 * Filter the upsell shown to a logged-in user without access.
			 *
			 * Returns raw HTML so the Polish copy and the call to action can be
			 * set from a snippet without editing the plugin.
			 *
			 * @param string $html    Default upsell markup.
			 * @param int    $user_id Current user ID.
			 */
			return (string) apply_filters( 'tbt_drag_drop_upsell_html', $default, get_current_user_id() );
		}

		return null;
	}

	/**
	 * The exercise requested for editing, when the current user may edit it.
	 *
	 * @return int
	 */
	private function requested_exercise_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation parameter.
		$exercise_id = isset( $_GET['exercise_id'] ) ? absint( wp_unslash( $_GET['exercise_id'] ) ) : 0;
		if ( ! $exercise_id ) {
			return 0;
		}

		$post = get_post( $exercise_id );
		if ( ! $post instanceof \WP_Post || Post_Type::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return 0;
		}

		return Access::can_edit( $exercise_id ) ? $exercise_id : 0;
	}

	/**
	 * Was an exercise requested that the current user may not edit?
	 *
	 * @return bool
	 */
	private function requested_but_denied(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation parameter.
		$requested = isset( $_GET['exercise_id'] ) ? absint( wp_unslash( $_GET['exercise_id'] ) ) : 0;

		return $requested > 0 && 0 === $this->requested_exercise_id();
	}

	/**
	 * Current front-end URL, for the login redirect.
	 *
	 * @return string
	 */
	private function current_url(): string {
		$post = get_post();

		return $post instanceof \WP_Post ? (string) get_permalink( $post ) : home_url( '/' );
	}

	/**
	 * Render a tool template.
	 *
	 * @param string $file Template file name.
	 * @param array  $vars Template variables.
	 * @return string
	 */
	private function template( string $file, array $vars ): string {
		$path = TBTDD_DIR . 'templates/' . $file;
		if ( ! file_exists( $path ) ) {
			return '';
		}

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template variables.
		extract( $vars, EXTR_SKIP );
		include $path;

		return (string) ob_get_clean();
	}
}

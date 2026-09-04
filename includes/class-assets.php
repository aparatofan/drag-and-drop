<?php
/**
 * Asset registration and loading.
 *
 * @package TBT_Drag_Drop
 */

namespace TBT\DragDrop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets {
	private bool $registered = false;
	private bool $tools_localised = false;
	private bool $game_localised = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_early' ), 20 );
	}

	/**
	 * Register front-end assets.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;

		wp_register_style(
			'tbtdd-fonts',
			// One request for all three Style Book §3 families: Roboto for
			// interface, Roboto Slab for learning content, Roboto Mono for
			// product identity. Replaces the 1.0.0 Roboto 300/700 request.
			'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&family=Roboto+Mono:wght@700&family=Roboto+Slab:wght@400;500;600;700&display=swap',
			array(),
			null
		);

		// 'tbt-tokens' is a hard dependency: these sheets consume the canonical
		// vocabulary directly and carry no local colour variables of their own.
		// The handle need not exist yet at registration time — WordPress
		// resolves dependencies when it prints — but it must exist by then,
		// which is what ensure_shared_styles() guarantees.
		wp_register_style( 'tbtdd-game', TBTDD_URL . 'assets/css/game.css', array( 'tbtdd-fonts', 'tbt-tokens' ), TBTDD_VERSION );
		wp_register_script( 'tbtdd-game', TBTDD_URL . 'assets/js/game.js', array(), TBTDD_VERSION, true );

		// The teaching tools are a separate surface from the playable exercise
		// and never load admin.css / admin.js, which are styled for wp-admin.
		wp_register_style( 'tbtdd-tools', TBTDD_URL . 'assets/css/tools.css', array( 'tbtdd-fonts', 'tbt-tokens' ), TBTDD_VERSION );
		wp_register_script( 'tbtdd-tools', TBTDD_URL . 'assets/js/tools.js', array(), TBTDD_VERSION, true );
	}

	/**
	 * Enqueue before wp_head for predictable standalone and shortcode use.
	 *
	 * @return void
	 */
	public function maybe_enqueue_early(): void {
		if ( is_singular( Post_Type::POST_TYPE ) ) {
			$this->enqueue_game();
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( has_shortcode( $post->post_content, Shortcode::SHORTCODE ) ) {
			$this->enqueue_game();
		}

		$has_generator = has_shortcode( $post->post_content, Tools_Shortcode::GENERATOR_SHORTCODE );
		$has_library   = has_shortcode( $post->post_content, Tools_Shortcode::LIBRARY_SHORTCODE );

		// Nothing to load for a visitor who will only be shown the gate.
		if ( ( $has_generator || $has_library ) && Access::can_use_tools() ) {
			$this->enqueue_tools();
		}
	}

	/**
	 * Enqueue the player bundle.
	 *
	 * Idempotent: the shortcode calls it too, and a page builder can render
	 * that shortcode after wp_enqueue_scripts has already run.
	 *
	 * @return void
	 */
	public function enqueue_game(): void {
		$this->register();
		$this->ensure_shared_styles();
		wp_enqueue_style( 'tbtdd-game' );
		wp_enqueue_script( 'tbtdd-game' );

		if ( ! $this->game_localised ) {
			$this->game_localised = true;
			wp_localize_script( 'tbtdd-game', 'TBTDDGame', array( 'strings' => $this->game_strings() ) );
		}

		/*
		 * The tree mark's stylesheet is Hub's, under the handle 'tbt-tree'. It
		 * is enqueued here rather than left to the shortcode because the
		 * shortcode runs during template render, after wp_head has printed: the
		 * stylesheet would then land in the footer and the leaves, which take
		 * their fill from that file, would flash black first.
		 *
		 * It is deliberately NOT declared as a dependency of 'tbtdd-game'. An
		 * unregistered dependency makes WordPress skip the dependent stylesheet
		 * entirely, so a deactivated Hub would take the whole player surface
		 * down rather than just the mark.
		 */
		if ( wp_style_is( 'tbt-tree', 'registered' ) ) {
			wp_enqueue_style( 'tbt-tree' );
		}

		// Shortcodes inserted by page builders may be discovered after wp_head.
		if ( did_action( 'wp_head' ) && ! wp_style_is( 'tbtdd-game', 'done' ) ) {
			wp_print_styles( 'tbtdd-game' );
		}
	}

	/**
	 * Enqueue the front-end teaching tools bundle.
	 *
	 * @param int $exercise_id Exercise being edited, when the generator knows one.
	 * @return void
	 */
	public function enqueue_tools( int $exercise_id = 0 ): void {
		$this->register();
		$this->ensure_shared_styles();
		wp_enqueue_style( 'tbtdd-tools' );
		wp_enqueue_script( 'tbtdd-tools' );

		if ( ! $this->tools_localised ) {
			$this->tools_localised = true;
			wp_localize_script( 'tbtdd-tools', 'TBTDDTools', $this->tools_data( $exercise_id ) );
		}

		if ( did_action( 'wp_head' ) && ! wp_style_is( 'tbtdd-tools', 'done' ) ) {
			wp_print_styles( 'tbtdd-tools' );
		}
	}

	/**
	 * Guarantee that the canonical TBT-Hub token stylesheet is registered.
	 *
	 * TBT-Hub owns 'tbt-tokens' and registers it on wp_enqueue_scripts at
	 * priority 5. If Hub is inactive we register the bundled fallback copy
	 * under **the same handle**, so a later Hub activation replaces it wholesale
	 * and no page can ever load two copies of the vocabulary under different
	 * handles.
	 *
	 * Deliberately called from the enqueue_* methods rather than from
	 * register(), which runs at priority 5 itself: at equal priority the winner
	 * is plugin load order, so checking there could register the fallback in the
	 * very request where Hub was about to provide the real thing.
	 *
	 * @return void
	 */
	private function ensure_shared_styles(): void {
		if ( wp_style_is( 'tbt-tokens', 'registered' ) ) {
			return;
		}

		wp_register_style(
			'tbt-tokens',
			TBTDD_URL . 'assets/vendor/tbt/tbt-tokens.css',
			array(),
			$this->asset_version( 'assets/vendor/tbt/tbt-tokens.css' )
		);
	}

	/**
	 * Cache-busting version for a bundled asset.
	 *
	 * The vendored token file changes when it is resynced with Hub, which need
	 * not coincide with a TBTDD_VERSION bump, so its modification time is the
	 * more reliable buster.
	 *
	 * @param string $relative_path Path relative to the plugin directory.
	 * @return string
	 */
	private function asset_version( string $relative_path ): string {
		$mtime = @filemtime( TBTDD_DIR . $relative_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return $mtime ? (string) $mtime : TBTDD_VERSION;
	}

	/**
	 * Strings game.js needs that are not per-exercise.
	 *
	 * @return array
	 */
	private function game_strings(): array {
		return array(
			/* translators: 1: correct answers, 2: number of gaps. */
			'score'       => __( '%1$d / %2$d', 'tbt-drag-drop' ),
			'picked'      => __( '%s picked up. Choose a gap to place it in.', 'tbt-drag-drop' ),
			'placed'      => __( '%s placed.', 'tbt-drag-drop' ),
			'returned'    => __( '%s returned to the word bank.', 'tbt-drag-drop' ),
			/* translators: 1: correct answers, 2: number of gaps. */
			'checked'     => __( 'Checked: %1$d of %2$d correct.', 'tbt-drag-drop' ),
			'shownAll'    => __( 'The correct words are in place.', 'tbt-drag-drop' ),
			'restarted'   => __( 'The exercise has been reset.', 'tbt-drag-drop' ),
			/* translators: %d: gap number. */
			'emptySlot'   => __( 'Gap %d, empty', 'tbt-drag-drop' ),
			/* translators: 1: gap number, 2: the word in the gap. */
			'filledSlot'  => __( 'Gap %1$d, contains %2$s', 'tbt-drag-drop' ),
		);
	}

	/**
	 * Data handed to tools.js.
	 *
	 * @param int $exercise_id Exercise being edited.
	 * @return array
	 */
	private function tools_data( int $exercise_id ): array {
		return array(
			'restBase'     => esc_url_raw( rest_url( Exercises_Controller::REST_NAMESPACE . '/exercises' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'maxItems'     => Exercise_Validator::MAX_ITEMS,
			'titleMax'     => Exercise_Validator::TITLE_MAX,
			'exerciseId'   => $exercise_id,
			'generatorUrl' => esc_url_raw( Tools_Shortcode::generator_url() ),
			'strings'      => array(
				'needTitle'        => __( 'Give the exercise a title before publishing.', 'tbt-drag-drop' ),
				'needGaps'         => __( 'Choose at least one gap before publishing.', 'tbt-drag-drop' ),
				'needText'         => __( 'Write the exercise text before publishing.', 'tbt-drag-drop' ),
				'emptyPicker'      => __( 'Write the exercise text above and it will appear here for you to click.', 'tbt-drag-drop' ),
				'removeGap'        => __( 'Remove this gap', 'tbt-drag-drop' ),
				'removeGapNamed'   => __( 'Remove the gap “%s”', 'tbt-drag-drop' ),
				/* translators: 1: gaps chosen, 2: maximum gaps. */
				'gapCount'         => __( '%1$d of %2$d gaps', 'tbt-drag-drop' ),
				/* translators: %d: maximum gaps. */
				'gapLimit'         => __( '%d gaps is the maximum for one exercise.', 'tbt-drag-drop' ),
				'gapOverlap'       => __( 'That selection overlaps a gap you already made.', 'tbt-drag-drop' ),
				/* translators: %s: the duplicated gap text. */
				'gapDuplicate'     => __( '“%s” is already a gap. Two identical gaps cannot be told apart when checking answers.', 'tbt-drag-drop' ),
				'gapsDroppedOne'   => __( '1 gap was dropped: it no longer covers whole words in the text.', 'tbt-drag-drop' ),
				/* translators: %d: number of gaps dropped. */
				'gapsDropped'      => __( '%d gaps were dropped: they no longer cover whole words in the text.', 'tbt-drag-drop' ),
				'saving'           => __( 'Saving…', 'tbt-drag-drop' ),
				'savedPublished'   => __( 'Published. The link above is live.', 'tbt-drag-drop' ),
				'savedDraft'       => __( 'Saved as a draft.', 'tbt-drag-drop' ),
				'saveFailed'       => __( 'The exercise could not be saved.', 'tbt-drag-drop' ),
				'draftNoLink'      => __( 'This exercise is a draft, so it has no public link yet. Publish it to get one.', 'tbt-drag-drop' ),
				'exerciseLink'     => __( 'Exercise link', 'tbt-drag-drop' ),
				'shortcodeLabel'   => __( 'Shortcode for a lesson page', 'tbt-drag-drop' ),
				'copy'             => __( 'Copy', 'tbt-drag-drop' ),
				'copied'           => __( 'Copied', 'tbt-drag-drop' ),
				'previewUnsaved'   => __( 'Save the exercise first — there is nothing to preview yet.', 'tbt-drag-drop' ),
				'loading'          => __( 'Loading…', 'tbt-drag-drop' ),
				'empty'            => __( 'No exercises yet. Build your first one and it will appear here.', 'tbt-drag-drop' ),
				'emptySearch'      => __( 'No exercises match that search.', 'tbt-drag-drop' ),
				'published'        => __( 'Published', 'tbt-drag-drop' ),
				'draft'            => __( 'Draft', 'tbt-drag-drop' ),
				'open'             => __( 'Open', 'tbt-drag-drop' ),
				/* translators: %s: exercise title. */
				'openNewTab'       => __( 'Open %s in a new tab', 'tbt-drag-drop' ),
				'edit'             => __( 'Edit', 'tbt-drag-drop' ),
				'share'            => __( 'Share', 'tbt-drag-drop' ),
				'duplicate'        => __( 'Duplicate', 'tbt-drag-drop' ),
				'delete'           => __( 'Delete', 'tbt-drag-drop' ),
				'confirmDelete'    => __( 'Move this exercise to the trash?', 'tbt-drag-drop' ),
				/* translators: %s: exercise title. */
				'confirmDiscard'   => __( 'Discard “%s”? It will be moved to the trash.', 'tbt-drag-drop' ),
				'discarding'       => __( 'Discarding…', 'tbt-drag-drop' ),
				'deleted'          => __( 'Exercise moved to the trash.', 'tbt-drag-drop' ),
				'duplicated'       => __( 'A draft copy was created.', 'tbt-drag-drop' ),
				'createHeading'    => __( 'Create a new exercise', 'tbt-drag-drop' ),
				'titleLabel'       => __( 'Exercise title', 'tbt-drag-drop' ),
				'cancel'           => __( 'Cancel', 'tbt-drag-drop' ),
				'create'           => __( 'Create', 'tbt-drag-drop' ),
				'creating'         => __( 'Creating…', 'tbt-drag-drop' ),
				'createFailed'     => __( 'The exercise could not be created.', 'tbt-drag-drop' ),
				/* translators: %d: number of characters still allowed in the title. */
				'charsLeft'        => __( '%d characters left', 'tbt-drag-drop' ),
				/* translators: %d: number of gaps. */
				'gapsInExercise'   => __( '%d gaps', 'tbt-drag-drop' ),
				'oneGapInExercise' => __( '1 gap', 'tbt-drag-drop' ),
				/* translators: %s: formatted date. */
				'modified'         => __( 'Edited %s', 'tbt-drag-drop' ),
				'networkError'     => __( 'Something went wrong. Check your connection and try again.', 'tbt-drag-drop' ),
				'sessionExpired'   => __( 'Your session has expired. Refresh the page and try again.', 'tbt-drag-drop' ),
				'prevPage'         => __( 'Previous', 'tbt-drag-drop' ),
				'nextPage'         => __( 'Next', 'tbt-drag-drop' ),
				/* translators: 1: current page, 2: total pages. */
				'pageOf'           => __( 'Page %1$d of %2$d', 'tbt-drag-drop' ),
			),
		);
	}
}

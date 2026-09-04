<?php
/**
 * WordPress admin interface.
 *
 * The meta box keeps the typed gap list it has always had. Click-to-gap is a
 * front-end interaction and is deliberately not ported here: this screen is
 * the fallback path, and two implementations of the same picker would be two
 * places for it to drift.
 *
 * @package TBT_Drag_Drop
 */

namespace TBT\DragDrop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	private Exercise_Repository $repository;
	private Exercise_Validator $validator;

	public function __construct( Exercise_Repository $repository, Exercise_Validator $validator ) {
		$this->repository = $repository;
		$this->validator  = $validator;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'add_meta_boxes_' . Post_Type::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Post_Type::POST_TYPE, array( $this, 'save_post' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		add_filter( 'manage_' . Post_Type::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Post_Type::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
	}

	/**
	 * Register the exercise builder meta box.
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'dd-gap-exercise-builder',
			__( 'Exercise Builder', 'tbt-drag-drop' ),
			array( $this, 'render_meta_box' ),
			Post_Type::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Load admin assets only on exercise screens.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		// The token file is a dependency here too: admin.css names no colour of
		// its own, so without it the fields would fall back to unstyled.
		$this->ensure_admin_tokens();

		wp_enqueue_style( 'tbtdd-admin', TBTDD_URL . 'assets/css/admin.css', array( 'tbt-tokens' ), TBTDD_VERSION );
		wp_enqueue_script( 'tbtdd-admin', TBTDD_URL . 'assets/js/admin.js', array(), TBTDD_VERSION, true );

		wp_localize_script(
			'tbtdd-admin',
			'TBTDDAdmin',
			array(
				'maxItems' => Exercise_Validator::MAX_ITEMS,
				'strings'  => array(
					'duplicate' => __( 'You cannot use the same item twice.', 'tbt-drag-drop' ),
					'limit'     => sprintf(
						/* translators: %d: maximum number of gap items. */
						__( 'You can only add up to %d items.', 'tbt-drag-drop' ),
						(int) Exercise_Validator::MAX_ITEMS
					),
					'min'       => __( 'Add at least 1 gap item.', 'tbt-drag-drop' ),
					'missing'   => __( 'Each item must exist in the text exactly as written.', 'tbt-drag-drop' ),
					'remove'    => __( 'Remove', 'tbt-drag-drop' ),
					'copied'    => __( 'Copied.', 'tbt-drag-drop' ),
				),
			)
		);
	}

	/**
	 * Register the vendored token stylesheet for wp-admin.
	 *
	 * Hub registers 'tbt-tokens' on the front end; in wp-admin nothing does, so
	 * the bundled copy is registered here under the same handle for the same
	 * reason it is on the front end — one vocabulary, one handle.
	 *
	 * @return void
	 */
	private function ensure_admin_tokens(): void {
		if ( wp_style_is( 'tbt-tokens', 'registered' ) ) {
			return;
		}

		$path  = TBTDD_DIR . 'assets/vendor/tbt/tbt-tokens.css';
		$mtime = @filemtime( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		wp_register_style(
			'tbt-tokens',
			TBTDD_URL . 'assets/vendor/tbt/tbt-tokens.css',
			array(),
			$mtime ? (string) $mtime : TBTDD_VERSION
		);
	}

	/**
	 * Exercise builder meta box.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		$data      = $this->repository->get( $post->ID );
		$shortcode = sprintf( '[dd_exercise id="%d"]', $post->ID );

		wp_nonce_field( 'dd_gap_save_exercise', 'dd_gap_nonce' );
		?>
		<div class="dd-gap-admin-wrap">
			<p>
				<label for="dd-gap-text"><strong><?php esc_html_e( 'Exercise text', 'tbt-drag-drop' ); ?></strong></label>
			</p>
			<textarea id="dd-gap-text" name="dd_gap_text" rows="8" class="widefat" required><?php echo esc_textarea( $data['text'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Paste the full text for the exercise.', 'tbt-drag-drop' ); ?></p>

			<hr/>

			<p>
				<strong>
					<?php
					printf(
						/* translators: %d: maximum number of gap items. */
						esc_html__( 'Gap items (1 to %d)', 'tbt-drag-drop' ),
						(int) Exercise_Validator::MAX_ITEMS
					);
					?>
				</strong>
			</p>
			<p class="description"><?php esc_html_e( 'Each selected word/expression must appear in the text. Duplicate items are not allowed.', 'tbt-drag-drop' ); ?></p>

			<div id="dd-gap-items">
				<?php foreach ( $data['items'] as $item ) : ?>
					<div class="dd-gap-item-row">
						<input type="text" name="dd_gap_items[]" value="<?php echo esc_attr( $item ); ?>" class="regular-text dd-gap-item-input" />
						<button type="button" class="button dd-gap-remove-item"><?php esc_html_e( 'Remove', 'tbt-drag-drop' ); ?></button>
					</div>
				<?php endforeach; ?>
			</div>

			<p>
				<button type="button" class="button button-secondary" id="dd-gap-add-item"><?php esc_html_e( 'Add gap item', 'tbt-drag-drop' ); ?></button>
			</p>

			<hr/>

			<p>
				<label for="dd-gap-distractors"><strong><?php esc_html_e( 'Extra words (optional)', 'tbt-drag-drop' ); ?></strong></label>
			</p>
			<input
				type="text"
				id="dd-gap-distractors"
				name="dd_gap_distractors"
				class="widefat"
				value="<?php echo esc_attr( implode( ', ', $data['distractors'] ) ); ?>"
				placeholder="<?php esc_attr_e( 'went, has been, were', 'tbt-drag-drop' ); ?>"
			/>
			<p class="description">
				<?php
				printf(
					/* translators: %d: maximum number of extra words. */
					esc_html__( 'Words that are not in the text, separated by commas. They join the word bank but fill no gap. Up to %d.', 'tbt-drag-drop' ),
					(int) Exercise_Validator::MAX_DISTRACTORS
				);
				?>
			</p>

			<hr/>

			<p>
				<label for="dd-gap-instructions"><strong><?php esc_html_e( 'Student instructions', 'tbt-drag-drop' ); ?></strong></label>
			</p>
			<input
				type="text"
				id="dd-gap-instructions"
				name="dd_gap_instructions"
				class="widefat"
				maxlength="<?php echo esc_attr( (string) Exercise_Validator::INSTRUCTIONS_MAX ); ?>"
				value="<?php echo esc_attr( $data['instructions'] ); ?>"
				placeholder="<?php echo esc_attr( Exercise_Repository::default_instructions() ); ?>"
			/>
			<p class="description"><?php esc_html_e( 'Shown under the title on the exercise page. Leave empty for the default line.', 'tbt-drag-drop' ); ?></p>

			<hr/>

			<p class="dd-gap-shortcode-note">
				<?php
				printf(
					/* translators: %s: the shortcode. */
					esc_html__( 'Use this shortcode in posts/pages: %s', 'tbt-drag-drop' ),
					'<code>' . esc_html( $shortcode ) . '</code>'
				);
				?>
			</p>

			<?php if ( 'publish' === $post->post_status ) : ?>
				<p class="dd-gap-permalink-note">
					<?php
					printf(
						/* translators: %s: the exercise permalink. */
						esc_html__( 'Its own page: %s', 'tbt-drag-drop' ),
						'<a href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( get_permalink( $post ) ) . '</a>'
					);
					?>
				</p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'A draft has no public page yet. Publish the exercise to give it one.', 'tbt-drag-drop' ); ?></p>
			<?php endif; ?>

			<?php $front_end = $this->front_end_edit_url( $post->ID ); ?>
			<?php if ( '' !== $front_end ) : ?>
				<p>
					<a class="button button-secondary" href="<?php echo esc_url( $front_end ); ?>"><?php esc_html_e( 'Edit on the front end', 'tbt-drag-drop' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Save exercise meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function save_post( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['dd_gap_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dd_gap_nonce'] ) ), 'dd_gap_save_exercise' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$before = $this->repository->get( $post_id );

		$payload = array(
			'title'        => $post->post_title,
			'text'         => isset( $_POST['dd_gap_text'] ) ? wp_unslash( $_POST['dd_gap_text'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by Exercise_Validator.
			'items'        => isset( $_POST['dd_gap_items'] ) ? (array) wp_unslash( $_POST['dd_gap_items'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by Exercise_Validator.
			'instructions' => isset( $_POST['dd_gap_instructions'] ) ? wp_unslash( $_POST['dd_gap_instructions'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by Exercise_Validator.
			'distractors'  => isset( $_POST['dd_gap_distractors'] ) ? wp_unslash( $_POST['dd_gap_distractors'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by Exercise_Validator.
			/*
			 * This screen has no way to say which occurrence of a word a gap
			 * means, so it never writes offsets. Whatever is stored is carried
			 * through untouched here and cleared below if the save moved the
			 * text or the item list out from under it.
			 */
			'offsets'      => $before['offsets'],
		);

		/*
		 * save_partial(), not save(): a half-finished exercise typed into
		 * wp-admin must still survive a save, exactly as it did before this
		 * release, when the meta box wrote whatever it was given.
		 */
		$after = $this->repository->save_partial( $post_id, $payload );

		if ( $this->offsets_are_stale( $before, $after ) ) {
			$this->repository->clear_offsets( $post_id );
		}

		// Publishing an incomplete exercise is worth a word, but never at the
		// cost of the save: the work is already stored either way.
		$validated = $this->validator->validate( $payload );
		if ( is_wp_error( $validated ) && 'publish' === $post->post_status ) {
			$this->set_notice(
				sprintf(
					/* translators: %s: validation message. */
					__( 'This exercise is published but will not render yet: %s', 'tbt-drag-drop' ),
					$validated->get_error_message()
				),
				'error'
			);
		}

		do_action( 'tbt_drag_drop_after_save', $post_id, $after );
	}

	/**
	 * Did this save move the text or the items out from under the offsets?
	 *
	 * @param array $before Data as stored before the save.
	 * @param array $after Data as stored after it.
	 * @return bool
	 */
	private function offsets_are_stale( array $before, array $after ): bool {
		if ( empty( $before['offsets'] ) ) {
			return false;
		}

		return $before['text'] !== $after['text'] || $before['items'] !== $after['items'];
	}

	/**
	 * Display a deferred notice.
	 *
	 * @return void
	 */
	public function admin_notice(): void {
		$key    = 'tbtdd_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );
		$type = 'error' === ( $notice['type'] ?? '' ) ? 'notice-error' : 'notice-success';
		printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $notice['message'] ) );
	}

	/**
	 * Custom list columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function columns( array $columns ): array {
		return array(
			'cb'             => $columns['cb'] ?? '<input type="checkbox" />',
			'title'          => __( 'Title', 'tbt-drag-drop' ),
			'tbtdd_gaps'     => __( 'Gaps', 'tbt-drag-drop' ),
			'tbtdd_shortcode' => __( 'Shortcode', 'tbt-drag-drop' ),
			'date'           => __( 'Date', 'tbt-drag-drop' ),
		);
	}

	/**
	 * Render custom column data.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function column_content( string $column, int $post_id ): void {
		if ( 'tbtdd_gaps' === $column ) {
			echo esc_html( (string) count( $this->repository->get( $post_id )['items'] ) );
			return;
		}

		if ( 'tbtdd_shortcode' === $column ) {
			$shortcode = sprintf( '[dd_exercise id="%d"]', $post_id );
			printf(
				'<code>%1$s</code> <button type="button" class="button-link" data-tbtdd-copy="%2$s">%3$s</button>',
				esc_html( $shortcode ),
				esc_attr( $shortcode ),
				esc_html__( 'Copy', 'tbt-drag-drop' )
			);
		}
	}

	/**
	 * Add an "Edit on the front end" row link.
	 *
	 * @param array    $actions Existing row actions.
	 * @param \WP_Post $post Current post.
	 * @return array
	 */
	public function row_actions( array $actions, \WP_Post $post ): array {
		if ( Post_Type::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		$url = $this->front_end_edit_url( $post->ID );
		if ( '' === $url ) {
			return $actions;
		}

		$actions['tbtdd_front_end'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'Edit on the front end', 'tbt-drag-drop' )
		);

		return $actions;
	}

	/**
	 * The generator page URL for one exercise, when a tool page is known.
	 *
	 * @param int $post_id Exercise post ID.
	 * @return string
	 */
	private function front_end_edit_url( int $post_id ): string {
		$base = Tools_Shortcode::tool_page_url();
		if ( '' === $base ) {
			return '';
		}

		return add_query_arg( 'exercise_id', $post_id, $base );
	}

	/**
	 * Store a one-time admin notice.
	 *
	 * @param string $message Notice text.
	 * @param string $type Notice type.
	 * @return void
	 */
	private function set_notice( string $message, string $type ): void {
		set_transient(
			'tbtdd_notice_' . get_current_user_id(),
			array(
				'message' => $message,
				'type'    => $type,
			),
			60
		);
	}
}

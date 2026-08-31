<?php
/**
 * REST CRUD for the front-end teaching tools.
 *
 * Every route is gated on Access: the capability decides who may use the tools
 * at all, and post_author decides which exercises they may touch. The post type
 * capability model is deliberately not involved — wp_insert_post() performs no
 * capability check of its own, so the checks here are the whole story for the
 * front end, and wp-admin keeps behaving exactly as before.
 *
 * @package TBT_Drag_Drop
 */

namespace TBT\DragDrop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Exercises_Controller {
	public const REST_NAMESPACE = 'tbt-drag-drop/v1';

	private const MAX_PER_PAGE = 50;

	private Exercise_Repository $repository;

	/**
	 * Validation is deliberately not injected here. Exercise_Repository::save()
	 * is the only place a payload is judged, so the controller cannot reach a
	 * different verdict than the write does.
	 */
	public function __construct( Exercise_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the CRUD routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/exercises',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => array( $this, 'check_tools_access' ),
					'args'                => array(
						'search'   => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'page'     => array(
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
						'status'   => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_tools_access' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/exercises/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_item_access' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_item_access' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_item_access' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/exercises/(?P<id>[\d]+)/duplicate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate_item' ),
				'permission_callback' => array( $this, 'check_item_access' ),
			)
		);
	}

	/**
	 * Gate collection routes on the teaching tools capability.
	 *
	 * @return true|\WP_Error
	 */
	public function check_tools_access() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'tbtdd_not_logged_in', __( 'You must be logged in to manage exercises.', 'tbt-drag-drop' ), array( 'status' => 401 ) );
		}

		if ( ! Access::can_use_tools() ) {
			return new \WP_Error( 'tbtdd_forbidden', __( 'Your account does not include the teaching tools.', 'tbt-drag-drop' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Gate single-exercise routes on capability plus ownership.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function check_item_access( \WP_REST_Request $request ) {
		$access = $this->check_tools_access();
		if ( is_wp_error( $access ) ) {
			return $access;
		}

		$post = $this->get_exercise_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( ! Access::can_edit( $post->ID ) ) {
			return new \WP_Error( 'tbtdd_not_owner', __( 'This exercise belongs to another teacher.', 'tbt-drag-drop' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * List the current user's exercises.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_items( \WP_REST_Request $request ): \WP_REST_Response {
		$per_page = min( self::MAX_PER_PAGE, max( 1, absint( $request->get_param( 'per_page' ) ) ) );
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$status   = (string) $request->get_param( 'status' );
		$statuses = in_array( $status, array( 'publish', 'draft' ), true ) ? array( $status ) : array( 'publish', 'draft' );

		$args = array(
			'post_type'      => Post_Type::POST_TYPE,
			'post_status'    => $statuses,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			// Always the current author: the library is a teacher's own shelf,
			// not a view of everyone's work.
			'author'         => get_current_user_id(),
		);

		$search = trim( (string) $request->get_param( 'search' ) );
		if ( '' !== $search ) {
			// The searchable content is the title; the exercise text lives in
			// meta, which WP_Query's 's' does not reach.
			$args['s'] = $search;
		}

		$query = new \WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->prepare_summary( $post );
		}

		$response = new \WP_REST_Response(
			array(
				'items'       => $items,
				'total'       => (int) $query->found_posts,
				'total_pages' => (int) $query->max_num_pages,
				'page'        => $page,
			),
			200
		);
		$response->header( 'X-WP-Total', (string) (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) (int) $query->max_num_pages );

		return $response;
	}

	/**
	 * Read one exercise.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( \WP_REST_Request $request ) {
		$post = $this->get_exercise_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return new \WP_REST_Response(
			array(
				'success'  => true,
				'exercise' => $this->prepare_exercise( $post ),
			),
			200
		);
	}

	/**
	 * Create a draft owned by the current user.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( \WP_REST_Request $request ) {
		// permission_callback has already run, but ownership on a create is
		// decided here, by naming the author explicitly rather than trusting
		// anything in the request body.
		$access = $this->check_tools_access();
		if ( is_wp_error( $access ) ) {
			return $access;
		}

		$payload = $this->payload_from_request( $request );

		$post_id = wp_insert_post(
			array(
				'post_type'   => Post_Type::POST_TYPE,
				'post_title'  => $payload['title'],
				// A new exercise is always a draft: publishing is a deliberate
				// second step, taken once there is something to play.
				'post_status' => 'draft',
				'post_author' => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$post_id->add_data( array( 'status' => 500 ) );
			return $post_id;
		}

		return $this->persist( (int) $post_id, $payload, $this->wants_publish( $request ) );
	}

	/**
	 * Update an exercise the current user owns.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( \WP_REST_Request $request ) {
		$post = $this->get_exercise_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		// Re-checked inside the callback, not only in permission_callback: the
		// two run at different points and only this one guards the write.
		if ( ! Access::can_edit( $post->ID ) ) {
			return new \WP_Error( 'tbtdd_not_owner', __( 'This exercise belongs to another teacher.', 'tbt-drag-drop' ), array( 'status' => 403 ) );
		}

		$payload = $this->payload_from_request( $request, $post->ID );

		// post_author is never taken from the request: ownership cannot move.
		// The status is left alone here and decided by persist(), once it knows
		// whether the payload is actually publishable.
		$updated = wp_update_post(
			array(
				'ID'         => $post->ID,
				'post_title' => $payload['title'],
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			$updated->add_data( array( 'status' => 500 ) );
			return $updated;
		}

		return $this->persist( $post->ID, $payload, $this->wants_publish( $request ) );
	}

	/**
	 * Duplicate an exercise as a draft owned by the current user.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function duplicate_item( \WP_REST_Request $request ) {
		$post = $this->get_exercise_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( ! Access::can_edit( $post->ID ) ) {
			return new \WP_Error( 'tbtdd_not_owner', __( 'This exercise belongs to another teacher.', 'tbt-drag-drop' ), array( 'status' => 403 ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => Post_Type::POST_TYPE,
				'post_title'  => sprintf(
					/* translators: %s: original exercise title. */
					__( '%s (copy)', 'tbt-drag-drop' ),
					$post->post_title
				),
				'post_status' => 'draft',
				'post_author' => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$post_id->add_data( array( 'status' => 500 ) );
			return $post_id;
		}

		$this->repository->copy( $post->ID, (int) $post_id );

		$copy = get_post( (int) $post_id );

		return new \WP_REST_Response(
			array(
				'success'  => true,
				'exercise' => $copy instanceof \WP_Post ? $this->prepare_summary( $copy ) : null,
			),
			201
		);
	}

	/**
	 * Trash an exercise the current user owns.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( \WP_REST_Request $request ) {
		$post = $this->get_exercise_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( ! Access::can_edit( $post->ID ) ) {
			return new \WP_Error( 'tbtdd_not_owner', __( 'This exercise belongs to another teacher.', 'tbt-drag-drop' ), array( 'status' => 403 ) );
		}

		// Trash, never force delete: a mis-tap between lessons is recoverable.
		$trashed = wp_trash_post( $post->ID );
		if ( ! $trashed ) {
			return new \WP_Error( 'tbtdd_delete_failed', __( 'The exercise could not be moved to the trash.', 'tbt-drag-drop' ), array( 'status' => 500 ) );
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'id'      => $post->ID,
			),
			200
		);
	}

	/**
	 * Write the payload, settle the post status, and build the save response.
	 *
	 * The status transition lives here rather than in the two callers because
	 * only this method knows whether the payload validated: a create that asked
	 * to publish must be promoted out of the draft it was inserted as, and an
	 * update that asked to publish must not be.
	 *
	 * A publish that fails validation is still stored, through save_partial(),
	 * with the reason attached. Losing a teacher's typing would be a worse
	 * outcome than an exercise that cannot go live yet — and an exercise that
	 * is already published stays published rather than silently vanishing from
	 * a URL the teacher has shared.
	 *
	 * @param int   $post_id Exercise post ID.
	 * @param array $payload Raw payload.
	 * @param bool  $publish Whether the caller asked to publish.
	 * @return \WP_REST_Response
	 */
	private function persist( int $post_id, array $payload, bool $publish ): \WP_REST_Response {
		$message   = '';
		$publishing = false;

		if ( $publish ) {
			$saved = $this->repository->save( $post_id, $payload );
			if ( is_wp_error( $saved ) ) {
				$message = $saved->get_error_message();
				$this->repository->save_partial( $post_id, $payload );
			} else {
				$publishing = true;
			}
		} else {
			$this->repository->save_partial( $post_id, $payload );
		}

		if ( $publishing && 'publish' !== get_post_status( $post_id ) ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				)
			);
		}

		$post = get_post( $post_id );

		return new \WP_REST_Response(
			array(
				'success'  => true,
				'status'   => $post instanceof \WP_Post ? $post->post_status : 'draft',
				'message'  => $message,
				'exercise' => $post instanceof \WP_Post ? $this->prepare_exercise( $post ) : null,
			),
			200
		);
	}

	/**
	 * Did the request ask for a published exercise?
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	private function wants_publish( \WP_REST_Request $request ): bool {
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : (array) $request->get_body_params();

		return isset( $body['status'] ) && 'publish' === sanitize_key( (string) $body['status'] );
	}

	/**
	 * Build a canonical payload from the request body.
	 *
	 * Fields the body omits keep their stored values, so a partial save cannot
	 * blank out something the teacher never touched.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param int              $post_id Existing exercise, when updating.
	 * @return array
	 */
	private function payload_from_request( \WP_REST_Request $request, int $post_id = 0 ): array {
		$existing = $post_id ? $this->repository->get( $post_id ) : Exercise_Repository::default_data();
		$body     = $request->get_json_params();
		$body     = is_array( $body ) ? $body : (array) $request->get_body_params();

		$title = isset( $body['title'] ) ? sanitize_text_field( (string) $body['title'] ) : (string) $existing['title'];
		if ( '' === trim( $title ) ) {
			$title = __( 'Untitled exercise', 'tbt-drag-drop' );
		}

		return array(
			'title'        => $title,
			'text'         => isset( $body['text'] ) && is_scalar( $body['text'] ) ? (string) $body['text'] : (string) $existing['text'],
			'items'        => isset( $body['items'] ) && is_array( $body['items'] ) ? array_values( $body['items'] ) : (array) $existing['items'],
			'offsets'      => isset( $body['offsets'] ) && is_array( $body['offsets'] ) ? array_values( $body['offsets'] ) : (array) $existing['offsets'],
			'instructions' => isset( $body['instructions'] ) && is_scalar( $body['instructions'] ) ? (string) $body['instructions'] : (string) $existing['instructions'],
			// A list or one comma-separated string; Exercise_Validator owns the
			// split. Absent from the body means keep what is stored, so a client
			// that does not know about extra words cannot silently drop them.
			'distractors'  => ( isset( $body['distractors'] ) && ( is_array( $body['distractors'] ) || is_scalar( $body['distractors'] ) ) )
				? $body['distractors']
				: (array) $existing['distractors'],
		);
	}

	/**
	 * Load an exercise post, or an error explaining why not.
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Post|\WP_Error
	 */
	private function get_exercise_post( int $post_id ) {
		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post instanceof \WP_Post || Post_Type::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return new \WP_Error( 'tbtdd_not_found', __( 'That exercise does not exist.', 'tbt-drag-drop' ), array( 'status' => 404 ) );
		}

		return $post;
	}

	/**
	 * Shape one exercise for a list response.
	 *
	 * @param \WP_Post $post Exercise post.
	 * @return array
	 */
	private function prepare_summary( \WP_Post $post ): array {
		$data = $this->repository->get( $post->ID );

		return array(
			'id'        => $post->ID,
			'title'     => $post->post_title,
			'status'    => $post->post_status,
			'gap_count' => count( $data['items'] ),
			/*
			 * Local time, not GMT. 'draft' is a date_floating status, so a post
			 * inserted as a draft is stored with post_modified_gmt as
			 * 0000-00-00 00:00:00 until something calls wp_update_post() on it;
			 * get_post_modified_time() reads that as no date and returns false,
			 * which is why a fresh draft's row printed "Edited" with nothing
			 * after it. post_modified holds a real timestamp from the insert,
			 * and 'c' carries the site's offset, so the client reads it exactly.
			 */
			'modified'  => (string) get_post_modified_time( 'c', false, $post ),
			// A draft's permalink 404s for a student, so the tools show the
			// link only for a published exercise; preview is the draft's way in.
			'permalink' => 'publish' === $post->post_status ? get_permalink( $post ) : '',
			'preview'   => get_preview_post_link( $post ),
			'shortcode' => sprintf( '[dd_exercise id="%d"]', $post->ID ),
		);
	}

	/**
	 * Shape one exercise for a read or save response.
	 *
	 * @param \WP_Post $post Exercise post.
	 * @return array
	 */
	private function prepare_exercise( \WP_Post $post ): array {
		return array_merge( $this->repository->get( $post->ID ), $this->prepare_summary( $post ) );
	}
}

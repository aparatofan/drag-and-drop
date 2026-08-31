<?php
/**
 * Shared exercise renderer.
 *
 * @package TBT_Drag_Drop
 */

namespace TBT\DragDrop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Renderer {
	private Exercise_Repository $repository;
	private Assets $assets;

	public function __construct( Exercise_Repository $repository, Assets $assets ) {
		$this->repository = $repository;
		$this->assets     = $assets;
	}

	/**
	 * Render an exercise.
	 *
	 * @param int   $post_id Exercise post ID.
	 * @param array $args Display arguments.
	 * @return string
	 */
	public function render( int $post_id, array $args = array() ): string {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || Post_Type::POST_TYPE !== $post->post_type || ! $this->repository->can_view( $post ) ) {
			return current_user_can( 'edit_posts' )
				? '<p class="tbtdd-admin-error">' . esc_html__( 'This exercise is unavailable.', 'tbt-drag-drop' ) . '</p>'
				: '';
		}

		$data = apply_filters( 'tbt_drag_drop_exercise_data', $this->repository->get( $post_id ), $post_id );

		$positions = Exercise_Repository::resolve_positions(
			(string) $data['text'],
			(array) $data['items'],
			(array) $data['offsets']
		);

		if ( '' === trim( (string) $data['text'] ) || empty( $positions ) ) {
			return current_user_can( 'edit_post', $post_id )
				? '<p class="tbtdd-admin-error">' . esc_html__( 'This exercise has no gaps yet.', 'tbt-drag-drop' ) . '</p>'
				: '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'show_title'        => true,
				'show_instructions' => true,
				'compact'           => false,
			)
		);

		$this->assets->enqueue_game();

		$instance_id  = wp_unique_id( 'tbtdd-instance-' );
		$reading_html = $this->reading_html( (string) $data['text'], $positions );

		$answers = array();
		$bank    = array();
		foreach ( $positions as $slot_number => $position ) {
			$answer  = (string) $data['items'][ $position['index'] ];
			$slot_id = 'slot-' . ( $slot_number + 1 );

			$answers[ $slot_id ] = $answer;
			$bank[]              = $answer;
		}

		// Shuffled server-side so the bank order is not the answer order even
		// with JavaScript disabled or still loading.
		shuffle( $bank );

		$instructions = '' !== trim( (string) $data['instructions'] )
			? (string) $data['instructions']
			: Exercise_Repository::default_instructions();

		$config = array(
			'instanceId' => $instance_id,
			'answers'    => $answers,
			'labels'     => array(
				'check'       => __( 'Check your answers', 'tbt-drag-drop' ),
				'show'        => __( 'Show correct', 'tbt-drag-drop' ),
				'redo'        => __( 'Redo exercise', 'tbt-drag-drop' ),
				'bank'        => __( 'Words to place', 'tbt-drag-drop' ),
			),
		);

		do_action( 'tbt_drag_drop_before_render', $post_id, $data, $args );

		ob_start();
		include TBTDD_DIR . 'templates/exercise.php';

		return (string) ob_get_clean();
	}

	/**
	 * Build the reading panel: escaped text runs with a slot at each gap.
	 *
	 * Slots are numbered in reading order, which is the order the positions
	 * arrive in, so slot-1 is always the first gap on the page whatever order
	 * the items were chosen in.
	 *
	 * @param string $text Exercise text.
	 * @param array  $positions Resolved gap positions.
	 * @return string
	 */
	private function reading_html( string $text, array $positions ): string {
		$html   = '';
		$cursor = 0;

		foreach ( $positions as $slot_number => $position ) {
			if ( $position['start'] > $cursor ) {
				$html .= $this->text_run( substr( $text, $cursor, $position['start'] - $cursor ) );
			}

			$html .= sprintf(
				'<span class="tbtdd-slot" data-slot="%1$s" tabindex="0" role="button" aria-label="%2$s"></span>',
				esc_attr( 'slot-' . ( $slot_number + 1 ) ),
				esc_attr(
					sprintf(
						/* translators: %d: gap number. */
						__( 'Gap %d, empty', 'tbt-drag-drop' ),
						$slot_number + 1
					)
				)
			);

			$cursor = $position['start'] + $position['length'];
		}

		if ( $cursor < strlen( $text ) ) {
			$html .= $this->text_run( substr( $text, $cursor ) );
		}

		return $html;
	}

	/**
	 * Escape one run of the exercise text.
	 *
	 * nl2br rather than wpautop: the runs are fragments around inline slots, so
	 * wrapping them in paragraphs would put block elements inside a sentence.
	 * The teacher's line breaks are kept as written either way.
	 *
	 * @param string $run Raw text run.
	 * @return string
	 */
	private function text_run( string $run ): string {
		return nl2br( esc_html( $run ), false );
	}
}

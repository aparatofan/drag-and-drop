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
		$words   = array();
		foreach ( $positions as $slot_number => $position ) {
			$answer  = (string) $data['items'][ $position['index'] ];
			$slot_id = 'slot-' . ( $slot_number + 1 );

			$answers[ $slot_id ] = $answer;
			$words[]             = $answer;
		}

		/*
		 * The extra words join the bank as equals. They fill no gap, so the
		 * student sees more tokens than there are gaps and has to choose rather
		 * than place what is left over. They are absent from $answers, which is
		 * what makes them wrong wherever they are dropped.
		 */
		foreach ( (array) ( $data['distractors'] ?? array() ) as $distractor ) {
			$distractor = (string) $distractor;
			if ( '' !== $distractor ) {
				$words[] = $distractor;
			}
		}

		// Shuffled server-side so the bank order is not the answer order even
		// with JavaScript disabled or still loading.
		shuffle( $words );

		// Letters are read off the shuffled bank and nothing else, so a letter
		// can never hint at the gap its word belongs to. The letter belongs to
		// the word: once assigned it travels with the token for the whole
		// attempt, and only a redo reshuffle reassigns it.
		$bank = array();
		foreach ( $words as $bank_index => $bank_word ) {
			$bank[] = array(
				'word'   => $bank_word,
				'letter' => self::bank_letter( $bank_index ),
			);
		}

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
	 * the items were chosen in. That same number is printed beside the slot so
	 * a gap can be named out loud during a lesson.
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

			// The number and the slot share a wrapper so they stay together
			// across a line break. The badge is aria-hidden because the slot's
			// own label already announces the gap by number.
			$html .= sprintf(
				'<span class="tbtdd-gap"><span class="tbtdd-tag tbtdd-tag--number" aria-hidden="true">%1$s</span><span class="tbtdd-slot" data-slot="%2$s" tabindex="0" role="button" aria-label="%3$s"></span></span>',
				esc_html( (string) ( $slot_number + 1 ) ),
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

	/**
	 * Bank letter for a position: A, B, C …
	 *
	 * A bank holds at most Exercise_Validator::MAX_ITEMS gaps plus
	 * Exercise_Validator::MAX_DISTRACTORS extra words — twenty-two — so one
	 * letter always suffices; the wrap only keeps an oversized bank from
	 * running past Z into punctuation.
	 *
	 * @param int $index Zero-based position in the shuffled bank.
	 * @return string
	 */
	private static function bank_letter( int $index ): string {
		return chr( 65 + ( $index % 26 ) );
	}
}

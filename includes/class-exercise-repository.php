<?php
/**
 * Exercise persistence.
 *
 * The single owner of the four exercise meta keys. Nothing else in this plugin
 * calls get_post_meta() or update_post_meta() for them: the meta box and the
 * REST controller both write through here, which is what keeps two authoring
 * paths from drifting into two definitions of what is stored.
 *
 * The key names are unchanged stored data. _dd_gap_text and _dd_gap_items date
 * from 1.0.0 and must keep their meaning; _dd_gap_offsets and
 * _dd_gap_instructions are additive.
 *
 * @package TBT_Drag_Drop
 */

namespace TBT\DragDrop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Exercise_Repository {
	public const META_TEXT         = '_dd_gap_text';
	public const META_ITEMS        = '_dd_gap_items';
	public const META_OFFSETS      = '_dd_gap_offsets';
	public const META_INSTRUCTIONS = '_dd_gap_instructions';

	private Exercise_Validator $validator;
	private array $request_cache = array();

	public function __construct( Exercise_Validator $validator ) {
		$this->validator = $validator;
	}

	/**
	 * The shape every consumer can rely on, whatever is stored.
	 *
	 * @return array
	 */
	public static function default_data(): array {
		return array(
			'title'        => '',
			'text'         => '',
			'items'        => array(),
			'offsets'      => array(),
			'instructions' => '',
		);
	}

	/**
	 * The player's support line when the exercise does not set one.
	 *
	 * @return string
	 */
	public static function default_instructions(): string {
		return __( 'Drag each word into the gap where it belongs.', 'tbt-drag-drop' );
	}

	/**
	 * Read canonical exercise data.
	 *
	 * @param int $post_id Exercise post ID.
	 * @return array
	 */
	public function get( int $post_id ): array {
		if ( isset( $this->request_cache[ $post_id ] ) ) {
			return $this->request_cache[ $post_id ];
		}

		$items = get_post_meta( $post_id, self::META_ITEMS, true );
		$items = is_array( $items ) ? array_values( array_map( 'strval', $items ) ) : array();

		$offsets = get_post_meta( $post_id, self::META_OFFSETS, true );
		$offsets = is_array( $offsets ) ? array_values( array_map( 'intval', $offsets ) ) : array();

		$data = array(
			'title'        => (string) get_the_title( $post_id ),
			'text'         => (string) get_post_meta( $post_id, self::META_TEXT, true ),
			'items'        => $items,
			'offsets'      => $offsets,
			'instructions' => (string) get_post_meta( $post_id, self::META_INSTRUCTIONS, true ),
		);

		$this->request_cache[ $post_id ] = $data;

		return $data;
	}

	/**
	 * Save a validated, complete exercise.
	 *
	 * @param int   $post_id Exercise post ID.
	 * @param array $raw Raw payload.
	 * @return array|\WP_Error
	 */
	public function save( int $post_id, array $raw ) {
		$validated = $this->validator->validate( $raw );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		return $this->write( $post_id, $validated );
	}

	/**
	 * Save a sanitised but incomplete payload.
	 *
	 * @param int   $post_id Exercise post ID.
	 * @param array $raw Raw payload.
	 * @return array
	 */
	public function save_partial( int $post_id, array $raw ): array {
		return $this->write( $post_id, $this->validator->sanitise( $raw ) );
	}

	/**
	 * Write already-clean data to the four meta keys.
	 *
	 * @param int   $post_id Exercise post ID.
	 * @param array $clean Clean exercise data.
	 * @return array
	 */
	private function write( int $post_id, array $clean ): array {
		update_post_meta( $post_id, self::META_TEXT, $clean['text'] );
		update_post_meta( $post_id, self::META_ITEMS, $clean['items'] );

		/*
		 * An empty offset list is deleted rather than stored empty, so "no
		 * offsets" reads the same whether they were never written or have just
		 * been invalidated by an edit.
		 */
		if ( empty( $clean['offsets'] ) ) {
			delete_post_meta( $post_id, self::META_OFFSETS );
		} else {
			update_post_meta( $post_id, self::META_OFFSETS, $clean['offsets'] );
		}

		if ( '' === $clean['instructions'] ) {
			delete_post_meta( $post_id, self::META_INSTRUCTIONS );
		} else {
			update_post_meta( $post_id, self::META_INSTRUCTIONS, $clean['instructions'] );
		}

		$clean['title'] = (string) get_the_title( $post_id );

		$this->request_cache[ $post_id ] = $clean;

		return $clean;
	}

	/**
	 * Drop the stored offsets for an exercise.
	 *
	 * The meta box has no way to record which occurrence of a word a gap meant,
	 * so once its text or its item list has changed the stored positions are
	 * guesses about a document that no longer exists. Clearing them costs the
	 * renderer nothing — it falls back to first-occurrence matching, which is
	 * what the meta box has always produced.
	 *
	 * @param int $post_id Exercise post ID.
	 * @return void
	 */
	public function clear_offsets( int $post_id ): void {
		delete_post_meta( $post_id, self::META_OFFSETS );

		if ( isset( $this->request_cache[ $post_id ] ) ) {
			$this->request_cache[ $post_id ]['offsets'] = array();
		}
	}

	/**
	 * Copy one exercise's stored data onto another post.
	 *
	 * @param int $from_id Source exercise.
	 * @param int $to_id Destination exercise.
	 * @return array
	 */
	public function copy( int $from_id, int $to_id ): array {
		return $this->write( $to_id, $this->validator->sanitise( $this->get( $from_id ) ) );
	}

	/**
	 * Determine whether the current visitor may view an exercise.
	 *
	 * @param \WP_Post $post Exercise post.
	 * @return bool
	 */
	public function can_view( \WP_Post $post ): bool {
		if ( 'publish' === $post->post_status ) {
			return true;
		}

		return current_user_can( 'read_post', $post->ID );
	}

	/**
	 * Resolve each gap to a position in the text.
	 *
	 * A stored offset wins when it still points at its own item; otherwise the
	 * gap falls back to the first occurrence that no earlier gap has claimed,
	 * which is what the 1.0.0 renderer did for every gap.
	 *
	 * @param string $text Exercise text.
	 * @param array  $items Gap texts, in reading order.
	 * @param array  $offsets Stored byte offsets, index-aligned with $items.
	 * @return array List of array{start:int, length:int, index:int}, sorted by start.
	 */
	public static function resolve_positions( string $text, array $items, array $offsets ): array {
		$positions = array();
		$claimed   = array();
		$pending   = array();

		// Offsets first: a gap that knows where it belongs claims that spot
		// before any first-occurrence search can take it.
		foreach ( array_values( $items ) as $index => $item ) {
			$item   = (string) $item;
			$length = strlen( $item );
			if ( 0 === $length ) {
				continue;
			}

			$offset = isset( $offsets[ $index ] ) ? (int) $offsets[ $index ] : -1;
			if ( $offset >= 0 && substr( $text, $offset, $length ) === $item && ! self::overlaps( $claimed, $offset, $length ) ) {
				$claimed[]   = array( $offset, $length );
				$positions[] = array(
					'start'  => $offset,
					'length' => $length,
					'index'  => $index,
				);
				continue;
			}

			$pending[ $index ] = $item;
		}

		foreach ( $pending as $index => $item ) {
			$length = strlen( $item );
			$search = 0;

			while ( true ) {
				$found = stripos( $text, $item, $search );
				if ( false === $found ) {
					break;
				}

				if ( ! self::overlaps( $claimed, $found, $length ) ) {
					$claimed[]   = array( $found, $length );
					$positions[] = array(
						'start'  => $found,
						'length' => $length,
						'index'  => $index,
					);
					break;
				}

				$search = $found + 1;
			}
		}

		usort(
			$positions,
			static function ( array $a, array $b ): int {
				return $a['start'] <=> $b['start'];
			}
		);

		return $positions;
	}

	/**
	 * Would a span collide with one already claimed?
	 *
	 * @param array $claimed List of array{0:int start, 1:int length}.
	 * @param int   $start Candidate start.
	 * @param int   $length Candidate length.
	 * @return bool
	 */
	private static function overlaps( array $claimed, int $start, int $length ): bool {
		$end = $start + $length;

		foreach ( $claimed as $span ) {
			if ( $start < $span[0] + $span[1] && $span[0] < $end ) {
				return true;
			}
		}

		return false;
	}
}

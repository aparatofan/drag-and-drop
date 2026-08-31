<?php
/**
 * Exercise data validation and sanitisation.
 *
 * Both authoring paths — the wp-admin meta box and the front-end tools — send
 * their raw input through this class before Exercise_Repository writes it, so
 * there is one definition of what a valid exercise is.
 *
 * @package TBT_Drag_Drop
 */

namespace TBT\DragDrop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Exercise_Validator {
	/**
	 * Gap items per exercise.
	 *
	 * Seven is a reading limit rather than a storage one: past it the token
	 * bank stops being scannable at a glance and the reading panel is more gap
	 * than text.
	 */
	public const MAX_ITEMS = 7;

	/**
	 * Extra bank words per exercise.
	 *
	 * The same reading limit as the gaps, applied to the other half of the
	 * bank: seven gaps plus seven extras is already a long row of tokens to
	 * scan before choosing one.
	 */
	public const MAX_DISTRACTORS = 7;

	/**
	 * Length caps. The title lives in the player hero, the instructions in the
	 * support line beneath it, so both are layout constraints.
	 */
	public const TITLE_MAX        = 120;
	public const INSTRUCTIONS_MAX = 300;

	/**
	 * Validate and sanitise a complete exercise payload.
	 *
	 * @param array $raw Raw exercise data.
	 * @return array|\WP_Error
	 */
	public function validate( array $raw ) {
		$clean = $this->sanitise( $raw );

		if ( '' === $clean['title'] ) {
			return new \WP_Error( 'tbtdd_missing_title', __( 'The exercise needs a title.', 'tbt-drag-drop' ) );
		}

		if ( '' === trim( $clean['text'] ) ) {
			return new \WP_Error( 'tbtdd_missing_text', __( 'The exercise text cannot be empty.', 'tbt-drag-drop' ) );
		}

		/*
		 * Checked before the empty-list guard, not after. sanitise() drops
		 * items the text does not contain, so a teacher whose only gap has
		 * drifted out of the text would otherwise be told to choose a gap they
		 * can see they already chose. Naming the gaps that were lost is the
		 * answer to the question they will actually ask.
		 */
		$missing = $this->missing_items( $clean['text'], $raw );
		if ( ! empty( $missing ) ) {
			return new \WP_Error(
				'tbtdd_item_not_in_text',
				sprintf(
					/* translators: %s: comma-separated list of gap texts. */
					__( 'These gaps no longer appear in the exercise text: %s', 'tbt-drag-drop' ),
					implode( ', ', $missing )
				)
			);
		}

		if ( empty( $clean['items'] ) ) {
			return new \WP_Error( 'tbtdd_no_gaps', __( 'Choose at least one gap before publishing.', 'tbt-drag-drop' ) );
		}

		return $clean;
	}

	/**
	 * Sanitise a payload without enforcing the completeness rules.
	 *
	 * Draft saves take this path: every field is cleaned exactly as a full save
	 * would clean it, but an exercise with no gaps yet is still stored, so a
	 * teacher never loses work by saving early.
	 *
	 * @param array $raw Raw exercise data.
	 * @return array
	 */
	public function sanitise( array $raw ): array {
		$title = isset( $raw['title'] ) && is_scalar( $raw['title'] ) ? sanitize_text_field( (string) $raw['title'] ) : '';
		$title = $this->truncate( $title, self::TITLE_MAX );

		// wp_kses_post(), as in 1.0.0: the text is teacher-authored content and
		// a stored exercise may already contain inline markup.
		$text = isset( $raw['text'] ) && is_scalar( $raw['text'] ) ? wp_kses_post( (string) $raw['text'] ) : '';

		$instructions = isset( $raw['instructions'] ) && is_scalar( $raw['instructions'] )
			? sanitize_textarea_field( (string) $raw['instructions'] )
			: '';
		$instructions = $this->truncate( $instructions, self::INSTRUCTIONS_MAX );

		list( $items, $offsets ) = $this->clean_items( $text, $raw );

		return array(
			'title'        => $title,
			'text'         => $text,
			'items'        => $items,
			'offsets'      => $offsets,
			'instructions' => $instructions,
			'distractors'  => $this->clean_distractors( $raw, $items ),
		);
	}

	/**
	 * Clean the gap list and the offsets that travel with it.
	 *
	 * Items and offsets are index-aligned, so they are cleaned together: an
	 * item that is dropped takes its offset with it, and an offset that no
	 * longer points at its item is discarded rather than left to mislead the
	 * renderer.
	 *
	 * @param string $text Clean exercise text.
	 * @param array  $raw Raw exercise data.
	 * @return array{0: string[], 1: int[]}
	 */
	private function clean_items( string $text, array $raw ): array {
		$raw_items   = isset( $raw['items'] ) && is_array( $raw['items'] ) ? array_values( $raw['items'] ) : array();
		$raw_offsets = isset( $raw['offsets'] ) && is_array( $raw['offsets'] ) ? array_values( $raw['offsets'] ) : array();

		$items   = array();
		$offsets = array();
		$seen    = array();

		foreach ( $raw_items as $index => $raw_item ) {
			if ( ! is_scalar( $raw_item ) ) {
				continue;
			}

			$item = trim( wp_strip_all_tags( (string) $raw_item ) );
			if ( '' === $item ) {
				continue;
			}

			/*
			 * Case-insensitive, because the answer check is: two gaps that
			 * differ only in case produce two bank tokens that either one can
			 * satisfy, which is exactly the ambiguity the rule exists to stop.
			 */
			$key = $this->duplicate_key( $item );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			// An item the text does not contain has no gap to fill.
			if ( false === mb_stripos( $text, $item ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$items[]      = $item;
			$offsets[]    = $this->clean_offset( $text, $item, $raw_offsets[ $index ] ?? null );

			if ( count( $items ) >= self::MAX_ITEMS ) {
				break;
			}
		}

		// An offset list that is all -1 says nothing the renderer does not
		// already assume, so it is not stored at all.
		if ( array() === array_filter( $offsets, static fn( int $offset ): bool => $offset >= 0 ) ) {
			$offsets = array();
		}

		return array( $items, $offsets );
	}

	/**
	 * Clean the extra bank words.
	 *
	 * Deliberately not checked against the text: an extra word exists to be
	 * wrong, so being absent from the text is the whole point, and that is what
	 * separates this list from the gap items.
	 *
	 * Accepts a list or one comma-separated string, because the front-end field
	 * is a single input the teacher types into and the meta box is the same:
	 * splitting here keeps one definition of what an extra word is.
	 *
	 * A word that matches a gap item is dropped. Two identical tokens where one
	 * of them is the answer is not a harder exercise, it is an unfair one.
	 *
	 * @param array $raw Raw exercise data.
	 * @param array $items Clean gap items.
	 * @return string[]
	 */
	private function clean_distractors( array $raw, array $items ): array {
		$raw_distractors = $raw['distractors'] ?? array();

		if ( is_scalar( $raw_distractors ) ) {
			$raw_distractors = explode( ',', (string) $raw_distractors );
		}

		if ( ! is_array( $raw_distractors ) ) {
			return array();
		}

		$seen = array();
		foreach ( $items as $item ) {
			$seen[ $this->duplicate_key( (string) $item ) ] = true;
		}

		$distractors = array();
		foreach ( $raw_distractors as $raw_distractor ) {
			if ( ! is_scalar( $raw_distractor ) ) {
				continue;
			}

			$distractor = trim( wp_strip_all_tags( (string) $raw_distractor ) );
			if ( '' === $distractor ) {
				continue;
			}

			$key = $this->duplicate_key( $distractor );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ]  = true;
			$distractors[] = $distractor;

			if ( count( $distractors ) >= self::MAX_DISTRACTORS ) {
				break;
			}
		}

		return $distractors;
	}

	/**
	 * Accept an offset only when it still points at its own item.
	 *
	 * Offsets are an optimisation of fidelity, never a requirement: a stale or
	 * missing one costs the second-occurrence gap its exact position and
	 * nothing else, so it is quietly discarded rather than raised as an error.
	 *
	 * @param string $text Clean exercise text.
	 * @param string $item Clean gap text.
	 * @param mixed  $offset Raw offset.
	 * @return int Byte offset, or -1 when there is no usable one.
	 */
	private function clean_offset( string $text, string $item, $offset ): int {
		if ( ! is_numeric( $offset ) ) {
			return -1;
		}

		$offset = (int) $offset;
		if ( $offset < 0 ) {
			return -1;
		}

		return substr( $text, $offset, strlen( $item ) ) === $item ? $offset : -1;
	}

	/**
	 * Gap texts the caller asked for that the text does not contain.
	 *
	 * @param string $text Clean exercise text.
	 * @param array  $raw Raw exercise data.
	 * @return string[]
	 */
	private function missing_items( string $text, array $raw ): array {
		$raw_items = isset( $raw['items'] ) && is_array( $raw['items'] ) ? $raw['items'] : array();
		$missing   = array();

		foreach ( $raw_items as $raw_item ) {
			if ( ! is_scalar( $raw_item ) ) {
				continue;
			}

			$item = trim( wp_strip_all_tags( (string) $raw_item ) );
			if ( '' === $item || false !== mb_stripos( $text, $item ) ) {
				continue;
			}

			$missing[ $this->duplicate_key( $item ) ] = $item;
		}

		return array_values( $missing );
	}

	/**
	 * Normalise a gap text for duplicate detection.
	 *
	 * @param string $value Gap text.
	 * @return string
	 */
	private function duplicate_key( string $value ): string {
		$value = (string) preg_replace( '/\s+/u', ' ', trim( $value ) );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
	}

	/**
	 * Truncate safely when mbstring is available.
	 *
	 * @param string $value Text.
	 * @param int    $limit Character limit.
	 * @return string
	 */
	private function truncate( string $value, int $limit ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $limit );
		}

		return substr( $value, 0, $limit );
	}
}

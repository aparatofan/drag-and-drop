<?php
/**
 * Exercise shortcode.
 *
 * @package TBT_Drag_Drop
 */

namespace TBT\DragDrop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcode {
	/**
	 * Unchanged since 1.0.0 — every lesson page already embedding an exercise
	 * uses this name.
	 */
	public const SHORTCODE = 'dd_exercise';

	private Renderer $renderer;

	public function __construct( Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * Render shortcode output.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'                => 0,
				'show_title'        => 'yes',
				'show_instructions' => 'yes',
				// An embedded exercise sits inside a lesson that already has a
				// heading and an identity of its own, so it renders without the
				// hero by default. hero="yes" brings it back.
				'compact'           => 'yes',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);

		$post_id = absint( $atts['id'] );
		if ( ! $post_id ) {
			return '';
		}

		return $this->renderer->render(
			$post_id,
			array(
				'show_title'        => 'no' !== strtolower( (string) $atts['show_title'] ),
				'show_instructions' => 'no' !== strtolower( (string) $atts['show_instructions'] ),
				'compact'           => 'no' !== strtolower( (string) $atts['compact'] ),
			)
		);
	}
}

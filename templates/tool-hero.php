<?php
/**
 * Canonical Tool Hero (Style Book v1.0 §6B).
 *
 * Available variables: $hero (eyebrow, title, support, logo).
 *
 * @package TBT_Drag_Drop
 */

namespace TBT\DragDrop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $hero ) || ! is_array( $hero ) ) {
	return;
}
?>
<header class="tbt-tool-hero">
	<div class="tbt-tool-hero__content">
		<?php if ( '' !== (string) $hero['eyebrow'] ) : ?>
			<p class="tbt-tool-hero__eyebrow"><?php echo esc_html( $hero['eyebrow'] ); ?></p>
		<?php endif; ?>
		<h1 class="tbt-tool-hero__title"><?php echo esc_html( $hero['title'] ); ?></h1>
		<?php if ( '' !== (string) $hero['support'] ) : ?>
			<p class="tbt-tool-hero__support"><?php echo esc_html( $hero['support'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	/*
	 * The same fallback the player's hero uses. Printing the flat white PNG
	 * unconditionally left a ghost tree on the tool heroes while the player
	 * showed the colour one.
	 *
	 * animate="no" here: a tool page is a workspace, not an arrival, and a
	 * tree unfurling over a form the teacher is already typing into competes
	 * with the work.
	 */
	if ( shortcode_exists( 'tbt_tree' ) ) {
		echo do_shortcode( '[tbt_tree width="190px" animate="no"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} elseif ( '' !== (string) $hero['logo'] ) {
		printf(
			'<img class="tbt-tool-hero__logo" src="%1$s" alt="%2$s" loading="lazy" decoding="async">',
			esc_url( $hero['logo'] ),
			esc_attr__( 'The Blue Tree', 'tbt-drag-drop' )
		);
	}
	?>
</header>

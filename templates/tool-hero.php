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
	<?php if ( '' !== (string) $hero['logo'] ) : ?>
		<img
			class="tbt-tool-hero__logo"
			src="<?php echo esc_url( $hero['logo'] ); ?>"
			alt="<?php esc_attr_e( 'The Blue Tree', 'tbt-drag-drop' ); ?>"
			loading="lazy"
			decoding="async"
		>
	<?php endif; ?>
</header>

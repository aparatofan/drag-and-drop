<?php
/**
 * Shared exercise player markup.
 *
 * Available variables: $post, $data, $args, $instance_id, $config,
 * $reading_html, $bank, $instructions. $bank is a list of
 * array( 'word' => string, 'letter' => string ) in shuffled order.
 *
 * @package TBT_Drag_Drop
 */

namespace TBT\DragDrop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tbtdd_classes = array( 'tbtdd-exercise' );
if ( ! empty( $args['compact'] ) ) {
	$tbtdd_classes[] = 'tbtdd-exercise--compact';
}

$tbtdd_show_hero = empty( $args['compact'] ) && ( ! empty( $args['show_title'] ) || ! empty( $args['show_instructions'] ) );
?>
<div
	class="<?php echo esc_attr( implode( ' ', $tbtdd_classes ) ); ?>"
	id="<?php echo esc_attr( $instance_id ); ?>"
	data-tbtdd-instance="<?php echo esc_attr( $instance_id ); ?>"
>
	<?php if ( $tbtdd_show_hero ) : ?>
		<header class="tbtdd-hero">
			<div class="tbtdd-hero__content">
				<p class="tbtdd-hero__eyebrow"><?php esc_html_e( 'Drag &amp; Drop', 'tbt-drag-drop' ); ?></p>
				<?php if ( ! empty( $args['show_title'] ) ) : ?>
					<h1 class="tbtdd-hero__title"><?php echo esc_html( $data['title'] ); ?></h1>
				<?php endif; ?>
				<?php if ( ! empty( $args['show_instructions'] ) ) : ?>
					<p class="tbtdd-hero__support"><?php echo esc_html( $instructions ); ?></p>
				<?php endif; ?>
			</div>
			<?php
			/*
			 * The mark comes from TBT Hub's [tbt_tree] shortcode, which inlines
			 * the SVG so its leaves can animate individually. Hub is not a hard
			 * dependency: if it is inactive the shortcode does not exist and the
			 * player falls back to the flat white PNG, so an exercise page never
			 * renders without a mark.
			 */
			if ( shortcode_exists( 'tbt_tree' ) ) {
				echo do_shortcode( '[tbt_tree width="190px" animate="yes"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				printf(
					'<img class="tbtdd-hero__logo" src="%1$s" alt="%2$s" loading="lazy" decoding="async">',
					esc_url( Tools_Shortcode::LOGO_URL ),
					esc_attr__( 'The Blue Tree', 'tbt-drag-drop' )
				);
			}
			?>
		</header>
	<?php elseif ( ! empty( $args['compact'] ) && ! empty( $args['show_title'] ) ) : ?>
		<h2 class="tbtdd-embedded-title"><?php echo esc_html( $data['title'] ); ?></h2>
	<?php endif; ?>

	<?php if ( ! empty( $args['compact'] ) && ! empty( $args['show_instructions'] ) ) : ?>
		<p class="tbtdd-embedded-instructions"><?php echo esc_html( $instructions ); ?></p>
	<?php endif; ?>

	<div class="tbtdd-bank" data-tbtdd-bank aria-label="<?php esc_attr_e( 'Words to place', 'tbt-drag-drop' ); ?>">
		<?php foreach ( $bank as $tbtdd_entry ) : ?>
			<?php
			/*
			 * The letter is a flex child of the token, not an overlay: it has to
			 * sit beside the word rather than on top of it. game.js reads
			 * data-tbtdd-letter to keep the bank in letter order.
			 */
			?>
			<button
				type="button"
				class="tbtdd-token"
				draggable="true"
				data-tbtdd-token="<?php echo esc_attr( $tbtdd_entry['word'] ); ?>"
				data-tbtdd-letter="<?php echo esc_attr( $tbtdd_entry['letter'] ); ?>"
			><span class="tbtdd-tag tbtdd-tag--letter"><?php echo esc_html( $tbtdd_entry['letter'] ); ?></span><?php echo esc_html( $tbtdd_entry['word'] ); ?></button>
		<?php endforeach; ?>
	</div>

	<?php
	/*
	 * Not part of the teacher's instructions, which are stored per exercise:
	 * this describes how the player works, so it is the plugin's line to
	 * write and it appears on every exercise. Desktop wording on purpose —
	 * the keyboard route exists for a touchpad, not for a phone.
	 */
	?>
	<p class="tbtdd-hint"><?php esc_html_e( 'You can also press Tab to move between the gaps, then type the letter shown on a word to place it.', 'tbt-drag-drop' ); ?></p>

	<div class="tbtdd-reading" data-tbtdd-reading><?php
		echo $reading_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped run by run in Renderer::reading_html().
	?></div>

	<p class="tbtdd-sr-only" aria-live="polite" data-tbtdd-live></p>

	<div class="tbtdd-actions">
		<button type="button" class="tbtdd-button tbtdd-button--cta" data-tbtdd-check><?php esc_html_e( 'Check your answers', 'tbt-drag-drop' ); ?></button>
		<span class="tbtdd-score" data-tbtdd-score hidden></span>
		<button type="button" class="tbtdd-button" data-tbtdd-show hidden><?php esc_html_e( 'Show correct', 'tbt-drag-drop' ); ?></button>
		<?php
		/*
		 * Filled, so the row after Check reads as white "Show correct" beside
		 * blue "Redo exercise" — and once Show correct hides itself, one blue
		 * button remains rather than a lone white one.
		 */
		?>
		<button type="button" class="tbtdd-button tbtdd-button--primary" data-tbtdd-redo hidden><?php esc_html_e( 'Redo exercise', 'tbt-drag-drop' ); ?></button>
	</div>

	<script type="application/json" class="tbtdd-config"><?php
		echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?></script>
</div>
<?php do_action( 'tbt_drag_drop_after_render', $post->ID, $data, $args ); ?>

<?php
/**
 * Front-end exercise library.
 *
 * Rows are rendered by tools.js from GET /exercises so search, pagination and
 * the row actions all read from one owner-scoped source of truth.
 *
 * Available variables: $hero, $generator_url.
 *
 * @package TBT_Drag_Drop
 */

namespace TBT\DragDrop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tbtdd_uid = 'tbtdd-lib-' . wp_unique_id();

/*
 * The resolved generator URL travels on the markup rather than in the localised
 * config: the bundle is localised once, before any shortcode has run, and two
 * libraries on one page may point at different generators.
 */
$tbtdd_generator_url = isset( $generator_url ) ? (string) $generator_url : '';
?>
<div class="tbt tbt-tool tbtdd-tool tbtdd-library" data-tbtdd-tool="library" data-tbtdd-generator-url="<?php echo esc_url( $tbtdd_generator_url ); ?>">

	<?php require TBTDD_DIR . 'templates/tool-hero.php'; ?>

	<div class="tbtdd-section-head">
		<h2 class="tbtdd-section-title"><?php esc_html_e( 'Your exercises', 'tbt-drag-drop' ); ?></h2>
		<span class="tbtdd-section-rule" aria-hidden="true"></span>
	</div>

	<div class="tbtdd-library__head">
		<div class="tbtdd-field tbtdd-field--search">
			<label for="<?php echo esc_attr( $tbtdd_uid ); ?>-search"><?php esc_html_e( 'Search your exercises', 'tbt-drag-drop' ); ?></label>
			<input
				type="search"
				id="<?php echo esc_attr( $tbtdd_uid ); ?>-search"
				data-tbtdd-search
				placeholder="<?php esc_attr_e( 'Exercise title', 'tbt-drag-drop' ); ?>"
				autocomplete="off"
			>
		</div>

		<?php
		/*
		 * No generator URL, no button: creating an exercise would have nowhere
		 * to land.
		 */
		?>
		<?php if ( '' !== $tbtdd_generator_url ) : ?>
			<button type="button" class="tbtdd-button tbtdd-button--primary tbtdd-library__create" data-tbtdd-create>
				<?php esc_html_e( 'Create new', 'tbt-drag-drop' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<div class="tbtdd-notice" data-tbtdd-notice role="status" aria-live="polite" hidden></div>

	<div class="tbtdd-library__list" data-tbtdd-list aria-live="polite" aria-busy="false"></div>

	<nav class="tbtdd-pagination" data-tbtdd-pagination aria-label="<?php esc_attr_e( 'Exercise library pages', 'tbt-drag-drop' ); ?>" hidden></nav>
</div>

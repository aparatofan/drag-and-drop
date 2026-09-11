<?php
/**
 * Front-end exercise library.
 *
 * Rows are rendered by tools.js from GET /exercises so search, pagination and
 * the row actions all read from one owner-scoped source of truth.
 *
 * Available variables: $hero, $generator_url, $total.
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

	<?php
	$tbtdd_total = isset( $total ) ? (int) $total : 0;
	$tbtdd_empty = 0 === $tbtdd_total;
	?>
	<div class="tbtdd-libbar<?php echo $tbtdd_empty ? ' is-empty' : ''; ?>" data-tbtdd-libbar data-tbtdd-total="<?php echo esc_attr( $tbtdd_total ); ?>">
		<div class="tbtdd-libbar__title">
			<h2 class="tbtdd-section-title"><?php esc_html_e( 'Your exercises', 'tbt-drag-drop' ); ?></h2>
			<span class="tbtdd-libbar__line" aria-hidden="true"></span>
		</div>

		<div class="tbtdd-libbar__filter" role="search" data-tbtdd-libbar-filter<?php echo $tbtdd_empty ? ' hidden' : ''; ?>>
			<div class="tbtdd-libbar__search">
				<label class="tbtdd-sr-only" for="<?php echo esc_attr( $tbtdd_uid ); ?>-search"><?php esc_html_e( 'Search your exercises', 'tbt-drag-drop' ); ?></label>
				<svg class="tbtdd-libbar__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
					<circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2.2"/>
					<path d="m20 20-3.6-3.6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
				</svg>
				<input type="search" id="<?php echo esc_attr( $tbtdd_uid ); ?>-search" class="tbtdd-libbar__input" data-tbtdd-search
					placeholder="<?php esc_attr_e( 'Search by exercise title', 'tbt-drag-drop' ); ?>"
					autocomplete="off" spellcheck="false">
				<button type="button" class="tbtdd-libbar__clear" data-tbtdd-search-clear
					aria-label="<?php esc_attr_e( 'Clear search', 'tbt-drag-drop' ); ?>" hidden>&times;</button>
			</div>
		</div>

		<span class="tbtdd-libbar__line tbtdd-libbar__line--end" aria-hidden="true"></span>

		<?php
		/*
		 * No generator URL, no button: creating an exercise would have nowhere
		 * to land.
		 */
		?>
		<?php if ( '' !== $tbtdd_generator_url ) : ?>
			<button type="button" class="tbtdd-button tbtdd-button--primary tbtdd-libbar__cta" data-tbtdd-create>
				<?php esc_html_e( 'Create new exercise', 'tbt-drag-drop' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<p class="tbtdd-libbar__summary" data-tbtdd-summary aria-live="polite" hidden>
		<span data-tbtdd-summary-text></span>
		<button type="button" class="tbtdd-libbar__link" data-tbtdd-reset><?php esc_html_e( 'Clear filters', 'tbt-drag-drop' ); ?></button>
	</p>

	<div class="tbtdd-notice" data-tbtdd-notice role="status" aria-live="polite" hidden></div>

	<div class="tbtdd-library__list" data-tbtdd-list aria-live="polite" aria-busy="false"></div>

	<nav class="tbtdd-pagination" data-tbtdd-pagination aria-label="<?php esc_attr_e( 'Exercise library pages', 'tbt-drag-drop' ); ?>" hidden></nav>
</div>

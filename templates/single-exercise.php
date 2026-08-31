<?php
/**
 * Standalone exercise template.
 *
 * Themes may override this file at:
 * tbt-drag-drop/single-exercise.php
 *
 * @package TBT_Drag_Drop
 */

use TBT\DragDrop\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main class="tbtdd-standalone" id="primary">
	<div class="tbtdd-standalone__inner">
		<?php echo Plugin::instance()->renderer()->render( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</main>
<?php
get_footer();

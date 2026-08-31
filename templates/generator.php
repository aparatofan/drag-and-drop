<?php
/**
 * Front-end generator tool.
 *
 * Available variables: $exercise_id, $data, $status, $permalink, $preview,
 * $denied, $hero, $library_url.
 *
 * @package TBT_Drag_Drop
 */

namespace TBT\DragDrop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tbtdd_uid = 'tbtdd-gen-' . wp_unique_id();

/*
 * The resolved library URL travels on the markup rather than in the localised
 * config: the bundle is localised once, before any shortcode has run.
 */
$tbtdd_library_url = isset( $library_url ) ? (string) $library_url : '';
$tbtdd_shortcode   = $exercise_id ? sprintf( '[dd_exercise id="%d"]', $exercise_id ) : '';

/*
 * The picker opens on exactly the gaps the player renders, so the offsets are
 * resolved here with the same rules rather than handed over raw. Each gap
 * carries the text as it actually appears at that position — an item stored in
 * a different case than the text it matched would otherwise redraw the
 * sentence when it was re-rendered as a gap.
 *
 * Offsets cross into JavaScript as character indexes, because that is what a
 * JS string is indexed by; tools.js converts them back to byte offsets when it
 * saves, which is what §4's substr() rule is written against.
 */
$tbtdd_initial_gaps = array();
foreach ( Exercise_Repository::resolve_positions( (string) $data['text'], (array) $data['items'], (array) $data['offsets'] ) as $tbtdd_position ) {
	$tbtdd_initial_gaps[] = array(
		'text'   => substr( (string) $data['text'], $tbtdd_position['start'], $tbtdd_position['length'] ),
		'offset' => mb_strlen( substr( (string) $data['text'], 0, $tbtdd_position['start'] ), 'UTF-8' ),
	);
}
?>
<div
	class="tbt tbt-tool tbtdd-tool tbtdd-generator"
	data-tbtdd-tool="generator"
	data-tbtdd-exercise-id="<?php echo esc_attr( (string) $exercise_id ); ?>"
	data-tbtdd-status="<?php echo esc_attr( (string) $status ); ?>"
	data-tbtdd-library-url="<?php echo esc_url( $tbtdd_library_url ); ?>"
	data-tbtdd-preview-url="<?php echo esc_url( (string) $preview ); ?>"
>
	<?php
	/*
	 * Above the hero, not inside it: the hero says what this tool is, and the
	 * way out of the page is chrome rather than part of that statement.
	 */
	?>
	<?php if ( '' !== $tbtdd_library_url ) : ?>
		<p class="tbtdd-backlink">
			<a class="tbtdd-backlink__link" href="<?php echo esc_url( $tbtdd_library_url ); ?>">
				<span class="tbtdd-backlink__arrow" aria-hidden="true">&#8592;</span>
				<?php esc_html_e( 'Back to my exercises', 'tbt-drag-drop' ); ?>
			</a>
		</p>
	<?php endif; ?>

	<?php require TBTDD_DIR . 'templates/tool-hero.php'; ?>

	<?php if ( ! empty( $denied ) ) : ?>
		<p class="tbtdd-notice tbtdd-notice--error"><?php esc_html_e( 'That exercise belongs to another teacher, so a new exercise was started instead.', 'tbt-drag-drop' ); ?></p>
	<?php endif; ?>

	<div class="tbtdd-notice" data-tbtdd-notice role="status" aria-live="polite" hidden></div>

	<?php
	/*
	 * data-state drives the stage rim: grey when the stage cannot be worked on
	 * yet, blue while it is in hand, green once it is finished. tools.js keeps
	 * it in step with the fields; these are the states an exercise opens in.
	 */
	?>
	<section class="tbtdd-stage" data-tbtdd-stage="1" data-state="active">
		<div class="tbtdd-stage__head">
			<span class="tbtdd-stage__no"><?php esc_html_e( 'Stage 1.', 'tbt-drag-drop' ); ?></span>
			<h2 class="tbtdd-stage__name"><?php esc_html_e( 'Write the exercise', 'tbt-drag-drop' ); ?></h2>
		</div>
		<p class="tbtdd-hint"><?php esc_html_e( 'Paste or type the text your students will read. Punctuation and line breaks are kept as written.', 'tbt-drag-drop' ); ?></p>

		<div class="tbtdd-field">
			<label for="<?php echo esc_attr( $tbtdd_uid ); ?>-title"><?php esc_html_e( 'Exercise title', 'tbt-drag-drop' ); ?></label>
			<input
				type="text"
				id="<?php echo esc_attr( $tbtdd_uid ); ?>-title"
				data-tbtdd-field="title"
				maxlength="<?php echo esc_attr( (string) Exercise_Validator::TITLE_MAX ); ?>"
				placeholder="<?php esc_attr_e( 'Example: Present perfect — travel', 'tbt-drag-drop' ); ?>"
				value="<?php echo esc_attr( $exercise_id ? $data['title'] : '' ); ?>"
			>
		</div>

		<div class="tbtdd-field">
			<label for="<?php echo esc_attr( $tbtdd_uid ); ?>-text"><?php esc_html_e( 'Exercise text', 'tbt-drag-drop' ); ?></label>
			<textarea
				id="<?php echo esc_attr( $tbtdd_uid ); ?>-text"
				data-tbtdd-field="text"
				rows="8"
			><?php echo esc_textarea( $data['text'] ); ?></textarea>
		</div>

		<div class="tbtdd-field">
			<label for="<?php echo esc_attr( $tbtdd_uid ); ?>-instructions"><?php esc_html_e( 'Student instructions', 'tbt-drag-drop' ); ?></label>
			<input
				type="text"
				id="<?php echo esc_attr( $tbtdd_uid ); ?>-instructions"
				data-tbtdd-field="instructions"
				maxlength="<?php echo esc_attr( (string) Exercise_Validator::INSTRUCTIONS_MAX ); ?>"
				placeholder="<?php echo esc_attr( Exercise_Repository::default_instructions() ); ?>"
				<?php
				/*
				 * The real sentence, not just a placeholder: a teacher edits
				 * text that is already there instead of retyping the default to
				 * change three words of it. Emptying the field still deletes
				 * the meta, and the renderer still falls back to the default.
				 */
				?>
				value="<?php echo esc_attr( '' !== trim( (string) $data['instructions'] ) ? $data['instructions'] : Exercise_Repository::default_instructions() ); ?>"
			>
			<p class="tbtdd-hint tbtdd-hint--field"><?php esc_html_e( 'Shown under the title on the exercise page. Leave empty for the default line.', 'tbt-drag-drop' ); ?></p>
		</div>
	</section>

	<section class="tbtdd-stage" data-tbtdd-stage="2" data-state="active">
		<div class="tbtdd-stage__head">
			<span class="tbtdd-stage__no"><?php esc_html_e( 'Stage 2.', 'tbt-drag-drop' ); ?></span>
			<h2 class="tbtdd-stage__name"><?php esc_html_e( 'Choose the gaps', 'tbt-drag-drop' ); ?></h2>
		</div>
		<p class="tbtdd-hint"><?php esc_html_e( 'Click a word to turn it into a gap. Drag across several words to gap a whole phrase. Click a blue gap to put the words back.', 'tbt-drag-drop' ); ?></p>

		<div class="tbtdd-picker" data-tbtdd-picker></div>
		<div class="tbtdd-chips" data-tbtdd-chips></div>
		<p class="tbtdd-notice tbtdd-notice--error tbtdd-notice--inline" data-tbtdd-gap-notice role="status" aria-live="polite" hidden></p>

		<div class="tbtdd-field tbtdd-field--extras">
			<label for="<?php echo esc_attr( $tbtdd_uid ); ?>-distractors"><?php esc_html_e( 'Extra words (optional)', 'tbt-drag-drop' ); ?></label>
			<?php
			/*
			 * Typed, not picked: these words are precisely the ones the picker
			 * cannot offer, because they are not in the text. One field with
			 * commas rather than a row of inputs — a teacher adds two or three
			 * of these in passing, and Exercise_Validator does the splitting.
			 */
			?>
			<input
				type="text"
				id="<?php echo esc_attr( $tbtdd_uid ); ?>-distractors"
				data-tbtdd-field="distractors"
				placeholder="<?php esc_attr_e( 'Example: went, has been, were', 'tbt-drag-drop' ); ?>"
				value="<?php echo esc_attr( implode( ', ', $data['distractors'] ) ); ?>"
			>
			<p class="tbtdd-hint tbtdd-hint--field">
				<?php
				printf(
					/* translators: %d: maximum number of extra words. */
					esc_html__( 'Words that are not in the text, separated by commas. They join the word bank but fill no gap, so students see more words than they need. Up to %d.', 'tbt-drag-drop' ),
					(int) Exercise_Validator::MAX_DISTRACTORS
				);
				?>
			</p>
		</div>
	</section>

	<section class="tbtdd-stage" data-tbtdd-stage="3" data-state="active">
		<div class="tbtdd-stage__head">
			<span class="tbtdd-stage__no"><?php esc_html_e( 'Stage 3.', 'tbt-drag-drop' ); ?></span>
			<h2 class="tbtdd-stage__name"><?php esc_html_e( 'Publish and share', 'tbt-drag-drop' ); ?></h2>
		</div>
		<p class="tbtdd-hint"><?php esc_html_e( 'A published exercise has its own page you can send to a student, and a shortcode for a lesson page.', 'tbt-drag-drop' ); ?></p>

		<div class="tbtdd-share" data-tbtdd-share>
			<?php if ( '' !== (string) $permalink ) : ?>
				<div class="tbtdd-linkrow">
					<code data-tbtdd-permalink><?php echo esc_html( $permalink ); ?></code>
					<button type="button" class="tbtdd-button tbtdd-button--small" data-tbtdd-copy="<?php echo esc_attr( $permalink ); ?>"><?php esc_html_e( 'Copy link', 'tbt-drag-drop' ); ?></button>
				</div>
			<?php else : ?>
				<p class="tbtdd-hint" data-tbtdd-draft-note><?php esc_html_e( 'This exercise is a draft, so it has no public link yet. Publish it to get one.', 'tbt-drag-drop' ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $tbtdd_shortcode ) : ?>
				<div class="tbtdd-linkrow">
					<code data-tbtdd-shortcode><?php echo esc_html( $tbtdd_shortcode ); ?></code>
					<button type="button" class="tbtdd-button tbtdd-button--small" data-tbtdd-copy="<?php echo esc_attr( $tbtdd_shortcode ); ?>"><?php esc_html_e( 'Copy shortcode', 'tbt-drag-drop' ); ?></button>
				</div>
			<?php endif; ?>
		</div>

		<div class="tbtdd-actions">
			<button type="button" class="tbtdd-button tbtdd-button--primary" data-tbtdd-publish><?php esc_html_e( 'Publish exercise', 'tbt-drag-drop' ); ?></button>
			<button type="button" class="tbtdd-button" data-tbtdd-preview><?php esc_html_e( 'Preview as student', 'tbt-drag-drop' ); ?></button>
			<button type="button" class="tbtdd-button" data-tbtdd-draft><?php esc_html_e( 'Save draft', 'tbt-drag-drop' ); ?></button>
			<span class="tbtdd-status" data-tbtdd-save-status role="status" aria-live="polite"></span>
		</div>
	</section>

	<?php
	/*
	 * Below the stage, not inside its button row: finishing this exercise and
	 * starting the next one are different moves, and the row is where the
	 * teacher works on the exercise they still have open. Revealed by tools.js
	 * once the exercise is saved and hidden again the moment anything is
	 * edited; tools.js sets the href from the generator URL with exercise_id
	 * dropped, and leaves it hidden if none resolves.
	 */
	?>
	<div class="tbtdd-next" data-tbtdd-next hidden>
		<a class="tbtdd-button tbtdd-button--primary tbtdd-button--next" data-tbtdd-create-new href="#"><?php esc_html_e( 'Create another exercise', 'tbt-drag-drop' ); ?></a>
	</div>

	<?php
	/*
	 * Drafts only, and at the end of the page behind its own rule. Deleting a
	 * published exercise is the library's job, where the teacher can see what
	 * they are removing from the whole collection; this is here to undo an
	 * abandoned creation.
	 */
	?>
	<div class="tbtdd-actions tbtdd-actions--discard" data-tbtdd-discard-row <?php echo ( $exercise_id && 'draft' === $status ) ? '' : 'hidden'; ?>>
		<button type="button" class="tbtdd-button tbtdd-button--danger" data-tbtdd-discard><?php esc_html_e( 'Discard exercise', 'tbt-drag-drop' ); ?></button>
		<span class="tbtdd-status" data-tbtdd-discard-status role="status" aria-live="polite"></span>
	</div>

	<script type="application/json" data-tbtdd-initial-gaps><?php
		echo wp_json_encode( $tbtdd_initial_gaps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?></script>
</div>

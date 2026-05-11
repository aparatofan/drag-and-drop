<?php
/**
 * Plugin Name: Drag & Drop Gap Exercises
 * Description: Create drag-and-drop gap-fill exercises and embed them with a shortcode.
 * Version: 1.0.0
 * Author: Codex
 * Text Domain: dd-gap-exercises
 */

if (!defined('ABSPATH')) {
    exit;
}

class DD_Gap_Exercises_Plugin {
    private const META_TEXT = '_dd_gap_text';
    private const META_ITEMS = '_dd_gap_items';

    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'register_meta_box']);
        add_action('save_post_dd_exercise', [$this, 'save_exercise']);

        add_shortcode('dd_exercise', [$this, 'render_shortcode']);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'register_front_assets']);
    }

    public function register_post_type(): void {
        register_post_type('dd_exercise', [
            'labels' => [
                'name' => __('Drag Exercises', 'dd-gap-exercises'),
                'singular_name' => __('Drag Exercise', 'dd-gap-exercises'),
                'add_new_item' => __('Add New Exercise', 'dd-gap-exercises'),
                'edit_item' => __('Edit Exercise', 'dd-gap-exercises'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-editor-ol',
            'supports' => ['title'],
            'has_archive' => false,
            'rewrite' => false,
        ]);
    }

    public function register_meta_box(): void {
        add_meta_box(
            'dd-gap-exercise-builder',
            __('Exercise Builder', 'dd-gap-exercises'),
            [$this, 'render_meta_box'],
            'dd_exercise',
            'normal',
            'high'
        );
    }

    public function render_meta_box(\WP_Post $post): void {
        $text = (string) get_post_meta($post->ID, self::META_TEXT, true);
        $items = get_post_meta($post->ID, self::META_ITEMS, true);

        if (!is_array($items)) {
            $items = [];
        }

        wp_nonce_field('dd_gap_save_exercise', 'dd_gap_nonce');
        ?>
        <div class="dd-gap-admin-wrap">
            <p>
                <label for="dd-gap-text"><strong><?php esc_html_e('Exercise text', 'dd-gap-exercises'); ?></strong></label>
            </p>
            <textarea id="dd-gap-text" name="dd_gap_text" rows="8" class="widefat" required><?php echo esc_textarea($text); ?></textarea>
            <p class="description"><?php esc_html_e('Paste the full text for the exercise.', 'dd-gap-exercises'); ?></p>

            <hr/>

            <p><strong><?php esc_html_e('Gap items (1 to 7)', 'dd-gap-exercises'); ?></strong></p>
            <p class="description"><?php esc_html_e('Each selected word/expression must appear in the text. Duplicate items are not allowed.', 'dd-gap-exercises'); ?></p>

            <div id="dd-gap-items">
                <?php foreach ($items as $item) : ?>
                    <div class="dd-gap-item-row">
                        <input type="text" name="dd_gap_items[]" value="<?php echo esc_attr($item); ?>" class="regular-text dd-gap-item-input" />
                        <button type="button" class="button dd-gap-remove-item"><?php esc_html_e('Remove', 'dd-gap-exercises'); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>

            <p>
                <button type="button" class="button button-secondary" id="dd-gap-add-item"><?php esc_html_e('Add gap item', 'dd-gap-exercises'); ?></button>
            </p>

            <p class="dd-gap-shortcode-note">
                <?php
                printf(
                    esc_html__('Use this shortcode in posts/pages: %s', 'dd-gap-exercises'),
                    '<code>[dd_exercise id="' . absint($post->ID) . '"]</code>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    public function enqueue_admin_assets(string $hook): void {
        global $post_type;

        if (($hook !== 'post.php' && $hook !== 'post-new.php') || $post_type !== 'dd_exercise') {
            return;
        }

        wp_enqueue_style(
            'dd-gap-admin',
            plugin_dir_url(__FILE__) . 'assets/css/admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'dd-gap-admin',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            [],
            '1.0.0',
            true
        );

        wp_localize_script('dd-gap-admin', 'ddGapAdmin', [
            'maxItems' => 7,
            'duplicateMessage' => __('You cannot use the same item twice.', 'dd-gap-exercises'),
            'limitMessage' => __('You can only add up to 7 items.', 'dd-gap-exercises'),
            'minMessage' => __('Add at least 1 gap item.', 'dd-gap-exercises'),
            'missingMessage' => __('Each item must exist in the text exactly as written.', 'dd-gap-exercises'),
            'removeLabel' => __('Remove', 'dd-gap-exercises'),
        ]);
    }

    public function register_front_assets(): void {
        wp_register_style(
            'dd-gap-roboto',
            'https://fonts.googleapis.com/css2?family=Roboto:wght@300;700&display=swap',
            [],
            null
        );

        wp_register_style(
            'dd-gap-frontend',
            plugin_dir_url(__FILE__) . 'assets/css/frontend.css',
            ['dd-gap-roboto'],
            '1.0.2'
        );

        wp_register_script(
            'dd-gap-frontend',
            plugin_dir_url(__FILE__) . 'assets/js/frontend.js',
            [],
            '1.0.0',
            true
        );
    }

    public function save_exercise(int $post_id): void {
        if (!isset($_POST['dd_gap_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dd_gap_nonce'])), 'dd_gap_save_exercise')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $text = isset($_POST['dd_gap_text']) ? wp_kses_post(wp_unslash($_POST['dd_gap_text'])) : '';

        $items_raw = isset($_POST['dd_gap_items']) ? (array) wp_unslash($_POST['dd_gap_items']) : [];
        $items = [];

        foreach ($items_raw as $item) {
            $value = trim(wp_strip_all_tags((string) $item));
            if ($value === '') {
                continue;
            }
            if (!in_array($value, $items, true)) {
                $items[] = $value;
            }
        }

        if (count($items) > 7) {
            $items = array_slice($items, 0, 7);
        }

        $filtered_items = array_values(array_filter($items, static function (string $item) use ($text): bool {
            return mb_stripos($text, $item) !== false;
        }));

        update_post_meta($post_id, self::META_TEXT, $text);
        update_post_meta($post_id, self::META_ITEMS, $filtered_items);
    }

    public function render_shortcode(array $atts): string {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts, 'dd_exercise');

        $post_id = absint($atts['id']);
        if ($post_id <= 0) {
            return '';
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'dd_exercise') {
            return '';
        }

        $text = (string) get_post_meta($post_id, self::META_TEXT, true);
        $items = get_post_meta($post_id, self::META_ITEMS, true);

        if (!is_array($items) || empty($items) || $text === '') {
            return '';
        }

        $items = array_values(array_slice(array_unique(array_map('strval', $items)), 0, 7));
        if (empty($items)) {
            return '';
        }

        $exercise = $this->build_exercise_markup($text, $items);
        if (!$exercise) {
            return '';
        }

        wp_enqueue_style('dd-gap-frontend');
        wp_enqueue_script('dd-gap-frontend');

        $container_id = 'dd-gap-' . $post_id . '-' . wp_rand(1000, 9999);

        ob_start();
        ?>
        <div
            class="dd-gap-exercise"
            id="<?php echo esc_attr($container_id); ?>"
            data-answers="<?php echo esc_attr(wp_json_encode($exercise['answers'])); ?>"
        >
            <?php $exercise_title = get_the_title($post_id); if ($exercise_title) : ?>
                <h2 class="dd-gap-title"><?php echo esc_html($exercise_title); ?></h2>
            <?php endif; ?>

            <div class="dd-gap-bank" aria-label="<?php esc_attr_e('Drag these items', 'dd-gap-exercises'); ?>">
                <?php foreach ($exercise['bank'] as $bank_item) : ?>
                    <button
                        class="dd-gap-token"
                        type="button"
                        draggable="true"
                        data-token="<?php echo esc_attr($bank_item); ?>"
                    >
                        <?php echo esc_html($bank_item); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="dd-gap-text" aria-live="polite">
                <?php echo $exercise['text_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>

            <div class="dd-gap-actions">
                <button type="button" class="dd-gap-check button"><?php esc_html_e('CHECK YOUR ANSWERS', 'dd-gap-exercises'); ?></button>
                <div class="dd-gap-score" hidden></div>
                <button type="button" class="dd-gap-reset button button-secondary" hidden><?php esc_html_e('Redo exercise', 'dd-gap-exercises'); ?></button>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function build_exercise_markup(string $text, array $items): array {
        $answers = [];
        $used_items = [];
        $placeholders = [];

        foreach ($items as $index => $item) {
            $placeholder = sprintf('__DD_GAP_%d__', $index);
            $quoted = '/' . preg_quote($item, '/') . '/iu';

            if (!preg_match($quoted, $text)) {
                continue;
            }

            $text = preg_replace($quoted, $placeholder, $text, 1);
            if ($text === null) {
                continue;
            }

            $slot_id = 'slot-' . ($index + 1);
            $answers[$slot_id] = $item;
            $used_items[] = $item;
            $placeholders[$placeholder] = $slot_id;
        }

        if (empty($answers)) {
            return [];
        }

        $escaped_text = esc_html($text);

        foreach ($placeholders as $placeholder => $slot_id) {
            $slot_markup = '<span class="dd-gap-slot" data-slot-id="' . esc_attr($slot_id) . '" tabindex="0"></span>';
            $escaped_text = str_replace(esc_html($placeholder), $slot_markup, $escaped_text);
        }

        $bank = $used_items;
        shuffle($bank);

        return [
            'text_html' => wpautop($escaped_text),
            'answers' => $answers,
            'bank' => $bank,
        ];
    }
}

new DD_Gap_Exercises_Plugin();

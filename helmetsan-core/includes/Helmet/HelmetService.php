<?php

declare(strict_types=1);

namespace Helmetsan\Core\Helmet;

use WP_Post;

/**
 * Service for managing helmet-specific logic, metadata inheritance, and admin interfaces.
 */
final class HelmetService
{
    private const NONCE_ACTION = 'helmetsan_helmet_meta';
    private const NONCE_FIELD  = '_helmetsan_helmet_nonce';

    /**
     * Register admin hooks for meta boxes.
     */
    public function register(): void
    {
        add_action('add_meta_boxes_helmet', [$this, 'registerMetaBoxes']);
        add_action('save_post_helmet', [$this, 'saveMeta'], 10, 2);
    }

    /**
     * Get a meta value with optional inheritance from parent model.
     *
     * @param int|WP_Post $post Post ID or object.
     * @param string $key Meta key.
     * @param bool $inherit Whether to fallback to parent if child value is empty.
     * @return mixed
     */
    public function getInheritedMeta($post, string $key, bool $inherit = true): mixed
    {
        $post = get_post($post);
        if (!$post instanceof WP_Post) {
            return '';
        }

        $value = get_post_meta($post->ID, $key, true);

        // If explicitly requested to inherit and current value is empty, check parent
        if ($inherit && ($value === '' || $value === null || $value === []) && $post->post_parent > 0) {
            return get_post_meta($post->post_parent, $key, true);
        }

        return $value;
    }

    /**
     * Register meta boxes for the helmet CPT.
     */
    public function registerMetaBoxes(): void
    {
        add_meta_box(
            'helmetsan_helmet_specs',
            'Helmet Specifications',
            [$this, 'renderSpecsMetaBox'],
            'helmet',
            'normal',
            'high'
        );

        add_meta_box(
            'helmetsan_helmet_features',
            'Helmet Features & Comfort',
            [$this, 'renderFeaturesMetaBox'],
            'helmet',
            'normal',
            'default'
        );

        add_meta_box(
            'helmetsan_helmet_analysis',
            'Technical Analysis & Rich Content',
            [$this, 'renderAnalysisMetaBox'],
            'helmet',
            'normal',
            'default'
        );
    }

    /**
     * Render the specifications meta box.
     */
    public function renderSpecsMetaBox(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $fields = $this->getSpecFieldDefinitions();
        $this->renderFieldTable($post, $fields);
    }

    /**
     * Render the features meta box.
     */
    public function renderFeaturesMetaBox(WP_Post $post): void
    {
        $fields = $this->getFeatureFieldDefinitions();
        $this->renderFieldTable($post, $fields);
    }

    /**
     * Render the technical analysis meta box.
     */
    public function renderAnalysisMetaBox(WP_Post $post): void
    {
        $fields = $this->getAnalysisFieldDefinitions();
        $this->renderFieldTable($post, $fields);
    }

    /**
     * Shared field table renderer.
     */
    private function renderFieldTable(WP_Post $post, array $fields): void
    {
        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ($fields as $key => $field) {
            $value = get_post_meta($post->ID, $key, true);
            $id = esc_attr('helmetsan_' . $key);
            $name = esc_attr($key);
            
            // Check for inheritance hint
            $inheritedValue = '';
            $isInherited = false;
            if ($post->post_parent > 0 && ($value === '' || $value === null || $value === '[]')) {
                $inheritedValue = get_post_meta($post->post_parent, $key, true);
                if ($inheritedValue !== '' && $inheritedValue !== null && $inheritedValue !== '[]') {
                    $isInherited = true;
                }
            }

            echo '<tr>';
            echo '<th scope="row"><label for="' . $id . '">' . esc_html($field['label']) . '</label></th>';
            echo '<td>';

            $placeholder = $isInherited ? 'Inherited: ' . wp_trim_words((string)$inheritedValue, 5, '...') : '';
            $style = $isInherited ? 'border-left: 3px solid #72aee6; padding-left: 10px;' : '';

            if ($field['type'] === 'textarea') {
                $rows = $field['rows'] ?? 4;
                echo '<textarea id="' . $id . '" name="' . $name . '" rows="' . $rows . '" class="large-text" placeholder="' . esc_attr($placeholder) . '" style="' . $style . '">' . esc_textarea((string)$value) . '</textarea>';
            } elseif ($field['type'] === 'select') {
                echo '<select id="' . $id . '" name="' . $name . '" style="' . $style . '">';
                foreach ($field['options'] as $v => $l) {
                    echo '<option value="' . esc_attr((string)$v) . '" ' . selected($value, $v, false) . '>' . esc_html($l) . '</option>';
                }
                echo '</select>';
            } elseif ($field['type'] === 'checkbox_list') {
                $current = json_decode((string)$value, true);
                if (!is_array($current)) $current = [];
                
                echo '<div style="' . $style . '">';
                foreach ($field['options'] as $opt) {
                    $checked = in_array($opt, $current) ? 'checked' : '';
                    // Use array notation for name to capture multiple values
                    echo '<label style="display:inline-block; margin-right: 15px; margin-bottom: 5px;">';
                    echo '<input type="checkbox" name="' . $name . '[]" value="' . esc_attr($opt) . '" ' . $checked . '> ' . esc_html($opt);
                    echo '</label><br>';
                }
                echo '</div>';
            } else {
                echo '<input type="' . esc_attr($field['type']) . '" id="' . $id . '" name="' . $name . '" value="' . esc_attr((string)$value) . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '" style="' . $style . '" />';
            }

            if ($isInherited) {
                // Determine inherited display value
                $displayInherited = (string)$inheritedValue;
                if ($field['type'] === 'checkbox_list' && is_string($inheritedValue)) {
                    $arr = json_decode($inheritedValue, true);
                    if (is_array($arr)) $displayInherited = implode(', ', $arr);
                }
                
                echo '<p class="description"><span class="dashicons dashicons-arrow-down-alt2" style="color: #72aee6; vertical-align: text-top;"></span> <span style="color: #50575e; font-style: italic;">Inheriting from parent model: <strong>' . esc_html(wp_trim_words($displayInherited, 10)) . '</strong> (Leave empty to keep inheriting)</span></p>';
            }

            if (isset($field['hint'])) {
                echo '<p class="description">' . esc_html($field['hint']) . '</p>';
            }

            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    /**
     * Save meta data.
     */
    public function saveMeta(int $postId, WP_Post $post): void
    {
        if (!isset($_POST[self::NONCE_FIELD]) || !wp_verify_nonce($_POST[self::NONCE_FIELD], self::NONCE_ACTION)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $allFields = array_merge(
            $this->getSpecFieldDefinitions(),
            $this->getFeatureFieldDefinitions(),
            $this->getAnalysisFieldDefinitions()
        );

        foreach ($allFields as $key => $field) {
            // Checkbox lists might not be sent if nothing checked, so default to empty
            if (!isset($_POST[$key]) && $field['type'] !== 'checkbox_list') {
                continue;
            }

            $raw = $_POST[$key] ?? '';
            
            if ($field['type'] === 'checkbox_list') {
                // If checkbox array is provided, sanitize items and json_encode. 
                // If empty or not set (unchecked all), save as empty json array "[]"
                if (is_array($raw)) {
                    $clean = array_map('sanitize_text_field', $raw);
                    // Re-index to ensure JSON array, not object
                    $value = json_encode(array_values($clean));
                } else {
                    $value = '[]';
                }
            } elseif ($field['type'] === 'textarea' || (isset($field['sanitize']) && $field['sanitize'] === 'textarea')) {
                $value = sanitize_textarea_field(wp_unslash($raw));
            } elseif ($field['type'] === 'number') {
                $value = (string) (float) wp_unslash($raw);
            } else {
                $value = sanitize_text_field(wp_unslash($raw));
            }

            update_post_meta($postId, $key, $value);
        }
    }

    private function getSpecFieldDefinitions(): array
    {
        return [
            'spec_weight_g' => [
                'label' => 'Weight (grams)',
                'type'  => 'number',
                'hint'  => 'Child variants often override this for graphics/materials.'
            ],
            'spec_shell_material' => [
                'label' => 'Shell Material',
                'type'  => 'text',
                'hint'  => 'e.g. Carbon Fiber, Polycarbonate, AIM+'
            ],
            'head_shape' => [
                'label' => 'Head Shape',
                'type'  => 'select',
                'options' => [
                    '' => '— Select —',
                    'long-oval' => 'Long Oval',
                    'intermediate-oval' => 'Intermediate Oval',
                    'round-oval' => 'Round Oval'
                ]
            ],
            'helmet_family' => [
                'label' => 'Product Family',
                'type'  => 'text',
                'hint'  => 'Grouping key for series (e.g. RF-Series, Star-Series)'
            ]
        ];
    }

    private function getFeatureFieldDefinitions(): array
    {
        return [
            'warranty_years' => [
                'label' => 'Warranty (Years)',
                'type'  => 'number',
                'hint'  => 'Duration of manufacturer warranty'
            ],
            'strap_type' => [
                'label' => 'Strap Type',
                'type'  => 'select',
                'options' => [
                    '' => '— Select —',
                    'Double D-Ring' => 'Double D-Ring',
                    'Micrometric' => 'Micrometric', 
                    'Fidlock' => 'Fidlock',
                    'Quick Release' => 'Quick Release'
                ]
            ],
            'visor_features_json' => [
                'label' => 'Visor Features',
                'type'  => 'checkbox_list', 
                'options' => [
                    'Pinlock Ready', 'Pinlock Included', 'UV Protection', 'Anti-Scratch', 
                    'Drop-down Sun Visor', 'Photochromic', 'Quick Release System', 'Tear-off Ready'
                ],
                'hint'  => 'Select all that apply.'
            ],
            'liner_features_json' => [
                'label' => 'Liner Features',
                'type'  => 'checkbox_list',
                'options' => [
                    'Removable', 'Washable', 'Antibacterial', 'Moisture Wicking', 
                    'Emergency Release System (EQRS)', 'Glasses Groove', 'Speaker Pockets'
                ],
                'hint'  => 'Select all that apply.'
            ]
        ];
    }

    private function getAnalysisFieldDefinitions(): array
    {
        return [
            'technical_analysis' => [
                'label' => 'Technical Analysis',
                'type'  => 'textarea',
                'rows'  => 8,
                'hint'  => 'Deep dive into safety tech and performance.'
            ],
            'key_specs_json' => [
                'label' => 'Key Specifications (JSON)',
                'type'  => 'textarea',
                'rows'  => 4,
                'hint'  => 'Format: {"Ventilation": "5 ports", "Closure": "Double D-Ring"}'
            ],
            'compatible_accessories_json' => [
                'label' => 'Compatible Accessories (JSON/IDs)',
                'type'  => 'textarea',
                'rows'  => 3,
                'hint'  => 'References to accessory post IDs.'
            ]
        ];
    }
}

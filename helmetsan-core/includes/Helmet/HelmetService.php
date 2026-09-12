<?php

declare(strict_types=1);

namespace Helmetsan\Core\Helmet;

use Helmetsan\Core\Support\Config;
use WP_Post;

/**
 * Service for managing helmet-specific logic, metadata inheritance, and admin interfaces.
 */
final class HelmetService
{
    private const NONCE_ACTION = 'helmetsan_helmet_meta';
    private const NONCE_FIELD  = '_helmetsan_helmet_nonce';

    public function __construct(
        private readonly Config $config
    ) {}

    /**
     * Register admin hooks for meta boxes.
     */
    public function register(): void
    {
        add_action('add_meta_boxes_helmet', [$this, 'registerMetaBoxes']);
        add_action('save_post_helmet', [$this, 'saveMeta'], 10, 2);
        add_action('save_post_helmet', [$this, 'clearHelmetCache'], 20, 1);
        
        // Auto-sync custom meta and taxonomies across Polylang translations
        add_action('save_post_helmet', [$this, 'syncTranslationsOnSave'], 30, 2);
        add_action('save_post_accessory', [$this, 'syncTranslationsOnSave'], 30, 2);
        add_action('save_post_motorcycle', [$this, 'syncTranslationsOnSave'], 30, 2);
        add_action('save_post_brand', [$this, 'syncTranslationsOnSave'], 30, 2);
        add_action('pll_save_post_translations', [$this, 'syncTranslationsOnLink'], 30, 1);
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

        $perf = $this->config->performanceConfig();
        $cacheEnabled = !empty($perf['enable_metadata_caching']);
        $cacheKey = "hs_meta_{$post->ID}_{$key}_" . ($inherit ? '1' : '0');

        if ($cacheEnabled) {
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $value = get_post_meta($post->ID, $key, true);
        $isEmpty = ($value === '' || $value === null || $value === [] || $value === '[]');

        // 1. Child -> Parent inheritance (Variant inherits from Model)
        if ($inherit && $isEmpty && (int) $post->post_parent > 0) {
            $value = get_post_meta((int) $post->post_parent, $key, true);
            $isEmpty = ($value === '' || $value === null || $value === [] || $value === '[]');
        }

        // 2. Parent -> Variant roll-up (Model inherits from one of its children variants if empty)
        if ($inherit && $isEmpty && (int) $post->post_parent === 0) {
            $childCacheKey = "hs_helmet_children_{$post->ID}";
            $children      = wp_cache_get($childCacheKey);
            if ($children === false) {
                $children = get_posts([
                    'post_type'        => 'helmet',
                    'post_parent'      => $post->ID,
                    'numberposts'      => 10,
                    'fields'           => 'ids',
                    'post_status'      => 'any',
                    'suppress_filters' => true,
                ]);
                wp_cache_set($childCacheKey, $children, '', 3600);
            }
            if (is_array($children) && $children !== []) {
                foreach ($children as $childId) {
                    $childValue = get_post_meta($childId, $key, true);
                    $childIsEmpty = ($childValue === '' || $childValue === null || $childValue === [] || $childValue === '[]');
                    if (!$childIsEmpty) {
                        $value = $childValue;
                        $isEmpty = false;
                        break;
                    }
                }
            }
        }

        // 3. Translation Fallback (Polylang master language post fallback)
        if ($isEmpty) {
            if (function_exists('pll_default_language') && function_exists('pll_get_post')) {
                $defaultLang = pll_default_language();
                $masterPostId = pll_get_post($post->ID, $defaultLang);
                if ($masterPostId && $masterPostId > 0 && $masterPostId !== $post->ID) {
                    $value = get_post_meta($masterPostId, $key, true);
                    $isEmpty = ($value === '' || $value === null || $value === [] || $value === '[]');
                    
                    // Child -> Parent fallback on master translation post
                    if ($inherit && $isEmpty) {
                        $masterPost = get_post($masterPostId);
                        if ($masterPost instanceof WP_Post && (int) $masterPost->post_parent > 0) {
                            $value = get_post_meta((int) $masterPost->post_parent, $key, true);
                            $isEmpty = ($value === '' || $value === null || $value === [] || $value === '[]');
                        }
                    }

                    // Parent -> Variant roll-up on master translation post
                    if ($inherit && $isEmpty) {
                        $masterPost = get_post($masterPostId);
                        if ($masterPost instanceof WP_Post && (int) $masterPost->post_parent === 0) {
                            $masterChildCacheKey = "hs_helmet_children_{$masterPostId}";
                            $masterChildren      = wp_cache_get($masterChildCacheKey);
                            if ($masterChildren === false) {
                                $masterChildren = get_posts([
                                    'post_type'        => 'helmet',
                                    'post_parent'      => $masterPostId,
                                    'numberposts'      => 10,
                                    'fields'           => 'ids',
                                    'post_status'      => 'any',
                                    'suppress_filters' => true,
                                ]);
                                wp_cache_set($masterChildCacheKey, $masterChildren, '', 3600);
                            }
                            if (is_array($masterChildren) && $masterChildren !== []) {
                                foreach ($masterChildren as $childId) {
                                    $childValue = get_post_meta($childId, $key, true);
                                    $childIsEmpty = ($childValue === '' || $childValue === null || $childValue === [] || $childValue === '[]');
                                    if (!$childIsEmpty) {
                                        $value = $childValue;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($cacheEnabled) {
            $ttl = (int) ($perf['cache_expiration_hours'] ?? 24) * HOUR_IN_SECONDS;
            set_transient($cacheKey, $value, $ttl);
        }

        return $value;
    }

    /**
     * Clear all metadata transients for a specific helmet.
     * Note: This only clears the most common keys. For a full flush, use the admin action.
     */
    public function clearHelmetCache(int $postId): void
    {
        $keys = [
            'marketing_description', 'technical_analysis', 'key_specs_json', 
            'spec_weight_g', 'spec_shell_material', 'head_shape', 'helmet_family',
            'homologation_standard', 'sharp_rating', 'rotational_tech', 'warranty_years',
            'strap_type', 'visor_features_json', 'liner_features_json', 'noise_db_at_100kph',
            'ventilation_score', 'comms_ready', 'compatible_accessories_json'
        ];
        foreach ($keys as $key) {
            delete_transient("hs_meta_{$postId}_{$key}_0");
            delete_transient("hs_meta_{$postId}_{$key}_1");
        }

        // If this is a parent post, clear transients for all children inheriting from it
        $children = get_posts([
            'post_type'   => 'helmet',
            'post_parent' => $postId,
            'fields'      => 'ids',
            'numberposts' => -1,
            'post_status' => 'any',
        ]);
        if (is_array($children) && $children !== []) {
            foreach ($children as $childId) {
                foreach ($keys as $key) {
                    delete_transient("hs_meta_{$childId}_{$key}_1");
                }
            }
        }
    }

    /**
     * Get terms for a post, with inheritance from parent if none are set on child.
     *
     * @param int|WP_Post $post Post ID or object.
     * @param string $taxonomy Taxonomy name.
     * @param bool $inherit Whether to fallback to parent.
     * @return array|\WP_Error|false Array of terms on success.
     */
    public function getInheritedTerms($post, string $taxonomy, bool $inherit = true): mixed
    {
        $post = get_post($post);
        if (!$post instanceof WP_Post) {
            return [];
        }

        $terms = get_the_terms($post->ID, $taxonomy);
        $isEmpty = (is_wp_error($terms) || empty($terms));

        if ($inherit && $isEmpty && (int) $post->post_parent > 0) {
            $terms = get_the_terms((int) $post->post_parent, $taxonomy);
            $isEmpty = (is_wp_error($terms) || empty($terms));
        }

        // Translation Fallback: check Polylang master translation post (default language)
        if ($isEmpty && function_exists('pll_default_language') && function_exists('pll_get_post')) {
            $defaultLang = pll_default_language();
            $masterPostId = pll_get_post($post->ID, $defaultLang);
            if ($masterPostId && $masterPostId > 0 && $masterPostId !== $post->ID) {
                $terms = get_the_terms($masterPostId, $taxonomy);
                $isEmpty = (is_wp_error($terms) || empty($terms));
                if ($inherit && $isEmpty) {
                    $masterPost = get_post($masterPostId);
                    if ($masterPost instanceof WP_Post && (int) $masterPost->post_parent > 0) {
                        $terms = get_the_terms((int) $masterPost->post_parent, $taxonomy);
                    }
                }
            }
        }

        return $terms;
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
            if ((int) $post->post_parent > 0 && ($value === '' || $value === null || $value === '[]')) {
                $inheritedValue = get_post_meta((int) $post->post_parent, $key, true);
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
            ],
            'homologation_standard' => [
                'label' => 'Homologation Standard',
                'type'  => 'text',
                'hint'  => 'e.g. ECE 22.06, SNELL M2020D, FIM FRHPhe-01'
            ],
            'sharp_rating' => [
                'label' => 'SHARP Rating (1-5)',
                'type'  => 'number',
                'hint'  => 'Safety stars from UK SHARP testing.'
            ],
            'rotational_tech' => [
                'label' => 'Rotational Protection',
                'type'  => 'text',
                'hint'  => 'e.g. MIPS, AIM+, MEDS, FLEX'
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
            ],
            'noise_db_at_100kph' => [
                'label' => 'Noise @ 100kph (dB)',
                'type'  => 'text',
                'hint'  => 'Wind noise level measured in decibels.'
            ],
            'ventilation_score' => [
                'label' => 'Ventilation Score (1-10)',
                'type'  => 'number',
                'hint'  => 'Efficiency of air flow and cooling.'
            ],
            'comms_ready' => [
                'label' => 'Comms Readiness',
                'type'  => 'text',
                'hint'  => 'e.g. Speaker Pockets, Integrated (Sena/Cardo), Pre-wired'
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

    /**
     * Hook to sync translations when a post is saved.
     */
    public function syncTranslationsOnSave(int $postId, WP_Post $post): void
    {
        $this->syncPostTranslations($postId);
    }

    /**
     * Hook to sync translations when translations are linked.
     */
    public function syncTranslationsOnLink(array $translations): void
    {
        $enPostId = $translations['en'] ?? 0;
        if ($enPostId > 0) {
            $this->syncPostTranslations($enPostId);
        }
    }

    /**
     * Synchronize technical specifications, custom meta, and taxonomy terms
     * from the English master post to all of its Polylang translation posts.
     */
    public function syncPostTranslations(int $postId): void
    {
        static $syncing = false;
        if ($syncing) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!function_exists('pll_get_post_translations') || !function_exists('pll_get_term')) {
            return;
        }

        $post = get_post($postId);
        if (!$post instanceof WP_Post) {
            return;
        }

        $supportedPostTypes = ['helmet', 'accessory', 'motorcycle', 'brand'];
        if (!in_array($post->post_type, $supportedPostTypes, true)) {
            return;
        }

        $translations = pll_get_post_translations($postId);
        if (empty($translations)) {
            return;
        }

        $enPostId = $translations['en'] ?? 0;
        if ($enPostId <= 0) {
            return;
        }

        $syncing = true;

        $syncKeys = [
            // Helmet specs
            'spec_weight_g', 'spec_weight_lbs', 'spec_shell_material', 'head_shape', 
            'strap_type', 'noise_db_at_100kph', 'ventilation_score', 'comms_ready',
            'emergency_release_system', 'multi_density_eps', 'glasses_grooves',
            'removable_interior', 'integrated_sun_visor', 'pinlock_included',
            'pinlock_type', 'breath_deflector', 'wind_tunnel_tested',
            'visor_features_json', 'liner_features_json',
            'sku', '_helmet_unique_id', 'rel_brand', 'helmet_family',
            
            // Accessory specs
            'warranty_years', 'material', 'weight_g', 'waterproof_rating',
            'battery_life_hours', 'connectivity_type',
            
            // Motorcycle specs
            'seat_height_mm', 'fuel_capacity_liters', 'weight_wet_kg',
            'transmission_type', 'horsepower', 'torque_nm', 'top_speed_kph',
            
            // Brand specs
            'founded_year', 'headquarters_city', 'warranty_policy_url'
        ];

        // Get taxonomies for this post type
        $taxonomies = get_object_taxonomies($post->post_type);
        $excludeTaxonomies = ['post_translations', 'language'];

        foreach ($translations as $lang => $translatedPostId) {
            if ($translatedPostId === $enPostId) {
                continue;
            }

            // 1. Sync custom metadata from English master post
            foreach ($syncKeys as $key) {
                $enMetaValues = get_post_meta($enPostId, $key);
                // Delete existing meta values on translation to ensure exact sync
                delete_post_meta($translatedPostId, $key);
                foreach ($enMetaValues as $val) {
                    update_post_meta($translatedPostId, $key, maybe_unserialize($val));
                }
            }

            // Sync other non-technical/non-spec metadata only if empty on translation
            $enAllMeta = get_post_meta($enPostId);
            foreach ($enAllMeta as $key => $values) {
                if (in_array($key, $syncKeys, true) || str_starts_with($key, '_edit_') || $key === '_thumbnail_id') {
                    continue;
                }
                // Only copy if not set on the translation (avoids overwriting AI-translated fields)
                $existingVal = get_post_meta($translatedPostId, $key, true);
                if ($existingVal === '' || $existingVal === null || $existingVal === [] || $existingVal === '[]') {
                    delete_post_meta($translatedPostId, $key);
                    foreach ($values as $val) {
                        update_post_meta($translatedPostId, $key, maybe_unserialize($val));
                    }
                }
            }

            // 2. Sync taxonomy terms
            foreach ($taxonomies as $taxonomy) {
                if (in_array($taxonomy, $excludeTaxonomies, true)) {
                    continue;
                }

                $enTerms = wp_get_object_terms($enPostId, $taxonomy, ['fields' => 'ids']);
                if (is_wp_error($enTerms) || empty($enTerms)) {
                    wp_set_object_terms($translatedPostId, [], $taxonomy, false);
                    continue;
                }

                $translatedTermIds = [];
                foreach ($enTerms as $termId) {
                    $translatedTermId = pll_get_term($termId, $lang);
                    if ($translatedTermId) {
                        $translatedTermIds[] = (int) $translatedTermId;
                    }
                }

                wp_set_object_terms($translatedPostId, $translatedTermIds, $taxonomy, false);
            }
        }

        $syncing = false;
    }
}

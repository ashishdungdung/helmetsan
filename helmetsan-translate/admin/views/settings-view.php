<?php
declare(strict_types=1);

namespace HelmetsanTranslate;

defined('ABSPATH') || exit;

$options = Settings::get_options();
$providers = $options['providers'] ?? [];
$redis = $options['redis'] ?? [];
$cloudflare = $options['cloudflare'] ?? [];
$policies = $options['policies'] ?? [];
$seo = $options['seo'] ?? [];
?>

<div class="wrap hs-translate-wrap">
    <h1><span class="dashicons dashicons-translation"></span> <?php esc_html_e('Helmetsan Translate - Enterprise Multi-AI Configuration', 'helmetsan-translate'); ?></h1>
    
    <?php settings_errors('hs_translate_messages'); ?>

    <nav class="nav-tab-wrapper hs-tabs">
        <a href="#tab-providers" class="nav-tab nav-tab-active"><span class="dashicons dashicons-superhero"></span> <?php esc_html_e('Multi-AI Fleet', 'helmetsan-translate'); ?></a>
        <a href="#tab-redis-edge" class="nav-tab"><span class="dashicons dashicons-database"></span> <?php esc_html_e('Redis & Cloudflare Edge', 'helmetsan-translate'); ?></a>
        <a href="#tab-policies" class="nav-tab"><span class="dashicons dashicons-shield"></span> <?php esc_html_e('Policies & Guardrails', 'helmetsan-translate'); ?></a>
        <a href="#tab-seo" class="nav-tab"><span class="dashicons dashicons-search"></span> <?php esc_html_e('SEO, Sitemaps & Slugs', 'helmetsan-translate'); ?></a>
    </nav>

    <form method="post" action="" class="hs-settings-form">
        <?php wp_nonce_field('hs_save_settings_action', 'hs_save_settings_nonce'); ?>

        <!-- TAB 1: AI FLEET -->
        <div id="tab-providers" class="hs-tab-content hs-tab-active">
            <p class="description">
                <?php esc_html_e('Configure your 10-tier translation fleet. Fast diffusion & free models are prioritized, with deep reasoning arbiters on failover.', 'helmetsan-translate'); ?>
            </p>

            <div class="hs-provider-cards-grid">
                <?php foreach ($providers as $id => $p): 
                    $masked_key = Encryption::mask(Encryption::decrypt($p['api_key'] ?? ''));
                ?>
                    <div class="hs-provider-card <?php echo !empty($p['enabled']) ? 'is-enabled' : ''; ?>" data-provider="<?php echo esc_attr($id); ?>">
                        <div class="hs-card-header">
                            <label class="hs-toggle-switch">
                                <input type="checkbox" name="hs_options[providers][<?php echo esc_attr($id); ?>][enabled]" value="1" <?php checked(!empty($p['enabled'])); ?>>
                                <span class="hs-slider"></span>
                            </label>
                            <h3><?php echo esc_html($p['name']); ?></h3>
                            <span class="hs-badge hs-badge-priority"><?php printf(esc_html__('Priority: %d', 'helmetsan-translate'), (int)($p['priority'] ?? 3)); ?></span>
                        </div>

                        <div class="hs-card-body">
                            <div class="hs-field-row">
                                <label><?php esc_html_e('API Key', 'helmetsan-translate'); ?></label>
                                <div class="hs-input-group">
                                    <input type="password" name="hs_options[providers][<?php echo esc_attr($id); ?>][api_key]" value="<?php echo esc_attr($masked_key); ?>" class="regular-text hs-api-key-input">
                                    <button type="button" class="button hs-toggle-mask"><span class="dashicons dashicons-visibility"></span></button>
                                </div>
                            </div>

                            <div class="hs-field-row">
                                <label><?php esc_html_e('Base URL', 'helmetsan-translate'); ?></label>
                                <input type="text" name="hs_options[providers][<?php echo esc_attr($id); ?>][base_url]" value="<?php echo esc_attr($p['base_url'] ?? ''); ?>" class="large-text">
                            </div>

                            <div class="hs-field-row">
                                <label><?php esc_html_e('Model ID', 'helmetsan-translate'); ?></label>
                                <div class="hs-input-group">
                                    <select name="hs_options[providers][<?php echo esc_attr($id); ?>][model]" class="hs-model-select">
                                        <option value="<?php echo esc_attr($p['model'] ?? ''); ?>"><?php echo esc_html($p['model'] ?? 'Select Model'); ?></option>
                                    </select>
                                    <button type="button" class="button hs-fetch-models-btn" data-provider="<?php echo esc_attr($id); ?>">
                                        <span class="dashicons dashicons-update"></span> <?php esc_html_e('Fetch Models', 'helmetsan-translate'); ?>
                                    </button>
                                </div>
                            </div>

                            <div class="hs-field-row">
                                <label><?php esc_html_e('Priority Order', 'helmetsan-translate'); ?></label>
                                <select name="hs_options[providers][<?php echo esc_attr($id); ?>][priority]">
                                    <option value="1" <?php selected($p['priority'] ?? 3, 1); ?>>1 (Primary High-Throughput)</option>
                                    <option value="2" <?php selected($p['priority'] ?? 3, 2); ?>>2 (Secondary Volume / Batches)</option>
                                    <option value="3" <?php selected($p['priority'] ?? 3, 3); ?>>3 (Fast JSON Failover)</option>
                                    <option value="4" <?php selected($p['priority'] ?? 3, 4); ?>>4 (Linguistic Specialist)</option>
                                    <option value="5" <?php selected($p['priority'] ?? 3, 5); ?>>5 (Frontier Editorial Arbiter)</option>
                                    <option value="6" <?php selected($p['priority'] ?? 3, 6); ?>>6 (Edge Redundancy)</option>
                                </select>
                            </div>

                            <div class="hs-limits-box">
                                <strong><span class="dashicons dashicons-info"></span> <?php esc_html_e('Live Limits & Best For:', 'helmetsan-translate'); ?></strong>
                                <p><?php echo esc_html($p['limits'] ?? ''); ?></p>
                                <small><em><?php echo esc_html($p['best_for'] ?? ''); ?></em></small>
                            </div>
                        </div>

                        <div class="hs-card-footer">
                            <button type="button" class="button hs-test-conn-btn" data-provider="<?php echo esc_attr($id); ?>">
                                <span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e('Test Ping', 'helmetsan-translate'); ?>
                            </button>
                            <span class="hs-conn-status hs-status-idle"><?php esc_html_e('Ready', 'helmetsan-translate'); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TAB 2: REDIS & EDGE -->
        <div id="tab-redis-edge" class="hs-tab-content">
            <h2><?php esc_html_e('High-Speed In-Memory & Edge Infrastructure', 'helmetsan-translate'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Redis L1 Translation Memory', 'helmetsan-translate'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="hs_options[redis][enabled]" value="1" <?php checked(!empty($redis['enabled'])); ?>>
                            <?php esc_html_e('Enable Redis L1 Phrase Deduplication (0.05ms instant cache hits for recurring motorcycle specs)', 'helmetsan-translate'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Prevents calling AI for repetitive specs like "Double-D ring titanium retention system". Saves 60% of API tokens.', 'helmetsan-translate'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Redis Host & Port', 'helmetsan-translate'); ?></th>
                    <td>
                        <input type="text" name="hs_options[redis][host]" value="<?php echo esc_attr($redis['host'] ?? '127.0.0.1'); ?>" class="regular-text">
                        : <input type="number" name="hs_options[redis][port]" value="<?php echo esc_attr($redis['port'] ?? 6379); ?>" class="small-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Cloudflare Edge Cache Purge', 'helmetsan-translate'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="hs_options[cloudflare][purge_enabled]" value="1" <?php checked(!empty($cloudflare['purge_enabled'])); ?>>
                            <?php esc_html_e('Automatically purge Cloudflare CDN cache for localized URLs upon translation update', 'helmetsan-translate'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Cloudflare Zone ID', 'helmetsan-translate'); ?></th>
                    <td>
                        <input type="text" name="hs_options[cloudflare][zone_id]" value="<?php echo esc_attr($cloudflare['zone_id'] ?? ''); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Cloudflare API Token', 'helmetsan-translate'); ?></th>
                    <td>
                        <input type="password" name="hs_options[cloudflare][api_token]" value="<?php echo esc_attr(Encryption::mask(Encryption::decrypt($cloudflare['api_token'] ?? ''))); ?>" class="large-text">
                    </td>
                </tr>
            </table>
        </div>

        <!-- TAB 3: POLICIES & GUARDRAILS -->
        <div id="tab-policies" class="hs-tab-content">
            <h2><?php esc_html_e('Operational Safety & Concurrency Policies', 'helmetsan-translate'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Auto-Translate on Save', 'helmetsan-translate'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="hs_options[policies][auto_translate_on_save]" value="1" <?php checked(!empty($policies['auto_translate_on_save'])); ?>>
                            <?php esc_html_e('Automatically dispatch background translation whenever an English master post is saved in WP-Admin', 'helmetsan-translate'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Autosave Debounce Window', 'helmetsan-translate'); ?></th>
                    <td>
                        <input type="number" name="hs_options[policies][debounce_seconds]" value="<?php echo esc_attr($policies['debounce_seconds'] ?? 30); ?>" class="small-text"> <?php esc_html_e('seconds', 'helmetsan-translate'); ?>
                        <p class="description"><?php esc_html_e('Collapses rapid Gutenberg autosaves and successive edits into a single execution.', 'helmetsan-translate'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Maximum Worker Concurrency', 'helmetsan-translate'); ?></th>
                    <td>
                        <input type="number" name="hs_options[policies][concurrency]" value="<?php echo esc_attr($policies['concurrency'] ?? 5); ?>" class="small-text"> <?php esc_html_e('simultaneous threads', 'helmetsan-translate'); ?>
                        <p class="description"><?php esc_html_e('Controls parallel background processing to prevent hitting server CPU bottlenecks or provider rate limits.', 'helmetsan-translate'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Enabled Post Types', 'helmetsan-translate'); ?></th>
                    <td>
                        <?php 
                        $all_types = ['helmet' => 'Helmets', 'motorcycle' => 'Motorcycles', 'post' => 'Blog Posts', 'brand' => 'Brands', 'safety_standard' => 'Safety Standards', 'accessory' => 'Accessories', 'page' => 'Standard Pages'];
                        $active_types = $policies['post_types'] ?? ['helmet', 'motorcycle', 'post', 'brand', 'safety_standard', 'accessory'];
                        foreach ($all_types as $slug => $label): ?>
                            <label style="display:inline-block; margin-right: 15px;">
                                <input type="checkbox" name="hs_options[policies][post_types][]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $active_types, true)); ?>>
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- TAB 4: SEO, SITEMAPS & SLUGS -->
        <div id="tab-seo" class="hs-tab-content">
            <h2><?php esc_html_e('Search Engine Optimization, Sitemaps & Slugs', 'helmetsan-translate'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Yoast SEO Metadata Sync', 'helmetsan-translate'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="hs_options[seo][sync_yoast]" value="1" <?php checked(!empty($seo['sync_yoast'])); ?>>
                            <?php esc_html_e('Translate and sync Yoast SEO Meta Title & Meta Description atomically', 'helmetsan-translate'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Image ALT Text Localization', 'helmetsan-translate'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="hs_options[seo][translate_image_alts]" value="1" <?php checked(!empty($seo['translate_image_alts'])); ?>>
                            <?php esc_html_e('Automatically translate Image ALT tags inside post body to capture Google Image Search traffic', 'helmetsan-translate'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('IndexNow Instant Search Indexing', 'helmetsan-translate'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="hs_options[seo][indexnow_enabled]" value="1" <?php checked(!empty($seo['indexnow_enabled'])); ?>>
                            <?php esc_html_e('Ping IndexNow API (Bing, Yandex, Naver) instantly upon new translation generation', 'helmetsan-translate'); ?>
                        </label>
                        <br>
                        <input type="text" name="hs_options[seo][indexnow_key]" value="<?php echo esc_attr($seo['indexnow_key'] ?? ''); ?>" placeholder="IndexNow API Key" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Custom Meta Whitelist (Translate)', 'helmetsan-translate'); ?></th>
                    <td>
                        <input type="text" name="hs_options[seo][meta_whitelist]" value="<?php echo esc_attr($seo['meta_whitelist'] ?? ''); ?>" class="large-text">
                        <p class="description"><?php esc_html_e('Comma-separated postmeta keys containing textual editorial content that should be translated.', 'helmetsan-translate'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Numeric/Neutral Meta (Copy 1:1)', 'helmetsan-translate'); ?></th>
                    <td>
                        <input type="text" name="hs_options[seo][meta_copy_as_is]" value="<?php echo esc_attr($seo['meta_copy_as_is'] ?? ''); ?>" class="large-text">
                        <p class="description"><?php esc_html_e('Comma-separated postmeta keys copied directly without touching AI (e.g. prices, weights, thumbnail IDs).', 'helmetsan-translate'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary button-hero"><span class="dashicons dashicons-saved"></span> <?php esc_html_e('Save Configuration & Hot-Reload Daemon', 'helmetsan-translate'); ?></button>
        </p>
    </form>
</div>

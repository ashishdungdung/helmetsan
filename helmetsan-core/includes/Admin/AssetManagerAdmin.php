<?php

declare(strict_types=1);

namespace Helmetsan\Core\Admin;

use Helmetsan\Core\Ingestion\AssetIngestionService;
use WP_Post;

/**
 * Handles the wp-admin integration for the Asset Manager.
 */
class AssetManagerAdmin
{
    public function __construct(
        private readonly AssetIngestionService $ingestionService
    ) {}

    public function register(): void
    {
        add_action('add_meta_boxes_helmet', [$this, 'addMetaBox']);
        add_action('wp_ajax_helmetsan_ingest_assets', [$this, 'handleAjaxIngestion']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }

    public function enqueueScripts(string $hook): void
    {
        global $post;
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            if ($post && $post->post_type === 'helmet') {
                // In a real scenario, this would be a proper registered asset.
                // Outputting inline script for brevity in this initial implementation.
                add_action('admin_footer', [$this, 'renderInlineScript']);
            }
        }
    }

    public function addMetaBox(WP_Post $post): void
    {
        add_meta_box(
            'helmetsan_asset_manager',
            'Asset Manager (AI Ingestion)',
            [$this, 'renderMetaBox'],
            'helmet',
            'normal',
            'high'
        );
    }

    public function renderMetaBox(WP_Post $post): void
    {
        // 1. Display existing assets linked to this helmet
        $assets = get_posts([
            'post_type' => 'asset',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'rel_helmets',
                    'value' => serialize($post->ID),
                    'compare' => 'LIKE',
                ]
            ]
        ]);

        echo '<h4>Current Linked Assets</h4>';
        if (empty($assets)) {
            echo '<p>No assets found.</p>';
        } else {
            echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
            foreach ($assets as $asset) {
                $thumb = get_the_post_thumbnail_url($asset->ID, 'thumbnail');
                $type = get_post_meta($asset->ID, '_asset_type', true);
                echo '<div style="border: 1px solid #ccc; padding: 5px; text-align: center; width: 150px;">';
                if ($thumb) {
                    echo '<img src="' . esc_url($thumb) . '" style="max-width: 100%; height: auto;" />';
                } else {
                    echo '<div style="background: #eee; height: 100px; display:flex; align-items:center; justify-content:center;">No Image</div>';
                }
                echo '<strong style="display:block; margin-top:5px; font-size:11px;">' . esc_html(strtoupper($type)) . '</strong>';
                echo '<a href="' . get_edit_post_link($asset->ID) . '">Edit Asset</a>';
                echo '</div>';
            }
            echo '</div>';
        }

        // 2. Input for scraping external URLs
        echo '<hr />';
        echo '<h4>Import Assets via URL</h4>';
        echo '<p>Enter a product page URL (e.g. Myntra) to scrape, analyze, and ingest images automatically.</p>';
        echo '<input type="url" id="helmetsan_ingest_url" class="large-text" placeholder="https://www.myntra.com/..." />';
        echo '<button type="button" class="button button-primary" id="helmetsan_trigger_ingest" style="margin-top:10px;">Import & Analyze</button>';
        echo '<div id="helmetsan_ingest_status" style="margin-top: 10px; font-weight: bold;"></div>';

        wp_nonce_field('helmetsan_ingest_assets_nonce', '_helmetsan_ingest_nonce');
    }

    public function renderInlineScript(): void
    {
        global $post;
?>
        <script>
            jQuery(document).ready(function($) {
                $('#helmetsan_trigger_ingest').on('click', function(e) {
                    e.preventDefault();
                    var url = $('#helmetsan_ingest_url').val();
                    var nonce = $('#_helmetsan_ingest_nonce').val();

                    if (!url) {
                        alert('Please enter a URL');
                        return;
                    }

                    var $btn = $(this);
                    $btn.prop('disabled', true).text('Ingesting & Analyzing...');
                    $('#helmetsan_ingest_status').html('<span style="color:blue;">Processing... This may take a minute depending on the number of images and AI rate limits.</span>');

                    $.post(ajaxurl, {
                        action: 'helmetsan_ingest_assets',
                        url: url,
                        helmet_id: <?php echo $post->ID; ?>,
                        nonce: nonce
                    }, function(response) {
                        if (response.success) {
                            $('#helmetsan_ingest_status').html('<span style="color:green;">Success! ' + response.data.success + ' assets imported. Page will reload in 2 seconds.</span>');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            var errorMsg = response.data && response.data.errors ? response.data.errors.join('<br>') : 'Unknown error';
                            $('#helmetsan_ingest_status').html('<span style="color:red;">Error: ' + errorMsg + '</span>');
                            $btn.prop('disabled', false).text('Import & Analyze');
                        }
                    }).fail(function() {
                        $('#helmetsan_ingest_status').html('<span style="color:red;">Server connection failed. Check logs.</span>');
                        $btn.prop('disabled', false).text('Import & Analyze');
                    });
                });
            });
        </script>
<?php
    }

    public function handleAjaxIngestion(): void
    {
        if (!check_ajax_referer('helmetsan_ingest_assets_nonce', 'nonce', false)) {
            wp_send_json_error(['errors' => ['Invalid nonce.']]);
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['errors' => ['Permission denied.']]);
        }

        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        $helmetId = isset($_POST['helmet_id']) ? (int) $_POST['helmet_id'] : 0;

        if (empty($url) || $helmetId <= 0) {
            wp_send_json_error(['errors' => ['Missing URL or Helmet ID.']]);
        }

        $result = $this->ingestionService->ingestFromUrl($helmetId, $url);

        if ($result['failures'] > 0 || !empty($result['errors'])) {
            // Still send success if *some* imported
            if ($result['success'] > 0) {
                wp_send_json_success([
                    'success' => $result['success'],
                    'warnings' => $result['errors']
                ]);
            } else {
                wp_send_json_error(['errors' => $result['errors']]);
            }
        }

        wp_send_json_success([
            'success' => $result['success']
        ]);
    }
}

<?php
declare(strict_types=1);

namespace HelmetsanTranslate;

defined('ABSPATH') || exit;

class CliCommands {
    /**
     * Show live translation queue health and provider status
     *
     * ## EXAMPLES
     *     wp helmetsan-translate status
     */
    public function status($args, $assoc_args): void {
        global $wpdb;
        $table = $wpdb->prefix . HELMETSAN_TRANSLATE_QUEUE_TABLE;

        $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'");
        $processing = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'processing'");
        $completed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'completed'");
        $failed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'failed'");

        \WP_CLI::line('====================================================');
        \WP_CLI::line('   🌐 HELMETSAN TRANSLATE QUEUE HEALTH');
        \WP_CLI::line('====================================================');
        \WP_CLI::line("Pending Tasks:    {$pending}");
        \WP_CLI::line("Processing Tasks: {$processing}");
        \WP_CLI::line("Completed Tasks:  {$completed}");
        \WP_CLI::line("Failed Tasks:     {$failed}");
        \WP_CLI::line('----------------------------------------------------');

        $options = Settings::get_options();
        \WP_CLI::line('ACTIVE AI PROVIDERS:');
        foreach ($options['providers'] as $id => $p) {
            if (!empty($p['enabled'])) {
                \WP_CLI::line("  ✓ [{$id}] {$p['name']} (Model: {$p['model']}, Priority: {$p['priority']})");
            }
        }
    }

    /**
     * Synchronize a specific post across all 9 languages
     *
     * ## OPTIONS
     * <id>
     * : The ID of the English master post to translate.
     *
     * ## EXAMPLES
     *     wp helmetsan-translate sync 32155
     */
    public function sync($args, $assoc_args): void {
        $post_id = (int) ($args[0] ?? 0);
        $post = get_post($post_id);
        if (!$post) {
            \WP_CLI::error("Post ID {$post_id} not found.");
        }

        $hash = md5($post->post_title . '|' . $post->post_content);
        $queue_id = QueueManager::enqueue($post_id, $post->post_type, $hash);
        \WP_CLI::success("Post {$post_id} ('{$post->post_title}') enqueued for translation (Queue ID: {$queue_id}).");
    }

    /**
     * Repair all missing brand, helmet type, and certification mappings across all languages
     *
     * ## OPTIONS
     * [--dry-run]
     * : Report missing taxonomy counts without writing changes to the database.
     *
     * ## EXAMPLES
     *     wp helmetsan-translate fix-taxonomies
     *     wp helmetsan-translate fix-taxonomies --dry-run
     */
    public function fix_taxonomies($args, $assoc_args): void {
        $dry_run = isset($assoc_args['dry-run']);
        \WP_CLI::line("Scanning catalog and repairing missing taxonomy links across all 10 languages" . ($dry_run ? " [DRY RUN]" : "") . "...");

        $start = microtime(true);
        $res = TaxonomyRepair::repair_all($dry_run);
        $elapsed = round(microtime(true) - $start, 2);

        \WP_CLI::line("----------------------------------------------------");
        \WP_CLI::line("Processed:           {$res['posts_processed']} non-English helmets");
        \WP_CLI::line("Brands Linked:       {$res['brands_fixed']}");
        \WP_CLI::line("Helmet Types Linked: {$res['types_fixed']}");
        \WP_CLI::line("Certifications Linked: {$res['certs_fixed']}");
        \WP_CLI::line("Execution Time:      {$elapsed}s");
        \WP_CLI::line("----------------------------------------------------");

        if ($dry_run) {
            \WP_CLI::success("Dry run completed. Run without --dry-run to apply changes.");
        } else {
            \WP_CLI::success("All catalog taxonomy links successfully repaired and synchronized!");
        }
    }

    /**
     * Localize formulaic engineering spec paragraphs (Paragraphs 2-6) and guide URLs across all non-English helmets
     *
     * ## OPTIONS
     * [--lang=<lang>]
     * : Specific language to process (de, fr, es, it, nl, pl, pt, ja, zh). Default all.
     * [--limit=<n>]
     * : Process only first N helmets. Default 0 (all).
     * [--batch-size=<n>]
     * : Batch size. Default 1000.
     *
     * ## EXAMPLES
     *     wp helmetsan-translate localize_spec_bodies --lang=de --limit=50
     *     wp helmetsan-translate localize_spec_bodies --batch-size=2000
     */
    public function localize_spec_bodies($args, $assoc_args): void {
        $lang = isset($assoc_args['lang']) ? (string) $assoc_args['lang'] : null;
        $limit = (int) ($assoc_args['limit'] ?? 0);
        $batch = (int) ($assoc_args['batch-size'] ?? 1000);

        \WP_CLI::line("Localizing formulaic technical spec bodies (Paragraphs 2-6) across catalog...");
        $res = SpecBodyLocalizer::localizeCatalog($limit, $batch, $lang);

        \WP_CLI::line("----------------------------------------------------");
        \WP_CLI::line("Scanned:        {$res['total_scanned']} helmets");
        \WP_CLI::line("Updated:        {$res['total_updated']} helmets");
        \WP_CLI::line("Execution Time: {$res['duration_sec']}s");
        \WP_CLI::line("----------------------------------------------------");
        \WP_CLI::success("Catalog spec body localization complete!");
    }
}

if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('helmetsan-translate', CliCommands::class);
}

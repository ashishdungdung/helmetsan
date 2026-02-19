<?php

declare(strict_types=1);

namespace Helmetsan\Core\Marketplace;

use Helmetsan\Core\Support\Config;
use Helmetsan\Core\Price\PriceHistory;

/**
 * Scheduled task for importing affiliate feed data.
 *
 * Iterates enabled affiliate feeds, downloads CSV/XML files,
 * and records prices in PriceHistory for each matched helmet.
 * Hooked into WordPress cron via SchedulerService.
 */
final class FeedIngestionTask
{
    private const HOOK_NAME = 'helmetsan_feed_ingestion';

    public function __construct(
        private readonly Config $config,
        private readonly ConnectorRegistry $registry,
        private readonly PriceHistory $priceHistory,
    ) {
    }

    /**
     * Register WordPress cron hooks.
     */
    public function register(): void
    {
        add_action(self::HOOK_NAME, [$this, 'run']);
    }

    /**
     * Schedule the task if not already scheduled.
     */
    public function schedule(): void
    {
        if (!wp_next_scheduled(self::HOOK_NAME)) {
            wp_schedule_event(time(), 'helmetsan_six_hours', self::HOOK_NAME);
        }
    }

    /**
     * Unschedule the task.
     */
    public function unschedule(): void
    {
        $ts = wp_next_scheduled(self::HOOK_NAME);
        if ($ts) {
            wp_unschedule_event($ts, self::HOOK_NAME);
        }
    }

    /**
     * Register custom cron interval (6 hours).
     *
     * @param array<string, array{interval: int, display: string}> $schedules
     * @return array<string, array{interval: int, display: string}>
     */
    public function addInterval(array $schedules): array
    {
        $schedules['helmetsan_six_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => 'Every 6 Hours',
        ];
        return $schedules;
    }

    /**
     * Run feed ingestion for all enabled affiliate feeds.
     */
    public function run(): void
    {
        $mktCfg = $this->config->marketplaceConfig();
        $feeds  = $mktCfg['affiliate_feeds'] ?? [];

        if (!is_array($feeds)) {
            return;
        }

        foreach ($feeds as $feedId => $feed) {
            if (empty($feed['enabled'])) {
                continue;
            }

            $feedUrl = $feed['url'] ?? '';
            if ($feedUrl === '') {
                continue;
            }

            try {
                $this->importFeed(
                    (string) $feedId,
                    $feedUrl,
                    $feed['column_map'] ?? [],
                    $feed['currency'] ?? 'USD',
                    $feed['countries'] ?? ['US']
                );
            } catch (\Throwable $e) {
                do_action('helmetsan_feed_error', $feedId, $e->getMessage());
            }
        }
    }

    /**
     * Import a single feed (CSV format).
     *
     * @param array<string,string> $columnMap
     * @param string[] $countries
     */
    private function importFeed(
        string $feedId,
        string $feedUrl,
        array  $columnMap,
        string $currency,
        array  $countries
    ): void {
        $response = wp_remote_get($feedUrl, ['timeout' => 60]);

        if (is_wp_error($response)) {
            do_action('helmetsan_feed_error', $feedId, $response->get_error_message());
            return;
        }

        $body = wp_remote_retrieve_body($response);
        if ($body === '') {
            return;
        }

        $lines = explode("\n", $body);
        if (count($lines) < 2) {
            return;
        }

        // Parse CSV header
        $header = str_getcsv(array_shift($lines));
        $priceCol = $columnMap['price'] ?? 'price';
        $nameCol  = $columnMap['name'] ?? 'product_name';
        $urlCol   = $columnMap['url'] ?? 'product_url';
        $eanCol   = $columnMap['ean'] ?? 'gtin';

        $priceIdx = array_search($priceCol, $header);
        $nameIdx  = array_search($nameCol, $header);
        $urlIdx   = array_search($urlCol, $header);
        $eanIdx   = array_search($eanCol, $header);

        if ($priceIdx === false || $nameIdx === false) {
            return;
        }

        $imported = 0;
        $countryCode = $countries[0] ?? 'US';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $cols = str_getcsv($line);
            $price = (float) ($cols[$priceIdx] ?? 0);
            $name  = (string) ($cols[$nameIdx] ?? '');

            if ($price <= 0 || $name === '') {
                continue;
            }

            // Try to match to a helmet post by EAN or name
            $ean = $eanIdx !== false ? (string) ($cols[$eanIdx] ?? '') : '';
            $helmetId = $this->resolveHelmet($ean, $name);

            if ($helmetId <= 0) {
                continue;
            }

            $this->priceHistory->record(
                $helmetId,
                $feedId,
                $countryCode,
                $currency,
                $price
            );

            $imported++;
        }

        do_action('helmetsan_feed_imported', $feedId, $imported);
    }

    /**
     * Try to resolve a helmet post ID from an EAN or name.
     */
    private function resolveHelmet(string $ean, string $name): int
    {
        global $wpdb;

        // Try EAN match first
        if ($ean !== '') {
            $id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'ean' AND meta_value = %s LIMIT 1",
                $ean
            ));
            if ($id) {
                return (int) $id;
            }
        }

        // Fuzzy name match as fallback (exact title match)
        $post = get_page_by_title($name, OBJECT, 'helmet');
        if ($post instanceof \WP_Post) {
            return (int) $post->ID;
        }

        return 0;
    }
}

<?php

declare(strict_types=1);

namespace Helmetsan\Core\Admin;

use Helmetsan\Core\Revenue\RevenueService;
use Helmetsan\Core\Price\PriceHistory;
use Helmetsan\Core\Support\Config;

/**
 * WP Admin revenue dashboard page.
 *
 * Displays click analytics, marketplace performance, and
 * affiliate network configuration under the Helmetsan menu.
 */
final class RevenueDashboard
{
    public function __construct(
        private readonly RevenueService $revenue,
        private readonly PriceHistory $priceHistory,
        private readonly Config $config,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'helmetsan',
            'Revenue Dashboard',
            'Revenue',
            'manage_options',
            'helmetsan-revenue',
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        $days   = isset($_GET['days']) ? max(1, (int) $_GET['days']) : 30;
        $report = $this->revenue->report($days);
        $byMarketplace = $this->revenue->reportByMarketplace($days);
        $attributionReport = $this->revenue->getAttributionReport($days);
        $revCfg = $this->config->revenueConfig();
        $networkCpc = $revCfg['network_cpc'] ?? [];
        $defaultCpc = 0.04;
        $snapStats  = $this->priceHistory->getSnapshotStats();
        ?>
        <div class="wrap">
            <h1>Revenue Dashboard</h1>

            <div style="display:flex;gap:8px;margin:16px 0;">
                <?php foreach ([7, 30, 90] as $d) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=helmetsan-revenue&days=' . $d)); ?>"
                       class="button <?php echo $days === $d ? 'button-primary' : ''; ?>">
                        <?php echo esc_html($d); ?> Days
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (!($report['ok'] ?? false)) : ?>
                <div class="notice notice-warning"><p><?php echo esc_html($report['message'] ?? 'Revenue table not available.'); ?></p></div>
                <?php return; ?>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
                <div class="postbox" style="padding:16px;margin:0;">
                    <h3 style="margin:0 0 4px;">Total Clicks</h3>
                    <strong style="font-size:28px;"><?php echo esc_html(number_format($report['total_clicks'] ?? 0)); ?></strong>
                </div>
                <div class="postbox" style="padding:16px;margin:0;">
                    <h3 style="margin:0 0 4px;">Estimated Revenue</h3>
                    <?php
                    $estRevenue = 0;
                    foreach (($report['by_network'] ?? []) as $net => $clicks) {
                        $cpc = (float) ($networkCpc[$net] ?? $defaultCpc);
                        $estRevenue += (int) $clicks * $cpc;
                    }
                    ?>
                    <strong style="font-size:28px;">$<?php echo esc_html(number_format($estRevenue, 2)); ?></strong>
                    <small style="display:block;color:#6b7280;margin-top:2px;">Per-network CPC rates</small>
                </div>
                <div class="postbox" style="padding:16px;margin:0;">
                    <h3 style="margin:0 0 4px;">Networks Active</h3>
                    <strong style="font-size:28px;"><?php echo esc_html((string) count($report['by_network'] ?? [])); ?></strong>
                </div>
                <div class="postbox" style="padding:16px;margin:0;">
                    <h3 style="margin:0 0 4px;">Marketplaces</h3>
                    <strong style="font-size:28px;"><?php echo esc_html((string) count($byMarketplace)); ?></strong>
                </div>
            </div>

            <!-- Price Coverage -->
            <div class="postbox" style="padding:16px;margin:0 0 24px;">
                <h2>Price Coverage</h2>
                <?php if ($snapStats['total_snapshots'] > 0) : ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:12px;">
                        <div>
                            <strong style="font-size:24px;"><?php echo esc_html(number_format($snapStats['total_snapshots'])); ?></strong>
                            <small style="display:block;color:#6b7280;">Total Snapshots</small>
                        </div>
                        <div>
                            <strong style="font-size:24px;"><?php echo esc_html((string) count($snapStats['by_marketplace'])); ?></strong>
                            <small style="display:block;color:#6b7280;">Marketplaces Tracked</small>
                        </div>
                        <div>
                            <strong style="font-size:24px;"><?php echo esc_html($snapStats['last_captured_at'] ?? '—'); ?></strong>
                            <small style="display:block;color:#6b7280;">Last Capture</small>
                        </div>
                    </div>
                    <?php if (!empty($snapStats['by_marketplace'])) : ?>
                        <table class="widefat striped" style="margin-top:8px;">
                            <thead><tr><th>Marketplace</th><th>Snapshots</th></tr></thead>
                            <tbody>
                            <?php foreach ($snapStats['by_marketplace'] as $mpId => $cnt) : ?>
                                <tr>
                                    <td><?php echo esc_html((string) $mpId); ?></td>
                                    <td><?php echo esc_html(number_format($cnt)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php else : ?>
                    <p>No price snapshots recorded yet.</p>
                <?php endif; ?>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                <!-- Clicks by Network -->
                <div class="postbox" style="padding:16px;margin:0;">
                    <h2>Clicks by Network</h2>
                    <table class="widefat striped">
                        <thead><tr><th>Network</th><th>Clicks</th><th>CPC</th><th>Est. Revenue</th></tr></thead>
                        <tbody>
                        <?php foreach (($report['by_network'] ?? []) as $net => $clicks) :
                            $netCpcRate = (float) ($networkCpc[$net] ?? $defaultCpc);
                        ?>
                            <tr>
                                <td><?php echo esc_html((string) $net); ?></td>
                                <td><?php echo esc_html(number_format((int) $clicks)); ?></td>
                                <td>$<?php echo esc_html(number_format($netCpcRate, 2)); ?></td>
                                <td>$<?php echo esc_html(number_format((int) $clicks * $netCpcRate, 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($report['by_network'])) : ?>
                            <tr><td colspan="4">No data yet</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Clicks by Marketplace -->
                <div class="postbox" style="padding:16px;margin:0;">
                    <h2>Clicks by Marketplace</h2>
                    <table class="widefat striped">
                        <thead><tr><th>Marketplace</th><th>Clicks</th><th>Est. Revenue</th></tr></thead>
                        <tbody>
                        <?php foreach ($byMarketplace as $mp => $clicks) : ?>
                            <tr>
                                <td><?php echo esc_html((string) $mp); ?></td>
                                <td><?php echo esc_html(number_format((int) $clicks)); ?></td>
                                <td>$<?php echo esc_html(number_format((int) $clicks * $defaultCpc, 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($byMarketplace)) : ?>
                            <tr><td colspan="3">No data yet</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Traffic Attribution & Referral Channels (Pillar IV Growth Engine) -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:24px;">
                <!-- Clicks by Referral Channel -->
                <div class="postbox" style="padding:16px;margin:0;">
                    <h2>Traffic Attribution by Channel</h2>
                    <table class="widefat striped">
                        <thead><tr><th>Channel</th><th>Clicks</th><th>Share</th></tr></thead>
                        <tbody>
                        <?php
                        $channelLabels = [
                            'ai_assistant' => ['label' => 'AI Assistants (ChatGPT, Perplexity, Claude)', 'color' => '#8b5cf6'],
                            'forum'        => ['label' => 'Motorcycle Forums & Reddit', 'color' => '#f97316'],
                            'social'       => ['label' => 'Social Networks (YouTube, IG, X)', 'color' => '#ec4899'],
                            'search'       => ['label' => 'Search Engines (Google, Bing)', 'color' => '#3b82f6'],
                            'email'        => ['label' => 'Email & Newsletters', 'color' => '#10b981'],
                            'direct'       => ['label' => 'Direct Navigation', 'color' => '#64748b'],
                            'referral'     => ['label' => 'Other External Referrals', 'color' => '#06b6d4'],
                        ];
                        $totalAttrClicks = max(1, (int) ($attributionReport['total_clicks'] ?? 1));
                        foreach (($attributionReport['by_channel'] ?? []) as $channelKey => $cnt) :
                            $meta = $channelLabels[$channelKey] ?? ['label' => ucfirst($channelKey), 'color' => '#64748b'];
                            $pct = round(($cnt / $totalAttrClicks) * 100, 1);
                        ?>
                            <tr>
                                <td>
                                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?php echo esc_attr($meta['color']); ?>;margin-right:6px;"></span>
                                    <strong><?php echo esc_html($meta['label']); ?></strong>
                                </td>
                                <td><?php echo esc_html(number_format((int) $cnt)); ?></td>
                                <td><?php echo esc_html((string) $pct); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($attributionReport['by_channel'])) : ?>
                            <tr><td colspan="3">No attribution data yet</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Top Inbound UTM Sources -->
                <div class="postbox" style="padding:16px;margin:0;">
                    <h2>Top Inbound UTM Campaigns & Sources</h2>
                    <table class="widefat striped">
                        <thead><tr><th>UTM Source</th><th>Clicks</th></tr></thead>
                        <tbody>
                        <?php foreach (($attributionReport['by_utm_source'] ?? []) as $src => $cnt) : ?>
                            <tr>
                                <td><code><?php echo esc_html((string) $src); ?></code></td>
                                <td><?php echo esc_html(number_format((int) $cnt)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($attributionReport['by_utm_source'])) : ?>
                            <tr><td colspan="2">No UTM tagged clicks recorded</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Helmets with Latest Prices -->
            <div class="postbox" style="padding:16px;margin:24px 0 0;">
                <h2>Top Clicked Helmets</h2>
                <table class="widefat striped">
                    <thead><tr><th>Helmet</th><th>Clicks</th><th>Est. Revenue</th><th>Latest Price</th><th>Marketplace</th></tr></thead>
                    <tbody>
                    <?php foreach (($report['top_helmets'] ?? []) as $helmet) :
                        $helmetId = (int) $helmet['helmet_id'];
                        $latestPrices = $this->priceHistory->getLatestByMarketplace($helmetId);
                        $topPrice = !empty($latestPrices) ? reset($latestPrices) : null;
                        $topMpId  = !empty($latestPrices) ? key($latestPrices) : null;
                    ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link($helmetId)); ?>">
                                    <?php echo esc_html((string) $helmet['title']); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html(number_format((int) $helmet['clicks'])); ?></td>
                            <td>$<?php echo esc_html(number_format((int) $helmet['clicks'] * $defaultCpc, 2)); ?></td>
                            <td>
                                <?php if ($topPrice) : ?>
                                    <?php echo esc_html($topPrice['currency'] . ' ' . number_format((float) $topPrice['price'], 2)); ?>
                                <?php else : ?>
                                    <em>—</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $topMpId ? esc_html($topMpId) : '—'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($report['top_helmets'])) : ?>
                        <tr><td colspan="5">No data yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Attributed Conversions (Pillar IV) -->
            <?php if (! empty($attributionReport['recent_conversions'])) : ?>
            <div class="postbox" style="padding:16px;margin:24px 0 0;">
                <h2>Recent Attributed Conversions</h2>
                <table class="widefat striped">
                    <thead><tr><th>Time</th><th>Helmet</th><th>Marketplace</th><th>Channel</th><th>UTM Source</th></tr></thead>
                    <tbody>
                    <?php foreach ($attributionReport['recent_conversions'] as $conv) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $conv['created_at']); ?></td>
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link((int) $conv['helmet_id'])); ?>">
                                    <?php echo esc_html((string) $conv['title']); ?>
                                </a>
                            </td>
                            <td><code><?php echo esc_html((string) $conv['marketplace_id']); ?></code></td>
                            <td><strong><?php echo esc_html((string) $conv['referral_channel']); ?></strong></td>
                            <td><?php echo $conv['utm_source'] !== '' ? '<code>' . esc_html((string) $conv['utm_source']) . '</code>' : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Network Configuration -->
            <div class="postbox" style="padding:16px;margin:24px 0 0;">
                <h2>Affiliate Networks</h2>
                <table class="widefat">
                    <thead><tr><th>Network</th><th>Status</th><th>Configuration</th></tr></thead>
                    <tbody>
                    <?php
                    $networks = $revCfg['affiliate_networks'] ?? [];
                    foreach ($networks as $netId => $netCfg) :
                        $enabled = !empty($netCfg['enabled']);
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html(ucfirst($netId)); ?></strong></td>
                            <td>
                                <span style="color:<?php echo $enabled ? '#059669' : '#dc2626'; ?>;">
                                    <?php echo $enabled ? '● Active' : '○ Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $keys = array_filter($netCfg, fn($v, $k) => $k !== 'enabled' && $v !== '', ARRAY_FILTER_USE_BOTH);
                                if (!empty($keys)) {
                                    echo '<code>' . esc_html(implode(', ', array_keys($keys))) . '</code>';
                                } else {
                                    echo '<em>Not configured</em>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description" style="margin-top:12px;">
                    Configure affiliate credentials in <code>wp-config.php</code> using
                    <code>HELMETSAN_CJ_WEBSITE_ID</code>, <code>HELMETSAN_ALLEGRO_AFF_ID</code>,
                    <code>HELMETSAN_JUMIA_AFF_ID</code> constants.
                </p>
            </div>
        </div>
        <?php
    }
}

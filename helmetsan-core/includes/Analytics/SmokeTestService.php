<?php

declare(strict_types=1);

namespace Helmetsan\Core\Analytics;

use Helmetsan\Core\Support\Config;

final class SmokeTestService
{
    public function run(): array
    {
        $config = (new Config())->analyticsDefaults();
        $saved  = get_option(Config::OPTION_ANALYTICS, []);
        $opts   = wp_parse_args(is_array($saved) ? $saved : [], $config);

        $monsterInsightsActive = class_exists('MonsterInsights');

        return [
            'enabled'            => (bool) $opts['enable_analytics'],
            'ga4_ready'          => ! empty($opts['ga4_measurement_id']),
            'gtm_ready'          => ! empty($opts['gtm_container_id']),
            'clarity_ready'      => (bool) $opts['enable_heatmap_clarity'] && ! empty($opts['clarity_project_id']),
            'hotjar_ready'       => (bool) $opts['enable_heatmap_hotjar'] && ! empty($opts['hotjar_site_id']),
            'monsterinsights'    => $monsterInsightsActive,
            'compat_mode_active' => $monsterInsightsActive && (bool) $opts['analytics_respect_monsterinsights'],
            'status'             => ((bool) $opts['enable_analytics']) ? 'ready' : 'disabled',
        ];
    }
}

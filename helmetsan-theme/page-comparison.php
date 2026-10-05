<?php
/**
 * Template Name: Comparison
 *
 * @package HelmetsanTheme
 */

// 1. Fetch Helmets by numeric ID or post_name slug (multilingual & order preserving)
$helmets = [];
if (isset($_GET["ids"]) && !empty($_GET["ids"])) {
    $raw = array_filter(
        array_map(
            "trim",
            explode(",", sanitize_text_field(wp_unslash($_GET["ids"]))),
        ),
    );
    $resolvedPosts = [];
    $currentLang = function_exists("pll_current_language")
        ? pll_current_language()
        : "";

    foreach ($raw as $identifier) {
        $postObj = null;
        if (is_numeric($identifier)) {
            $postId = (int) $identifier;
            if ($currentLang && function_exists("pll_get_post")) {
                $translatedId = pll_get_post($postId, $currentLang);
                if ($translatedId) {
                    $postId = $translatedId;
                }
            }
            $postObj = get_post($postId);
        } else {
            // Slug resolution
            $args = [
                "post_type" => "helmet",
                "name" => sanitize_title($identifier),
                "posts_per_page" => 1,
                "post_status" => "publish",
            ];
            if ($currentLang) {
                $args["lang"] = $currentLang;
            }
            $posts = get_posts($args);
            if (!empty($posts)) {
                $postObj = $posts[0];
            } else {
                // Try without language restriction
                $posts = get_posts([
                    "post_type" => "helmet",
                    "name" => sanitize_title($identifier),
                    "posts_per_page" => 1,
                    "post_status" => "publish",
                    "lang" => "",
                ]);
                if (!empty($posts)) {
                    $origId = $posts[0]->ID;
                    if ($currentLang && function_exists("pll_get_post")) {
                        $transId = pll_get_post($origId, $currentLang);
                        $postObj = $transId ? get_post($transId) : $posts[0];
                    } else {
                        $postObj = $posts[0];
                    }
                }
            }
        }

        if (
            $postObj instanceof WP_Post &&
            $postObj->post_type === "helmet" &&
            !isset($resolvedPosts[$postObj->ID])
        ) {
            $resolvedPosts[$postObj->ID] = $postObj;
        }
        if (count($resolvedPosts) >= 4) {
            break;
        }
    }
    $helmets = array_values($resolvedPosts);
}

// 2. Set dynamic title, meta description, and AI discovery before get_header()
if (count($helmets) >= 2) {
    $comparedNames = array_map(static fn($h) => get_the_title($h), $helmets);
    $vsTitle = implode(" vs ", $comparedNames);

    add_filter(
        "document_title_parts",
        static function (array $parts) use ($vsTitle): array {
            $parts[
                "title"
            ] = "{$vsTitle} — Weight, Noise, Safety & Price Comparison";
            $parts["site"] = "Helmetsan";
            return $parts;
        },
        20,
    );

    add_filter(
        "pre_get_document_title",
        static function () use ($vsTitle): string {
            return "{$vsTitle} — Weight, Noise, Safety & Price Comparison | Helmetsan";
        },
        20,
    );

    add_action(
        "wp_head",
        static function () use ($helmets, $vsTitle): void {
            $first = $helmets[0];
            $second = $helmets[1];
            $w1 =
                get_post_meta($first->ID, "spec_weight_g", true) ?:
                get_post_meta($first->ID, "weight_g", true);
            $w2 =
                get_post_meta($second->ID, "spec_weight_g", true) ?:
                get_post_meta($second->ID, "weight_g", true);
            $n1 =
                get_post_meta($first->ID, "noise_db_at_100kph", true) ?:
                get_post_meta($first->ID, "spec_noise_db", true);
            $n2 =
                get_post_meta($second->ID, "noise_db_at_100kph", true) ?:
                get_post_meta($second->ID, "spec_noise_db", true);

            $specParts = [];
            if ($w1 && $w2) {
                $specParts[] = "weights ({$w1}g vs {$w2}g)";
            }
            if ($n1 && $n2) {
                $specParts[] = "noise levels ({$n1}dB vs {$n2}dB)";
            }
            $specDesc = !empty($specParts)
                ? " Compare " . implode(" and ", $specParts) . "."
                : "";

            $desc = "Side-by-side technical comparison of {$vsTitle}.{$specDesc} Which one is safer, lighter, and quieter? Verified specs by Helmetsan.";
            echo '<meta name="description" content="' .
                esc_attr($desc) .
                '">' .
                "\n";

            $mdUrl = add_query_arg("format", "md");
            echo '<link rel="alternate" type="text/markdown" href="' .
                esc_url($mdUrl) .
                '" title="Structured Comparison (Markdown)">' .
                "\n";
        },
        1,
    );
} else {
    add_action(
        "wp_head",
        static function (): void {
            $mdUrl = add_query_arg("format", "md");
            echo '<link rel="alternate" type="text/markdown" href="' .
                esc_url($mdUrl) .
                '" title="Comparison Matrix (Markdown)">' .
                "\n";
        },
        1,
    );
}

get_header();

// 3. Schema.org Structured Data
if (count($helmets) >= 2) {
    $itemElements = [];
    $names = [];
    foreach ($helmets as $idx => $h) {
        $names[] = get_the_title($h);
        $tech = function_exists("helmetsan_get_technical_profile")
            ? helmetsan_get_technical_profile($h->ID)
            : [];
        $weight =
            $tech["weight"] ?? get_post_meta($h->ID, "spec_weight_g", true);
        $homologation =
            $tech["homologation"] ??
            get_post_meta($h->ID, "homologation_standard", true);
        $price =
            get_post_meta($h->ID, "price_retail_usd", true) ?:
            get_post_meta($h->ID, "price_usd", true);

        $prodSchema = [
            "@type" => "Product",
            "name" => get_the_title($h),
            "url" => get_permalink($h),
            "image" => get_the_post_thumbnail_url($h, "large") ?: "",
        ];
        if (!empty($price) && is_numeric((string) $price)) {
            $prodSchema["offers"] = [
                "@type" => "Offer",
                "price" => (float) $price,
                "priceCurrency" => "USD",
                "availability" => "https://schema.org/InStock",
            ];
        }
        $additional = [];
        if (!empty($homologation) && $homologation !== "N/A") {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "Safety Homologation",
                "value" => (string) $homologation,
            ];
        }
        if (!empty($weight) && is_numeric((string) $weight)) {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "Weight",
                "value" => (int) $weight . " g",
            ];
        }
        if (!empty($tech["noise_db"]) && $tech["noise_db"] !== "N/A") {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "Noise Level @ 100km/h",
                "value" => (string) $tech["noise_db"],
            ];
        }
        if ($additional !== []) {
            $prodSchema["additionalProperty"] = $additional;
        }

        $itemElements[] = [
            "@type" => "ListItem",
            "position" => $idx + 1,
            "item" => $prodSchema,
        ];
    }

    $comparisonSchema = [
        "@context" => "https://schema.org",
        "@type" => "ItemList",
        "name" => implode(" vs ", $names) . " — Motorcycle Helmet Comparison",
        "description" =>
            "Direct head-to-head comparison of motorcycle helmet specifications, verified weights, decibel noise levels, and safety ratings on Helmetsan.",
        "numberOfItems" => count($itemElements),
        "itemListElement" => $itemElements,
    ];

    echo '<script type="application/ld+json">' .
        wp_json_encode(
            $comparisonSchema,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
        ) .
        "</script>" .
        "\n";
}
?>

<script>
// Auto-redirect to ?ids=... if localStorage has items but URL parameter is missing
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.pathname.indexOf('/vs/') !== -1) {
        return;
    }
    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.get('ids') && window.location.pathname.includes('/comparison')) {
        try {
            const list = JSON.parse(localStorage.getItem('helmetsan_compare_list')) || [];
            if (list.length > 0) {
                const ids = list.map(item => item.slug || item.id).join(',');
                window.location.href = window.location.pathname + '?ids=' + encodeURIComponent(ids);
            }
        } catch(e) {}
    }
});
</script>

<?php
// 4. Calculate Intelligence Scores and Winner Metrics Ahead of Table Rendering
$calculatedScores = [];
$weightsList = [];
$noiseList = [];
$sharpList = [];

foreach ($helmets as $p) {
    $tech = function_exists("helmetsan_get_technical_profile")
        ? helmetsan_get_technical_profile($p->ID)
        : [];
    $priceRetail =
        get_post_meta($p->ID, "price_retail_usd", true) ?:
        get_post_meta($p->ID, "price_usd", true);
    $priceVal = is_numeric($priceRetail) ? (float) $priceRetail : 350.0;

    $weightNum =
        is_numeric($tech["weight"] ?? null) && (int) $tech["weight"] > 0
            ? (int) $tech["weight"]
            : (int) (get_post_meta($p->ID, "spec_weight_g", true) ?: 1450);

    $rawNoise = (string) ($tech["noise_db"] ?? "");
    $cleanNoiseDb = (int) preg_replace("/[^0-9]/", "", $rawNoise);

    $rawVent = (string) ($tech["ventilation_score"] ?? "");
    $cleanVentScore = (float) explode("/", $rawVent)[0];

    $scoreObj = function_exists("helmetsan_calculate_score")
        ? helmetsan_calculate_score([
            "specs" => [
                "weight_g" => $weightNum,
            ],
            "safety_intelligence" => [
                "homologation_standard" =>
                    ($tech["homologation"] ?? "") !== "N/A"
                        ? $tech["homologation"] ?? ""
                        : (string) get_post_meta(
                            $p->ID,
                            "homologation_standard",
                            true,
                        ),
                "sharp_rating" => !empty($tech["sharp_rating"])
                    ? (int) $tech["sharp_rating"]
                    : (int) get_post_meta($p->ID, "sharp_rating", true),
                "rotational_mitigation" =>
                    ($tech["rotational_tech"] ?? "") !== "N/A"
                        ? $tech["rotational_tech"] ?? ""
                        : "",
            ],
            "aero_acoustic_profile" => [
                "noise_db_at_100kph" => $cleanNoiseDb > 0 ? $cleanNoiseDb : 85,
                "ventilation_efficiency_score" =>
                    $cleanVentScore > 0 ? $cleanVentScore : 8.0,
                "wind_tunnel_tested" => !empty($tech["wind_tunnel_tested"]),
            ],
            "sizing_fit" => [
                "head_shape" =>
                    ($tech["head_shape"] ?? "") !== "N/A"
                        ? $tech["head_shape"] ?? ""
                        : "",
            ],
            "tech_integration" => [
                "comms_cutout_type" =>
                    ($tech["comms_ready"] ?? "") !== "N/A"
                        ? $tech["comms_ready"] ?? ""
                        : "",
            ],
            "features_data" => [
                "visor" =>
                    !empty($tech["visor_features"]) ||
                    !empty($tech["integrated_sun_visor"]),
            ],
            "price" => [
                "usd" => $priceVal,
            ],
        ])
        : ["overall" => 90, "dimensions" => []];

    $calculatedScores[$p->ID] = $scoreObj;
    if ($weightNum > 0) {
        $weightsList[$p->ID] = $weightNum;
    }
    if ($cleanNoiseDb > 0) {
        $noiseList[$p->ID] = $cleanNoiseDb;
    }
    $sharpStars = !empty($tech["sharp_rating"])
        ? (int) $tech["sharp_rating"]
        : (int) get_post_meta($p->ID, "sharp_rating", true);
    if ($sharpStars > 0) {
        $sharpList[$p->ID] = $sharpStars;
    }
}

// Determine Winners (only when comparing 2 or more)
$winningScoreId = null;
$winningWeightId = null;
$winningNoiseId = null;
$winningSharpId = null;

if (count($helmets) >= 2) {
    if (!empty($calculatedScores)) {
        $maxScore = -1;
        foreach ($calculatedScores as $id => $sObj) {
            if ($sObj["overall"] > $maxScore) {
                $maxScore = $sObj["overall"];
                $winningScoreId = $id;
            }
        }
    }
    if (!empty($weightsList)) {
        $minWeight = min($weightsList);
        foreach ($weightsList as $id => $w) {
            if ($w === $minWeight) {
                $winningWeightId = $id;
                break;
            }
        }
    }
    if (!empty($noiseList)) {
        $minNoise = min($noiseList);
        foreach ($noiseList as $id => $n) {
            if ($n === $minNoise) {
                $winningNoiseId = $id;
                break;
            }
        }
    }
    if (!empty($sharpList)) {
        $maxSharp = max($sharpList);
        if ($maxSharp >= 4) {
            foreach ($sharpList as $id => $sh) {
                if ($sh === $maxSharp) {
                    $winningSharpId = $id;
                    break;
                }
            }
        }
    }
}

// 5. Comparison Attributes Definition
$attributes = [
    "score_header" => [
        "type" => "header",
        "label" => "Helmetsan Intelligence Score",
    ],
    "score" => [
        "label" => "Helmetsan Score",
        "callback" => function ($p) use ($calculatedScores, $winningScoreId) {
            $scoreObj = $calculatedScores[$p->ID] ?? [
                "overall" => 90,
                "dimensions" => [],
            ];
            $score = $scoreObj["overall"];
            $color = $score >= 90 ? "hs-chip-success" : "hs-chip-neutral";
            $isWinner = $winningScoreId === $p->ID;
            $winnerBadge = $isWinner
                ? '<span class="hs-comp-winner-badge">★ Top Score</span>'
                : "";

            $dims = $scoreObj["dimensions"] ?? [];
            $breakdownHtml = "";
            if (!empty($dims)) {
                $breakdownHtml =
                    '<div class="hs-comp-mini-bars" style="margin-top:0.4rem; display:flex; flex-direction:column; gap:3px; font-size:0.75rem; opacity:0.85;">' .
                    '<span title="Safety Index">Safety: ' .
                    ($dims["safety"] ?? "-") .
                    "/100</span>" .
                    '<span title="Comfort & Fit">Comfort: ' .
                    ($dims["comfort"] ?? "-") .
                    "/100</span>" .
                    '<span title="Aero & Ventilation">Ventilation: ' .
                    ($dims["ventilation"] ?? "-") .
                    "/100</span>" .
                    "</div>";
            }

            return '<div class="hs-flex hs-flex-col hs-gap-1">' .
                '<div class="hs-flex hs-items-center hs-gap-2">' .
                '<span class="hs-chip ' .
                $color .
                ' hs-font-black hs-text-base">' .
                $score .
                " / 100</span>" .
                $winnerBadge .
                "</div>" .
                $breakdownHtml .
                "</div>";
        },
        "is_html" => true,
    ],

    "general_header" => ["type" => "header", "label" => "Overview & Pricing"],
    "price" => [
        "label" => "Price",
        "callback" => function ($p) {
            return helmetsan_render_price_element(
                $p->ID,
                "hs-comparison-price",
            );
        },
        "is_html" => true,
    ],
    "brand" => [
        "label" => "Brand",
        "callback" => function ($p) {
            return helmetsan_get_brand_name($p->ID);
        },
    ],
    "type" => [
        "label" => "Helmet Type",
        "callback" => function ($p) {
            $terms = get_the_terms($p->ID, "helmet_type");
            return is_array($terms) && !empty($terms)
                ? implode(", ", wp_list_pluck($terms, "name"))
                : "-";
        },
    ],
    "family" => [
        "label" => "Model Family",
        "callback" => function ($p) {
            return get_post_meta($p->ID, "helmet_family", true) ?: "-";
        },
    ],

    "safety_header" => [
        "type" => "header",
        "label" => "Safety & Certifications",
    ],
    "certs" => [
        "label" => "Certifications",
        "callback" => function ($p) {
            return helmetsan_get_certifications($p->ID);
        },
    ],
    "homologation" => [
        "label" => "Homologation Standard",
        "callback" => function ($p) {
            $prof = helmetsan_get_technical_profile($p->ID);
            return $prof["homologation"] ?? "-";
        },
    ],
    "sharp" => [
        "label" => "SHARP Rating",
        "callback" => function ($p) use ($winningSharpId) {
            $prof = helmetsan_get_technical_profile($p->ID);
            $rating = $prof["sharp_rating"] ?? 0;
            if (!$rating) {
                return "—";
            }
            $isWinner = $winningSharpId === $p->ID;
            $winnerBadge = $isWinner
                ? '<span class="hs-comp-winner-badge">★ Top Safety</span>'
                : "";
            return '<div class="hs-flex hs-items-center hs-gap-2">' .
                '<div class="hs-rating" aria-label="' .
                esc_attr($rating) .
                ' stars">' .
                str_repeat("★", $rating) .
                str_repeat("☆", 5 - $rating) .
                "</div>" .
                $winnerBadge .
                "</div>";
        },
        "is_html" => true,
    ],
    "rotational" => [
        "label" => "Rotational Tech",
        "callback" => function ($p) {
            $prof = helmetsan_get_technical_profile($p->ID);
            return $prof["rotational_tech"] ?? "-";
        },
    ],
    "emergency_release" => [
        "label" => "Emergency Release (EQRS)",
        "callback" => function ($p) {
            $prof = helmetsan_get_technical_profile($p->ID);
            return !empty($prof["emergency_release"])
                ? "✓ Yes (Quick-Release Cheek Pads)"
                : "No";
        },
    ],
    "multi_density_eps" => [
        "label" => "Multi-Density EPS",
        "callback" => function ($p) {
            $prof = helmetsan_get_technical_profile($p->ID);
            return !empty($prof["multi_density_eps"])
                ? "✓ Yes"
                : "Standard EPS";
        },
    ],

    "aero_header" => [
        "type" => "header",
        "label" => "Acoustics & Aerodynamics",
    ],
    "noise" => [
        "label" => "Noise Level @ 100km/h",
        "callback" => function ($p) use ($winningNoiseId) {
            $prof = helmetsan_get_technical_profile($p->ID);
            $noise = $prof["noise_db"] ?? "-";
            if ($noise === "N/A" || $noise === "-") {
                return "—";
            }
            $isWinner = $winningNoiseId === $p->ID;
            $winnerBadge = $isWinner
                ? '<span class="hs-comp-winner-badge">✓ Quietest</span>'
                : "";
            return '<div class="hs-flex hs-items-center hs-gap-2"><span>' .
                esc_html($noise) .
                "</span>" .
                $winnerBadge .
                "</div>";
        },
        "is_html" => true,
    ],
    "ventilation" => [
        "label" => "Ventilation Efficiency",
        "callback" => function ($p) {
            $prof = helmetsan_get_technical_profile($p->ID);
            return $prof["ventilation_score"] ?? "-";
        },
    ],
    "wind_tunnel" => [
        "label" => "Wind Tunnel Developed",
        "callback" => function ($p) {
            $prof = helmetsan_get_technical_profile($p->ID);
            return !empty($prof["wind_tunnel_tested"])
                ? "✓ Yes"
                : "CFD / Computational Simulation";
        },
    ],

    "construction_header" => [
        "type" => "header",
        "label" => "Construction & Fit",
    ],
    "weight" => [
        "label" => "Verified Weight",
        "callback" => function ($p) use ($winningWeightId) {
            $prof = helmetsan_get_technical_profile($p->ID);
            $w = $prof["weight"] ?? 0;
            if ($w <= 0) {
                return "—";
            }
            $isWinner = $winningWeightId === $p->ID;
            $winnerBadge = $isWinner
                ? '<span class="hs-comp-winner-badge">✓ Lightest</span>'
                : "";
            return '<div class="hs-flex hs-items-center hs-gap-2"><span>' .
                (int) $w .
                " g</span>" .
                $winnerBadge .
                "</div>";
        },
        "is_html" => true,
    ],
    "material" => [
        "label" => "Shell Material",
        "callback" => function ($p) {
            $prof = helmetsan_get_technical_profile($p->ID);
            return $prof["shell"] ?? "-";
        },
    ],
    "shape" => [
        "label" => "Head Shape",
        "callback" => function ($p) {
            $prof = helmetsan_get_technical_profile($p->ID);
            $s = $prof["head_shape"] ?? "";
            return $s && $s !== "N/A"
                ? ucwords(str_replace("-", " ", $s))
                : "-";
        },
    ],
    "glasses" => [
        "label" => "Eyewear Grooves",
        "callback" => function ($p) {
            $prof = helmetsan_get_technical_profile($p->ID);
            return !empty($prof["glasses_grooves"])
                ? "✓ Glasses-Friendly"
                : "Standard";
        },
    ],
    "liner" => [
        "label" => "Removable Interior Liner",
        "callback" => function ($p) {
            $prof = helmetsan_get_technical_profile($p->ID);
            return !empty($prof["removable_interior"])
                ? "✓ Fully Removable & Washable"
                : "Fixed";
        },
    ],

    "features_header" => [
        "type" => "header",
        "label" => "Optical & Hardware Features",
    ],
    "sun_visor" => [
        "label" => "Integrated Sun Visor",
        "callback" => function ($p) {
            $prof = helmetsan_get_technical_profile($p->ID);
            return !empty($prof["integrated_sun_visor"])
                ? "✓ Yes (Internal Drop-Down)"
                : "No (Clear Shield Only)";
        },
    ],
    "pinlock" => [
        "label" => "Pinlock Anti-Fog",
        "callback" => function ($p) {
            $prof = helmetsan_get_technical_profile($p->ID);
            if (!empty($prof["pinlock_included"])) {
                $type =
                    $prof["pinlock_type"] && $prof["pinlock_type"] !== "N/A"
                        ? " (" . $prof["pinlock_type"] . ")"
                        : "";
                return "✓ Included in Box" . $type;
            }
            return "Pinlock Ready (Lens sold separately)";
        },
    ],
    "strap" => [
        "label" => "Retention Strap",
        "callback" => function ($p) {
            $prof = helmetsan_get_technical_profile($p->ID);
            return $prof["strap_type"] ?? "-";
        },
    ],
    "comms" => [
        "label" => "Bluetooth / Comms Ready",
        "callback" => function ($p) {
            $prof = helmetsan_get_technical_profile($p->ID);
            return $prof["comms_ready"] ?? "-";
        },
    ],
    "warranty" => [
        "label" => "Manufacturer Warranty",
        "callback" => function ($p) {
            $prof = helmetsan_get_technical_profile($p->ID);
            return $prof["warranty"] ?? "-";
        },
    ],
    "key_features" => [
        "label" => "Editorial Highlights",
        "callback" => function ($p) {
            return helmetsan_get_helmet_key_features_html($p->ID);
        },
        "is_html" => true,
    ],
];

$helmets_link = get_post_type_archive_link("helmet") ?: home_url("/helmets/");
?>

<div class="hs-section hs-section--comparison">
    <div class="hs-container">
        <header class="hs-comparison-hero">
            <h1><?php hs_e("Helmet Comparison"); ?></h1>
            <p class="hs-comparison-hero__lead"><?php hs_e(
                "Compare specs, certifications, weight, and price side by side. Add up to four helmets from the catalog, then use this page to see differences at a glance and choose the right one.",
            ); ?></p>
        </header>

        <?php if (empty($helmets)): ?>
            <div class="hs-panel hs-comparison-empty">
                <p><strong><?php hs_e(
                    "No helmets selected yet.",
                ); ?></strong> <?php hs_e(
    "Browse the catalog and click “+ Compare” on any helmet to add it here. You can compare up to four helmets at once.",
); ?></p>
                <a href="<?php echo esc_url(
                    $helmets_link,
                ); ?>" class="hs-btn hs-btn--primary"><?php hs_e(
    "Browse helmets",
); ?> &rarr;</a>
            </div>
        <?php else:
            $helmet_ids = array_map(static fn($p) => $p->ID, $helmets);
            $helmet_slugs = array_map(static fn($p) => $p->post_name, $helmets);
            $helmet_titles = array_combine(
                $helmet_ids,
                array_map(static fn($p) => $p->post_title, $helmets),
            );
            ?>
            <script>
                window.helmetsanComparisonIds = <?php echo wp_json_encode(
                    array_values($helmet_ids),
                ); ?>;
                window.helmetsanComparisonSlugs = <?php echo wp_json_encode(
                    array_values($helmet_slugs),
                ); ?>;
                window.helmetsanComparisonTitles = <?php echo wp_json_encode(
                    $helmet_titles,
                ); ?>;
                <?php if (count($helmets) === 2): ?>
                window.helmetsanShareableUrl = <?php echo wp_json_encode(
                    home_url(
                        "/vs/" .
                            $helmets[0]->post_name .
                            "-vs-" .
                            $helmets[1]->post_name .
                            "/",
                    ),
                ); ?>;
                <?php endif; ?>
            </script>
            <div class="hs-comp-toolbar">
                <button type="button" class="hs-btn hs-btn--sm hs-btn--ghost" id="hs-comp-toggle-empty" aria-pressed="false"><?php hs_e(
                    "Show empty fields",
                ); ?></button>
                <button type="button" class="hs-btn hs-btn--sm hs-btn--ghost js-comparison-clear" id="hs-comp-clear-all"><?php hs_e(
                    "Clear All",
                ); ?></button>
                <button type="button" class="hs-btn hs-btn--sm hs-btn--primary js-share-comparison" id="hs-comp-share" title="<?php hs_attr_e(
                    "Copy link to clipboard",
                ); ?>">
                    <?php hs_e("Share this comparison"); ?>
                </button>
            </div>
            <div class="hs-comparison-table-wrap">
                <table class="hs-comparison-table" id="hs-comparison-table">
                    <thead>
                        <tr>
                            <th scope="col" id="col-feature" class="hs-comp-label-col"><?php hs_e(
                                "Feature",
                            ); ?></th>
                            <?php foreach ($helmets as $helmet):

                                $colId = "col-helmet-" . $helmet->ID;
                                $slug = get_post_field(
                                    "post_name",
                                    $helmet->ID,
                                );
                                $go_url = $slug
                                    ? home_url(
                                        "/go/" . $slug . "/?source=comparison",
                                    )
                                    : get_permalink($helmet->ID);
                                ?>
                                <th scope="col" id="<?php echo esc_attr(
                                    $colId,
                                ); ?>" class="hs-comp-header">
                                    <div class="hs-comp-img">
                                        <?php if (
                                            has_post_thumbnail($helmet->ID)
                                        ): ?>
                                            <?php echo get_the_post_thumbnail(
                                                $helmet->ID,
                                                "medium",
                                                [
                                                    "alt" => sprintf(
                                                        esc_html(
                                                            hs_t("Photo of %s"),
                                                        ),
                                                        $helmet->post_title,
                                                    ),
                                                ],
                                            ); ?>
                                        <?php else: ?>
                                            <span class="hs-comp-img-placeholder" aria-hidden="true">—</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="hs-comp-title">
                                        <a href="<?php echo esc_url(
                                            get_permalink($helmet->ID),
                                        ); ?>">
                                            <?php echo esc_html(
                                                $helmet->post_title,
                                            ); ?>
                                        </a>
                                    </div>
                                    <div class="hs-comp-header-actions">
                                        <a href="<?php echo esc_url(
                                            get_permalink($helmet->ID),
                                        ); ?>" class="hs-btn hs-btn--sm hs-btn--ghost" aria-label="<?php echo esc_attr(
    sprintf(hs_t("View details for %s"), $helmet->post_title),
); ?>"><?php hs_e("View"); ?></a>
                                        <a href="<?php echo esc_url(
                                            $go_url,
                                        ); ?>" class="hs-btn hs-btn--sm hs-btn--primary hs-price-cta" data-marketplace="amazon" rel="nofollow sponsored" aria-label="<?php echo esc_attr(
    sprintf(hs_t("Check price for %s"), $helmet->post_title),
); ?>"><?php hs_e("Check price"); ?></a>
                                        <button type="button" class="hs-btn hs-btn--sm hs-btn--ghost js-comp-remove-helmet"
                                                data-id="<?php echo (int) $helmet->ID; ?>"
                                                data-slug="<?php echo esc_attr(
                                                    $slug,
                                                ); ?>"
                                                aria-label="<?php echo esc_attr(
                                                    sprintf(
                                                        __(
                                                            "Remove %s from comparison",
                                                            "helmetsan-theme",
                                                        ),
                                                        $helmet->post_title,
                                                    ),
                                                ); ?>">Remove</button>
                                    </div>
                                </th>
                            <?php
                            endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attributes as $key => $attr):
                            $rowId = "row-" . sanitize_title($key); ?>
                            <?php if (
                                isset($attr["type"]) &&
                                $attr["type"] === "header"
                            ): ?>
                                <tr class="hs-comp-section-header">
                                    <th scope="colgroup" id="<?php echo esc_attr(
                                        $rowId,
                                    ); ?>" colspan="<?php echo count($helmets) +
    1; ?>"><?php echo esc_html($attr["label"]); ?></th>
                                </tr>
                            <?php else:
                                $values = array_map(
                                    $attr["callback"],
                                    $helmets,
                                );
                                $is_html =
                                    isset($attr["is_html"]) && $attr["is_html"];
                                $all_empty =
                                    count(
                                        array_filter($values, static function (
                                            $v,
                                        ) {
                                            $v = trim(strip_tags((string) $v));
                                            return $v !== "" &&
                                                $v !== "-" &&
                                                $v !== "—" &&
                                                $v !== "N/A";
                                        }),
                                    ) === 0;
                                $row_class = $all_empty
                                    ? "hs-comp-row--empty"
                                    : "";
                                ?>
                                <tr class="<?php echo esc_attr(
                                    $row_class,
                                ); ?>" <?php echo $all_empty
    ? ' data-empty="1"'
    : ""; ?>>
                                    <th scope="row" id="<?php echo esc_attr(
                                        $rowId,
                                    ); ?>" class="hs-comp-label"><?php echo esc_html(
    $attr["label"],
); ?></th>
                                    <?php foreach ($helmets as $index => $h):

                                        $colId = "col-helmet-" . $h->ID;
                                        $val = $values[$index];
                                        ?>
                                        <td class="hs-comp-value <?php echo $is_html
                                            ? "hs-comp-value--html"
                                            : ""; ?>" headers="<?php echo esc_attr(
    $colId . " " . $rowId,
); ?>">
                                            <?php echo $is_html
                                                ? $val
                                                : esc_html((string) $val); ?>
                                        </td>
                                    <?php
                                    endforeach; ?>
                                </tr>
                            <?php endif; ?>
                        <?php
                        endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="hs-comparison-cta hs-panel">
                <p class="hs-comparison-cta__lead"><?php hs_e(
                    "Share this comparison or add more helmets to compare.",
                ); ?></p>
                <div class="hs-comp-toolbar-bottom">
                    <button type="button" class="hs-btn hs-btn--sm hs-btn--ghost" id="hs-comp-toggle-empty-bottom" aria-pressed="false"><?php hs_e(
                        "Show empty fields",
                    ); ?></button>
                    <button type="button" class="hs-btn hs-btn--sm hs-btn--primary js-share-comparison" id="hs-comp-share-bottom" title="<?php hs_attr_e(
                        "Copy link to clipboard",
                    ); ?>"><?php hs_e("Share link"); ?></button>
                    <a href="<?php echo esc_url(
                        $helmets_link,
                    ); ?>" class="hs-btn hs-btn--sm hs-btn--primary"><?php hs_e(
    "Add more helmets",
); ?></a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    var table = document.getElementById('hs-comparison-table');
    function setToggle(pressed) {
        if (table) table.classList.toggle('show-empty-rows', !!pressed);
        document.querySelectorAll('#hs-comp-toggle-empty, #hs-comp-toggle-empty-bottom').forEach(function(btn) {
            if (btn) { btn.setAttribute('aria-pressed', pressed ? 'true' : 'false'); btn.textContent = pressed ? 'Hide empty fields' : 'Show empty fields'; }
        });
    }
    document.getElementById('hs-comp-toggle-empty') && document.getElementById('hs-comp-toggle-empty').addEventListener('click', function() { setToggle(this.getAttribute('aria-pressed') !== 'true'); });
    document.getElementById('hs-comp-toggle-empty-bottom') && document.getElementById('hs-comp-toggle-empty-bottom').addEventListener('click', function() { setToggle(this.getAttribute('aria-pressed') !== 'true'); });

    function shareComparison(btn) {
        var url = window.helmetsanShareableUrl || window.location.href;
        if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function() {
                var orig = btn.textContent;
                btn.textContent = 'Link copied!';
                btn.disabled = true;
                setTimeout(function() { btn.textContent = orig; btn.disabled = false; }, 2000);
            }).catch(function() { btn.textContent = 'Copy link'; });
        } else {
            var input = document.createElement('input');
            input.value = url;
            input.setAttribute('readonly', '');
            input.style.position = 'fixed'; input.style.opacity = '0';
            document.body.appendChild(input);
            input.select();
            try {
                document.execCommand('copy');
                var orig = btn.textContent;
                btn.textContent = 'Link copied!';
                btn.disabled = true;
                setTimeout(function() { btn.textContent = orig; btn.disabled = false; document.body.removeChild(input); }, 2000);
            } catch (e) { document.body.removeChild(input); }
        }
    }
    document.querySelectorAll('.js-share-comparison').forEach(function(btn) {
        btn.addEventListener('click', function() { shareComparison(this); });
    });
})();
</script>

<?php get_footer();

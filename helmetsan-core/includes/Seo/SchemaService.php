<?php

declare(strict_types=1);

namespace Helmetsan\Core\Seo;

use Helmetsan\Core\Reviews\ReviewService;
use Helmetsan\Core\Cache\ObjectCacheService;

/**
 * Outputs JSON-LD structured data for rich results: Product (helmet, accessory, motorcycle),
 * BreadcrumbList, WebSite, Organization, ItemList, CollectionPage.
 */
final class SchemaService
{
    private const OFFER_VALID_DAYS_DEFAULT = 30;

    public function __construct(
        private readonly ReviewService $reviewService
    ) {}

    public function register(): void
    {
        add_action('wp_head', [$this, 'printProductSchema'], 30);
        add_action('wp_head', [$this, 'printBreadcrumbListSchema'], 31);
        add_action('wp_head', [$this, 'printWebSiteSchema'], 32);
        add_action('wp_head', [$this, 'printOrganizationSchema'], 33);
        add_action('wp_head', [$this, 'printItemListSchema'], 34);
        add_action('wp_head', [$this, 'printCollectionPageSchema'], 35);
    }

    public function printProductSchema(): void
    {
        if (! is_singular(['helmet', 'accessory', 'motorcycle'])) {
            return;
        }

        $postId = (int) get_queried_object_id();
        if ($postId <= 0) {
            return;
        }

        $post = get_post($postId);
        if (! $post instanceof \WP_Post) {
            return;
        }

        $schema = null;
        $basePrice = 0.0;
        if ($post->post_type === 'helmet') {
            $schema = $this->buildProductSchemaHelmet($postId);
            $basePrice = (float) get_post_meta($postId, 'price_retail_usd', true);
        } elseif ($post->post_type === 'accessory') {
            $schema = $this->buildProductSchemaAccessory($postId);
            $priceJson = (string) get_post_meta($postId, 'price_json', true);
            $priceData = $priceJson !== '' ? json_decode($priceJson, true) : null;
            $basePrice = is_array($priceData) && isset($priceData['value']) ? (float) $priceData['value'] : 0.0;
        } elseif ($post->post_type === 'motorcycle') {
            $schema = $this->buildProductSchemaMotorcycle($postId);
            $basePrice = (float) get_post_meta($postId, 'price_retail_usd', true);
        }

        if ($schema !== null && $schema !== []) {
            printf(
                '<script type="application/ld+json" class="helmetsan-schema-product" data-base-price="%s">%s</script>' . "\n",
                esc_attr((string) $basePrice),
                wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
        }

        // Generative Engine Optimization (GEO): Print FAQPage schema for AI search engines
        if ($post->post_type === 'helmet') {
            $faqSchema = $this->buildFaqSchemaHelmet($postId);
            if ($faqSchema !== null && !empty($faqSchema['mainEntity'])) {
                printf(
                    '<script type="application/ld+json" class="helmetsan-schema-faq">%s</script>' . "\n",
                    wp_json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                );
            }
        }
    }

    /**
     * Build Offer with priceValidUntil (required for rich results).
     *
     * @param array<string,mixed> $offerMeta best_offer_json decode or similar
     * @param float|string        $price     Fallback price if offer has none
     * @param string              $currency  e.g. USD
     * @param string              $url       Offer URL (e.g. permalink)
     * @return array<string,mixed>
     */
    private function buildOfferSchema(array $offerMeta, $price, string $currency, string $url): array
    {
        $offerPrice = isset($offerMeta['offer_price']) ? (float) $offerMeta['offer_price'] : (float) $price;
        $offerCurrency = ! empty($offerMeta['currency']) ? (string) $offerMeta['currency'] : $currency;
        $validUntil = $this->resolvePriceValidUntil($offerMeta);

        $offer = [
            '@type'          => 'Offer',
            'priceCurrency'  => $offerCurrency,
            'price'          => $offerPrice,
            'availability'   => 'https://schema.org/InStock',
            'url'            => $url,
            'priceValidUntil' => $validUntil,
            'shippingDetails' => [
                '@type' => 'OfferShippingDetails',
                'shippingRate' => [
                    '@type' => 'MonetaryAmount',
                    'value' => 0,
                    'currency' => $offerCurrency
                ],
                'deliveryTime' => [
                    '@type' => 'ShippingDeliveryTime',
                    'handlingTime' => [
                        '@type' => 'QuantitativeValue',
                        'minValue' => 0,
                        'maxValue' => 1,
                        'unitCode' => 'd'
                    ],
                    'transitTime' => [
                        '@type' => 'ShippingDeliveryTime',
                        'minValue' => 3,
                        'maxValue' => 7,
                        'unitCode' => 'd'
                    ]
                ]
            ],
            'hasMerchantReturnPolicy' => [
                '@type' => 'MerchantReturnPolicy',
                'applicableCountry' => 'US',
                'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
                'merchantReturnDays' => 30,
                'returnMethod' => 'https://schema.org/ReturnByMail',
                'returnFees' => 'https://schema.org/FreeReturn'
            ]
        ];

        return $offer;
    }

    private function resolvePriceValidUntil(array $offerMeta): string
    {
        $raw = (string) ($offerMeta['valid_until'] ?? '');
        if ($raw !== '' && strtotime($raw) !== false) {
            return gmdate('c', strtotime($raw));
        }

        return gmdate('c', strtotime('+' . self::OFFER_VALID_DAYS_DEFAULT . ' days'));
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buildProductSchemaHelmet(int $postId): ?array
    {
        $post = get_post($postId);
        if (! $post instanceof \WP_Post || $post->post_type !== 'helmet') {
            return null;
        }

        $lookupId = $postId;
        if (function_exists('pll_default_language') && function_exists('pll_get_post')) {
            $defaultLang = pll_default_language();
            $masterId = (int) pll_get_post($postId, $defaultLang);
            if ($masterId && $masterId > 0) {
                $lookupId = $masterId;
            }
        }

        $brandName = '';
        $brandId = (int) get_post_meta($lookupId, 'rel_brand', true);
        if ($brandId > 0) {
            $brandPost = get_post($brandId);
            if ($brandPost instanceof \WP_Post) {
                $brandName = (string) $brandPost->post_title;
            }
        }

        $price = get_post_meta($lookupId, 'price_retail_usd', true);
        $image = get_the_post_thumbnail_url($postId, 'full');
        if (!$image) {
            $image = get_the_post_thumbnail_url($lookupId, 'full');
        }
        $sharpRating = get_post_meta($lookupId, 'sharp_rating', true);
        $weight = get_post_meta($lookupId, 'spec_weight_g', true);
        $bestOfferRaw = (string) get_post_meta($lookupId, 'best_offer_json', true);
        $bestOffer = $bestOfferRaw !== '' ? json_decode($bestOfferRaw, true) : null;
        $bestOffer = is_array($bestOffer) ? $bestOffer : [];

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => (string) $post->post_title,
            'url'         => get_permalink($postId),
            'description' => wp_strip_all_tags((string) get_the_excerpt($postId)),
        ];

        if (is_string($image) && $image !== '') {
            $schema['image'] = [$image];
        }

        if ($brandName !== '') {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name'  => $brandName,
            ];
        }

        $geoPriceActive = false;
        if (function_exists('helmetsan_core')) {
            $perf = helmetsan_core()->config()->performanceConfig();
            $geoPriceActive = !empty($perf['enable_geoip_pricing']);
        }

        if ($geoPriceActive && function_exists('helmetsan_core')) {
            $best = helmetsan_core()->price()->getBestPrice($postId);
            if ($best !== null) {
                $offerPrice = $best->price;
                $offerCurrency = $best->currency;
            } else {
                $offerPrice = isset($bestOffer['offer_price']) ? (float) $bestOffer['offer_price'] : (is_numeric((string) $price) ? (float) $price : 0.0);
                $offerCurrency = ! empty($bestOffer['currency']) ? (string) $bestOffer['currency'] : 'USD';
            }
        } else {
            $offerPrice = isset($bestOffer['offer_price']) ? (float) $bestOffer['offer_price'] : (is_numeric((string) $price) ? (float) $price : 0.0);
            $offerCurrency = ! empty($bestOffer['currency']) ? (string) $bestOffer['currency'] : 'USD';
        }

        if ($offerPrice > 0) {
            $schema['offers'] = $this->buildOfferSchema(
                $bestOffer,
                $offerPrice,
                $offerCurrency,
                get_permalink($postId)
            );
        }

        $this->appendAggregateRatingAndReviews($schema, $lookupId);

        $additionalProps = [];
        if (is_numeric((string) $weight)) {
            $additionalProps[] = [
                '@type' => 'PropertyValue',
                'name'  => 'Weight',
                'value' => (int) $weight . ' g',
            ];
        }
        if (is_numeric((string) $sharpRating) && (int) $sharpRating > 0) {
            $additionalProps[] = [
                '@type' => 'PropertyValue',
                'name'  => 'SHARP safety rating',
                'value' => (string) $sharpRating,
            ];
        }
        $material = (string) get_post_meta($lookupId, 'spec_material', true);
        if ($material !== '') {
            $additionalProps[] = [
                '@type' => 'PropertyValue',
                'name'  => 'Shell Material',
                'value' => $material,
            ];
        }
        $headShape = (string) get_post_meta($lookupId, 'spec_head_shape', true);
        if ($headShape !== '') {
            $additionalProps[] = [
                '@type' => 'PropertyValue',
                'name'  => 'Head Shape Profile',
                'value' => $headShape,
            ];
        }
        $acousticDb = (string) get_post_meta($lookupId, 'spec_acoustic_db', true);
        if ($acousticDb !== '') {
            $additionalProps[] = [
                '@type' => 'PropertyValue',
                'name'  => 'Acoustic Sound Isolation',
                'value' => $acousticDb,
            ];
        }
        if ($additionalProps !== []) {
            $schema['additionalProperty'] = $additionalProps;
        }

        // Cross-entity compatibility relations
        $entityId = (string) get_post_meta($lookupId, '_helmet_unique_id', true);
        if ($entityId === '') {
            $entityId = (string) $post->post_name;
        }
        if (class_exists('Helmetsan_CompatibilityEngine')) {
            $compat = \Helmetsan_CompatibilityEngine::get_entity_compatibility('helmet', $entityId);
            $related = [];
            foreach (array_slice($compat['recommended_motorcycles'] ?? [], 0, 3) as $bm) {
                $related[] = [
                    '@type' => 'Motorcycle',
                    'name'  => $bm['title'] ?? '',
                    'disambiguatingDescription' => $bm['match_reason'] ?? 'Complementary riding posture match'
                ];
            }
            foreach (array_slice($compat['compatible_accessories'] ?? [], 0, 2) as $ac) {
                $related[] = [
                    '@type' => 'Product',
                    'name'  => $ac['title'] ?? '',
                    'disambiguatingDescription' => $ac['fitment'] ?? 'Verified accessory fitment'
                ];
            }
            if ($related !== []) {
                $schema['isRelatedTo'] = $related;
            }
        }

        // Generative Engine Optimization (GEO): Audience, Condition, & Speakable specifications
        $schema['itemCondition'] = 'https://schema.org/NewCondition';
        $schema['audience'] = [
            '@type'           => 'PeopleAudience',
            'suggestedGender' => 'unisex',
            'audienceType'    => 'Motorcycle Riders & Track Enthusiasts',
        ];
        $schema['speakable'] = [
            '@type'       => 'SpeakableSpecification',
            'cssSelector' => ['.hs-pdp-verdict-card', '#quick-verdict', '.hs-pdp-verdict-group', '.helmet-single__summary'],
        ];

        $schema = apply_filters('helmetsan_schema_product', $schema, $postId, 'helmet');

        return is_array($schema) ? $schema : null;
    }

    /**
     * Build automated FAQPage schema for Generative Engine Optimization (GEO).
     * Empowers AI Search Engines (ChatGPT, Perplexity, Gemini, Copilot) to cite Helmetsan direct answers.
     *
     * @param int $postId
     * @return array<string,mixed>|null
     */
    public function buildFaqSchemaHelmet(int $postId): ?array
    {
        $post = get_post($postId);
        if (! $post instanceof \WP_Post || $post->post_type !== 'helmet') {
            return null;
        }

        $lookupId = $postId;
        if (function_exists('pll_default_language') && function_exists('pll_get_post')) {
            $defaultLang = pll_default_language();
            $masterId = (int) pll_get_post($postId, $defaultLang);
            if ($masterId && $masterId > 0) {
                $lookupId = $masterId;
            }
        }

        $title = (string) $post->post_title;
        $brandName = '';
        $brandId = (int) get_post_meta($lookupId, 'rel_brand', true);
        if ($brandId > 0) {
            $brandPost = get_post($brandId);
            if ($brandPost instanceof \WP_Post) {
                $brandName = (string) $brandPost->post_title;
            }
        }

        $weight = get_post_meta($lookupId, 'spec_weight_g', true);
        $material = (string) get_post_meta($lookupId, 'spec_material', true);
        $headShape = (string) get_post_meta($lookupId, 'spec_head_shape', true);
        $sharpRating = get_post_meta($lookupId, 'sharp_rating', true);
        $safetyTerms = wp_get_post_terms($lookupId, 'safety_standard', ['fields' => 'names']);
        $standardsStr = !empty($safetyTerms) && !is_wp_error($safetyTerms) ? implode(', ', $safetyTerms) : 'ECE / DOT';

        $lang = 'en';
        if (function_exists('pll_get_post_language')) {
            $pllLang = pll_get_post_language($postId);
            if (!empty($pllLang)) {
                $lang = strtolower($pllLang);
            }
        } elseif (function_exists('pll_current_language')) {
            $curr = pll_current_language();
            if (!empty($curr)) {
                $lang = strtolower($curr);
            }
        }

        $faqs = [];

        if ($lang === 'de') {
            // German Localization
            $ans1 = "Der {$title}";
            if ($brandName !== '') {
                $ans1 .= " von {$brandName}";
            }
            $ans1 .= " ist nach den Motorrad-Sicherheitsnormen {$standardsStr} zertifiziert.";
            if ($material !== '') {
                $ans1 .= " Die Helmschale besteht aus fortschrittlichem {$material} für maximale Stoßdämpfung.";
            }
            if (is_numeric((string) $sharpRating)) {
                $ans1 .= " Im britischen SHARP-Sicherheitslabortest erreichte er {$sharpRating} von 5 Sternen.";
            }
            $faqs[] = [
                '@type' => 'Question',
                'name'  => "Welche Sicherheitsstandards und Schalenmaterialien verwendet der {$title}?",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $ans1,
                ],
            ];

            $ans2 = "Der {$title}";
            if (is_numeric((string) $weight)) {
                $ans2 .= " wiegt ca. {$weight} Gramm.";
            }
            if ($headShape !== '') {
                $ans2 .= " Er besitzt ein ergonomisches {$headShape}-Kopfformprofil für hohen Langstreckenkomfort.";
            } else {
                $ans2 .= " Er ist mit einem Intermediate-Oval-Profil für die Mehrheit europäischer Fahrer optimiert.";
            }
            $faqs[] = [
                '@type' => 'Question',
                'name'  => "Was wiegt der {$title} und welches Kopfform-Profil hat er?",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $ans2,
                ],
            ];

            $faqs[] = [
                '@type' => 'Question',
                'name'  => "Wo kann ich verifizierte Preise und Bezugsquellen für den {$title} finden?",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => "Auf Helmetsan können Sie tagesaktuelle internationale Amazon-Preise, regionale ECE 22.06 vs DOT Zertifizierungen und verifizierte Fahrer-Bewertungen direkt vergleichen.",
                ],
            ];
        } elseif ($lang === 'fr') {
            // French Localization
            $ans1 = "Le {$title}";
            if ($brandName !== '') {
                $ans1 .= " par {$brandName}";
            }
            $ans1 .= " est certifié conforme aux normes de sécurité moto {$standardsStr}.";
            if ($material !== '') {
                $ans1 .= " Sa calotte est fabriquée en {$material} pour une dissipation optimale des chocs.";
            }
            if (is_numeric((string) $sharpRating)) {
                $ans1 .= " Il a obtenu {$sharpRating} étoiles aux tests de sécurité officiels SHARP.";
            }
            $faqs[] = [
                '@type' => 'Question',
                'name'  => "Quelles sont les normes de sécurité et les matériaux de calotte du {$title} ?",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $ans1,
                ],
            ];

            $ans2 = "Le {$title}";
            if (is_numeric((string) $weight)) {
                $ans2 .= " pèse environ {$weight} grammes.";
            }
            if ($headShape !== '') {
                $ans2 .= " Il présente un profil morphologique {$headShape} pour minimiser les points de pression.";
            } else {
                $ans2 .= " Il est conçu avec un profil ovale intermédiaire adapté à la grande majorité des motards.";
            }
            $faqs[] = [
                '@type' => 'Question',
                'name'  => "Quel est le poids et la morphologie de tête du {$title} ?",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $ans2,
                ],
            ];

            $faqs[] = [
                '@type' => 'Question',
                'name'  => "Où trouver les prix vérifiés et la disponibilité du {$title} ?",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => "Vous pouvez comparer en temps réel les tarifs internationaux Amazon, la conformité aux normes régionales (ECE 22.06 / DOT) et les avis motards certifiés sur Helmetsan.",
                ],
            ];
        } elseif ($lang === 'zh') {
            // Chinese Localization
            $ans1 = ($brandName !== '' ? "{$brandName} 的 " : '') . "{$title}";
            $ans1 .= " 已通过 {$standardsStr} 摩托车安全认证标准。";
            if ($material !== '') {
                $ans1 .= " 头盔外壳采用高级 {$material} 材质，具备出色的抗冲击与能量分散性能。";
            }
            if (is_numeric((string) $sharpRating)) {
                $ans1 .= " 该头盔在英国 SHARP 安全测试中获得 {$sharpRating} 星评级。";
            }
            $faqs[] = [
                '@type' => 'Question',
                'name'  => "{$title} 具备哪些安全认证标准？采用什么外壳材质？",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $ans1,
                ],
            ];

            $ans2 = "{$title}";
            if (is_numeric((string) $weight)) {
                $ans2 .= " 的实测重量约为 {$weight} 克。";
            }
            if ($headShape !== '') {
                $ans2 .= " 采用符合人体工程学的 {$headShape} 头型设计，有效减少头部压迫感。";
            } else {
                $ans2 .= " 采用中等椭圆（Intermediate Oval）头型轮廓，契合绝大多数公路与赛道骑士。";
            }
            $faqs[] = [
                '@type' => 'Question',
                'name'  => "{$title} 的重量和头型贴合度如何？",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $ans2,
                ],
            ];

            $faqs[] = [
                '@type' => 'Question',
                'name'  => "在哪里可以查询 {$title} 的正品价格和全球购买渠道？",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => "您可以在 Helmetsan 实时对比亚马逊全球各大站点（美洲、欧洲、日本）的最新官方售价、安全合规认证以及车友真实实测评价。",
                ],
            ];
        } else {
            // English (Default)
            $ans1 = "The {$title}";
            if ($brandName !== '') {
                $ans1 .= " by {$brandName}";
            }
            $ans1 .= " is certified to {$standardsStr} motorcycle safety standards.";
            if ($material !== '') {
                $ans1 .= " It is engineered with an advanced {$material} shell structure for high-velocity impact absorption.";
            }
            if (is_numeric((string) $sharpRating)) {
                $ans1 .= " It achieved a {$sharpRating}-star rating in UK SHARP laboratory safety testing.";
            }
            $faqs[] = [
                '@type' => 'Question',
                'name'  => "What safety standards and shell materials does the {$title} use?",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $ans1,
                ],
            ];

            $ans2 = "The {$title}";
            if (is_numeric((string) $weight)) {
                $lbs = round(((float)$weight) / 453.592, 2);
                $ans2 .= " has an approximate weight of {$weight} grams (~{$lbs} lbs).";
            }
            if ($headShape !== '') {
                $ans2 .= " It features an {$headShape} head shape profile designed for rider comfort and reduced pressure points.";
            } else {
                $ans2 .= " It is designed with an intermediate oval profile accommodating the majority of street and track riders.";
            }
            $faqs[] = [
                '@type' => 'Question',
                'name'  => "What is the weight and head shape profile of the {$title}?",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $ans2,
                ],
            ];

            $faqs[] = [
                '@type' => 'Question',
                'name'  => "Where can I find verified pricing, regional certifications, and retailers for {$title}?",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => "You can compare real-time international Amazon marketplace pricing, regional certification availability (DOT FMVSS 218 vs ECE 22.06), and verified rider reviews directly on Helmetsan.",
                ],
            ];
        }

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $faqs,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buildProductSchemaAccessory(int $postId): ?array
    {
        $post = get_post($postId);
        if (! $post instanceof \WP_Post || $post->post_type !== 'accessory') {
            return null;
        }

        $lookupId = $postId;
        if (function_exists('pll_default_language') && function_exists('pll_get_post')) {
            $defaultLang = pll_default_language();
            $masterId = (int) pll_get_post($postId, $defaultLang);
            if ($masterId && $masterId > 0) {
                $lookupId = $masterId;
            }
        }

        $priceJson = (string) get_post_meta($lookupId, 'price_json', true);
        $priceData = $priceJson !== '' ? json_decode($priceJson, true) : null;
        $price = is_array($priceData) && isset($priceData['value']) ? (float) $priceData['value'] : 0.0;
        $currency = is_array($priceData) && ! empty($priceData['currency']) ? (string) $priceData['currency'] : 'USD';
        $image = get_the_post_thumbnail_url($postId, 'full');
        if (!$image) {
            $image = get_the_post_thumbnail_url($lookupId, 'full');
        }

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => (string) $post->post_title,
            'url'         => get_permalink($postId),
            'description' => wp_strip_all_tags((string) get_the_excerpt($postId)),
        ];

        if (is_string($image) && $image !== '') {
            $schema['image'] = [$image];
        } else {
            // Fallback for accessories missing images
            $schema['image'] = [home_url('/wp-content/themes/helmetsan-theme/assets/images/placeholder-accessory.jpg')];
        }

        // Brand Inheritance
        $brandName = '';
        $brandId = (int) get_post_meta($lookupId, 'rel_brand', true);
        if ($brandId > 0) {
            $brandPost = get_post($brandId);
            if ($brandPost instanceof \WP_Post) {
                $brandName = (string) $brandPost->post_title;
            }
        }

        if ($brandName !== '') {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name'  => $brandName,
            ];
        }

        // Category
        $subcategory = (string) get_post_meta($lookupId, 'accessory_subcategory', true);
        $accType = (string) get_post_meta($lookupId, 'accessory_type', true);
        $schema['category'] = $subcategory !== '' ? $subcategory : ($accType !== '' ? $accType : 'Motorcycle Helmet Accessories');

        $geoPriceActive = false;
        if (function_exists('helmetsan_core')) {
            $perf = helmetsan_core()->config()->performanceConfig();
            $geoPriceActive = !empty($perf['enable_geoip_pricing']);
        }

        if ($geoPriceActive && $price > 0 && function_exists('helmetsan_core')) {
            $visitorCurrency = helmetsan_core()->geo()->getCurrency();
            $price = helmetsan_core()->exchangeRates()->convert($price, $currency, $visitorCurrency);
            $price = helmetsan_core()->exchangeRates()->applyVat($price, helmetsan_core()->geo()->getCountry());
            $price = helmetsan_core()->exchangeRates()->charmRound($price, $visitorCurrency);
            $currency = $visitorCurrency;
        }

        if ($price > 0) {
            $schema['offers'] = $this->buildOfferSchema([], $price, $currency, get_permalink($postId));
        }

        $this->appendAggregateRatingAndReviews($schema, $lookupId);

        // Additional Technical Specifications (Physics Matrix)
        $entityId = (string) get_post_meta($lookupId, '_accessory_unique_id', true);
        if ($entityId === '') {
            $entityId = (string) $post->post_name;
        }

        $physicsRaw = get_post_meta($lookupId, 'accessory_physics_matrix', true);
        $accJson = [];
        if (empty($physicsRaw)) {
            $srcFile = (string) get_post_meta($lookupId, '_source_file', true);
            if ($srcFile !== '' && file_exists($srcFile)) {
                $accJson = json_decode((string) file_get_contents($srcFile), true) ?: [];
                $physicsRaw = $accJson['accessory_physics_matrix'] ?? null;
            } elseif (defined('WP_CONTENT_DIR')) {
                $candidatePath = WP_CONTENT_DIR . '/uploads/helmetsan-data/accessories/' . $entityId . '.json';
                if (file_exists($candidatePath)) {
                    $accJson = json_decode((string) file_get_contents($candidatePath), true) ?: [];
                    $physicsRaw = $accJson['accessory_physics_matrix'] ?? null;
                }
            }
        }
        $physics = is_array($physicsRaw) ? $physicsRaw : (is_string($physicsRaw) && $physicsRaw !== '' ? json_decode($physicsRaw, true) : []);

        // Brand fallback from JSON if missing
        if (!isset($schema['brand']) && !empty($accJson['brand'])) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name'  => (string) $accJson['brand'],
            ];
        }
        
        $additionalProps = [];
        if (!empty($physics) && is_array($physics)) {
            $labelMap = [
                'noise_reduction_snr_db'          => 'Noise Reduction Rating',
                'acoustic_filter_profile'         => 'Acoustic Filter Profile',
                'mesh_network_range_m'            => 'Intercom Mesh Network Range',
                'battery_life_operating_hours'    => 'Battery Operating Life',
                'waterproof_ip_rating'            => 'Waterproof / Weather Rating',
                'installation_difficulty'         => 'Installation Rating',
                'optical_tint_vlt_percentage'     => 'Optical Visible Light Transmission (VLT)',
                'optical_clarity_class'           => 'Optical Clarity Standard',
                'uv_protection_rating'            => 'UV Radiation Protection',
                'sensor_deceleration_detection'   => 'Deceleration Sensor Technology',
                'optical_luminance_output'        => 'Optical Luminance Output',
                'thermal_regulation_range'        => 'Operating Temperature Range',
                'antimicrobial_hygiene'           => 'Antimicrobial Treatment',
                'mechanical_tensile_rating'       => 'Mechanical Tensile Load Rating',
                'chemical_safety_certification'   => 'Chemical & Shell Safety',
                'audio_driver_specification'      => 'Acoustic / Driver Specification',
            ];
            foreach ($labelMap as $key => $label) {
                if (!empty($physics[$key])) {
                    $additionalProps[] = [
                        '@type' => 'PropertyValue',
                        'name'  => $label,
                        'value' => (string) $physics[$key],
                    ];
                }
            }
        }
        if ($additionalProps !== []) {
            $schema['additionalProperty'] = $additionalProps;
        }

        // Schema.org isAccessoryOrSparePartFor Linking
        $partFor = [];
        if (class_exists('Helmetsan_CompatibilityEngine')) {
            $compat = \Helmetsan_CompatibilityEngine::get_entity_compatibility('accessory', $entityId);
            foreach (array_slice($compat['compatible_helmets'] ?? [], 0, 4) as $ch) {
                $partFor[] = [
                    '@type' => 'Product',
                    'name'  => (string) ($ch['title'] ?? ucwords(str_replace(['_', '-'], ' ', $ch['id'] ?? ''))),
                    'disambiguatingDescription' => $ch['fitment'] ?? 'Verified accessory fitment'
                ];
            }
        }
        if ($partFor !== []) {
            $schema['isAccessoryOrSparePartFor'] = $partFor;
        }

        $schema = apply_filters('helmetsan_schema_product', $schema, $postId, 'accessory');

        return is_array($schema) ? $schema : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buildProductSchemaMotorcycle(int $postId): ?array
    {
        $post = get_post($postId);
        if (! $post instanceof \WP_Post || $post->post_type !== 'motorcycle') {
            return null;
        }

        $lookupId = $postId;
        if (function_exists('pll_default_language') && function_exists('pll_get_post')) {
            $defaultLang = pll_default_language();
            $masterId = (int) pll_get_post($postId, $defaultLang);
            if ($masterId && $masterId > 0) {
                $lookupId = $masterId;
            }
        }

        $image = get_the_post_thumbnail_url($postId, 'full');
        if (!$image) {
            $image = get_the_post_thumbnail_url($lookupId, 'full');
        }
        $make = (string) get_post_meta($lookupId, 'motorcycle_make', true);
        $model = (string) get_post_meta($lookupId, 'motorcycle_model', true);
        $engineCc = (int) get_post_meta($lookupId, 'motorcycle_engine_cc', true);
        $topSpeed = (int) get_post_meta($lookupId, 'motorcycle_top_speed_kmh', true);
        $category = (string) get_post_meta($lookupId, 'motorcycle_category', true);
        $fuelCap = (float) get_post_meta($lookupId, 'motorcycle_fuel_capacity_l', true);

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => ['Vehicle', 'Motorcycle'],
            'name'        => (string) $post->post_title,
            'url'         => get_permalink($postId),
            'description' => wp_strip_all_tags((string) get_the_excerpt($postId)),
            'seatingCapacity' => 2,
        ];

        if ($category !== '') {
            $schema['bodyType'] = $category;
        }

        if (is_string($image) && $image !== '') {
            $schema['image'] = [$image];
        }

        if ($make !== '' || $model !== '') {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name'  => $make !== '' ? $make : $model,
            ];
        }

        if ($engineCc > 0) {
            $schema['vehicleEngine'] = [
                '@type' => 'EngineSpecification',
                'engineDisplacement' => [
                    '@type'    => 'QuantitativeValue',
                    'value'    => $engineCc,
                    'unitCode' => 'CMQ'
                ]
            ];
        }

        if ($topSpeed > 0) {
            $schema['speed'] = [
                '@type'    => 'QuantitativeValue',
                'value'    => $topSpeed,
                'unitCode' => 'KMH'
            ];
        }

        if ($fuelCap > 0) {
            $schema['fuelCapacity'] = [
                '@type'    => 'QuantitativeValue',
                'value'    => $fuelCap,
                'unitCode' => 'LTR'
            ];
        }

        // Additional Chassis & Engine Specifications
        $motoProps = [];
        if ($category !== '') {
            $motoProps[] = [
                '@type' => 'PropertyValue',
                'name'  => 'Motorcycle Classification',
                'value' => $category,
            ];
        }
        if ($engineCc > 0) {
            $motoProps[] = [
                '@type' => 'PropertyValue',
                'name'  => 'Engine Displacement',
                'value' => $engineCc . ' cc',
            ];
        }
        if ($topSpeed > 0) {
            $motoProps[] = [
                '@type' => 'PropertyValue',
                'name'  => 'Top Speed Rating',
                'value' => $topSpeed . ' km/h',
            ];
        }
        if ($fuelCap > 0) {
            $motoProps[] = [
                '@type' => 'PropertyValue',
                'name'  => 'Fuel Tank Capacity',
                'value' => $fuelCap . ' L',
            ];
        }
        if ($motoProps !== []) {
            $schema['additionalProperty'] = $motoProps;
        }

        // Cross-entity compatible helmets & accessories relation
        $entityId = (string) get_post_meta($lookupId, '_motorcycle_unique_id', true);
        if ($entityId === '') {
            $entityId = (string) $post->post_name;
        }
        if (class_exists('Helmetsan_CompatibilityEngine')) {
            $compat = \Helmetsan_CompatibilityEngine::get_entity_compatibility('motorcycle', $entityId);
            $related = [];
            foreach (array_slice($compat['recommended_helmets'] ?? [], 0, 4) as $rh) {
                $related[] = [
                    '@type' => 'Product',
                    'name'  => $rh['title'] ?? '',
                    'disambiguatingDescription' => $rh['match_reason'] ?? 'Recommended aerodynamic helmet pairing'
                ];
            }
            foreach (array_slice($compat['recommended_accessories'] ?? [], 0, 3) as $ra) {
                $related[] = [
                    '@type' => 'Product',
                    'name'  => $ra['title'] ?? '',
                    'disambiguatingDescription' => $ra['reason'] ?? 'Recommended motorcycle cockpit gear'
                ];
            }
            if ($related !== []) {
                $schema['isRelatedTo'] = $related;
            }
        }

        $this->appendAggregateRatingAndReviews($schema, $lookupId);

        $schema = apply_filters('helmetsan_schema_product', $schema, $postId, 'motorcycle');

        return is_array($schema) ? $schema : null;
    }

    private function appendAggregateRatingAndReviews(array &$schema, int $postId): void
    {
        $ratingValue = (float) get_post_meta($postId, '_wc_average_rating', true);
        $reviewCount = (int) get_post_meta($postId, '_wc_review_count', true);
        
        // Add AggregateRating if there are reviews
        if ($reviewCount > 0) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => round($ratingValue, 1),
                'reviewCount' => $reviewCount,
                'bestRating'  => 5,
            ];
        }

        // Add up to 10 latest reviews
        $comments = $this->reviewService->getReviews($postId, 10, 0, 'newest');
        if (!empty($comments)) {
            $list = [];
            foreach ($comments as $comment) {
                $rating = (int) $comment['rating'];
                if ($rating < 1) {
                    continue;
                }

                $revItem = [
                    '@type'         => 'Review',
                    'author'        => ['@type' => 'Person', 'name' => $comment['author_name'] ?: 'Anonymous'],
                    'datePublished' => gmdate('c', strtotime($comment['created_at'])),
                    'reviewBody'    => wp_strip_all_tags($comment['content']),
                    'reviewRating'  => [
                        '@type'       => 'Rating',
                        'ratingValue' => $rating,
                        'bestRating'  => 5,
                    ],
                ];

                if (!empty($comment['pros']) && is_array($comment['pros'])) {
                    $posElements = [];
                    foreach (array_values($comment['pros']) as $idx => $p) {
                        $cleanP = wp_strip_all_tags((string)$p);
                        if ($cleanP !== '') {
                            $posElements[] = [
                                '@type'    => 'ListItem',
                                'position' => $idx + 1,
                                'name'     => $cleanP,
                            ];
                        }
                    }
                    if ($posElements !== []) {
                        $revItem['positiveNotes'] = [
                            '@type'           => 'ItemList',
                            'itemListElement' => $posElements,
                        ];
                    }
                }

                if (!empty($comment['cons']) && is_array($comment['cons'])) {
                    $negElements = [];
                    foreach (array_values($comment['cons']) as $idx => $c) {
                        $cleanC = wp_strip_all_tags((string)$c);
                        if ($cleanC !== '') {
                            $negElements[] = [
                                '@type'    => 'ListItem',
                                'position' => $idx + 1,
                                'name'     => $cleanC,
                            ];
                        }
                    }
                    if ($negElements !== []) {
                        $revItem['negativeNotes'] = [
                            '@type'           => 'ItemList',
                            'itemListElement' => $negElements,
                        ];
                    }
                }

                $list[] = $revItem;
            }
            if ($list !== []) {
                $schema['review'] = $list;
            }
        }
    }

    public function printBreadcrumbListSchema(): void
    {
        $items = $this->buildBreadcrumbItems();
        if ($items === []) {
            return;
        }

        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildBreadcrumbItems(): array
    {
        $out = [];
        $pos = 1;

        $out[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => get_bloginfo('name'),
            'item'     => home_url('/'),
        ];

        if (is_singular(['helmet', 'accessory', 'motorcycle', 'brand'])) {
            $post = get_queried_object();
            if ($post instanceof \WP_Post) {
                $type = $post->post_type;
                $archiveSlug = $type === 'brand' ? 'brands' : $type . 's';
                $archiveUrl = get_post_type_archive_link($type) ?: home_url('/' . $archiveSlug . '/');
                $out[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos++,
                    'name'     => ucfirst($archiveSlug),
                    'item'     => $archiveUrl,
                ];
                $out[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos,
                    'name'     => get_the_title($post),
                    'item'     => get_permalink($post),
                ];
            }
        } elseif (is_post_type_archive('helmet')) {
            $out[] = [
                '@type'    => 'ListItem',
                'position' => $pos,
                'name'     => 'Helmets',
                'item'     => get_post_type_archive_link('helmet') ?: home_url('/helmets/'),
            ];
        } elseif (is_post_type_archive('accessory')) {
            $out[] = [
                '@type'    => 'ListItem',
                'position' => $pos,
                'name'     => 'Accessories',
                'item'     => get_post_type_archive_link('accessory') ?: home_url('/accessories/'),
            ];
        } elseif (is_post_type_archive('motorcycle')) {
            $out[] = [
                '@type'    => 'ListItem',
                'position' => $pos,
                'name'     => 'Motorcycles',
                'item'     => get_post_type_archive_link('motorcycle') ?: home_url('/motorcycles/'),
            ];
        } elseif (is_singular()) {
            $post = get_queried_object();
            if ($post instanceof \WP_Post) {
                $out[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos,
                    'name'     => get_the_title($post),
                    'item'     => get_permalink($post),
                ];
            }
        }

        return $out;
    }

    public function printWebSiteSchema(): void
    {
        if (! is_front_page()) {
            return;
        }

        $schema = [
            '@context'      => 'https://schema.org',
            '@type'         => 'WebSite',
            'name'          => get_bloginfo('name'),
            'url'           => home_url('/'),
            'description'   => get_bloginfo('description'),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => home_url('/?s={search_term_string}'),
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        $schema = apply_filters('helmetsan_schema_website', $schema);
        if (! is_array($schema)) {
            return;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    public function printOrganizationSchema(): void
    {
        if (! is_front_page()) {
            return;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'    => get_bloginfo('name'),
            'url'     => home_url('/'),
            'description' => get_bloginfo('description'),
        ];

        $schema = apply_filters('helmetsan_schema_organization', $schema);
        if (! is_array($schema)) {
            return;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    /**
     * ItemList for post type archives (helmets, accessories, motorcycles) for list/carousel rich results.
     * Respects main query pagination so positions and item counts reflect true catalog depth.
     */
    public function printItemListSchema(): void
    {
        global $wp_query;

        $postTypes = ['helmet', 'accessory', 'motorcycle'];
        $current = null;
        foreach ($postTypes as $pt) {
            if (is_post_type_archive($pt)) {
                $current = $pt;
                break;
            }
        }
        if ($current === null) {
            return;
        }

        $paged = max(1, (int) (get_query_var('paged') ?: (get_query_var('page') ?: 1)));

        $postIds = [];
        $totalItems = 0;
        $ppp = 40;

        if (isset($wp_query) && $wp_query->is_main_query() && $wp_query->have_posts()) {
            $totalItems = (int) $wp_query->found_posts;
            $ppp = (int) ($wp_query->get('posts_per_page') ?: 40);
            foreach ($wp_query->posts as $post) {
                $postIds[] = $post instanceof \WP_Post ? $post->ID : (int) $post;
            }
        } else {
            $query = new \WP_Query([
                'post_type'      => $current,
                'post_status'    => 'publish',
                'posts_per_page' => 40,
                'paged'          => $paged,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'fields'         => 'ids',
            ]);
            $totalItems = (int) $query->found_posts;
            $postIds = array_map('intval', $query->posts);
            wp_reset_postdata();
        }

        if (empty($postIds)) {
            return;
        }

        $startPosition = (($paged - 1) * $ppp) + 1;
        $items = [];
        foreach ($postIds as $idx => $id) {
            $url = get_permalink($id);
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $startPosition + $idx,
                'name'     => get_the_title($id),
                'url'      => $url !== false ? $url : '',
            ];
        }

        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'name'            => ucfirst($current) . ' catalog',
            'numberOfItems'   => $totalItems > 0 ? $totalItems : count($items),
            'itemListElement' => $items,
        ];

        $schema = apply_filters('helmetsan_schema_item_list', $schema, $current);
        if (! is_array($schema)) {
            return;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    /**
     * CollectionPage schema for brand hubs (/brands/{slug}/) establishing topical cluster authority.
     */
    public function printCollectionPageSchema(): void
    {
        if (! is_singular('brand')) {
            return;
        }

        $brandId = (int) get_queried_object_id();
        if ($brandId <= 0) {
            return;
        }

        $brandPost = get_post($brandId);
        if (! $brandPost instanceof \WP_Post) {
            return;
        }

        $brandUrl = (string) get_permalink($brandId);
        if ($brandUrl === '') {
            return;
        }

        $cacheKey = 'brand_collection_schema_' . $brandId;
        $schema = class_exists(ObjectCacheService::class)
            ? ObjectCacheService::remember($cacheKey, ObjectCacheService::GROUP_SCHEMA, function () use ($brandId, $brandPost, $brandUrl): array {
                return $this->buildCollectionPageSchema($brandId, $brandPost, $brandUrl);
            }, 86400)
            : $this->buildCollectionPageSchema($brandId, $brandPost, $brandUrl);

        $schema = apply_filters('helmetsan_schema_collection_page', $schema, $brandId);
        if (! is_array($schema) || empty($schema)) {
            return;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    /**
     * Build CollectionPage schema array for a brand.
     *
     * @return array<string, mixed>
     */
    private function buildCollectionPageSchema(int $brandId, \WP_Post $brandPost, string $brandUrl): array
    {
        $helmetPosts = get_posts([
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'post_parent'    => 0,
            'posts_per_page' => 50,
            'meta_query'     => [
                [
                    'key'     => 'brand',
                    'value'   => $brandId,
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $itemListElements = [];
        $hasParts = [];

        foreach ($helmetPosts as $idx => $helmet) {
            $hUrl = (string) get_permalink($helmet->ID);
            if ($hUrl === '') {
                continue;
            }

            $productPart = [
                '@type' => 'Product',
                'name'  => $helmet->post_title,
                'url'   => $hUrl,
            ];

            $hasParts[] = $productPart;
            $itemListElements[] = [
                '@type'    => 'ListItem',
                'position' => $idx + 1,
                'item'     => $productPart,
            ];
        }

        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'CollectionPage',
            'name'        => sprintf('%s Helmets Collection', $brandPost->post_title),
            'url'         => $brandUrl,
            'description' => sprintf('Official verified helmet specifications, laboratory noise tests, and safety homologations for %s.', $brandPost->post_title),
            'mainEntity'  => [
                '@type'           => 'ItemList',
                'name'            => sprintf('%s Helmet Models', $brandPost->post_title),
                'numberOfItems'   => count($itemListElements),
                'itemListElement' => $itemListElements,
            ],
            'hasPart'     => $hasParts,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function audit(int $limit = 200, int $offset = 0): array
    {
        $query = new \WP_Query([
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => max(1, $limit),
            'offset'         => max(0, $offset),
            'fields'         => 'ids',
        ]);

        $checked = 0;
        $valid   = 0;
        $issues  = [];

        foreach ($query->posts as $postId) {
            $postId = (int) $postId;
            if ($postId <= 0) {
                continue;
            }
            $checked++;

            $missing = [];
            if (get_the_title($postId) === '') {
                $missing[] = 'title';
            }
            if (get_permalink($postId) === false) {
                $missing[] = 'permalink';
            }
            if (! is_numeric((string) get_post_meta($postId, 'price_retail_usd', true))) {
                $missing[] = 'price_retail_usd';
            }
            if (get_post_meta($postId, 'rel_brand', true) === '') {
                $missing[] = 'rel_brand';
            }
            if (get_the_post_thumbnail_url($postId, 'full') === false) {
                $missing[] = 'featured_image';
            }

            if ($missing === []) {
                $valid++;
                continue;
            }

            $issues[] = [
                'post_id' => $postId,
                'title'   => get_the_title($postId),
                'missing' => $missing,
            ];
        }

        wp_reset_postdata();

        return [
            'ok'      => true,
            'checked' => $checked,
            'valid'   => $valid,
            'invalid' => max(0, $checked - $valid),
            'issues'  => $issues,
            'limit'   => $limit,
            'offset'  => $offset,
        ];
    }
}

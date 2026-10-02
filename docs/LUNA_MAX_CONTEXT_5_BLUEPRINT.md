# Helmetsan Context 5 Production Blueprint

The implementation should be split into five independently deployable layers:

1. **Localization runtime**
2. **Canonical product and entity data**
3. **Geo-pricing**
4. **Structured-data rendering**
5. **Compatibility scoring**

All calculations should use canonical SI values internally:

- Circumference: centimeters
- Speed: km/h
- Sound: dB(A)
- Angles: degrees
- Price: base currency minor-unit-safe decimal strings
- Dates: ISO 8601 internally, localized only at presentation time

Do not store localized strings, converted sizes, or converted prices as the canonical product value.

---

# 1. Context 5.1 — Deep Native Localization

## 1.1 Locale model

Use these locales:

```php
const HELMETSAN_LOCALES = [
    'en', 'de', 'es', 'fr', 'it',
    'ja', 'nl', 'pl', 'pt', 'zh',
];
```

Use full locale tags for formatting:

```php
const HELMETSAN_LOCALE_TAGS = [
    'en' => 'en-US',
    'de' => 'de-DE',
    'es' => 'es-ES',
    'fr' => 'fr-FR',
    'it' => 'it-IT',
    'ja' => 'ja-JP',
    'nl' => 'nl-NL',
    'pl' => 'pl-PL',
    'pt' => 'pt-BR',
    'zh' => 'zh-CN',
];
```

The WordPress locale should remain the source of truth:

```php
function hs_current_language(): string
{
    $locale = function_exists('determine_locale')
        ? determine_locale()
        : get_locale();

    $language = strtolower(substr((string) $locale, 0, 2));

    return in_array($language, HELMETSAN_LOCALES, true)
        ? $language
        : 'en';
}
```

---

## 1.2 High-efficiency gettext cache

The required runtime caches are:

- `hs_t`: translated string cache
- `hs_e`: escaped translated string cache

The cache key must include:

- language
- text domain
- source string
- context

Otherwise, strings from different domains or contexts can collide.

```php
final class Helmetsan_Localization
{
    /**
     * Raw translated string cache.
     *
     * @var array<string, string>
     */
    private static array $hs_t = [];

    /**
     * Escaped translated string cache.
     *
     * @var array<string, string>
     */
    private static array $hs_e = [];

    /**
     * Prevent accidental cache growth on long-running workers.
     */
    private const MAX_CACHE_ENTRIES = 8192;

    private static function key(
        string $source,
        string $domain,
        ?string $context,
        string $locale
    ): string {
        return sha1(implode("\x1F", [
            $locale,
            $domain,
            $context ?? '',
            $source,
        ]));
    }

    public static function t(
        string $source,
        ?string $domain = null,
        ?string $context = null
    ): string {
        $domain ??= 'helmetsan';
        $locale = hs_current_language();
        $key = self::key($source, $domain, $context, $locale);

        if (isset(self::$hs_t[$key])) {
            return self::$hs_t[$key];
        }

        if ($context !== null && function_exists('_x')) {
            $translated = _x($source, $context, $domain);
        } elseif (function_exists('__')) {
            $translated = __($source, $domain);
        } else {
            $translated = $source;
        }

        self::boundInsert(self::$hs_t, $key, (string) $translated);

        return (string) $translated;
    }

    public static function e(
        string $source,
        ?string $domain = null,
        ?string $context = null
    ): string {
        $domain ??= 'helmetsan';
        $locale = hs_current_language();
        $key = self::key($source, $domain, $context, $locale);

        if (isset(self::$hs_e[$key])) {
            return self::$hs_e[$key];
        }

        $translated = self::t($source, $domain, $context);
        $escaped = esc_html($translated);

        self::boundInsert(self::$hs_e, $key, $escaped);

        return $escaped;
    }

    public static function reset(): void
    {
        self::$hs_t = [];
        self::$hs_e = [];
    }

    /**
     * @param array<string, string> $cache
     */
    private static function boundInsert(
        array &$cache,
        string $key,
        string $value
    ): void {
        if (count($cache) >= self::MAX_CACHE_ENTRIES) {
            array_shift($cache);
        }

        $cache[$key] = $value;
    }
}
```

Convenience wrappers:

```php
function hs_t(
    string $source,
    ?string $domain = null,
    ?string $context = null
): string {
    return Helmetsan_Localization::t($source, $domain, $context);
}

function hs_e(
    string $source,
    ?string $domain = null,
    ?string $context = null
): string {
    return Helmetsan_Localization::e($source, $domain, $context);
}
```

### Usage in archive loops

Do this:

```php
$label_weight = hs_e('Weight');
$label_price  = hs_e('Price');
$label_view   = hs_e('View helmet');

foreach ($helmets as $helmet) {
    echo '<span class="label">' . $label_weight . '</span>';
    echo '<span class="label">' . $label_price . '</span>';
    echo '<a href="' . esc_url($helmet['url']) . '">';
    echo $label_view;
    echo '</a>';
}
```

Avoid this:

```php
foreach ($helmets as $helmet) {
    echo esc_html__('Weight', 'helmetsan');
}
```

The latter repeatedly executes the translation lookup path for every card.

---

## 1.3 Required string wrapping

### `front-page.php`

Wrap all user-visible strings, including:

```php
hs_e('Find your next helmet')
hs_e('Shop helmets')
hs_e('Shop accessories')
hs_e('Compare helmets')
hs_e('Recommended for your riding position')
hs_e('Featured helmets')
hs_e('Popular accessories')
hs_e('New arrivals')
hs_e('Best sellers')
hs_e('Read the buying guide')
hs_e('Explore all helmets')
hs_e('Explore all accessories')
hs_e('Free shipping')
hs_e('Secure checkout')
hs_e('Verified customer reviews')
hs_e('Available in multiple sizes')
hs_e('Ships to your country')
```

### `archive-helmet.php`

```php
hs_e('Helmets')
hs_e('Helmet')
hs_e('Showing %1$s–%2$s of %3$s helmets')
hs_e('Sort by')
hs_e('Relevance')
hs_e('Price: low to high')
hs_e('Price: high to low')
hs_e('Newest')
hs_e('Customer rating')
hs_e('Filter')
hs_e('Clear filters')
hs_e('Riding style')
hs_e('Safety certification')
hs_e('Shell material')
hs_e('Weight')
hs_e('Noise rating')
hs_e('Available sizes')
hs_e('From')
hs_e('Compare')
hs_e('View details')
hs_e('Add to comparison')
hs_e('In stock')
hs_e('Out of stock')
hs_e('Limited stock')
hs_e('Notify me when available')
```

Pluralized text must use WordPress plural APIs:

```php
printf(
    esc_html(
        _n(
            '%s helmet',
            '%s helmets',
            $count,
            'helmetsan'
        )
    ),
    number_format_i18n($count)
);
```

For contextual translations:

```php
echo hs_e('Touring', 'helmetsan', 'motorcycle riding style');
```

### `archive-accessory.php`

```php
hs_e('Accessories')
hs_e('Accessory')
hs_e('Helmet visors')
hs_e('Pinlock inserts')
hs_e('Replacement liners')
hs_e('Communication systems')
hs_e('Helmet bags')
hs_e('Intercom compatibility')
hs_e('Fits this helmet')
hs_e('Compatible models')
hs_e('View accessory')
hs_e('Add to cart')
hs_e('Select options')
```

### `page-comparison.php`

```php
hs_e('Helmet comparison')
hs_e('Compare helmets')
hs_e('Remove')
hs_e('Add helmet')
hs_e('Specification')
hs_e('Safety')
hs_e('Certification')
hs_e('Weight')
hs_e('Shell sizes')
hs_e('Ventilation')
hs_e('Visor')
hs_e('Noise dampening')
hs_e('Aerodynamic stability')
hs_e('Chin curtain')
hs_e('Price')
hs_e('Availability')
hs_e('Best match')
hs_e('Your riding position')
hs_e('No helmets selected')
hs_e('Select at least two helmets to compare')
```

Never concatenate translated fragments:

```php
// Incorrect.
echo hs_e('Weight') . ': ' . $weight . ' g';

// Better.
printf(
    esc_html__('%s: %s g', 'helmetsan'),
    esc_html__('Weight', 'helmetsan'),
    esc_html($weight)
);
```

The second form still has limitations for languages with different word order. Prefer complete translatable sentences:

```php
printf(
    esc_html__('Weight: %s g', 'helmetsan'),
    esc_html($weight)
);
```

---

## 1.4 Number and date formatting

Use `NumberFormatter`, not manual comma replacement.

```php
final class Helmetsan_Formatter
{
    public static function localeTag(?string $language = null): string
    {
        $language ??= hs_current_language();

        return HELMETSAN_LOCALE_TAGS[$language] ?? 'en-US';
    }

    public static function number(
        int|float $value,
        int $minFraction = 0,
        int $maxFraction = 2
    ): string {
        $formatter = new NumberFormatter(
            self::localeTag(),
            NumberFormatter::DECIMAL
        );

        $formatter->setAttribute(
            NumberFormatter::MIN_FRACTION_DIGITS,
            $minFraction
        );

        $formatter->setAttribute(
            NumberFormatter::MAX_FRACTION_DIGITS,
            $maxFraction
        );

        return $formatter->format($value) ?: (string) $value;
    }

    public static function integer(int|float $value): string
    {
        return self::number($value, 0, 0);
    }

    public static function date(
        DateTimeInterface|string $date,
        int $dateType = IntlDateFormatter::LONG
    ): string {
        $dateTime = $date instanceof DateTimeInterface
            ? $date
            : new DateTimeImmutable($date, new DateTimeZone('UTC'));

        $formatter = new IntlDateFormatter(
            self::localeTag(),
            $dateType,
            IntlDateFormatter::NONE,
            wp_timezone_string() ?: 'UTC'
        );

        return $formatter->format($dateTime) ?: $dateTime->format('Y-m-d');
    }
}
```

Example:

```php
echo esc_html(Helmetsan_Formatter::number(1234.5, 1, 1));
// en-US: 1,234.5
// de-DE: 1.234,5
// fr-FR: 1 234,5
```

---

## 1.5 Helmet sizing conversion

Helmet sizing must not be treated as an exact universal conversion. Shell geometry and manufacturer grading vary. Store the measured circumference as canonical and provide a rounded reference size.

### Conversion

```php
function hs_cm_to_us_hat_size(float $circumferenceCm): float
{
    if ($circumferenceCm <= 0) {
        throw new InvalidArgumentException('Circumference must be positive.');
    }

    // US hat size = circumference in inches / pi.
    $inches = $circumferenceCm / 2.54;

    // Helmet/hat sizes are normally expressed in eighths.
    return round(($inches / M_PI) * 8.0) / 8.0;
}
```

Formatter for values such as `6 5/8`:

```php
function hs_format_us_hat_size(float $size): string
{
    $whole = (int) floor($size);
    $eighths = (int) round(($size - $whole) * 8);

    if ($eighths === 0) {
        return (string) $whole;
    }

    if ($eighths === 8) {
        return (string) ($whole + 1);
    }

    $fractionMap = [
        1 => '1/8',
        2 => '1/4',
        3 => '3/8',
        4 => '1/2',
        5 => '5/8',
        6 => '3/4',
        7 => '7/8',
    ];

    return $whole . ' ' . $fractionMap[$eighths];
}
```

Examples:

```php
hs_format_us_hat_size(hs_cm_to_us_hat_size(53.0)); // 6 5/8
hs_format_us_hat_size(hs_cm_to_us_hat_size(56.0)); // 7
hs_format_us_hat_size(hs_cm_to_us_hat_size(58.0)); // 7 1/4
hs_format_us_hat_size(hs_cm_to_us_hat_size(61.0)); // 7 5/8
hs_format_us_hat_size(hs_cm_to_us_hat_size(64.0)); // 8
```

For product pages, expose:

```text
Head circumference: 53–64 cm
US reference: 6 5/8–8
```

Add a disclaimer:

```php
echo hs_e(
    'Reference conversion only. Always follow the manufacturer sizing chart.'
);
```

---

# 2. Context 5.2 — GEO and Schema.org Architecture

## 2.1 Canonical graph design

Each product page should emit one JSON-LD object containing an `@graph`.

Recommended nodes:

1. `WebSite`
2. `Organization`
3. `WebPage`
4. `Product`
5. `AggregateOffer`
6. `Brand`
7. `Motorcycle`
8. `PropertyValue` safety nodes
9. `PropertyValue` acoustic node
10. `BreadcrumbList`
11. Optional `FAQPage`

Use stable URLs as `@id` values:

```text
https://helmetsan.com/helmet/example/#product
https://helmetsan.com/motorcycles/yamaha-r1/#motorcycle
https://helmetsan.com/helmet/example/#certification-ece-2206
https://helmetsan.com/helmet/example/#acoustics
```

---

## 2.2 Product JSON-LD example

Important distinction:

- `Product.offers` is appropriate for a product.
- `AggregateOffer` is useful when multiple offers or regional offers exist.
- Google Merchant Center requires accurate price, currency, availability, and product identifiers.
- Do not expose a converted regional currency in Schema.org unless that currency is the actual checkout currency for that page/request.

```php
function hs_product_jsonld(array $product, array $pricing): array
{
    $url = esc_url_raw($product['url']);
    $productId = $url . '#product';

    $graph = [
        [
            '@type' => 'WebSite',
            '@id' => home_url('/#website'),
            'url' => home_url('/'),
            'name' => get_bloginfo('name'),
            'inLanguage' => hs_current_language(),
        ],
        [
            '@type' => 'Organization',
            '@id' => home_url('/#organization'),
            'name' => 'Helmetsan',
            'url' => home_url('/'),
            'logo' => [
                '@type
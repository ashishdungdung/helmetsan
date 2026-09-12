<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('pll_get_term_language') || ! function_exists('pll_set_term_language') || ! function_exists('pll_save_term_translations')) {
    echo "Polylang is not active.\n";
    exit(1);
}

$taxonomy = isset($args[0]) ? sanitize_key($args[0]) : '';
$target_lang = isset($args[1]) ? sanitize_key($args[1]) : '';
$limit = isset($args[2]) ? (int) $args[2] : 50;

if (! in_array($taxonomy, ['helmet_type', 'feature_tag', 'certification', 'accessory_category'], true)) {
    echo "Invalid taxonomy. Supported: helmet_type, feature_tag, certification, accessory_category\n";
    exit(1);
}

$langNames = [
    'de' => 'German',
    'zh' => 'Simplified Chinese',
    'ja' => 'Japanese',
    'fr' => 'French',
    'ar' => 'Arabic',
];

if (! array_key_exists($target_lang, $langNames)) {
    echo "Invalid target language. Supported: de, zh, ja, fr, ar\n";
    exit(1);
}

$lang_name = $langNames[$target_lang];

echo "Translating taxonomy '$taxonomy' to $lang_name (Limit: $limit)...\n";

$terms = get_terms([
    'taxonomy'   => $taxonomy,
    'hide_empty' => false,
    'lang'       => 'en',
]);

if (empty($terms) || is_wp_error($terms)) {
    echo "No English terms found for $taxonomy.\n";
    exit;
}

if (! function_exists('helmetsan_core') || ! method_exists(helmetsan_core(), 'config')) {
    echo "Helmetsan core not initialized.\n";
    exit(1);
}

// Access the AI service directly from helmetsan_core
$ai = null;
if (function_exists('helmetsan_core')) {
    $plugin = helmetsan_core();
    // Use reflection or standard access to get private/protected properties if needed,
    // or instantiate a direct provider registry.
    // Wait, let's inspect helmetsan_core() to see if we can get $aiService.
    // In Plugin.php:
    // private AiService $aiService;
    // But does it have a getter? Let's check Plugin.php for any getters.
    // It has: getSearchService(), config(), price(), helmets(), mediaService(), marketplace(), priceHistory(), geo(), router(), revenue(), ingestion(), sync(), brands(), reviews(), accessories(), defaultImages(), motorcycles()
    // It does not have getAiService() or similar.
    // But wait! We can instantiate AiService ourselves since all config and classes are loaded!
    // Let's do that to avoid access issues.
    $config = $plugin->config();
    $providerRegistry = new \Helmetsan\Core\AI\ProviderRegistry($config);
    $heals = new \Helmetsan\Core\AI\HealRepository();
    $ai = new \Helmetsan\Core\AI\AiService($providerRegistry, $heals);
}

if (! $ai) {
    echo "AiService could not be constructed.\n";
    exit(1);
}

$count = 0;
foreach ($terms as $term) {
    $en_id = $term->term_id;
    
    // Check if translation already exists
    $existing_id = pll_get_term($en_id, $target_lang);
    if ($existing_id) {
        continue;
    }
    
    $name = $term->name;
    $desc = $term->description;
    
    // Translate name
    $name_prompt = "You are a professional translator for a premium motorcycle gear catalog. Translate the following motorcycle taxonomy term name into $lang_name. Keep model/standard names untranslated if appropriate. Output ONLY the translated name:\n\n" . $name;
    $translated_name = $ai->generate($name_prompt, 0);
    if (empty($translated_name)) {
        $translated_name = $name;
    }
    $translated_name = trim($translated_name, "\"' ");
    
    // Translate description
    $translated_desc = '';
    if (! empty($desc)) {
        $desc_prompt = "You are a professional translator for a premium motorcycle gear catalog. Translate the following category/tag description into $lang_name. Output ONLY the translated description:\n\n" . $desc;
        $translated_desc = $ai->generate($desc_prompt, 0);
        if (empty($translated_desc)) {
            $translated_desc = $desc;
        }
        $translated_desc = trim($translated_desc, "\"' ");
    }
    
    // Create term
    $slug = sanitize_title($translated_name);
    $check = get_term_by('slug', $slug, $taxonomy);
    if ($check) {
        $slug .= '-' . $target_lang;
    }
    
    $new_term = wp_insert_term($translated_name, $taxonomy, [
        'description' => $translated_desc,
        'slug'        => $slug,
    ]);
    
    if (is_wp_error($new_term)) {
        echo "Error creating term '$translated_name': " . $new_term->get_error_message() . "\n";
        continue;
    }
    
    $target_id = (int) $new_term['term_id'];
    
    // Set language in Polylang
    pll_set_term_language($target_id, $target_lang);
    
    // Link translation
    $translations = pll_get_term_translations($en_id);
    $translations['en'] = $en_id;
    $translations[$target_lang] = $target_id;
    pll_save_term_translations($translations);
    
    $count++;
    echo "[$count] Translated term: '$name' -> '$translated_name'\n";
    if ($count >= $limit) {
        break;
    }
}

echo "Successfully translated $count terms for taxonomy '$taxonomy' to $target_lang.\n";

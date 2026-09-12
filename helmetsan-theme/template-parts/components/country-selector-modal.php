<?php
/**
 * Country & Currency Selector Modal Component.
 *
 * Provides a clean, dedicated region & currency selection modal
 * completely decoupled from the language/locale switcher.
 *
 * @package HelmetsanTheme
 */

declare(strict_types=1);

$visitorCc = 'IN';
if (function_exists('helmetsan_core')) {
    $visitorCc = helmetsan_core()->geo()->getCountry();
}

// Full supported regions & countries dynamically grouped from single source of truth
$supported = function_exists('helmetsan_get_supported_countries') ? helmetsan_get_supported_countries() : [];

$regionLabels = [
    'APAC' => 'Asia-Pacific',
    'NA'   => 'North America',
    'EU'   => 'Europe & UK',
    'ME'   => 'Middle East & Latin America',
    'SA'   => 'Middle East & Latin America',
    'AF'   => 'Africa',
];

$countryRegions = [];
foreach ($supported as $cc => $data) {
    if ($cc === 'UK') {
        continue; // Display GB in UI to avoid duplicates
    }
    $regionKey = $data['region'] ?? 'APAC';
    $groupName = $regionLabels[$regionKey] ?? 'Other';
    $countryRegions[$groupName][$cc] = $data;
}
?>

<!-- Country & Currency Modal -->
<div id="hsCountryModal" class="hs-country-modal" role="dialog" aria-modal="true" aria-labelledby="hsCountryModalTitle" hidden>
    <div class="hs-country-modal__backdrop" data-close-modal></div>
    <div class="hs-country-modal__dialog">
        <div class="hs-country-modal__header">
            <div>
                <h2 id="hsCountryModalTitle" class="hs-country-modal__title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="hs-inline-icon">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                    </svg>
                    <?php esc_html_e('Select Country & Currency', 'helmetsan-theme'); ?>
                </h2>
                <p class="hs-country-modal__desc">
                    <?php esc_html_e('Prices, taxes, and local safety standards automatically adapt to your chosen location.', 'helmetsan-theme'); ?>
                </p>
            </div>
            <button type="button" class="hs-country-modal__close" data-close-modal aria-label="<?php esc_attr_e('Close modal', 'helmetsan-theme'); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <!-- Live Detected Connection Box -->
        <div class="hs-country-detected-box" id="hsDetectedCountryBox" style="display: none;">
            <div class="hs-country-detected-box__info">
                <span class="hs-country-detected-box__badge"><?php esc_html_e('Detected IP', 'helmetsan-theme'); ?></span>
                <span class="hs-country-detected-box__text" id="hsDetectedCountryText"></span>
            </div>
            <button type="button" class="hs-country-detected-box__btn" id="hsUseDetectedBtn">
                <?php esc_html_e('Use Detected', 'helmetsan-theme'); ?>
            </button>
        </div>

        <!-- Search Input -->
        <div class="hs-country-modal__search-wrap">
            <svg class="hs-country-modal__search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="search" id="hsCountrySearchInput" class="hs-country-modal__search" placeholder="<?php esc_attr_e('Search country or currency (e.g. India, EUR, ₹)...', 'helmetsan-theme'); ?>" autocomplete="off" />
        </div>

        <!-- Country Selection List -->
        <div class="hs-country-modal__body" id="hsCountryListBody" role="listbox" aria-label="<?php esc_attr_e('Select country and currency', 'helmetsan-theme'); ?>">
            <?php foreach ($countryRegions as $regionName => $countries) : ?>
                <div class="hs-country-region-group">
                    <div class="hs-country-region-group__title"><?php echo esc_html($regionName); ?></div>
                    <div class="hs-country-grid">
                        <?php foreach ($countries as $cc => $data) : 
                            $isSelected = ($cc === $visitorCc);
                        ?>
                            <button type="button" 
                                class="hs-country-card <?php echo $isSelected ? 'is-active' : ''; ?>" 
                                role="option"
                                aria-selected="<?php echo $isSelected ? 'true' : 'false'; ?>"
                                data-country-code="<?php echo esc_attr($cc); ?>"
                                data-country-name="<?php echo esc_attr($data['name']); ?>"
                                data-currency="<?php echo esc_attr($data['currency']); ?>"
                                data-symbol="<?php echo esc_attr($data['symbol']); ?>"
                                data-flag="<?php echo esc_attr($data['flag']); ?>">
                                <span class="hs-country-card__flag" aria-hidden="true"><?php echo esc_html($data['flag']); ?></span>
                                <div class="hs-country-card__meta">
                                    <span class="hs-country-card__name"><?php echo esc_html($data['name']); ?></span>
                                    <span class="hs-country-card__curr"><?php echo esc_html($data['currency']); ?> (<?php echo esc_html($data['symbol']); ?>)</span>
                                </div>
                                <span class="hs-country-card__check" aria-hidden="true">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

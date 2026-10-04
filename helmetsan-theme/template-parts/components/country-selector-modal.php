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

$visitorCc = function_exists('helmetsan_get_visitor_country') ? helmetsan_get_visitor_country() : 'US';
$visitorCurrency = function_exists('helmetsan_get_visitor_currency') ? helmetsan_get_visitor_currency() : 'USD';

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

$currenciesList = [
    'USD' => ['name' => 'US Dollar',             'symbol' => '$',     'flag' => '🇺🇸'],
    'EUR' => ['name' => 'Euro',                  'symbol' => '€',     'flag' => '🇪🇺'],
    'GBP' => ['name' => 'British Pound',         'symbol' => '£',     'flag' => '🇬🇧'],
    'INR' => ['name' => 'Indian Rupee',          'symbol' => '₹',     'flag' => '🇮🇳'],
    'JPY' => ['name' => 'Japanese Yen',          'symbol' => '¥',     'flag' => '🇯🇵'],
    'CAD' => ['name' => 'Canadian Dollar',       'symbol' => 'CA$',   'flag' => '🇨🇦'],
    'AUD' => ['name' => 'Australian Dollar',     'symbol' => 'A$',    'flag' => '🇦🇺'],
    'CHF' => ['name' => 'Swiss Franc',           'symbol' => 'CHF',   'flag' => '🇨🇭'],
    'AED' => ['name' => 'UAE Dirham',            'symbol' => 'AED',   'flag' => '🇦🇪'],
    'SAR' => ['name' => 'Saudi Riyal',           'symbol' => 'SAR',   'flag' => '🇸🇦'],
    'SGD' => ['name' => 'Singapore Dollar',      'symbol' => 'S$',    'flag' => '🇸🇬'],
    'NZD' => ['name' => 'New Zealand Dollar',    'symbol' => 'NZ$',   'flag' => '🇳🇿'],
    'MXN' => ['name' => 'Mexican Peso',          'symbol' => 'MX$',   'flag' => '🇲🇽'],
    'BRL' => ['name' => 'Brazilian Real',        'symbol' => 'R$',    'flag' => '🇧🇷'],
    'PLN' => ['name' => 'Polish Zloty',          'symbol' => 'zł',    'flag' => '🇵🇱'],
    'SEK' => ['name' => 'Swedish Krona',         'symbol' => 'kr',    'flag' => '🇸🇪'],
    'NOK' => ['name' => 'Norwegian Krone',       'symbol' => 'kr',    'flag' => '🇳🇴'],
    'KRW' => ['name' => 'South Korean Won',      'symbol' => '₩',     'flag' => '🇰🇷'],
    'TRY' => ['name' => 'Turkish Lira',          'symbol' => '₺',     'flag' => '🇹🇷'],
    'NGN' => ['name' => 'Nigerian Naira',        'symbol' => '₦',     'flag' => '🇳🇬'],
    'KES' => ['name' => 'Kenyan Shilling',       'symbol' => 'KSh',   'flag' => '🇰🇪'],
    'EGP' => ['name' => 'Egyptian Pound',        'symbol' => 'E£',    'flag' => '🇪🇬'],
    'MAD' => ['name' => 'Moroccan Dirham',       'symbol' => 'MAD',   'flag' => '🇲🇦'],
    'GHS' => ['name' => 'Ghanaian Cedi',         'symbol' => 'GH₵',   'flag' => '🇬🇭'],
    'UGX' => ['name' => 'Ugandan Shilling',      'symbol' => 'USh',   'flag' => '🇺🇬'],
    'TZS' => ['name' => 'Tanzanian Shilling',    'symbol' => 'TSh',   'flag' => '🇹🇿'],
];
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
                    <?php esc_html_e('Select Region & Currency', 'helmetsan-theme'); ?>
                </h2>
                <p class="hs-country-modal__desc">
                    <?php esc_html_e('Choose your regional compliance standard and independent display currency.', 'helmetsan-theme'); ?>
                </p>
            </div>
            <button type="button" class="hs-country-modal__close" data-close-modal aria-label="<?php esc_attr_e('Close modal', 'helmetsan-theme'); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <!-- Mode Switcher Tabs: Country / Region vs Currency -->
        <div class="hs-modal-tabs" role="tablist">
            <button type="button" class="hs-modal-tab is-active" id="hsTabCountryBtn" role="tab" aria-selected="true" aria-controls="hsCountryTabPanel">
                <span class="hs-modal-tab__icon">🌍</span>
                <span><?php esc_html_e('Country / Region', 'helmetsan-theme'); ?></span>
                <span class="hs-modal-tab__badge" id="hsActiveCountryBadge"><?php echo esc_html($visitorCc); ?></span>
            </button>
            <button type="button" class="hs-modal-tab" id="hsTabCurrencyBtn" role="tab" aria-selected="false" aria-controls="hsCurrencyTabPanel">
                <span class="hs-modal-tab__icon">💱</span>
                <span><?php esc_html_e('Currency', 'helmetsan-theme'); ?></span>
                <span class="hs-modal-tab__badge" id="hsActiveCurrencyBadge"><?php echo esc_html($visitorCurrency); ?></span>
            </button>
        </div>

        <!-- Tab Panel 1: Country / Region -->
        <div class="hs-modal-tab-panel" id="hsCountryTabPanel" role="tabpanel" aria-labelledby="hsTabCountryBtn">
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

            <!-- Country Search Input -->
            <div class="hs-country-modal__search-wrap">
                <svg class="hs-country-modal__search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="search" id="hsCountrySearchInput" class="hs-country-modal__search" placeholder="<?php esc_attr_e('Search country (e.g. United States, Germany, India, Japan)...', 'helmetsan-theme'); ?>" autocomplete="off" />
            </div>

            <!-- Country Selection List -->
            <div class="hs-country-modal__body" id="hsCountryListBody" role="listbox" aria-label="<?php esc_attr_e('Select country and region', 'helmetsan-theme'); ?>">
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

        <!-- Tab Panel 2: Independent Currency Selection -->
        <div class="hs-modal-tab-panel" id="hsCurrencyTabPanel" role="tabpanel" aria-labelledby="hsTabCurrencyBtn" style="display: none;">
            <!-- Currency Search Input -->
            <div class="hs-country-modal__search-wrap">
                <svg class="hs-country-modal__search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="search" id="hsCurrencySearchInput" class="hs-country-modal__search" placeholder="<?php esc_attr_e('Search currency (e.g. USD, EUR, GBP, INR, ¥, €)...', 'helmetsan-theme'); ?>" autocomplete="off" />
            </div>

            <!-- Currency Selection Grid -->
            <div class="hs-country-modal__body" id="hsCurrencyListBody" role="listbox" aria-label="<?php esc_attr_e('Select currency', 'helmetsan-theme'); ?>">
                <div class="hs-currency-grid">
                    <?php foreach ($currenciesList as $currCode => $cMeta) : 
                        $isCurrActive = ($currCode === $visitorCurrency);
                    ?>
                        <button type="button"
                            class="hs-currency-card <?php echo $isCurrActive ? 'is-active' : ''; ?>"
                            role="option"
                            aria-selected="<?php echo $isCurrActive ? 'true' : 'false'; ?>"
                            data-currency-code="<?php echo esc_attr($currCode); ?>"
                            data-currency-symbol="<?php echo esc_attr($cMeta['symbol']); ?>"
                            data-currency-name="<?php echo esc_attr($cMeta['name']); ?>"
                            data-currency-flag="<?php echo esc_attr($cMeta['flag']); ?>">
                            <span class="hs-currency-card__flag" aria-hidden="true"><?php echo esc_html($cMeta['flag']); ?></span>
                            <div class="hs-currency-card__meta">
                                <span class="hs-currency-card__code"><?php echo esc_html($currCode); ?> <small style="opacity:0.8;">(<?php echo esc_html($cMeta['symbol']); ?>)</small></span>
                                <span class="hs-currency-card__name"><?php echo esc_html($cMeta['name']); ?></span>
                            </div>
                            <span class="hs-currency-card__check" aria-hidden="true">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Modal Footer Summary -->
        <div class="hs-modal-selection-summary">
            <div class="hs-modal-selection-summary__text">
                <span><?php esc_html_e('Active Selection:', 'helmetsan-theme'); ?></span>
                <strong id="hsSummaryCountry"><?php echo esc_html($supported[$visitorCc]['name'] ?? 'United States'); ?></strong>
                <span class="hs-summary-dot">•</span>
                <strong id="hsSummaryCurrency"><?php echo esc_html($visitorCurrency . ' (' . ($currenciesList[$visitorCurrency]['symbol'] ?? '$') . ')'); ?></strong>
            </div>
            <button type="button" class="hs-moto-btn hs-moto-btn--primary" data-close-modal style="padding:0.4rem 1rem; font-size:0.85rem;">
                <?php esc_html_e('Done', 'helmetsan-theme'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.hs-modal-tabs {
    display: flex;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem 0.25rem;
    border-bottom: 1px solid var(--hs-border);
    background: var(--hs-surface);
}
.hs-modal-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0.5rem 1rem;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    color: var(--hs-text-muted);
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.hs-modal-tab:hover {
    color: var(--hs-text);
}
.hs-modal-tab.is-active {
    color: var(--hs-accent);
    border-bottom-color: var(--hs-accent);
}
.hs-modal-tab__badge {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid var(--hs-border);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 700;
}
.hs-modal-tab.is-active .hs-modal-tab__badge {
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.3);
    color: var(--hs-accent);
}
.hs-currency-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.75rem;
}
.hs-currency-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0.75rem 1rem;
    background: var(--hs-surface);
    border: 1px solid var(--hs-border);
    border-radius: 8px;
    text-align: left;
    cursor: pointer;
    transition: all 0.2s ease;
    color: var(--hs-text);
}
.hs-currency-card:hover {
    border-color: var(--hs-accent);
    transform: translateY(-1px);
}
.hs-currency-card.is-active {
    border-color: var(--hs-accent);
    background: rgba(239, 68, 68, 0.08);
}
.hs-currency-card__flag {
    font-size: 1.35rem;
}
.hs-currency-card__meta {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.hs-currency-card__code {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--hs-text);
}
.hs-currency-card__name {
    font-size: 0.75rem;
    color: var(--hs-text-muted);
}
.hs-currency-card__check {
    display: none;
    color: var(--hs-accent);
}
.hs-currency-card.is-active .hs-currency-card__check {
    display: flex;
}
.hs-modal-selection-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.9rem 1.5rem;
    border-top: 1px solid var(--hs-border);
    background: var(--hs-surface);
    font-size: 0.85rem;
}
.hs-modal-selection-summary__text {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--hs-text-muted);
}
.hs-modal-selection-summary__text strong {
    color: var(--hs-text);
}
.hs-summary-dot {
    opacity: 0.4;
}
</style>

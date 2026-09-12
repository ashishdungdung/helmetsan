(function() {
    'use strict';

    let exchangeRates = null;
    let detectedEdgeCountry = null;

    const FALLBACK_RATES = {
        'USD': 1.0,
        'EUR': 0.92,
        'GBP': 0.79,
        'INR': 86.5,
        'JPY': 155.0,
        'CAD': 1.38,
        'AUD': 1.52,
        'MXN': 19.5,
        'BRL': 5.6,
        'PLN': 4.0,
        'AED': 3.67,
        'NGN': 1500.0,
        'KES': 129.0,
        'EGP': 49.0,
        'MAD': 9.8,
        'GHS': 15.5,
        'UGX': 3700.0,
        'TZS': 2600.0,
        'CHF': 0.88,
        'NZD': 1.65,
        'SGD': 1.34,
        'SEK': 10.4,
        'NOK': 10.6,
        'SAR': 3.75,
        'KRW': 1350.0
    };

    async function fetchRates() {
        if (exchangeRates) return { rates: exchangeRates, detected_country: detectedEdgeCountry };
        try {
            const response = await fetch('/wp-json/helmetsan/v1/edge/rates');
            if (response.ok) {
                const data = await response.json();
                exchangeRates = data.rates || FALLBACK_RATES;
                detectedEdgeCountry = data.detected_country || null;
                return { rates: exchangeRates, detected_country: detectedEdgeCountry };
            }
        } catch (e) {
            console.error('Failed to fetch exchange rates for client-side conversion:', e);
        }
        exchangeRates = FALLBACK_RATES;
        return { rates: FALLBACK_RATES, detected_country: null };
    }

    // Comprehensive Country metadata dictionary
    const countryData = {
        'IN': { name: 'India',                currency: 'INR', symbol: '₹',    flag: '🇮🇳', region: 'APAC' },
        'US': { name: 'United States',        currency: 'USD', symbol: '$',    flag: '🇺🇸', region: 'NA' },
        'GB': { name: 'United Kingdom',       currency: 'GBP', symbol: '£',    flag: '🇬🇧', region: 'EU' },
        'UK': { name: 'United Kingdom',       currency: 'GBP', symbol: '£',    flag: '🇬🇧', region: 'EU' },
        'DE': { name: 'Germany',              currency: 'EUR', symbol: '€',    flag: '🇩🇪', region: 'EU' },
        'FR': { name: 'France',               currency: 'EUR', symbol: '€',    flag: '🇫🇷', region: 'EU' },
        'IT': { name: 'Italy',                currency: 'EUR', symbol: '€',    flag: '🇮🇹', region: 'EU' },
        'ES': { name: 'Spain',                currency: 'EUR', symbol: '€',    flag: '🇪🇸', region: 'EU' },
        'NL': { name: 'Netherlands',          currency: 'EUR', symbol: '€',    flag: '🇳🇱', region: 'EU' },
        'PL': { name: 'Poland',               currency: 'PLN', symbol: 'zł',   flag: '🇵🇱', region: 'EU' },
        'BE': { name: 'Belgium',              currency: 'EUR', symbol: '€',    flag: '🇧🇪', region: 'EU' },
        'CH': { name: 'Switzerland',          currency: 'CHF', symbol: 'CHF ', flag: '🇨🇭', region: 'EU' },
        'SE': { name: 'Sweden',               currency: 'SEK', symbol: ' kr',  flag: '🇸🇪', region: 'EU' },
        'NO': { name: 'Norway',               currency: 'NOK', symbol: ' kr',  flag: '🇳🇴', region: 'EU' },
        'CA': { name: 'Canada',               currency: 'CAD', symbol: 'CA$',  flag: '🇨🇦', region: 'NA' },
        'MX': { name: 'Mexico',               currency: 'MXN', symbol: 'MX$',  flag: '🇲🇽', region: 'NA' },
        'JP': { name: 'Japan',                currency: 'JPY', symbol: '¥',    flag: '🇯🇵', region: 'APAC' },
        'AU': { name: 'Australia',            currency: 'AUD', symbol: 'A$',   flag: '🇦🇺', region: 'APAC' },
        'NZ': { name: 'New Zealand',          currency: 'NZD', symbol: 'NZ$',  flag: '🇳🇿', region: 'APAC' },
        'SG': { name: 'Singapore',            currency: 'SGD', symbol: 'S$',   flag: '🇸🇬', region: 'APAC' },
        'KR': { name: 'South Korea',          currency: 'KRW', symbol: '₩',    flag: '🇰🇷', region: 'APAC' },
        'AE': { name: 'United Arab Emirates', currency: 'AED', symbol: 'AED ', flag: '🇦🇪', region: 'ME' },
        'SA': { name: 'Saudi Arabia',         currency: 'SAR', symbol: 'SAR ', flag: '🇸🇦', region: 'ME' },
        'BR': { name: 'Brazil',               currency: 'BRL', symbol: 'R$',   flag: '🇧🇷', region: 'SA' },
        'NG': { name: 'Nigeria',              currency: 'NGN', symbol: '₦',    flag: '🇳🇬', region: 'AF' },
        'KE': { name: 'Kenya',                currency: 'KES', symbol: 'KSh ', flag: '🇰🇪', region: 'AF' },
        'EG': { name: 'Egypt',                currency: 'EGP', symbol: 'E£',   flag: '🇪🇬', region: 'AF' },
        'MA': { name: 'Morocco',              currency: 'MAD', symbol: 'MAD',  flag: '🇲🇦', region: 'AF' },
        'GH': { name: 'Ghana',                currency: 'GHS', symbol: 'GH₵',  flag: '🇬🇭', region: 'AF' },
        'UG': { name: 'Uganda',               currency: 'UGX', symbol: 'USh ', flag: '🇺🇬', region: 'AF' },
        'TZ': { name: 'Tanzania',             currency: 'TZS', symbol: 'TSh ', flag: '🇹🇿', region: 'AF' },
        'IE': { name: 'Ireland',              currency: 'EUR', symbol: '€',    flag: '🇮🇪', region: 'EU' },
        'TR': { name: 'Turkey',               currency: 'TRY', symbol: '₺',    flag: '🇹🇷', region: 'EU' }
    };

    function isVatCountry(country) {
        const vatCountries = [
            'DE', 'FR', 'IT', 'ES', 'GB', 'UK', 'PL', 'AT', 'BE', 'BG', 'CY', 'CZ', 'DK', 'EE', 'FI',
            'GR', 'HR', 'HU', 'IE', 'LT', 'LU', 'LV', 'MT', 'NL', 'PT', 'RO', 'SE', 'SI', 'SK', 'CH', 'NO'
        ];
        return vatCountries.includes(country);
    }

    function applyVat(amount, country) {
        const vatRates = {
            'DE': 1.19, 'FR': 1.20, 'IT': 1.22, 'ES': 1.21, 'GB': 1.20, 'UK': 1.20, 'PL': 1.23,
            'CH': 1.081, 'NO': 1.25,
            'AT': 1.20, 'BE': 1.21, 'BG': 1.20, 'CY': 1.19, 'CZ': 1.21, 'DK': 1.25, 'EE': 1.22,
            'FI': 1.24, 'GR': 1.24, 'HR': 1.25, 'HU': 1.27, 'IE': 1.23, 'LT': 1.21, 'LU': 1.17,
            'LV': 1.21, 'MT': 1.18, 'NL': 1.21, 'PT': 1.23, 'RO': 1.19, 'SE': 1.25, 'SI': 1.22, 'SK': 1.20
        };
        const rate = vatRates[country] || 1.0;
        return amount * rate;
    }

    function charmRound(amount, currency) {
        if (amount <= 0.0) return 0.0;
        if (amount < 5.0) return amount;
        const decimalCurrencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'MXN', 'BRL', 'PLN', 'CHF', 'SEK', 'NOK', 'NZD', 'SGD', 'SAR'];
        if (decimalCurrencies.includes(currency)) {
            const rounded = Math.round(amount);
            if (currency === 'EUR' || currency === 'GBP' || currency === 'CHF') {
                return rounded - 0.01;
            }
            return rounded - 0.05;
        }
        if (amount < 1000.0) {
            return Math.round(amount / 10.0) * 10.0 - 1.0;
        } else {
            return Math.round(amount / 100.0) * 100.0 - 10.0;
        }
    }

    function formatCurrency(amount, currency) {
        const CURRENCIES = {
            'USD': { symbol: '$',    position: 'before', decimals: 2, thousands: ',', decimal: '.' },
            'EUR': { symbol: '€',    position: 'before', decimals: 2, thousands: '.', decimal: ',' },
            'GBP': { symbol: '£',    position: 'before', decimals: 2, thousands: ',', decimal: '.' },
            'CHF': { symbol: 'CHF ', position: 'before', decimals: 2, thousands: "'", decimal: '.' },
            'SEK': { symbol: ' kr',  position: 'after',  decimals: 2, thousands: ' ', decimal: ',' },
            'NOK': { symbol: ' kr',  position: 'after',  decimals: 2, thousands: ' ', decimal: ',' },
            'INR': { symbol: '₹',    position: 'before', decimals: 0, thousands: ',', decimal: '.' },
            'JPY': { symbol: '¥',    position: 'before', decimals: 0, thousands: ',', decimal: '.' },
            'AUD': { symbol: 'A$',   position: 'before', decimals: 2, thousands: ',', decimal: '.' },
            'NZD': { symbol: 'NZ$',  position: 'before', decimals: 2, thousands: ',', decimal: '.' },
            'CAD': { symbol: 'CA$',  position: 'before', decimals: 2, thousands: ',', decimal: '.' },
            'SGD': { symbol: 'S$',   position: 'before', decimals: 2, thousands: ',', decimal: '.' },
            'MXN': { symbol: 'MX$',  position: 'before', decimals: 2, thousands: ',', decimal: '.' },
            'BRL': { symbol: 'R$',   position: 'before', decimals: 2, thousands: '.', decimal: ',' },
            'PLN': { symbol: 'zł',   position: 'after',  decimals: 2, thousands: ' ', decimal: ',' },
            'AED': { symbol: 'AED ', position: 'before', decimals: 2, thousands: ',', decimal: '.' },
            'SAR': { symbol: 'SAR ', position: 'before', decimals: 2, thousands: ',', decimal: '.' },
            'NGN': { symbol: '₦',    position: 'before', decimals: 0, thousands: ',', decimal: '.' },
            'KES': { symbol: 'KSh ', position: 'before', decimals: 0, thousands: ',', decimal: '.' },
            'EGP': { symbol: 'E£',   position: 'before', decimals: 2, thousands: ',', decimal: '.' },
            'MAD': { symbol: ' MAD', position: 'after',  decimals: 2, thousands: ' ', decimal: ',' },
            'GHS': { symbol: 'GH₵',  position: 'before', decimals: 2, thousands: ',', decimal: '.' },
            'UGX': { symbol: 'USh ', position: 'before', decimals: 0, thousands: ',', decimal: '.' },
            'TZS': { symbol: 'TSh ', position: 'before', decimals: 0, thousands: ',', decimal: '.' },
            'KRW': { symbol: '₩',    position: 'before', decimals: 0, thousands: ',', decimal: '.' }
        };
        const cfg = CURRENCIES[currency] || { symbol: ' ' + currency, position: 'after', decimals: 2, thousands: ',', decimal: '.' };
        let parts = amount.toFixed(cfg.decimals).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, cfg.thousands);
        const formatted = parts.join(cfg.decimal);
        return cfg.position === 'before' ? cfg.symbol + formatted : formatted + cfg.symbol;
    }

    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    /**
     * Get the authoritative active country.
     * Order of precedence:
     * 1. Explicit user selection in localStorage (immune to CDN/server cache)
     * 2. helmetsan_geo cookie
     * 3. Default to 'IN'
     */
    function getActiveCountry() {
        try {
            const stored = localStorage.getItem('helmetsan_country');
            if (stored && countryData[stored]) {
                return stored;
            }
        } catch (e) {}

        const cookieVal = getCookie('helmetsan_geo');
        if (cookieVal && countryData[cookieVal]) {
            return cookieVal;
        }

        // Check the pre-rendered server/edge DOM country code
        const domCode = document.getElementById('hsCurrentCode')?.textContent?.trim()?.toUpperCase();
        if (domCode && countryData[domCode]) {
            return domCode;
        }

        return 'IN';
    }

    function updateHeaderTriggerBadge(countryCode) {
        const info = countryData[countryCode] || countryData['IN'];
        const flagEl = document.getElementById('hsCurrentFlag');
        const nameEl = document.getElementById('hsCurrentCountry');
        const codeEl = document.getElementById('hsCurrentCode');
        const currEl = document.getElementById('hsCurrentCurrency');
        const triggerBtn = document.getElementById('hsCountryTrigger');

        if (flagEl) flagEl.textContent = info.flag;
        if (nameEl) nameEl.textContent = info.name;
        if (codeEl) codeEl.textContent = countryCode;
        if (currEl) currEl.textContent = `(${info.symbol})`;
        if (triggerBtn) {
            triggerBtn.setAttribute('aria-label', `Select Country and Currency: Current selection ${info.name} (${info.currency})`);
        }
    }

    function updateRoadLegalityBadge(countryCode) {
        const badge = document.getElementById('hs-road-legality-badge');
        if (!badge) return;

        let certs = [];
        try {
            certs = JSON.parse(badge.getAttribute('data-certifications') || '[]');
        } catch (e) {}

        const brand = (badge.getAttribute('data-brand') || '').toUpperCase();
        const certsStr = certs.map(c => String(c).toUpperCase()).join(' ');

        const hasIsi = certsStr.includes('ISI') || certsStr.includes('IS 4151') || certsStr.includes('IS4151') || ['VEGA', 'STEELBIRD', 'STUDDS', 'AXOR', 'SMK'].includes(brand);
        const hasDot = certsStr.includes('DOT') || certsStr.includes('FMVSS');
        const hasSnell = certsStr.includes('SNELL');
        const hasEce2206 = certsStr.includes('22.06') || certsStr.includes('22-06');
        const hasEce2205 = certsStr.includes('22.05') || certsStr.includes('22-05') || certsStr.includes('ECE');
        const hasEce = hasEce2206 || hasEce2205;

        let isLegal = false;
        let flag = '🌐';
        let headline = '';
        let subtitle = '';

        const cc = (countryCode || 'IN').toUpperCase();
        if (cc === 'IN') {
            flag = '🇮🇳';
            if (hasIsi) {
                isLegal = true;
                headline = 'Legal for Indian Roads (IS 4151 Certified)';
                subtitle = 'Fully compliant with Bureau of Indian Standards (BIS) motor vehicle safety mandate.';
            } else {
                isLegal = false;
                headline = 'International Spec (' + (hasEce2206 ? 'ECE 22.06' : (hasEce ? 'ECE' : 'DOT')) + ')';
                subtitle = 'Meets world-class international safety benchmarks; verify local RTO enforcement regarding IS 4151 mandate.';
            }
        } else if (cc === 'US') {
            flag = '🇺🇸';
            if (hasSnell && hasDot) {
                isLegal = true;
                headline = 'DOT + SNELL Certified (US Street & Track Legal)';
                subtitle = 'Exceeds FMVSS No. 218 federal standard with rigorous Snell Memorial Foundation impact testing.';
            } else if (hasDot) {
                isLegal = true;
                headline = 'DOT Street Legal (FMVSS No. 218)';
                subtitle = 'Certified for highway and street riding across all 50 US states.';
            } else {
                isLegal = false;
                headline = 'Non-DOT Spec (Track / Off-Highway Only)';
                subtitle = 'May not satisfy US public highway DOT (FMVSS 218) requirements.';
            }
        } else if (cc === 'CA') {
            flag = '🇨🇦';
            if (hasDot || hasEce || hasSnell) {
                isLegal = true;
                headline = 'Street Legal in Canada (CMVSS / DOT / ECE)';
                subtitle = 'Approved across all Canadian provinces and territories under CMVSS guidelines.';
            } else {
                isLegal = false;
                headline = 'Non-Approved Spec in Canada';
                subtitle = 'Check provincial motorcycle safety helmet requirements before highway use.';
            }
        } else if (cc === 'FR') {
            flag = '🇫🇷';
            if (hasEce) {
                isLegal = true;
                headline = (hasEce2206 ? 'ECE 22.06' : 'ECE 22.05') + ' Certified (Street Legal in France)';
                subtitle = 'Mandatory: 4 retro-reflective stickers affixed (Art. R431-1 Code de la route) to avoid 3-point license penalty.';
            } else {
                isLegal = false;
                headline = 'Non-ECE Spec (Not Street Legal in France)';
                subtitle = 'Requires UNECE 22.05 / 22.06 homologation for public highway use in France.';
            }
        } else if (['GB', 'UK', 'DE', 'IT', 'ES', 'NL', 'AT', 'BE', 'SE', 'DK', 'FI', 'NO', 'CH', 'PL', 'PT', 'EU', 'IE', 'TR'].includes(cc)) {
            const flagMap = { GB: '🇬🇧', UK: '🇬🇧', DE: '🇩🇪', IT: '🇮🇹', ES: '🇪🇸', NL: '🇳🇱', AT: '🇦🇹', BE: '🇧🇪', SE: '🇸🇪', DK: '🇩🇰', FI: '🇫🇮', NO: '🇳🇴', CH: '🇨🇭', PL: '🇵🇱', PT: '🇵🇹', IE: '🇮🇪', TR: '🇹🇷' };
            flag = flagMap[cc] || '🇪🇺';
            if (hasEce) {
                isLegal = true;
                headline = (hasEce2206 ? 'ECE 22.06' : 'ECE 22.05') + ' Certified (Street Legal)';
                subtitle = 'Fully homologated for European and UK public highways.';
            } else {
                isLegal = false;
                headline = 'Non-ECE Spec';
                subtitle = 'Requires UNECE 22.05 / 22.06 certification for public road legality in Europe.';
            }
        } else if (cc === 'JP') {
            flag = '🇯🇵';
            const hasJis = certsStr.includes('JIS') || certsStr.includes('MFJ');
            if (hasJis || hasEce2206) {
                isLegal = true;
                headline = 'JIS / MFJ Road Compliant (Japan)';
                subtitle = 'Meets Japanese Industrial Standards and safety regulations for public roads.';
            } else {
                isLegal = false;
                headline = 'Non-JIS Spec';
                subtitle = 'Carries international certs; verify local Japanese Ministry of Land, Infrastructure, Transport rules.';
            }
        } else if (cc === 'AU' || cc === 'NZ') {
            flag = cc === 'NZ' ? '🇳🇿' : '🇦🇺';
            const hasAs = certsStr.includes('1698') || certsStr.includes('AS/NZS');
            if (hasEce2206 || hasEce2205 || hasAs) {
                isLegal = true;
                headline = 'Road Legal in Australia & NZ (ECE 22.06 / AS 1698)';
                subtitle = 'Compliant with Australian Road Rules and state/territory motorcycle helmet standards.';
            } else {
                isLegal = false;
                headline = 'Non-Approved Spec in Australia / NZ';
                subtitle = 'Requires UNECE 22.05/22.06 or AS/NZS 1698 homologation for public road use.';
            }
        } else {
            isLegal = hasEce2206 || hasDot || hasEce;
            flag = countryData[cc] ? countryData[cc].flag : '🌐';
            headline = isLegal ? ('Certified Road Helmet (' + (hasEce2206 ? 'ECE 22.06' : (hasDot ? 'DOT' : 'ECE')) + ')') : 'Safety Homologation Unverified';
            subtitle = isLegal ? 'Meets internationally recognized motorcycle impact and retention safety benchmarks.' : 'Confirm local road legality certifications before purchasing.';
        }

        badge.classList.remove('hs-road-legality-badge--legal', 'hs-road-legality-badge--warning');
        badge.classList.add(isLegal ? 'hs-road-legality-badge--legal' : 'hs-road-legality-badge--warning');
        badge.setAttribute('data-country', cc);

        const flagEl = badge.querySelector('.hs-road-legality-badge__flag');
        if (flagEl) flagEl.textContent = flag;

        const titleEl = badge.querySelector('.hs-road-legality-badge__headline-text');
        if (titleEl) titleEl.textContent = headline;

        const subtitleEl = badge.querySelector('.hs-road-legality-badge__subtitle');
        if (subtitleEl) subtitleEl.textContent = subtitle;
    }

    function updateImportDutyNotice(countryCode) {
        const notice = document.getElementById('hs-import-duty-notice');
        if (!notice) return;

        const isDomesticIn = notice.getAttribute('data-domestic-in') === 'true';
        const cc = (countryCode || 'IN').toUpperCase();

        // Show import advisory for Indian riders when helmet is an imported brand
        if (cc === 'IN' && !isDomesticIn) {
            notice.style.display = 'block';
        } else {
            notice.style.display = 'none';
        }
    }

    const amazonMarketplaces = {
        'IN': { host: 'www.amazon.in',     tag: 'virginiatete-21', label: 'Amazon.in' },
        'US': { host: 'www.amazon.com',    tag: 'vtete-20',        label: 'Amazon.com' },
        'GB': { host: 'www.amazon.co.uk',  tag: 'vtete-21',        label: 'Amazon.co.uk' },
        'UK': { host: 'www.amazon.co.uk',  tag: 'vtete-21',        label: 'Amazon.co.uk' },
        'DE': { host: 'www.amazon.de',     tag: 'vtete-20',        label: 'Amazon.de' },
        'AT': { host: 'www.amazon.de',     tag: 'vtete-20',        label: 'Amazon.de' },
        'CH': { host: 'www.amazon.de',     tag: 'vtete-20',        label: 'Amazon.de' },
        'FR': { host: 'www.amazon.fr',     tag: 'vtete-20',        label: 'Amazon.fr' },
        'IT': { host: 'www.amazon.it',     tag: 'vtete-20',        label: 'Amazon.it' },
        'ES': { host: 'www.amazon.es',     tag: 'vtete-20',        label: 'Amazon.es' },
        'NL': { host: 'www.amazon.nl',     tag: 'vtete-20',        label: 'Amazon.nl' },
        'PL': { host: 'www.amazon.pl',     tag: 'vtete-20',        label: 'Amazon.pl' },
        'SE': { host: 'www.amazon.se',     tag: 'vtete-20',        label: 'Amazon.se' },
        'BE': { host: 'www.amazon.com.be', tag: 'vtete-20',        label: 'Amazon.com.be' },
        'CA': { host: 'www.amazon.ca',     tag: 'vtete-20',        label: 'Amazon.ca' },
        'JP': { host: 'www.amazon.co.jp',  tag: 'vtete-22',        label: 'Amazon.co.jp' },
        'MX': { host: 'www.amazon.com.mx', tag: 'vtete-20',        label: 'Amazon.com.mx' },
        'AU': { host: 'www.amazon.com.au', tag: 'vtete-20',        label: 'Amazon.com.au' },
        'NZ': { host: 'www.amazon.com.au', tag: 'vtete-20',        label: 'Amazon.com.au' },
        'BR': { host: 'www.amazon.com.br', tag: 'vtete-20',        label: 'Amazon.com.br' },
        'AE': { host: 'www.amazon.ae',     tag: 'vtete08-21',      label: 'Amazon.ae' },
        'SA': { host: 'www.amazon.sa',     tag: 'vtete-20',        label: 'Amazon.sa' },
        'SG': { host: 'www.amazon.sg',     tag: 'vtete-20',        label: 'Amazon.sg' },
        'IE': { host: 'www.amazon.co.uk',  tag: 'vtete-21',        label: 'Amazon.co.uk' },
        'TR': { host: 'www.amazon.com.tr', tag: 'vtete-20',        label: 'Amazon.com.tr' }
    };

    // Hydrate dynamic StoreIDs from WordPress Admin settings via helmetsan_geo_config
    if (window.helmetsan_geo_config && window.helmetsan_geo_config.amazon_tags) {
        const dynamicTags = window.helmetsan_geo_config.amazon_tags;
        Object.keys(dynamicTags).forEach(function(code) {
            const upperCode = code.toUpperCase();
            if (amazonMarketplaces[upperCode]) {
                amazonMarketplaces[upperCode].tag = dynamicTags[code];
            }
        });
    }

    function updateAffiliateLinks(countryCode) {
        const cc = (countryCode || 'IN').toUpperCase();
        const countryInfo = countryData[cc];
        let defaultFallback = amazonMarketplaces['IN'];
        if (countryInfo && countryInfo.region === 'EU') {
            defaultFallback = amazonMarketplaces['DE'];
        } else if (countryInfo && countryInfo.region === 'NA') {
            defaultFallback = amazonMarketplaces['US'];
        }
        const regionalAmz = amazonMarketplaces[cc] || defaultFallback;

        // 1. Update primary Amazon CTA button
        document.querySelectorAll('.hs-price-cta, .hs-btn--amazon').forEach(btn => {
            btn.childNodes.forEach(node => {
                if (node.nodeType === Node.TEXT_NODE && node.textContent.includes('Amazon')) {
                    node.textContent = ` Buy on ${regionalAmz.label} →`;
                }
            });

            let href = btn.getAttribute('href');
            if (href && href.includes('/go/')) {
                try {
                    const url = new URL(href, window.location.origin);
                    url.searchParams.set('country', cc);
                    btn.setAttribute('href', url.toString());
                } catch (e) {}
            }
        });

        // 2. Update /go/ outbound links across the page (scoped to Amazon/marketplace affiliate links)
        document.querySelectorAll('a[href*="/go/"]').forEach(link => {
            const isAffiliate = link.hasAttribute('data-marketplace') || 
                                link.classList.contains('hs-price-cta') || 
                                link.classList.contains('hs-btn--amazon') ||
                                (link.getAttribute('data-retailer') || '').toLowerCase().includes('amazon');
            if (!isAffiliate) return;

            let href = link.getAttribute('href');
            if (href) {
                try {
                    const url = new URL(href, window.location.origin);
                    url.searchParams.set('country', cc);
                    link.setAttribute('href', url.toString());
                } catch (e) {}
            }
        });
    }

    function updateSchemaPrice(countryCode, rates) {
        const schemaScript = document.querySelector('script.helmetsan-schema-product');
        if (!schemaScript) return;

        try {
            const schema = JSON.parse(schemaScript.textContent);
            if (!schema || !schema.offers) return;

            const geo = countryData[countryCode] || { currency: 'USD' };
            const targetCurrency = geo.currency;

            let basePrice = parseFloat(schemaScript.getAttribute('data-base-price'));
            if (isNaN(basePrice) || basePrice <= 0) {
                if (schema.offers.priceCurrency === 'USD') {
                    basePrice = parseFloat(schema.offers.price);
                    schemaScript.setAttribute('data-base-price', basePrice.toString());
                } else {
                    return;
                }
            }

            let finalPrice = basePrice;
            let finalCurrency = 'USD';

            const mainPriceEl = document.querySelector('.hs-price');
            if (mainPriceEl && mainPriceEl.getAttribute('data-manual-pricing')) {
                const manualPricingAttr = mainPriceEl.getAttribute('data-manual-pricing');
                try {
                    const decodedJson = manualPricingAttr
                        .replace(/&quot;/g, '"')
                        .replace(/&amp;/g, '&')
                        .replace(/&lt;/g, '<')
                        .replace(/&gt;/g, '>');
                    const manualPricing = JSON.parse(decodedJson);
                    if (manualPricing && manualPricing[countryCode]) {
                        const entry = manualPricing[countryCode];
                        const price = parseFloat(entry.current_price || entry.price);
                        const curr = entry.currency || targetCurrency;
                        if (!isNaN(price) && price > 0) {
                            finalPrice = price;
                            finalCurrency = curr;
                        }
                    }
                } catch (e) {}
            }

            if (finalCurrency === 'USD' && targetCurrency !== 'USD' && rates && rates[targetCurrency]) {
                const toRate = rates[targetCurrency];
                let converted = basePrice * toRate;
                converted = applyVat(converted, countryCode);
                converted = charmRound(converted, targetCurrency);
                finalPrice = converted;
                finalCurrency = targetCurrency;
            }

            const offersList = Array.isArray(schema.offers) ? schema.offers : [schema.offers];
            offersList.forEach(offer => {
                if (offer && typeof offer === 'object') {
                    offer.price = parseFloat(finalPrice.toFixed(2));
                    offer.priceCurrency = finalCurrency;
                    if (offer.shippingDetails && offer.shippingDetails.shippingRate) {
                        offer.shippingDetails.shippingRate.currency = finalCurrency;
                    }
                }
            });
            schemaScript.textContent = JSON.stringify(schema, null, 2);
        } catch (e) {
            console.error('Error updating schema price:', e);
        }
    }

    async function updateAllPrices(countryCode) {
        if (!countryCode) {
            countryCode = getActiveCountry();
        }
        const ratesData = await fetchRates();
        const rates = ratesData ? ratesData.rates : null;
        const geo = countryData[countryCode] || { currency: 'INR' };
        const targetCurrency = geo.currency;

        document.querySelectorAll('.hs-price').forEach(el => {
            const basePriceAttr = el.getAttribute('data-base-price');
            const baseCurrencyAttr = el.getAttribute('data-base-currency') || 'USD';
            const manualPricingAttr = el.getAttribute('data-manual-pricing');

            if (!basePriceAttr) return;
            const basePrice = parseFloat(basePriceAttr);
            if (isNaN(basePrice) || basePrice <= 0) return;

            // 1. Manual pricing check
            if (manualPricingAttr) {
                try {
                    const decodedJson = manualPricingAttr
                        .replace(/&quot;/g, '"')
                        .replace(/&amp;/g, '&')
                        .replace(/&lt;/g, '<')
                        .replace(/&gt;/g, '>');
                    const manualPricing = JSON.parse(decodedJson);
                    if (manualPricing && manualPricing[countryCode]) {
                        const entry = manualPricing[countryCode];
                        const price = parseFloat(entry.current_price || entry.price);
                        const curr = entry.currency || targetCurrency;
                        if (!isNaN(price) && price > 0) {
                            let formattedPrice = formatCurrency(price, curr);
                            if (isVatCountry(countryCode)) {
                                formattedPrice += ' <small class="hs-tax-label">incl. VAT</small>';
                            }
                            el.innerHTML = formattedPrice;
                            return;
                        }
                    }
                } catch (e) {
                    console.error('Manual pricing JSON parse error:', e);
                }
            }

            // 2. Dynamic Exchange Rate Conversion
            if (rates && rates[targetCurrency]) {
                const fromRate = rates[baseCurrencyAttr] || 1.0;
                const toRate = rates[targetCurrency];
                let converted = (basePrice / fromRate) * toRate;

                converted = applyVat(converted, countryCode);
                converted = charmRound(converted, targetCurrency);

                let formattedPrice = formatCurrency(converted, targetCurrency);
                if (isVatCountry(countryCode)) {
                    formattedPrice += ' <small class="hs-tax-label">incl. VAT</small>';
                }
                el.innerHTML = formattedPrice;
            }
        });

        // 3. Dynamic Schema Sync
        updateSchemaPrice(countryCode, rates);
    }

    // Expose global price updater
    window.helmetsanUpdatePrices = updateAllPrices;

    function hydrateUI(countryCode) {
        countryCode = (countryCode || 'IN').toUpperCase();
        if (!countryData[countryCode]) countryCode = 'IN';

        updateHeaderTriggerBadge(countryCode);
        updateRoadLegalityBadge(countryCode);
        updateImportDutyNotice(countryCode);
        updateAffiliateLinks(countryCode);

        // Update active class and aria-selected on cards
        document.querySelectorAll('.hs-country-card').forEach(card => {
            const isMatch = (card.getAttribute('data-country-code') === countryCode);
            if (isMatch) {
                card.classList.add('is-active');
                card.setAttribute('aria-selected', 'true');
            } else {
                card.classList.remove('is-active');
                card.setAttribute('aria-selected', 'false');
            }
        });

        // Fallback selects
        document.querySelectorAll('.hs-currency-select').forEach(s => {
            s.value = countryCode;
        });

        updateAllPrices(countryCode);
    }

    function selectCountry(countryCode) {
        countryCode = countryCode.toUpperCase();
        if (!countryData[countryCode]) return;

        try {
            localStorage.setItem('helmetsan_country', countryCode);
        } catch (e) {}

        document.cookie = `helmetsan_geo=${countryCode}; path=/; max-age=86400; secure; SameSite=Lax`;

        hydrateUI(countryCode);

        // Dispatch country changed event for other components (e.g. compliance badges)
        document.dispatchEvent(new CustomEvent('helmetsan:country_changed', {
            detail: {
                country: countryCode,
                currency: countryData[countryCode].currency,
                symbol: countryData[countryCode].symbol
            }
        }));

        // Telemetry
        if (typeof window.gtag === 'function') {
            const geo = countryData[countryCode];
            window.gtag('event', 'country_changed', {
                'target_country': countryCode,
                'target_currency': geo.currency
            });
            window.gtag('set', 'user_properties', {
                'geo_country': countryCode,
                'geo_currency': geo.currency
            });
        }
    }

    function initCountryModal() {
        const modal = document.getElementById('hsCountryModal');
        const trigger = document.getElementById('hsCountryTrigger');
        if (!modal || !trigger) return;

        function openModal() {
            modal.removeAttribute('hidden');
            document.body.classList.add('hs-modal-open');
            trigger.setAttribute('aria-expanded', 'true');
            const searchInput = document.getElementById('hsCountrySearchInput');
            if (searchInput) {
                searchInput.value = '';
                filterCountries('');
                setTimeout(() => searchInput.focus(), 100);
            }
        }

        function closeModal() {
            modal.setAttribute('hidden', '');
            document.body.classList.remove('hs-modal-open');
            trigger.setAttribute('aria-expanded', 'false');
            trigger.focus();
        }

        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            openModal();
        });

        modal.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.hasAttribute('hidden')) {
                closeModal();
            }
        });

        // Search filtering
        const searchInput = document.getElementById('hsCountrySearchInput');
        function filterCountries(q) {
            q = q.toLowerCase().trim();
            const cards = modal.querySelectorAll('.hs-country-card');
            const regionGroups = modal.querySelectorAll('.hs-country-region-group');

            cards.forEach(card => {
                const name = (card.getAttribute('data-country-name') || '').toLowerCase();
                const code = (card.getAttribute('data-country-code') || '').toLowerCase();
                const curr = (card.getAttribute('data-currency') || '').toLowerCase();
                const symbol = (card.getAttribute('data-symbol') || '').toLowerCase();

                const matches = !q || name.includes(q) || code.includes(q) || curr.includes(q) || symbol.includes(q);
                card.hidden = !matches;
            });

            // Hide empty region groups
            regionGroups.forEach(group => {
                const visibleCards = group.querySelectorAll('.hs-country-card:not([hidden])');
                group.hidden = (visibleCards.length === 0);
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                filterCountries(e.target.value);
            });
        }

        // Country Card Selection
        modal.querySelectorAll('.hs-country-card').forEach(card => {
            card.addEventListener('click', () => {
                const code = card.getAttribute('data-country-code');
                if (code) {
                    selectCountry(code);
                    closeModal();
                }
            });
        });

        // Live connection detection pill
        fetchRates().then(data => {
            if (data && data.detected_country && countryData[data.detected_country]) {
                const detCode = data.detected_country;
                const detInfo = countryData[detCode];
                const box = document.getElementById('hsDetectedCountryBox');
                const text = document.getElementById('hsDetectedCountryText');
                const useBtn = document.getElementById('hsUseDetectedBtn');

                if (box && text && useBtn) {
                    text.textContent = `${detInfo.flag} ${detInfo.name} (${detInfo.currency} · ${detInfo.symbol})`;
                    box.style.display = 'flex';

                    useBtn.onclick = () => {
                        selectCountry(detCode);
                        closeModal();
                    };
                }
            }
        });
    }

    // Listen for catalog AJAX updates
    document.addEventListener('helmetsan:catalog_updated', () => {
        updateAllPrices(getActiveCountry());
    });

    document.addEventListener('DOMContentLoaded', async () => {
        const activeCountry = getActiveCountry();
        
        // Passively hydrate UI according to activeCountry without setting cookies
        hydrateUI(activeCountry);

        // Initialize modal handlers
        initCountryModal();

        // Legacy select support
        const selects = document.querySelectorAll('.hs-currency-select');
        selects.forEach(select => {
            select.addEventListener('change', (e) => {
                selectCountry(e.target.value);
            });
        });
    });
})();

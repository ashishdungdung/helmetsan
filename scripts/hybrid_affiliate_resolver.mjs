/**
 * Helmetsan SuperMulti-Marketplace Hybrid Affiliate Resolver
 * 
 * Implements policy-driven 4-stage smart redirector across 22 Amazon worldwide regions:
 * 1. Direct verified regional ASIN
 * 2. OneLink forwarding via US Store (with tag vtete-20)
 * 3. Precision geo-targeted automated search fallback (/s?k=Brand+Model+Variant&tag={local_tag})
 * 4. Alternate specialty retailers (RevZilla, FC-Moto, etc.)
 */

export const MARKETPLACE_REGISTRY = {
  'US': { host: 'www.amazon.com',    tld: '.com',   tag: 'vtete-20',        onelink: true,  currency: 'USD', name: 'Amazon US' },
  'CA': { host: 'www.amazon.ca',     tld: '.ca',    tag: 'vtete-20',        onelink: true,  currency: 'CAD', name: 'Amazon Canada' },
  'MX': { host: 'www.amazon.com.mx', tld: '.com.mx',tag: 'vtete-20',        onelink: true,  currency: 'MXN', name: 'Amazon Mexico' },
  'BR': { host: 'www.amazon.com.br', tld: '.com.br',tag: 'vtete-20',        onelink: true,  currency: 'BRL', name: 'Amazon Brazil' },
  'UK': { host: 'www.amazon.co.uk',  tld: '.co.uk', tag: 'vtete-21',        onelink: true,  currency: 'GBP', name: 'Amazon UK' },
  'GB': { host: 'www.amazon.co.uk',  tld: '.co.uk', tag: 'vtete-21',        onelink: true,  currency: 'GBP', name: 'Amazon UK' },
  'DE': { host: 'www.amazon.de',     tld: '.de',    tag: 'vtete-20',        onelink: true,  currency: 'EUR', name: 'Amazon Germany' },
  'FR': { host: 'www.amazon.fr',     tld: '.fr',    tag: 'vtete-20',        onelink: true,  currency: 'EUR', name: 'Amazon France' },
  'IT': { host: 'www.amazon.it',     tld: '.it',    tag: 'vtete-20',        onelink: true,  currency: 'EUR', name: 'Amazon Italy' },
  'ES': { host: 'www.amazon.es',     tld: '.es',    tag: 'vtete-20',        onelink: true,  currency: 'EUR', name: 'Amazon Spain' },
  'NL': { host: 'www.amazon.nl',     tld: '.nl',    tag: 'vtete-20',        onelink: true,  currency: 'EUR', name: 'Amazon Netherlands' },
  'PL': { host: 'www.amazon.pl',     tld: '.pl',    tag: 'vtete-20',        onelink: true,  currency: 'PLN', name: 'Amazon Poland' },
  'SE': { host: 'www.amazon.se',     tld: '.se',    tag: 'vtete-20',        onelink: true,  currency: 'SEK', name: 'Amazon Sweden' },
  'BE': { host: 'www.amazon.com.be', tld: '.com.be',tag: 'vtete-20',        onelink: true,  currency: 'EUR', name: 'Amazon Belgium' },
  'TR': { host: 'www.amazon.com.tr', tld: '.com.tr',tag: 'vtete-20',        onelink: false, currency: 'TRY', name: 'Amazon Turkey' },
  'IN': { host: 'www.amazon.in',     tld: '.in',    tag: 'virginiatete-21', onelink: false, currency: 'INR', name: 'Amazon India' },
  'JP': { host: 'www.amazon.co.jp',  tld: '.co.jp', tag: 'vtete-22',        onelink: false, currency: 'JPY', name: 'Amazon Japan' },
  'AU': { host: 'www.amazon.com.au', tld: '.com.au',tag: 'vtete-20',        onelink: false, currency: 'AUD', name: 'Amazon Australia' },
  'SG': { host: 'www.amazon.sg',     tld: '.sg',    tag: 'vtete-20',        onelink: false, currency: 'SGD', name: 'Amazon Singapore' },
  'AE': { host: 'www.amazon.ae',     tld: '.ae',    tag: 'vtete0c-21',      onelink: false, currency: 'AED', name: 'Amazon UAE' },
  'SA': { host: 'www.amazon.sa',     tld: '.sa',    tag: 'vtete-20',        onelink: false, currency: 'SAR', name: 'Amazon Saudi Arabia' },
  'EG': { host: 'www.amazon.eg',     tld: '.eg',    tag: 'vtete-20',        onelink: false, currency: 'EGP', name: 'Amazon Egypt' },
  'ZA': { host: 'www.amazon.co.za',  tld: '.co.za', tag: 'vtete-20',        onelink: false, currency: 'ZAR', name: 'Amazon South Africa' }
};

export class HybridAffiliateResolver {
  constructor(config = {}) {
    this.mode = config.mode || process.env.AFFILIATE_MODE || 'hybrid'; // 'hybrid' | 'api_only' | 'fallback_only'
    this.primaryTag = config.primaryTag || process.env.AMAZON_ONELINK_TAG || 'vtete-20';
    this.indiaTag = config.indiaTag || process.env.AMAZON_INDIA_TAG || 'virginiatete-21';
  }

  /**
   * Resolves the best affiliate route for a product in a target country.
   */
  resolveRoute(item, countryCode = 'US') {
    const cc = (countryCode || 'US').toUpperCase();
    const market = MARKETPLACE_REGISTRY[cc] || MARKETPLACE_REGISTRY['US'];
    const lowerKey = cc.toLowerCase();

    const identifiers = item.identifiers || {};
    const amazonReg = identifiers.amazon || {};
    const regionalEntry = amazonReg[lowerKey] || amazonReg['us'] || {};

    const brand = item.brand || '';
    const title = item.title || item.id || '';
    const searchTerms = [brand, title].filter(Boolean).join(' ').trim();

    // Stage 1: Direct Verified Regional ASIN
    if (this.mode !== 'fallback_only') {
      const asin = regionalEntry.asin || (lowerKey === 'us' ? identifiers.asin : null);
      const isQuarantined = regionalEntry.status === 'quarantined';

      if (asin && !isQuarantined && /^[A-Z0-9]{10}$/.test(asin)) {
        return {
          stage: 1,
          type: 'direct_product',
          marketplace: cc,
          asin,
          url: `https://${market.host}/dp/${asin}?tag=${market.tag}`,
          affiliateTag: market.tag,
          confidence: regionalEntry.confidence || 0.95
        };
      }
    }

    // Stage 2: OneLink Forwarding via US Store (if destination country supports OneLink and US ASIN is verified)
    if (this.mode !== 'fallback_only' && market.onelink && cc !== 'US') {
      const usEntry = amazonReg['us'] || {};
      const usAsin = usEntry.asin;
      const isUsQuarantined = usEntry.status === 'quarantined';

      if (usAsin && !isUsQuarantined && /^[A-Z0-9]{10}$/.test(usAsin)) {
        return {
          stage: 2,
          type: 'onelink_forwarding',
          marketplace: cc,
          originAsin: usAsin,
          url: `https://www.amazon.com/dp/${usAsin}?tag=${this.primaryTag}`,
          affiliateTag: this.primaryTag,
          confidence: 0.85
        };
      }
    }

    // Stage 3: Precision Search Fallback on exact localized Amazon domain
    const query = regionalEntry.search_fallback || searchTerms;
    const encodedQuery = encodeURIComponent(query.slice(0, 120));
    return {
      stage: 3,
      type: 'search_fallback',
      marketplace: cc,
      query,
      url: `https://${market.host}/s?k=${encodedQuery}&tag=${market.tag}`,
      affiliateTag: market.tag,
      confidence: 0.70
    };
  }

  /**
   * Generates localized links across all 22 Amazon regions for a given item.
   */
  resolveAllRegions(item) {
    const result = {};
    for (const [code, info] of Object.entries(MARKETPLACE_REGISTRY)) {
      result[code] = this.resolveRoute(item, code);
    }
    return result;
  }
}

export const affiliateResolver = new HybridAffiliateResolver();

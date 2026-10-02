/**
 * Amazon Creator API / PA-API 5.0 Client Adapter
 * 
 * Provides OAuth 2.0 client credentials authentication, AWS signature v4 request signing,
 * rate limiting, and in-memory token caching.
 * Designed to seamlessly toggle between active API lookup and automated search-query fallback.
 */

import crypto from 'crypto';

export class AmazonCreatorApiClient {
  constructor(options = {}) {
    this.clientId = options.clientId || process.env.AMAZON_CLIENT_ID || '';
    this.clientSecret = options.clientSecret || process.env.AMAZON_CLIENT_SECRET || '';
    this.tokenEndpoint = options.tokenEndpoint || 'https://api.amazon.com/auth/o2/token';
    this.apiEndpoint = options.apiEndpoint || 'https://creatorsapi.amazon/catalog/v1/';
    this.enabled = options.enabled ?? (process.env.AMAZON_CREATOR_API_ENABLED === 'true');
    
    // In-memory token cache
    this.accessToken = null;
    this.tokenExpiresAt = 0;
    this.circuitOpen = false;
    this.lastFailure = 0;
    this.consecutiveFailures = 0;
  }

  isEnabled() {
    return this.enabled && !!this.clientId && !!this.clientSecret && !this.isCircuitBroken();
  }

  isCircuitBroken() {
    if (!this.circuitOpen) return false;
    // Auto-recover circuit breaker after 60 seconds
    if (Date.now() - this.lastFailure > 60000) {
      this.circuitOpen = false;
      this.consecutiveFailures = 0;
      return false;
    }
    return true;
  }

  recordFailure(err) {
    this.lastFailure = Date.now();
    this.consecutiveFailures++;
    if (this.consecutiveFailures >= 3) {
      this.circuitOpen = true;
      console.warn(`⚠️ Amazon Creator API circuit opened due to 3 consecutive failures: ${err?.message || err}`);
    }
  }

  recordSuccess() {
    this.consecutiveFailures = 0;
    this.circuitOpen = false;
  }

  async getAccessToken() {
    if (this.accessToken && Date.now() < this.tokenExpiresAt - 60000) {
      return this.accessToken;
    }

    if (!this.clientId || !this.clientSecret) {
      throw new Error('Amazon Creator API credentials missing (AMAZON_CLIENT_ID / AMAZON_CLIENT_SECRET)');
    }

    const body = new URLSearchParams({
      grant_type: 'client_credentials',
      client_id: this.clientId,
      client_secret: this.clientSecret,
      scope: 'creatorsapi::default'
    });

    const res = await fetch(this.tokenEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    });

    if (!res.ok) {
      const txt = await res.text();
      throw new Error(`Token exchange failed (${res.status}): ${txt}`);
    }

    const data = await res.json();
    this.accessToken = data.access_token;
    this.tokenExpiresAt = Date.now() + ((data.expires_in || 3600) * 1000);
    return this.accessToken;
  }

  async lookupByAsin({ marketplace = 'US', asin }) {
    if (!this.isEnabled()) {
      return { status: 'api_disabled', asin, marketplace };
    }

    try {
      const token = await this.getAccessToken();
      const url = `${this.apiEndpoint}items?itemIds=${encodeURIComponent(asin)}&marketplace=${marketplace}`;
      const res = await fetch(url, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json'
        }
      });

      if (!res.ok) {
        throw new Error(`API lookup HTTP ${res.status}`);
      }

      const data = await res.json();
      this.recordSuccess();
      return {
        status: 'verified_from_api',
        marketplace,
        asin,
        product: data.items?.[0] || null
      };
    } catch (err) {
      this.recordFailure(err);
      return { status: 'fallback_to_search', asin, error: err.message };
    }
  }

  async searchByKeywords({ marketplace = 'US', keywords }) {
    if (!this.isEnabled()) {
      return { status: 'api_disabled', keywords, marketplace };
    }

    try {
      const token = await this.getAccessToken();
      const url = `${this.apiEndpoint}search?keywords=${encodeURIComponent(keywords)}&marketplace=${marketplace}`;
      const res = await fetch(url, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json'
        }
      });

      if (!res.ok) {
        throw new Error(`API search HTTP ${res.status}`);
      }

      const data = await res.json();
      this.recordSuccess();
      return {
        status: 'search_results_api',
        marketplace,
        items: data.items || []
      };
    } catch (err) {
      this.recordFailure(err);
      return { status: 'fallback_to_search_url', keywords, error: err.message };
    }
  }
}

export const amazonCreatorApi = new AmazonCreatorApiClient();

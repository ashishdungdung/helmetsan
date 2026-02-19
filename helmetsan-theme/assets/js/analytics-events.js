(function () {
  "use strict";

  /**
   * Custom analytics events for the Helmetsan price engine.
   *
   * Complements tracker.js (which handles view_item / generate_lead / select_item)
   * with price-chart and offer-table visibility events.
   */

  function pushEvent(name, params) {
    if (typeof window.gtag === "function") {
      window.gtag("event", name, params);
    } else if (typeof window.dataLayer !== "undefined") {
      window.dataLayer.push({ event: name, ...params });
    }
  }

  // ── hs_price_chart_view ──────────────────────────────────────────
  function observePriceChart() {
    var chart = document.querySelector(".hs-price-chart");
    if (!chart) return;

    // Fire once when chart container has data rendered
    var observer = new MutationObserver(function () {
      if (chart.querySelector("canvas, svg, .hs-chart-data")) {
        pushEvent("hs_price_chart_view", {
          helmet_id: chart.dataset.helmetId || "",
          days: chart.dataset.days || "30",
        });
        observer.disconnect();
      }
    });
    observer.observe(chart, { childList: true, subtree: true });

    // Also fire immediately if data is already present
    if (chart.querySelector("canvas, svg, .hs-chart-data")) {
      pushEvent("hs_price_chart_view", {
        helmet_id: chart.dataset.helmetId || "",
        days: chart.dataset.days || "30",
      });
    }
  }

  // ── hs_price_chart_toggle ────────────────────────────────────────
  function bindChartToggle() {
    document.addEventListener("click", function (e) {
      var toggle = e.target.closest(".hs-chart-toggle, [data-chart-days]");
      if (!toggle) return;

      pushEvent("hs_price_chart_toggle", {
        days: toggle.dataset.chartDays || toggle.textContent.trim(),
        helmet_id: (document.querySelector(".hs-price-chart") || {}).dataset
          ? (document.querySelector(".hs-price-chart") || {}).dataset
              .helmetId || ""
          : "",
      });
    });
  }

  // ── hs_offer_table_view ──────────────────────────────────────────
  function observeOfferTable() {
    var table = document.querySelector(".hs-where-to-buy, .hs-price-table");
    if (!table) return;

    var fired = false;
    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting && !fired) {
            fired = true;
            pushEvent("hs_offer_table_view", {
              helmet_id: table.dataset.helmetId || "",
              offer_count: table.querySelectorAll("tr, .hs-offer-row").length,
            });
            io.disconnect();
          }
        });
      },
      { threshold: 0.25 },
    );

    io.observe(table);
  }

  // ── hs_marketplace_click ─────────────────────────────────────────
  function bindMarketplaceClicks() {
    document.addEventListener("click", function (e) {
      var link = e.target.closest(".hs-price-cta");
      if (!link) return;

      pushEvent("hs_marketplace_click", {
        marketplace_id: link.dataset.marketplace || "unknown",
        price: link.dataset.price || "",
        helmet_id: (
          document.querySelector(".hs-price-chart, .hs-where-to-buy") || {}
        ).dataset
          ? (document.querySelector(".hs-price-chart, .hs-where-to-buy") || {})
              .dataset.helmetId || ""
          : "",
        url: link.getAttribute("href") || "",
      });
    });
  }

  // ── Init ─────────────────────────────────────────────────────────
  document.addEventListener("DOMContentLoaded", function () {
    observePriceChart();
    bindChartToggle();
    observeOfferTable();
    bindMarketplaceClicks();
  });
})();

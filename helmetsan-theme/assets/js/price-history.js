/**
 * Helmetsan Price History Chart
 *
 * Fetches price history data from the REST API and renders
 * a multi-series line chart using Chart.js.
 */
(function () {
  'use strict';

  const canvas = document.getElementById('hs-price-chart');
  if (!canvas) return;

  const helmetId = canvas.dataset.helmetId;
  if (!helmetId) return;

  const apiBase = (window.hsPrice && window.hsPrice.apiBase) || '/wp-json/hs/v1';

  // Marketplace color palette
  const COLORS = [
    '#3b82f6', // blue
    '#ef4444', // red
    '#10b981', // green
    '#f59e0b', // amber
    '#8b5cf6', // violet
    '#ec4899', // pink
    '#06b6d4', // cyan
    '#f97316', // orange
  ];

  let chart = null;

  async function fetchHistory(days) {
    try {
      const res = await fetch(`${apiBase}/prices/${helmetId}/history?days=${days}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return await res.json();
    } catch (err) {
      console.warn('Helmetsan: Failed to load price history', err);
      return null;
    }
  }

  function buildChart(data) {
    const wrap = document.getElementById('hs-price-chart-wrap');

    if (!data || !data.series || data.series.length === 0) {
      if (wrap) wrap.style.display = 'none';
      return;
    }

    if (wrap) wrap.style.display = '';

    const datasets = data.series.map(function (s, i) {
      return {
        label: s.marketplace_id.replace(/-/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }),
        data: s.data.map(function (point) {
          return { x: point.date, y: point.price };
        }),
        borderColor: COLORS[i % COLORS.length],
        backgroundColor: COLORS[i % COLORS.length] + '1a',
        fill: true,
        tension: 0.3,
        pointRadius: 2,
        pointHoverRadius: 5,
        borderWidth: 2,
      };
    });

    const currency = data.series[0].currency || 'USD';

    if (chart) {
      chart.data.datasets = datasets;
      chart.update('none');
      return;
    }

    chart = new Chart(canvas, {
      type: 'line',
      data: { datasets: datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false,
        },
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              usePointStyle: true,
              padding: 16,
              font: { size: 12 },
            },
          },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                return ctx.dataset.label + ': ' + currency + ' ' + ctx.parsed.y.toFixed(2);
              },
            },
          },
        },
        scales: {
          x: {
            type: 'time',
            time: {
              unit: 'day',
              displayFormats: { day: 'MMM d' },
            },
            grid: { display: false },
            ticks: { font: { size: 11 } },
          },
          y: {
            beginAtZero: false,
            ticks: {
              callback: function (val) { return currency + ' ' + val; },
              font: { size: 11 },
            },
            grid: { color: 'rgba(0,0,0,0.06)' },
          },
        },
      },
    });
  }

  // Initial load
  fetchHistory(30).then(buildChart);

  // Date toggle buttons
  var toggles = document.getElementById('hs-date-toggles');
  if (toggles) {
    toggles.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-days]');
      if (!btn) return;

      // Update active state
      toggles.querySelectorAll('button').forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');

      // Reload chart
      fetchHistory(parseInt(btn.dataset.days, 10)).then(buildChart);
    });
  }
})();

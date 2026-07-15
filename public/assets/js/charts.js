(function () {
  'use strict';

  if (typeof Chart === 'undefined') {
    return;
  }

  var isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  var textColor = isDark ? '#a8a29e' : '#57534e';
  var gridColor = isDark ? 'rgba(168, 162, 158, 0.15)' : 'rgba(87, 83, 78, 0.1)';

  Chart.defaults.color = textColor;
  Chart.defaults.font.family = 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
  Chart.defaults.font.size = 12;

  var money = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });

  function buildLineChart(canvas, payload) {
    return new Chart(canvas, {
      type: 'line',
      data: {
        labels: payload.labels,
        datasets: payload.datasets.map(function (ds) {
          return {
            label: ds.label,
            data: ds.data,
            borderColor: '#c94f32',
            backgroundColor: 'rgba(201, 79, 50, 0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 2,
          };
        }),
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: function (ctx) { return money.format(ctx.parsed.y); } } },
        },
        scales: {
          y: { grid: { color: gridColor }, ticks: { callback: function (v) { return money.format(v); } } },
          x: { grid: { display: false } },
        },
      },
    });
  }

  function buildBarChart(canvas, payload) {
    var colors = ['#059669', '#78716c'];
    return new Chart(canvas, {
      type: 'bar',
      data: {
        labels: payload.labels,
        datasets: payload.datasets.map(function (ds, i) {
          return { label: ds.label, data: ds.data, backgroundColor: colors[i % colors.length], borderRadius: 3 };
        }),
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12 } },
          tooltip: { callbacks: { label: function (ctx) { return ctx.dataset.label + ': ' + money.format(ctx.parsed.y); } } },
        },
        scales: {
          y: { grid: { color: gridColor }, ticks: { callback: function (v) { return money.format(v); } } },
          x: { grid: { display: false } },
        },
      },
    });
  }

  function buildCashFlowChart(canvas, payload) {
    var net = payload.datasets[0] || { data: [] };
    var cumulative = payload.datasets[1] || { data: [] };

    return new Chart(canvas, {
      data: {
        labels: payload.labels,
        datasets: [
          {
            type: 'bar',
            label: net.label,
            data: net.data,
            backgroundColor: function (ctx) {
              return ctx.raw >= 0 ? '#059669' : '#dc2626';
            },
            borderRadius: 3,
            order: 2,
          },
          {
            type: 'line',
            label: cumulative.label,
            data: cumulative.data,
            borderColor: '#c94f32',
            backgroundColor: 'rgba(201, 79, 50, 0.1)',
            tension: 0.3,
            pointRadius: 2,
            fill: false,
            order: 1,
            yAxisID: 'y1',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12 } },
          tooltip: { callbacks: { label: function (ctx) { return ctx.dataset.label + ': ' + money.format(ctx.parsed.y); } } },
        },
        scales: {
          y: { grid: { color: gridColor }, ticks: { callback: function (v) { return money.format(v); } } },
          y1: { position: 'right', grid: { display: false }, ticks: { callback: function (v) { return money.format(v); } } },
          x: { grid: { display: false } },
        },
      },
    });
  }

  function buildDoughnutChart(canvas, payload) {
    var ds = payload.datasets[0] || { data: [] };
    return new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: payload.labels,
        datasets: [{ data: ds.data, backgroundColor: ds.backgroundColor || [], borderWidth: 0 }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'right', labels: { boxWidth: 10, padding: 10 } },
          tooltip: { callbacks: { label: function (ctx) { return ctx.label + ': ' + money.format(ctx.parsed); } } },
        },
      },
    });
  }

  var builders = { line: buildLineChart, bar: buildBarChart, doughnut: buildDoughnutChart, cashflow: buildCashFlowChart };

  // Exposed so pages that fetch chart data dynamically (e.g. the Budgets
  // page's per-category history popover) can reuse the same builders and
  // styling instead of duplicating a second Chart.js setup.
  window.FinanceCharts = builders;

  document.querySelectorAll('[data-chart]').forEach(function (canvas) {
    var dataScript = document.getElementById(canvas.id + '-data');
    if (!dataScript) {
      return;
    }

    var payload;
    try {
      payload = JSON.parse(dataScript.textContent);
    } catch (e) {
      return;
    }

    var builder = builders[canvas.dataset.chart];
    if (builder && payload.labels && payload.labels.length > 0) {
      builder(canvas, payload);
    }
  });
})();

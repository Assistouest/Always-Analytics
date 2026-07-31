


(function () {
    'use strict';

    var i18n = (typeof alwaysAnalyticsAdmin !== 'undefined' && alwaysAnalyticsAdmin.i18n) || {};
    var locale = (typeof alwaysAnalyticsAdmin !== 'undefined' && alwaysAnalyticsAdmin.locale) || document.documentElement.lang || 'en-US';

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    window.AlwaysAnalyticsCharts = {
        visitsChart: null,
        devicesChart: null,
        visitorsChart: null,

        


        colors: {
            primary: '#6c63ff',
            primaryLight: 'rgba(108, 99, 255, 0.1)',
            success: '#10b981',
            successLight: 'rgba(16, 185, 129, 0.1)',
            warning: '#f59e0b',
            warningLight: 'rgba(245, 158, 11, 0.1)',
            danger: '#ef4444',
            info: '#3b82f6',
            purple: '#8b5cf6',
            pink: '#ec4899',
            teal: '#14b8a6',
            gray: '#6b7280',
        },

        


        defaults: function () {
            Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
            Chart.defaults.font.size = 12;
            Chart.defaults.color = '#646970';
            Chart.defaults.plugins.legend.display = false;
            Chart.defaults.plugins.tooltip.backgroundColor = '#1d2327';
            Chart.defaults.plugins.tooltip.titleFont = { weight: '600' };
            Chart.defaults.plugins.tooltip.cornerRadius = 8;
            Chart.defaults.plugins.tooltip.padding = 10;
            Chart.defaults.elements.point.radius = 0;
            Chart.defaults.elements.point.hoverRadius = 5;
        },

        


        campaigns: [],
        _lastIsHourly: false,
        _lastLabels: [],
        _lastDates: [],

        setCampaigns: function (campaigns) {
            this.campaigns = campaigns || [];
            if (this.visitsChart) {
                this.visitsChart._aaCampaigns = this.campaigns;
                this.visitsChart._aaIsHourly  = this._lastIsHourly;
                this.visitsChart._aaLabels    = this._lastLabels;
                this.visitsChart._aaDates     = this._lastDates;
                this.visitsChart.update();
            }
        },

        


        renderVisitsChart: function (data) {
            var ctx = document.getElementById('always-analytics-visits-chart');
            if (!ctx) return;

            if (this.visitsChart) {
                this.visitsChart.destroy();
            }


            var isHourly = data.length > 0 && data[0].hasOwnProperty('hour');
            this._lastIsHourly = isHourly;

            var labels, visitors, pageViews, sessions, rawDates;

            if (isHourly) {

                labels    = data.map(function (d) { return d.hour + 'h'; });
                rawDates  = [];  // pas de date matching en mode horaire
                visitors  = data.map(function (d) { return d.future ? null : d.visitors; });
                pageViews = data.map(function (d) { return d.future ? null : d.page_views; });
                sessions  = data.map(function (d) { return d.future ? null : d.sessions; });
            } else {
                rawDates  = data.map(function (d) { return d.date; }); // YYYY-MM-DD
                labels    = data.map(function (d) {
                    var parts = d.date.split('-');
                    var date  = new Date( parts[0], parts[1] - 1, parts[2] );
                    return date.toLocaleDateString(locale, { day: 'numeric', month: 'short' });
                });
                visitors  = data.map(function (d) { return d.visitors; });
                pageViews = data.map(function (d) { return d.page_views; });
                sessions  = data.map(function (d) { return d.sessions; });
            }

            this._lastLabels = labels;
            this._lastDates  = rawDates;

            var self = this;


            var campaignAnnotationsPlugin = {
                id: 'aaCampaigns',
                afterDraw: function (chart) {
                    var campaigns = chart._aaCampaigns;
                    var dates     = chart._aaDates;
                    if (!campaigns || !campaigns.length || !dates || !dates.length) return;

                    var ctx2   = chart.ctx;
                    var xScale = chart.scales.x;
                    var yScale = chart.scales.y;

                    campaigns.forEach(function (camp) {
                        var idx = dates.indexOf(camp.event_date);
                        if (idx === -1) return; // date hors plage visible

                        var xPx = xScale.getPixelForValue(idx);
                        var top  = yScale.top;
                        var bot  = yScale.bottom;
                        var color = camp.color || '#6c63ff';

                        ctx2.save();


                        ctx2.beginPath();
                        ctx2.setLineDash([5, 4]);
                        ctx2.strokeStyle = color;
                        ctx2.lineWidth   = 2;
                        ctx2.globalAlpha = 0.85;
                        ctx2.moveTo(xPx, top);
                        ctx2.lineTo(xPx, bot);
                        ctx2.stroke();


                        var r = 6;
                        ctx2.setLineDash([]);
                        ctx2.globalAlpha = 1;
                        ctx2.beginPath();
                        ctx2.moveTo(xPx, top - r - 2);
                        ctx2.lineTo(xPx + r, top + 2);
                        ctx2.lineTo(xPx, top + r + 2);
                        ctx2.lineTo(xPx - r, top + 2);
                        ctx2.closePath();
                        ctx2.fillStyle = color;
                        ctx2.fill();


                        var shortLabel = camp.label.length > 18 ? camp.label.substring(0, 16) + '…' : camp.label;
                        ctx2.font      = 'bold 10px -apple-system, sans-serif';
                        ctx2.fillStyle = color;
                        ctx2.textAlign = 'center';
                        ctx2.fillText(shortLabel, xPx, top - r - 8);

                        ctx2.restore();
                    });
                },


                afterEvent: function (chart, args) {
                    var campaigns = chart._aaCampaigns;
                    var dates     = chart._aaDates;
                    if (!campaigns || !campaigns.length || !dates || !dates.length) return;

                    var event  = args.event;
                    if (event.type !== 'mousemove') return;

                    var xScale = chart.scales.x;
                    var THRESHOLD = 12; // pixels

                    var found = null;
                    campaigns.forEach(function (camp) {
                        var idx = dates.indexOf(camp.event_date);
                        if (idx === -1) return;
                        var xPx = xScale.getPixelForValue(idx);
                        if (Math.abs(event.x - xPx) < THRESHOLD) {
                            found = camp;
                        }
                    });

                    var tip = document.getElementById('always-analytics-camp-tooltip');
                    if (!tip) {
                        tip = document.createElement('div');
                        tip.id = 'always-analytics-camp-tooltip';
                        tip.className = 'always-analytics-camp-tooltip';
                        document.body.appendChild(tip);
                    }

                    if (found) {
                        var rect   = chart.canvas.getBoundingClientRect();
                        var idx2   = dates.indexOf(found.event_date);
                        var xPx2   = xScale.getPixelForValue(idx2);


                        var parts  = found.event_date.split('-');
                        var dObj   = new Date(parts[0], parts[1]-1, parts[2]);
                        var dLabel = dObj.toLocaleDateString(locale, { day: 'numeric', month: 'long', year: 'numeric' });

                        tip.innerHTML =
                            '<div class="always-analytics-camp-tooltip-header" style="border-left:3px solid ' + (found.color || '#6c63ff') + '">' +
                            '<strong>' + escapeHtml(found.label) + '</strong>' +
                            '<span class="always-analytics-camp-tooltip-date">' + dLabel + '</span>' +
                            '</div>' +
                            (found.description ? '<div class="always-analytics-camp-tooltip-desc">' + escapeHtml(found.description) + '</div>' : '') +
                            '<button class="always-analytics-camp-tooltip-del" data-id="' + found.id + '" title="' + escapeHtml(i18n.delete || 'Delete') + '">✕</button>';

                        var tipLeft = rect.left + window.scrollX + xPx2 + 10;
                        var tipTop  = rect.top  + window.scrollY + chart.scales.y.top + 20;

                        if (tipLeft + 220 > window.innerWidth) tipLeft = tipLeft - 240;

                        tip.style.left    = tipLeft + 'px';
                        tip.style.top     = tipTop  + 'px';
                        tip.style.display = 'block';
                        tip.style.opacity = '1';
                        tip.style.borderColor = found.color || '#6c63ff';


                        var delBtn = tip.querySelector('.always-analytics-camp-tooltip-del');
                        if (delBtn && !delBtn._bound) {
                            delBtn._bound = true;
                            delBtn.addEventListener('click', function (e) {
                                e.stopPropagation();
                                var id = this.getAttribute('data-id');
                                if (id && window.AlwaysAnalyticsCampaigns) {
                                    window.AlwaysAnalyticsCampaigns.deleteCampaign(parseInt(id, 10));
                                }
                                tip.style.display = 'none';
                            });
                        }
                    } else {
                        tip.style.display = 'none';
                    }
                },
            };

            this.visitsChart = new Chart(ctx, {
                type: 'line',
                plugins: [ campaignAnnotationsPlugin ],
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: i18n.visitors || 'Visitors',
                            data: visitors,
                            borderColor: this.colors.primary,
                            backgroundColor: this.createGradient(ctx, this.colors.primary),
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2.5,
                            hidden: false,
                            spanGaps: false,
                        },
                        {
                            label: i18n.pageViews || 'Page views',
                            data: pageViews,
                            borderColor: this.colors.success,
                            backgroundColor: this.createGradient(ctx, this.colors.success),
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2.5,
                            hidden: true,
                            spanGaps: false,
                        },
                        {
                            label: i18n.sessions || 'Sessions',
                            data: sessions,
                            borderColor: this.colors.warning,
                            backgroundColor: this.createGradient(ctx, this.colors.warning),
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2.5,
                            hidden: true,
                            spanGaps: false,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: {
                                maxTicksLimit: isHourly ? 24 : 15,
                                font: { size: 11 },
                            },
                        },
                        y: {
                            beginAtZero: true,
                            border: { display: false },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.04)',
                                drawBorder: false,
                            },
                            ticks: {
                                font: { size: 11 },
                                callback: function (value) {
                                    if (value >= 1000) return (value / 1000).toFixed(1) + 'k';
                                    return value;
                                },
                            },
                        },
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                title: function (items) {
                                    return isHourly
                                        ? 'Today at ' + items[0].label
                                        : items[0].label;
                                },
                                label: function (context) {
                                    if (context.parsed.y === null) return null;
                                    return context.dataset.label + ': ' + context.parsed.y.toLocaleString(locale);
                                },
                            },
                        },
                    },
                },
            });


            this.visitsChart._aaCampaigns = this.campaigns;
            this.visitsChart._aaIsHourly  = isHourly;
            this.visitsChart._aaLabels    = labels;
            this.visitsChart._aaDates     = rawDates;
        },

        


        renderDevicesChart: function (devices) {
            var ctx = document.getElementById('always-analytics-devices-chart');
            if (!ctx) return;

            if (this.devicesChart) {
                this.devicesChart.destroy();
            }

            var labels = devices.map(function (d) {
                var names = { desktop: 'Desktop', mobile: 'Mobile', tablet: 'Tablet', unknown: 'Other' };
                return names[d.device_type] || d.device_type;
            });
            var values = devices.map(function (d) { return parseInt(d.count, 10); });
            var colors = [this.colors.primary, this.colors.success, this.colors.warning, this.colors.gray];

            this.devicesChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors.slice(0, values.length),
                        borderWidth: 0,
                        hoverOffset: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                padding: 12,
                                usePointStyle: true,
                                pointStyleWidth: 10,
                                font: { size: 11 },
                            },
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    var total = context.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                    var pct = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.parsed.toLocaleString(locale) + ' (' + pct + '%)';
                                },
                            },
                        },
                    },
                },
            });
        },

        


        renderVisitorsChart: function (data) {
            var ctx = document.getElementById('always-analytics-visitors-chart');
            if (!ctx) return;

            if (this.visitorsChart) {
                this.visitorsChart.destroy();
            }

            var newV = parseInt(data.new_visitors || 0, 10);
            var retV = parseInt(data.returning_visitors || 0, 10);

            this.visitorsChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['New', 'Returning'],
                    datasets: [{
                        data: [newV, retV],
                        backgroundColor: [this.colors.primary, this.colors.teal],
                        borderWidth: 0,
                        hoverOffset: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '60%',
                    plugins: {
                        legend: { display: false },
                    },
                },
            });


            var legend = document.getElementById('always-analytics-visitors-legend');
            if (legend) {
                var total = newV + retV;
                var newPct = total > 0 ? ((newV / total) * 100).toFixed(1) : 0;
                var retPct = total > 0 ? ((retV / total) * 100).toFixed(1) : 0;
                legend.innerHTML =
                    '<span><span class="always-analytics-legend-dot" style="background:' + this.colors.primary + '"></span> New: ' + newV.toLocaleString(locale) + ' (' + newPct + '%)</span>' +
                    '<span><span class="always-analytics-legend-dot" style="background:' + this.colors.teal + '"></span> Returning: ' + retV.toLocaleString(locale) + ' (' + retPct + '%)</span>';
            }
        },

        


        createGradient: function (ctx, color) {
            var canvas = ctx.getContext ? ctx : ctx.canvas || ctx;
            if (!canvas.getContext) canvas = canvas;
            try {
                var context = (canvas.getContext ? canvas : document.getElementById(canvas.id)).getContext('2d');
                var gradient = context.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, color.replace(')', ', 0.2)').replace('rgb', 'rgba'));
                gradient.addColorStop(1, color.replace(')', ', 0)').replace('rgb', 'rgba'));
                return gradient;
            } catch (e) {
                return color + '1a'; // fallback
            }
        },

        


        toggleDataset: function (datasetName) {
            if (!this.visitsChart) return;

            var map = { visitors: 0, page_views: 1, sessions: 2 };
            var idx = map[datasetName];
            if (idx === undefined) return;


            this.visitsChart.data.datasets.forEach(function (ds, i) {
                ds.hidden = (i !== idx);
            });
            this.visitsChart.update();
        },
    };


    if (typeof Chart !== 'undefined') {
        AlwaysAnalyticsCharts.defaults();
    }
})();

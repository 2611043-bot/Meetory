/* ============================================================
    管理者ダッシュボード専用JavaScript
    Chart.js を使用した推移グラフ描画処理
   ============================================================ */

(function () {
    'use strict';

    // ネイビー系配色
    const NAVY = '#1a3a5c';
    const NAVY_LIGHT = 'rgba(26, 58, 92, 0.1)';

    // 折れ線グラフ：ユーザー登録推移
    function drawUserRegistrationChart(labels, data) {
        const ctx = document.getElementById('userRegistrationChart');
        if (!ctx) {
            return;
        }

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '新規登録数',
                    data: data,
                    borderColor: NAVY,
                    backgroundColor: NAVY_LIGHT,
                    borderWidth: 2,
                    pointBackgroundColor: NAVY,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        title: {
                            display: false
                        }
                    },
                    x: {
                        title: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // 棒グラフ：商品出品推移
    function drawGoodsListingChart(labels, data) {
        const ctx = document.getElementById('goodsListingChart');
        if (!ctx) {
            return;
        }

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '新規出品数',
                    data: data,
                    backgroundColor: NAVY,
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        title: {
                            display: false
                        }
                    },
                    x: {
                        title: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // 初期化処理
    function init() {
        // グローバル変数（dashboard.php から注入）が存在する場合にグラフを描画
        if (typeof userChartLabels !== 'undefined' && typeof userChartData !== 'undefined') {
            drawUserRegistrationChart(userChartLabels, userChartData);
        }
        if (typeof goodsChartLabels !== 'undefined' && typeof goodsChartData !== 'undefined') {
            drawGoodsListingChart(goodsChartLabels, goodsChartData);
        }
    }

    // DOM読み込み完了後に初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

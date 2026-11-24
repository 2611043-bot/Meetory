/* ============================================================
    管理者ダッシュボード専用JavaScript
    Chart.js を使用した推移グラフ描画処理
    自動更新機能（fetch API による定期取得）
   ============================================================ */

(function () {
    'use strict';

    // ネイビー系配色
    const NAVY = '#1a3a5c';
    const NAVY_LIGHT = 'rgba(26, 58, 92, 0.1)';

    // Chart.js インスタンス保存用
    let userChart = null;
    let goodsChart = null;

    // 自動更新制御
    let isFetching = false;
    let refreshTimer = null;
    const REFRESH_INTERVAL = 10000; // 10秒
    const API_URL = './api/dashboard-data.php';

    // HTML要素（後から追加される可能性があるため、取得時にチェック）
    const autoRefreshButton = document.getElementById('autoRefreshButton');
    const lastUpdatedElement = document.getElementById('lastUpdated');
    const autoRefreshStatusElement = document.getElementById('autoRefreshStatus');

    // 折れ線グラフ：ユーザー登録推移
    function drawUserRegistrationChart(labels, data) {
        const ctx = document.getElementById('userRegistrationChart');
        if (!ctx) {
            return;
        }

        // 既存インスタンスがあれば破棄
        if (userChart) {
            userChart.destroy();
        }

        userChart = new Chart(ctx, {
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

        // 既存インスタンスがあれば破棄
        if (goodsChart) {
            goodsChart.destroy();
        }

        goodsChart = new Chart(ctx, {
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

    // APIからデータを取得
    async function fetchDashboardData() {
        if (isFetching) {
            return;
        }

        isFetching = true;

        // 更新ステータス表示
        if (autoRefreshStatusElement) {
            autoRefreshStatusElement.textContent = '更新中...';
            autoRefreshStatusElement.style.color = '#1a3a5c';
        }

        try {
            const response = await fetch(API_URL, {
                method: 'GET',
                headers: {
                    Accept: 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'データの取得に失敗しました。');
            }

            // 統計情報更新
            updateStats(data.stats);

            // グラフ更新
            updateCharts(data.charts);

            // 活動履歴更新
            updateActivities(data.activities);

            // 最終更新時刻
            if (data.updated_at && lastUpdatedElement) {
                lastUpdatedElement.textContent = data.updated_at;
            }

            // 更新完了
            if (autoRefreshStatusElement) {
                autoRefreshStatusElement.textContent = '更新完了';
                autoRefreshStatusElement.style.color = '#0f766e';
            }
        } catch (error) {
            console.error('自動更新エラー:', error);
            if (autoRefreshStatusElement) {
                autoRefreshStatusElement.textContent = '更新エラー';
                autoRefreshStatusElement.style.color = '#b42318';
            }
        } finally {
            isFetching = false;
        }
    }

    // 統計情報更新
    function updateStats(stats) {
        const statValues = document.querySelectorAll('.admin-stat-value');
        if (!statValues || statValues.length < 4) {
            return;
        }

        statValues[0].textContent = stats.total_users;
        statValues[1].textContent = stats.total_goods;
        statValues[2].textContent = stats.total_transactions;
        statValues[3].textContent = stats.completed_transactions;
    }

    // グラフ更新
    function updateCharts(charts) {
        // ユーザー登録推移グラフ
        if (userChart && charts.user_registrations) {
            userChart.data.labels = charts.user_registrations.map((item) => item.month);
            userChart.data.datasets[0].data = charts.user_registrations.map((item) => item.count);
            userChart.update();
        } else if (charts.user_registrations) {
            const labels = charts.user_registrations.map((item) => item.month);
            const data = charts.user_registrations.map((item) => item.count);
            drawUserRegistrationChart(labels, data);
        }

        // 商品出品推移グラフ
        if (goodsChart && charts.goods_listings) {
            goodsChart.data.labels = charts.goods_listings.map((item) => item.month);
            goodsChart.data.datasets[0].data = charts.goods_listings.map((item) => item.count);
            goodsChart.update();
        } else if (charts.goods_listings) {
            const labels = charts.goods_listings.map((item) => item.month);
            const data = charts.goods_listings.map((item) => item.count);
            drawGoodsListingChart(labels, data);
        }
    }

    // 活動履歴更新
    function updateActivities(activities) {
        const tbody = document.querySelector('.admin-activity-table tbody');
        if (!tbody) {
            return;
        }

        // 既存の行をすべて削除
        tbody.replaceChildren();

        if (!activities || activities.length === 0) {
            return;
        }

        activities.forEach((activity) => {
            const tr = document.createElement('tr');

            const tdTime = document.createElement('td');
            tdTime.textContent = activity.activity_time
                ? new Date(activity.activity_time).toLocaleString('ja-JP', {
                      year: 'numeric',
                      month: '2-digit',
                      day: '2-digit',
                      hour: '2-digit',
                      minute: '2-digit'
                  })
                : '';
            tr.appendChild(tdTime);

            const tdType = document.createElement('td');
            const badge = document.createElement('span');
            badge.className = `admin-activity-badge admin-activity-${activity.activity_type}`;
            badge.textContent = activity.activity_label;
            tdType.appendChild(badge);
            tr.appendChild(tdType);

            const tdContent = document.createElement('td');
            tdContent.textContent = activity.activity_content;
            tr.appendChild(tdContent);

            const tdUser = document.createElement('td');
            tdUser.textContent = activity.activity_user;
            tr.appendChild(tdUser);

            tbody.appendChild(tr);
        });
    }

    // 自動更新開始
    function startAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
        }
        refreshTimer = setInterval(() => {
            if (document.visibilityState === 'visible') {
                fetchDashboardData();
            }
        }, REFRESH_INTERVAL);
    }

    // 自動更新停止
    function stopAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
    }

    // 初期化処理
    function init() {
        // グラフ描画（既存のロジック）
        if (typeof userChartLabels !== 'undefined' && typeof userChartData !== 'undefined') {
            drawUserRegistrationChart(userChartLabels, userChartData);
        }
        if (typeof goodsChartLabels !== 'undefined' && typeof goodsChartData !== 'undefined') {
            drawGoodsListingChart(goodsChartLabels, goodsChartData);
        }

        // 自動更新開始
        startAutoRefresh();

        // 手動更新ボタン（存在する場合のみ）
        if (autoRefreshButton) {
            autoRefreshButton.addEventListener('click', () => {
                fetchDashboardData();
            });
        }

        // タブ表示/非表示時の処理
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                startAutoRefresh();
                fetchDashboardData();
            } else {
                stopAutoRefresh();
            }
        });

        // ページ離脱時にタイマーを停止
        window.addEventListener('beforeunload', () => {
            stopAutoRefresh();
        });
    }

    // DOM読み込み完了後に初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

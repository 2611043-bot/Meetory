<?php

// 管理者認証を共通化
require_once __DIR__ . '/includes/admin-auth.php';

require '../../meetory-header.php';
?>

<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.js" defer></script>

<main class="main-container admin-container">
    <div class="admin-layout">
        <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>
        <div class="admin-main-content">

    <h1 class="admin-page-title">統計・分析</h1>

    <nav class="admin-breadcrumb">
        <a href="index.php">ホーム</a>
        <span class="admin-breadcrumb-separator">›</span>
        <span>統計・分析</span>
    </nav>

    <!-- 月別購入件数（折れ線グラフ） -->
    <section class="admin-widget">
        <h2 class="admin-widget-title">月別購入件数</h2>
        <div class="admin-chart-container" style="position: relative; height: 300px;">
            <canvas id="purchaseCountChart"></canvas>
        </div>
    </section>

    <!-- 月別取引完了件数（折れ線グラフ） -->
    <section class="admin-widget">
        <h2 class="admin-widget-title">月別取引完了件数</h2>
        <div class="admin-chart-container" style="position: relative; height: 300px;">
            <canvas id="completedCountChart"></canvas>
        </div>
    </section>

    <!-- 月別売上金額（棒グラフ） -->
    <section class="admin-widget">
        <h2 class="admin-widget-title">月別売上金額</h2>
        <div class="admin-chart-container" style="position: relative; height: 300px;">
            <canvas id="salesAmountChart"></canvas>
        </div>
    </section>

    <!-- 商品分析 -->
    <section class="admin-product-analysis">
        <h2 class="admin-section-title">商品分析</h2>

        <!-- カテゴリー別出品数（円グラフ） -->
        <section class="admin-widget">
            <h2 class="admin-widget-title">カテゴリー別出品数</h2>
            <div class="admin-chart-container" style="position: relative; height: 300px;">
                <canvas id="categoryListingChart"></canvas>
            </div>
        </section>

        <!-- 商品の販売状況（ドーナツグラフ） -->
        <section class="admin-widget">
            <h2 class="admin-widget-title">商品の販売状況</h2>
            <div class="admin-chart-container" style="position: relative; height: 300px;">
                <canvas id="salesStatusChart"></canvas>
            </div>
        </section>

        <!-- 商品別売上ランキング TOP10（横棒グラフ＋一覧） -->
        <section class="admin-widget">
            <h2 class="admin-widget-title">商品別売上ランキング TOP10</h2>
            <div class="admin-chart-container" style="position: relative; height: 400px;">
                <canvas id="productRankingChart"></canvas>
            </div>
            <table class="admin-product-ranking-table">
                <thead>
                    <tr>
                        <th>順位</th>
                        <th>商品名</th>
                        <th>カテゴリー</th>
                        <th>購入件数</th>
                        <th>売上金額</th>
                    </tr>
                </thead>
                <tbody id="productRankingTableBody">
                </tbody>
            </table>
        </section>

        <!-- カテゴリー別売上金額（横棒グラフ） -->
        <section class="admin-widget">
            <h2 class="admin-widget-title">カテゴリー別売上金額</h2>
            <div class="admin-chart-container" style="position: relative; height: 300px;">
                <canvas id="categorySalesChart"></canvas>
            </div>
        </section>
    </section>

    <!-- ユーザー分析 -->
    <section class="admin-user-analysis">
        <h2 class="admin-section-title">ユーザー分析</h2>

        <!-- 月別ユーザー登録推移（折れ線グラフ、一般ユーザーのみ） -->
        <section class="admin-widget">
            <h2 class="admin-widget-title">月別ユーザー登録推移（一般ユーザー）</h2>
            <div class="admin-chart-container" style="position: relative; height: 300px;">
                <canvas id="userRegistrationCountChart"></canvas>
            </div>
        </section>

        <!-- ユーザー別購入回数ランキング TOP10（横棒グラフ＋一覧） -->
        <section class="admin-widget">
            <h2 class="admin-widget-title">ユーザー別購入回数ランキング TOP10</h2>
            <div class="admin-chart-container" style="position: relative; height: 400px;">
                <canvas id="userPurchaseRankingChart"></canvas>
            </div>
            <table class="admin-user-ranking-table">
                <thead>
                    <tr>
                        <th>順位</th>
                        <th>ユーザー名</th>
                        <th>購入件数</th>
                    </tr>
                </thead>
                <tbody id="userPurchaseRankingTableBody">
                </tbody>
            </table>
        </section>

        <!-- ユーザー別出品数ランキング TOP10（横棒グラフ＋一覧） -->
        <section class="admin-widget">
            <h2 class="admin-widget-title">ユーザー別出品数ランキング TOP10</h2>
            <div class="admin-chart-container" style="position: relative; height: 400px;">
                <canvas id="userListingRankingChart"></canvas>
            </div>
            <table class="admin-user-ranking-table">
                <thead>
                    <tr>
                        <th>順位</th>
                        <th>ユーザー名</th>
                        <th>出品件数（合計）</th>
                        <th>販売中</th>
                        <th>出品停止</th>
                        <th>売却済み</th>
                    </tr>
                </thead>
                <tbody id="userListingRankingTableBody">
                </tbody>
            </table>
        </section>

        <!-- 月別ログイン活動状況（成功・失敗2系列、折れ線グラフ） -->
        <section class="admin-widget">
            <h2 class="admin-widget-title">月別ログイン活動状況</h2>
            <div class="admin-chart-container" style="position: relative; height: 300px;">
                <canvas id="loginActivityChart"></canvas>
            </div>
        </section>

        <!-- ユーザー別平均評価ランキング TOP10（横棒グラフ＋一覧） -->
        <section class="admin-widget">
            <h2 class="admin-widget-title">ユーザー別平均評価ランキング TOP10</h2>
            <div class="admin-chart-container" style="position: relative; height: 400px;">
                <canvas id="userRatingRankingChart"></canvas>
            </div>
            <table class="admin-user-ranking-table">
                <thead>
                    <tr>
                        <th>順位</th>
                        <th>ユーザー名</th>
                        <th>レビュー件数</th>
                        <th>平均評価</th>
                    </tr>
                </thead>
                <tbody id="userRatingRankingTableBody">
                </tbody>
            </table>
        </section>
    </section>

        </div>
    </div>
</main>

<?php require '../../meetory-footer.php'; ?>

<script>
(function () {
    'use strict';

    // ネイビー系配色
    const NAVY = '#1a3a5c';
    const NAVY_LIGHT = 'rgba(26, 58, 92, 0.1)';
    const GREEN = '#0f766e';
    const GREEN_LIGHT = 'rgba(15, 118, 110, 0.1)';
    const PURPLE = '#6b21a8';
    const PURPLE_LIGHT = 'rgba(107, 33, 168, 0.1)';
    const ORANGE = '#d97706';
    const ORANGE_LIGHT = 'rgba(217, 119, 6, 0.1)';
    const INDIGO = '#4f46e5';
    const INDIGO_LIGHT = 'rgba(79, 70, 229, 0.1)';
    const ROSE = '#e11d48';
    const ROSE_LIGHT = 'rgba(225, 29, 72, 0.1)';
    const AMBER = '#d97706';
    const AMBER_LIGHT = 'rgba(217, 119, 6, 0.1)';
    const TEAL = '#0d9488';
    const TEAL_LIGHT = 'rgba(13, 148, 136, 0.1)';

    // Chart.js インスタンス保存用
    let purchaseChart = null;
    let completedChart = null;
    let salesChart = null;
    let categoryListingChart = null;
    let salesStatusChart = null;
    let productRankingChart = null;
    let categorySalesChart = null;
    let userRegistrationChart = null;
    let userPurchaseRankingChart = null;
    let userListingRankingChart = null;
    let loginActivityChart = null;
    let userRatingRankingChart = null;

    // API URL
    const API_URL = './api/analysis-data.php';

    // 自動更新制御
    let isFetching = false;
    let refreshTimer = null;
    const REFRESH_INTERVAL = 10000; // 10秒

    // 折れ線グラフ：月別購入件数
    function drawPurchaseCountChart(labels, data) {
        const ctx = document.getElementById('purchaseCountChart');
        if (!ctx) {
            return;
        }

        if (purchaseChart) {
            purchaseChart.destroy();
        }

        purchaseChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '購入件数',
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
                        intersect: false,
                        callbacks: {
                            label: function (context) {
                                return context.parsed.y + '件';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            maxTicksLimit: 10
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

    // 折れ線グラフ：月別取引完了件数
    function drawCompletedCountChart(labels, data) {
        const ctx = document.getElementById('completedCountChart');
        if (!ctx) {
            return;
        }

        if (completedChart) {
            completedChart.destroy();
        }

        completedChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '取引完了件数',
                    data: data,
                    borderColor: GREEN,
                    backgroundColor: GREEN_LIGHT,
                    borderWidth: 2,
                    pointBackgroundColor: GREEN,
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
                        intersect: false,
                        callbacks: {
                            label: function (context) {
                                return context.parsed.y + '件';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            maxTicksLimit: 10
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

    // 棒グラフ：月別売上金額
    function drawSalesAmountChart(labels, data) {
        const ctx = document.getElementById('salesAmountChart');
        if (!ctx) {
            return;
        }

        if (salesChart) {
            salesChart.destroy();
        }

        salesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '売上金額（円）',
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
                        intersect: false,
                        callbacks: {
                            label: function (context) {
                                return context.parsed.y.toLocaleString() + '円';
                            }
                        }
                    }
                },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                maxTicksLimit: 10
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

    // 円グラフ：カテゴリー別出品数
    function drawCategoryListingChart(labels, data) {
        const ctx = document.getElementById('categoryListingChart');
        if (!ctx) {
            return;
        }

        if (categoryListingChart) {
            categoryListingChart.destroy();
        }

        categoryListingChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    label: '出品数',
                    data: data,
                    backgroundColor: [
                        PURPLE, '#8b5cf6', '#a855f7', '#c084fc', '#d8b4fe',
                        '#e9d5ff', '#f0abfc', '#fae8ff', '#fce7f3', '#fce7f3'
                    ],
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { font: { size: 11 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.label + ': ' + context.parsed + '件';
                            }
                        }
                    }
                }
            }
        });
    }

    // ドーナツグラフ：商品の販売状況
    function drawSalesStatusChart(labels, data) {
        const ctx = document.getElementById('salesStatusChart');
        if (!ctx) {
            return;
        }

        if (salesStatusChart) {
            salesStatusChart.destroy();
        }

        salesStatusChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    label: '販売状況',
                    data: data,
                    backgroundColor: [GREEN, '#f59e0b', '#6b7280'],
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { font: { size: 12 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.label + ': ' + context.parsed + '件';
                            }
                        }
                    }
                }
            }
        });
    }

    // 横棒グラフ：商品別売上ランキング TOP10
    function drawProductRankingChart(labels, data) {
        const ctx = document.getElementById('productRankingChart');
        if (!ctx) {
            return;
        }

        if (productRankingChart) {
            productRankingChart.destroy();
        }

        productRankingChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '売上金額（円）',
                    data: data,
                    backgroundColor: ORANGE,
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.parsed.x.toLocaleString() + '円';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            maxTicksLimit: 10
                        },
                        title: {
                            display: false
                        }
                    },
                    y: {
                        title: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // 横棒グラフ：カテゴリー別売上金額
    function drawCategorySalesChart(labels, data) {
        const ctx = document.getElementById('categorySalesChart');
        if (!ctx) {
            return;
        }

        if (categorySalesChart) {
            categorySalesChart.destroy();
        }

        categorySalesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '売上金額（円）',
                    data: data,
                    backgroundColor: INDIGO,
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.parsed.x.toLocaleString() + '円';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            maxTicksLimit: 10
                        },
                        title: {
                            display: false
                        }
                    },
                    y: {
                        title: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // 折れ線グラフ：月別ユーザー登録推移（一般ユーザー）
    function drawUserRegistrationCountChart(labels, data) {
        const ctx = document.getElementById('userRegistrationCountChart');
        if (!ctx) {
            return;
        }

        if (userRegistrationChart) {
            userRegistrationChart.destroy();
        }

        userRegistrationChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '新規ユーザー登録数（一般）',
                    data: data,
                    borderColor: ROSE,
                    backgroundColor: ROSE_LIGHT,
                    borderWidth: 2,
                    pointBackgroundColor: ROSE,
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
                        intersect: false,
                        callbacks: {
                            label: function (context) {
                                return context.parsed.y + '人';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            maxTicksLimit: 10
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

    // 横棒グラフ：ユーザー別購入回数ランキング TOP10
    function drawUserPurchaseRankingChart(labels, data) {
        const ctx = document.getElementById('userPurchaseRankingChart');
        if (!ctx) {
            return;
        }

        if (userPurchaseRankingChart) {
            userPurchaseRankingChart.destroy();
        }

        userPurchaseRankingChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '購入件数',
                    data: data,
                    backgroundColor: TEAL,
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.parsed.x + '件';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            maxTicksLimit: 10
                        },
                        title: {
                            display: false
                        }
                    },
                    y: {
                        title: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // 横棒グラフ：ユーザー別出品数ランキング TOP10
    function drawUserListingRankingChart(labels, data) {
        const ctx = document.getElementById('userListingRankingChart');
        if (!ctx) {
            return;
        }

        if (userListingRankingChart) {
            userListingRankingChart.destroy();
        }

        userListingRankingChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '出品件数（合計）',
                    data: data,
                    backgroundColor: AMBER,
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.parsed.x + '件';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            maxTicksLimit: 10
                        },
                        title: {
                            display: false
                        }
                    },
                    y: {
                        title: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // 折れ線グラフ：月別ログイン活動状況（成功・失敗2系列）
    function drawLoginActivityChart(labels, successData, failData) {
        const ctx = document.getElementById('loginActivityChart');
        if (!ctx) {
            return;
        }

        if (loginActivityChart) {
            loginActivityChart.destroy();
        }

        loginActivityChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'ログイン成功',
                    data: successData,
                    borderColor: GREEN,
                    backgroundColor: GREEN_LIGHT,
                    borderWidth: 2,
                    pointBackgroundColor: GREEN,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    fill: false,
                    tension: 0.3
                }, {
                    label: 'ログイン失敗',
                    data: failData,
                    borderColor: ROSE,
                    backgroundColor: ROSE_LIGHT,
                    borderWidth: 2,
                    pointBackgroundColor: ROSE,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    fill: false,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 12 } }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function (context) {
                                return context.dataset.label + ': ' + context.parsed.y + '回';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            maxTicksLimit: 10
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

    // 横棒グラフ：ユーザー別平均評価ランキング TOP10
    function drawUserRatingRankingChart(labels, data) {
        const ctx = document.getElementById('userRatingRankingChart');
        if (!ctx) {
            return;
        }

        if (userRatingRankingChart) {
            userRatingRankingChart.destroy();
        }

        userRatingRankingChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '平均評価',
                    data: data,
                    backgroundColor: INDIGO,
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.parsed.x.toFixed(2) + '点';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 5,
                        ticks: {
                            maxTicksLimit: 10
                        },
                        title: {
                            display: false
                        }
                    },
                    y: {
                        title: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // APIからデータを取得
    async function fetchAnalysisData() {
        if (isFetching) {
            return;
        }

        isFetching = true;

        try {
            const response = await fetch(API_URL, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'データの取得に失敗しました。');
            }

            // 月ラベル
            const labels = data.monthLabels || [];

            // 購入件数グラフ更新
            if (Array.isArray(data.purchaseCounts)) {
                const purchaseLabels = data.purchaseCounts.map((item) => item.month);
                const purchaseValues = data.purchaseCounts.map((item) => item.count);
                if (purchaseChart) {
                    purchaseChart.data.labels = purchaseLabels;
                    purchaseChart.data.datasets[0].data = purchaseValues;
                    purchaseChart.update();
                } else {
                    drawPurchaseCountChart(purchaseLabels, purchaseValues);
                }
            }

            // 取引完了件数グラフ更新
            if (Array.isArray(data.completedCounts)) {
                const completedLabels = data.completedCounts.map((item) => item.month);
                const completedValues = data.completedCounts.map((item) => item.count);
                if (completedChart) {
                    completedChart.data.labels = completedLabels;
                    completedChart.data.datasets[0].data = completedValues;
                    completedChart.update();
                } else {
                    drawCompletedCountChart(completedLabels, completedValues);
                }
            }

            // 売上金額グラフ更新
            if (Array.isArray(data.salesAmounts)) {
                const salesLabels = data.salesAmounts.map((item) => item.month);
                const salesValues = data.salesAmounts.map((item) => item.amount);
                if (salesChart) {
                    salesChart.data.labels = salesLabels;
                    salesChart.data.datasets[0].data = salesValues;
                    salesChart.update();
                } else {
                    drawSalesAmountChart(salesLabels, salesValues);
                }
            }

            // カテゴリー別出品数グラフ更新
            if (Array.isArray(data.categoryListingData)) {
                const catLabels = data.categoryListingData.map((item) => item.label);
                const catValues = data.categoryListingData.map((item) => item.count);
                if (categoryListingChart) {
                    categoryListingChart.data.labels = catLabels;
                    categoryListingChart.data.datasets[0].data = catValues;
                    categoryListingChart.update();
                } else {
                    drawCategoryListingChart(catLabels, catValues);
                }
            }

            // 商品の販売状況グラフ更新
            if (Array.isArray(data.salesStatusData)) {
                const statusLabels = data.salesStatusData.map((item) => item.label);
                const statusValues = data.salesStatusData.map((item) => item.count);
                if (salesStatusChart) {
                    salesStatusChart.data.labels = statusLabels;
                    salesStatusChart.data.datasets[0].data = statusValues;
                    salesStatusChart.update();
                } else {
                    drawSalesStatusChart(statusLabels, statusValues);
                }
            }

            // 商品別売上ランキング更新
            if (Array.isArray(data.productRankingData)) {
                const rankingLabels = data.productRankingData.map((item) => item.goods_name);
                const rankingValues = data.productRankingData.map((item) => item.total_sales);
                if (productRankingChart) {
                    productRankingChart.data.labels = rankingLabels;
                    productRankingChart.data.datasets[0].data = rankingValues;
                    productRankingChart.update();
                } else {
                    drawProductRankingChart(rankingLabels, rankingValues);
                }

                // ランキング一覧テーブル更新
                const tbody = document.getElementById('productRankingTableBody');
                if (tbody) {
                    tbody.innerHTML = '';
                    data.productRankingData.forEach((item, index) => {
                        const tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>' + (index + 1) + '</td>' +
                            '<td>' + escapeHtml(item.goods_name) + '</td>' +
                            '<td>' + escapeHtml(item.category_name) + '</td>' +
                            '<td>' + item.purchase_count.toLocaleString() + '</td>' +
                            '<td>' + item.total_sales.toLocaleString() + '円</td>';
                        tbody.appendChild(tr);
                    });
                }
            }

            // カテゴリー別売上金額グラフ更新
            if (Array.isArray(data.categorySalesData)) {
                const catSalesLabels = data.categorySalesData.map((item) => item.label);
                const catSalesValues = data.categorySalesData.map((item) => item.amount);
                if (categorySalesChart) {
                    categorySalesChart.data.labels = catSalesLabels;
                    categorySalesChart.data.datasets[0].data = catSalesValues;
                    categorySalesChart.update();
                } else {
                    drawCategorySalesChart(catSalesLabels, catSalesValues);
                }
            }

            // 月別ユーザー登録推移グラフ更新
            if (Array.isArray(data.userRegistrationData)) {
                const regLabels = data.userRegistrationData.map((item) => item.month);
                const regValues = data.userRegistrationData.map((item) => item.count);
                if (userRegistrationChart) {
                    userRegistrationChart.data.labels = regLabels;
                    userRegistrationChart.data.datasets[0].data = regValues;
                    userRegistrationChart.update();
                } else {
                    drawUserRegistrationCountChart(regLabels, regValues);
                }
            }

            // ユーザー別購入回数ランキング更新
            if (Array.isArray(data.userPurchaseRankingData)) {
                const purLabels = data.userPurchaseRankingData.map((item) => item.user_name);
                const purValues = data.userPurchaseRankingData.map((item) => item.purchase_count);
                if (userPurchaseRankingChart) {
                    userPurchaseRankingChart.data.labels = purLabels;
                    userPurchaseRankingChart.data.datasets[0].data = purValues;
                    userPurchaseRankingChart.update();
                } else {
                    drawUserPurchaseRankingChart(purLabels, purValues);
                }

                const tbody = document.getElementById('userPurchaseRankingTableBody');
                if (tbody) {
                    tbody.innerHTML = '';
                    data.userPurchaseRankingData.forEach((item, index) => {
                        const tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>' + (index + 1) + '</td>' +
                            '<td>' + escapeHtml(item.user_name) + '</td>' +
                            '<td>' + item.purchase_count.toLocaleString() + '</td>';
                        tbody.appendChild(tr);
                    });
                }
            }

            // ユーザー別出品数ランキング更新
            if (Array.isArray(data.userListingRankingData)) {
                const listLabels = data.userListingRankingData.map((item) => item.user_name);
                const listValues = data.userListingRankingData.map((item) => item.listing_count);
                if (userListingRankingChart) {
                    userListingRankingChart.data.labels = listLabels;
                    userListingRankingChart.data.datasets[0].data = listValues;
                    userListingRankingChart.update();
                } else {
                    drawUserListingRankingChart(listLabels, listValues);
                }

                const tbody = document.getElementById('userListingRankingTableBody');
                if (tbody) {
                    tbody.innerHTML = '';
                    data.userListingRankingData.forEach((item, index) => {
                        const tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>' + (index + 1) + '</td>' +
                            '<td>' + escapeHtml(item.user_name) + '</td>' +
                            '<td>' + item.listing_count.toLocaleString() + '</td>' +
                            '<td>' + item.active_count.toLocaleString() + '</td>' +
                            '<td>' + item.inactive_count.toLocaleString() + '</td>' +
                            '<td>' + item.sold_count.toLocaleString() + '</td>';
                        tbody.appendChild(tr);
                    });
                }
            }

            // ユーザー別平均評価ランキング更新
            if (Array.isArray(data.userRatingRankingData)) {
                const rateLabels = data.userRatingRankingData.map((item) => item.user_name);
                const rateValues = data.userRatingRankingData.map((item) => item.avg_rating);
                if (userRatingRankingChart) {
                    userRatingRankingChart.data.labels = rateLabels;
                    userRatingRankingChart.data.datasets[0].data = rateValues;
                    userRatingRankingChart.update();
                } else {
                    drawUserRatingRankingChart(rateLabels, rateValues);
                }

                const tbody = document.getElementById('userRatingRankingTableBody');
                if (tbody) {
                    tbody.innerHTML = '';
                    data.userRatingRankingData.forEach((item, index) => {
                        const tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>' + (index + 1) + '</td>' +
                            '<td>' + escapeHtml(item.user_name) + '</td>' +
                            '<td>' + item.review_count.toLocaleString() + '</td>' +
                            '<td>' + item.avg_rating.toFixed(2) + '</td>';
                        tbody.appendChild(tr);
                    });
                }
            }

            // 月別ログイン活動状況グラフ更新
            if (Array.isArray(data.loginActivityData)) {
                const loginLabels = data.loginActivityData.map((item) => item.month);
                const successValues = data.loginActivityData.map((item) => item.success_count);
                const failValues = data.loginActivityData.map((item) => item.fail_count);
                if (loginActivityChart) {
                    loginActivityChart.data.labels = loginLabels;
                    loginActivityChart.data.datasets[0].data = successValues;
                    loginActivityChart.data.datasets[1].data = failValues;
                    loginActivityChart.update();
                } else {
                    drawLoginActivityChart(loginLabels, successValues, failValues);
                }
            }

        } catch (error) {
            console.error('分析データ取得エラー:', error);
        } finally {
            isFetching = false;
        }
    }

    // HTMLエスケープ
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // 自動更新開始
    function startAnalysisAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
        }
        refreshTimer = setInterval(() => {
            if (document.visibilityState === 'visible') {
                fetchAnalysisData();
            }
        }, REFRESH_INTERVAL);
    }

    // 自動更新停止
    function stopAnalysisAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
    }

    // 初期化
    function initAnalysis() {
        fetchAnalysisData();
        startAnalysisAutoRefresh();

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                startAnalysisAutoRefresh();
                fetchAnalysisData();
            } else {
                stopAnalysisAutoRefresh();
            }
        });

        window.addEventListener('beforeunload', () => {
            stopAnalysisAutoRefresh();
        });
    }

    // DOM読み込み完了後に初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAnalysis);
    } else {
        initAnalysis();
    }
})();
</script>

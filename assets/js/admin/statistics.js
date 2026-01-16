/**
 * OrderChatz 統計頁面 JavaScript
 *
 * 處理統計數據的載入、顯示和圖表渲染
 *
 * @package OrderChatz
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * 統計頁面管理器
     */
    window.StatisticsManager = {
        /**
         * Chart.js 實例
         */
        charts: {
            dailyMessages: null,
            messageTypes: null
        },

        /**
         * 載入狀態
         */
        isLoading: {
            quota: false,
            consumption: false,
            friends: false,
            messages: false,
            multiDay: false
        },

        /**
         * 儲存原始配額值，避免從格式化的 DOM 讀取造成錯誤
         */
        quotaTotal: 0,

        /**
         * 初始化
         */
        init: function () {
            this.bindEvents();
            this.loadAllStatistics();
        },

        /**
         * 綁定事件
         */
        bindEvents: function () {
            const self = this;

            // 重新整理按鈕
            $('#refresh-statistics').on('click', function () {
                self.loadAllStatistics();
            });

            // 重試按鈕
            $('#retry-statistics').on('click', function () {
                self.loadAllStatistics();
            });

            // 圖表期間選擇
            $('#daily-chart-period').on('change', function () {
                const days = parseInt($(this).val());
                self.loadMultiDayStatistics(days);
            });
        },

        /**
         * 載入所有統計數據
         */
        loadAllStatistics: function () {
            this.hideError();
            this.loadQuotaAndConsumption();
            this.loadTodayMessageStats();
            this.loadFriendsStatistics();
            this.loadMultiDayStatistics(30);
        },

        /**
         * 載入配額和使用量
         */
        loadQuotaAndConsumption: function () {
            const self = this;

            // 顯示載入指示器
            this.showLoading('quota');

            // 使用 Promise 確保兩個 API 都完成後再計算剩餘配額
            const quotaPromise = $.ajax({
                url: otzStatisticsConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_get_message_quota',
                    nonce: otzStatisticsConfig.nonce
                }
            });

            const consumptionPromise = $.ajax({
                url: otzStatisticsConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_get_message_consumption',
                    nonce: otzStatisticsConfig.nonce
                }
            });

            // 使用 Promise.allSettled 來處理兩個請求，即使有一個失敗也不會終止
            Promise.allSettled([quotaPromise, consumptionPromise])
                .then(function (results) {
                    const [quotaResult, consumptionResult] = results;

                    let hasQuota = false;
                    let hasConsumption = false;

                    // 處理配額資料
                    if (quotaResult.status === 'fulfilled' && quotaResult.value.success) {
                        self.updateQuotaDisplay(quotaResult.value.data.quota);
                        hasQuota = true;
                    } else {
                        console.warn('載入配額失敗:', quotaResult.reason || quotaResult.value?.data?.message);
                    }

                    // 處理使用量資料
                    if (consumptionResult.status === 'fulfilled' && consumptionResult.value.success) {
                        self.updateConsumptionDisplay(consumptionResult.value.data.consumption);
                        hasConsumption = true;
                    } else {
                        console.warn('載入使用量失敗:', consumptionResult.reason || consumptionResult.value?.data?.message);
                    }

                    // 只有在兩個請求都失敗時才顯示錯誤
                    if (!hasQuota && !hasConsumption) {
                        self.showError('無法載入配額資訊，請檢查 LINE API 設定');
                    }
                })
                .finally(function () {
                    self.hideLoading('quota');
                });
        },

        /**
         * 載入今日訊息統計
         */
        loadTodayMessageStats: function () {
            const self = this;

            this.showLoading(['reply', 'push', 'multicast', 'broadcast']);

            $.ajax({
                url: otzStatisticsConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_get_message_delivery_stats',
                    date: this.getTodayDate(),
                    nonce: otzStatisticsConfig.nonce
                },
                success: function (response) {
                    if (response.success) {
                        self.updateMessageStatsDisplay(response.data.stats);
                    } else {
                        console.warn('載入訊息統計失敗:', response.data.message);
                    }
                },
                error: function () {
                    console.warn('載入訊息統計網路錯誤');
                },
                complete: function () {
                    self.hideLoading(['reply', 'push', 'multicast', 'broadcast']);
                }
            });
        },

        /**
         * 載入好友統計
         */
        loadFriendsStatistics: function () {
            const self = this;

            this.showLoading(['friends', 'followers']);

            $.ajax({
                url: otzStatisticsConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_get_friends_statistics',
                    date: this.getTodayDate(),
                    nonce: otzStatisticsConfig.nonce
                },
                success: function (response) {
                    if (response.success) {
                        self.updateFriendsDisplay(response.data.friends);
                    } else {
                        console.warn('載入好友統計失敗:', response.data.message);
                    }
                },
                error: function () {
                    console.warn('載入好友統計網路錯誤');
                },
                complete: function () {
                    self.hideLoading(['friends', 'followers']);
                }
            });
        },

        /**
         * 載入多天統計數據
         */
        loadMultiDayStatistics: function (days = 30) {
            const self = this;

            this.showChartLoading(['daily', 'types']);

            $.ajax({
                url: otzStatisticsConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_get_multi_day_statistics',
                    days: days,
                    nonce: otzStatisticsConfig.nonce
                },
                success: function (response) {
                    if (response.success) {
                        self.updateChartsDisplay(response.data.statistics);
                        self.updateDetailedTable(response.data.statistics);
                    } else {
                        self.showError(response.data.message || '載入趨勢數據失敗');
                    }
                },
                error: function () {
                    self.showError('載入趨勢數據網路錯誤');
                },
                complete: function () {
                    self.hideChartLoading(['daily', 'types']);
                }
            });
        },

        /**
         * 更新配額顯示
         */
        updateQuotaDisplay: function (quota) {
            const total = quota.value || 0;
            this.quotaTotal = total;  // 儲存原始值以供後續計算使用
            $('#quota-total').text(this.formatNumber(total));
        },

        /**
         * 更新使用量顯示
         */
        updateConsumptionDisplay: function (consumption) {
            const used = consumption.totalUsage || 0;
            const total = this.quotaTotal || 0;  // 使用儲存的原始值，而非從 DOM 讀取
            const remaining = Math.max(0, total - used);
            const percentage = total > 0 ? ((used / total) * 100) : 0;

            $('#quota-used').text(this.formatNumber(used));
            $('#quota-remaining').text(this.formatNumber(remaining));
            $('#quota-percentage').text(percentage.toFixed(1) + '%');

            // 更新進度條
            $('#quota-progress-fill').css('width', Math.min(100, percentage) + '%');

            // 根據使用率設定顏色
            const progressBar = $('#quota-progress-fill');
            progressBar.removeClass('low medium high critical');
            if (percentage >= 90) {
                progressBar.addClass('critical');
            } else if (percentage >= 75) {
                progressBar.addClass('high');
            } else if (percentage >= 50) {
                progressBar.addClass('medium');
            } else {
                progressBar.addClass('low');
            }
        },

        /**
         * 更新訊息統計顯示
         */
        updateMessageStatsDisplay: function (stats) {
            $('#reply-count').text(this.formatNumber(stats.reply.success || 0));
            $('#push-count').text(this.formatNumber(stats.push.success || 0));
            $('#multicast-count').text(this.formatNumber(stats.multicast.success || 0));
            $('#broadcast-count').text(this.formatNumber(stats.broadcast.success || 0));
        },

        /**
         * 更新好友統計顯示
         */
        updateFriendsDisplay: function (friends) {
            $('#friends-count').text(this.formatNumber(friends.followers || 0));
            $('#followers-count').text(this.formatNumber(friends.targetedReaches || 0));
        },

        /**
         * 更新圖表顯示
         */
        updateChartsDisplay: function (statistics) {
            this.updateDailyMessagesChart(statistics);
            this.updateMessageTypesChart(statistics);
        },

        /**
         * 更新每日訊息圖表
         */
        updateDailyMessagesChart: function (statistics) {
            const ctx = document.getElementById('daily-messages-chart');
            if (!ctx) return;

            // 銷毀舊圖表
            if (this.charts.dailyMessages) {
                this.charts.dailyMessages.destroy();
            }

            const labels = statistics.map(item => {
                const date = new Date(item.date);
                return date.toLocaleDateString('zh-TW', {month: 'short', day: 'numeric'});
            });

            const replyData = statistics.map(item => item.reply || 0);
            const pushData = statistics.map(item => item.push || 0);
            const multicastData = statistics.map(item => item.multicast || 0);
            const broadcastData = statistics.map(item => item.broadcast || 0);

            this.charts.dailyMessages = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: '回覆訊息',
                            data: replyData,
                            borderColor: '#007cba',
                            backgroundColor: 'rgba(0, 124, 186, 0.1)',
                            fill: false,
                            tension: 0.4
                        },
                        {
                            label: '推播訊息',
                            data: pushData,
                            borderColor: '#00a32a',
                            backgroundColor: 'rgba(0, 163, 42, 0.1)',
                            fill: false,
                            tension: 0.4
                        },
                        {
                            label: '群發訊息',
                            data: multicastData,
                            borderColor: '#ff6900',
                            backgroundColor: 'rgba(255, 105, 0, 0.1)',
                            fill: false,
                            tension: 0.4
                        },
                        {
                            label: '廣播訊息',
                            data: broadcastData,
                            borderColor: '#dc3232',
                            backgroundColor: 'rgba(220, 50, 50, 0.1)',
                            fill: false,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (value) {
                                    return value.toLocaleString();
                                }
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
        },

        /**
         * 更新訊息類型圖表
         */
        updateMessageTypesChart: function (statistics) {
            const ctx = document.getElementById('message-types-chart');
            if (!ctx) return;

            // 銷毀舊圖表
            if (this.charts.messageTypes) {
                this.charts.messageTypes.destroy();
            }

            // 計算總計
            let totalReply = 0, totalPush = 0, totalMulticast = 0, totalBroadcast = 0;
            statistics.forEach(item => {
                totalReply += item.reply || 0;
                totalPush += item.push || 0;
                totalMulticast += item.multicast || 0;
                totalBroadcast += item.broadcast || 0;
            });

            const data = [totalReply, totalPush, totalMulticast, totalBroadcast];
            const labels = ['回覆訊息', '推播訊息', '群發訊息', '廣播訊息'];
            const colors = ['#007cba', '#00a32a', '#ff6900', '#dc3232'];

            this.charts.messageTypes = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                    return context.label + ': ' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        },

        /**
         * 更新詳細統計表格
         */
        updateDetailedTable: function (statistics) {
            const tbody = $('#detailed-stats-tbody');
            tbody.empty();

            if (statistics.length === 0) {
                tbody.append('<tr><td colspan="6" class="text-center">暫無數據</td></tr>');
                return;
            }

            // 只顯示最近 7 天的數據
            const recentStats = statistics.slice(-7);

            recentStats.forEach(item => {
                const total = (item.reply || 0) + (item.push || 0) + (item.multicast || 0) + (item.broadcast || 0);
                const row = `
                    <tr>
                        <td>${this.formatDate(item.date)}</td>
                        <td>${this.formatNumber(item.reply || 0)}</td>
                        <td>${this.formatNumber(item.push || 0)}</td>
                        <td>${this.formatNumber(item.multicast || 0)}</td>
                        <td>${this.formatNumber(item.broadcast || 0)}</td>
                        <td><strong>${this.formatNumber(total)}</strong></td>
                    </tr>
                `;
                tbody.append(row);
            });
        },

        /**
         * 顯示載入指示器
         */
        showLoading: function (indicators) {
            if (typeof indicators === 'string') {
                indicators = [indicators];
            }

            indicators.forEach(indicator => {
                $(`#${indicator}-loading`).show();
                this.isLoading[indicator] = true;
            });
        },

        /**
         * 隱藏載入指示器
         */
        hideLoading: function (indicators) {
            if (typeof indicators === 'string') {
                indicators = [indicators];
            }

            indicators.forEach(indicator => {
                $(`#${indicator}-loading`).hide();
                this.isLoading[indicator] = false;
            });
        },

        /**
         * 顯示圖表載入
         */
        showChartLoading: function (charts) {
            charts.forEach(chart => {
                $(`#${chart}-chart-loading`).show();
            });
        },

        /**
         * 隱藏圖表載入
         */
        hideChartLoading: function (charts) {
            charts.forEach(chart => {
                $(`#${chart}-chart-loading`).hide();
            });
        },

        /**
         * 顯示錯誤訊息
         */
        showError: function (message) {
            $('#error-message').text(message);
            $('#error-section').show();
        },

        /**
         * 隱藏錯誤訊息
         */
        hideError: function () {
            $('#error-section').hide();
        },

        /**
         * 格式化數字
         */
        formatNumber: function (num) {
            // 直接使用 toLocaleString 來顯示完整數字，包含千分位逗號
            return num.toLocaleString();
        },

        /**
         * 格式化日期
         */
        formatDate: function (dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('zh-TW', {
                month: 'short',
                day: 'numeric'
            });
        },

        /**
         * 取得今日日期
         */
        getTodayDate: function () {
            return new Date().toISOString().split('T')[0];
        }
    };

    // 當 DOM 準備完成時初始化
    $(document).ready(function () {
        StatisticsManager.init();
    });

})(jQuery);
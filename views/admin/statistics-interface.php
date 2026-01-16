<?php
/**
 * OrderChatz 統計介面模板
 *
 * LINE Messaging API 使用統計介面
 *
 * @package OrderChatz
 * @since 1.0.0
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="orderchatz-statistics-interface">
	<div class="statistics-header">
		<div class="header-actions">
		</div>
	</div>

	<div class="statistics-content">
		<!-- 配額概覽 -->
		<div class="statistics-section quota-overview">
			<h3><?php _e( '訊息配額概覽', 'otz' ); ?></h3>
			<div class="quota-cards">
				<div class="quota-card">
					<div class="quota-card-header">
						<h4><?php _e( '本月配額', 'otz' ); ?></h4>
						<span class="loading-indicator" id="quota-loading">
							<span class="dashicons dashicons-update spin"></span>
						</span>
					</div>
					<div class="quota-card-content">
						<div class="quota-number" id="quota-total">--</div>
						<div class="quota-label"><?php _e( '訊息數', 'otz' ); ?></div>
					</div>
				</div>

				<div class="quota-card">
					<div class="quota-card-header">
						<h4><?php _e( '已使用', 'otz' ); ?></h4>
					</div>
					<div class="quota-card-content">
						<div class="quota-number consumption" id="quota-used">--</div>
						<div class="quota-label"><?php _e( '訊息數', 'otz' ); ?></div>
					</div>
				</div>

				<div class="quota-card">
					<div class="quota-card-header">
						<h4><?php _e( '剩餘配額', 'otz' ); ?></h4>
					</div>
					<div class="quota-card-content">
						<div class="quota-number remaining" id="quota-remaining">--</div>
						<div class="quota-label"><?php _e( '訊息數', 'otz' ); ?></div>
					</div>
				</div>

				<div class="quota-card">
					<div class="quota-card-header">
						<h4><?php _e( '使用率', 'otz' ); ?></h4>
					</div>
					<div class="quota-card-content">
						<div class="quota-number percentage" id="quota-percentage">--%</div>
						<div class="quota-progress">
							<div class="progress-bar">
								<div class="progress-fill" id="quota-progress-fill" style="width: 0%;"></div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div style="display:flex; justify-content: flex-end; margin-top: 10px;">
				<button type="button" class="button button-primary" id="refresh-statistics" style="display: flex; align-items: center;">
					<span class="dashicons dashicons-update"></span>
					<?php _e( '重新整理', 'otz' ); ?>
				</button>
			</div>
			
		</div>

		<!-- 好友統計 -->
		<div class="statistics-section friend-stats">
			<h3><?php _e( '好友統計', 'otz' ); ?></h3>
			<div class="stats-grid">
				<div class="stats-card wide">
					<div class="stats-card-header">
						<h4><?php _e( '好友數量', 'otz' ); ?></h4>
						<span class="loading-indicator" id="friends-loading">
							<span class="dashicons dashicons-update spin"></span>
						</span>
					</div>
					<div class="stats-card-content">
						<div class="stats-number" id="friends-count">--</div>
						<div class="stats-label"><?php _e( '總好友數', 'otz' ); ?></div>
					</div>
				</div>

				<div class="stats-card wide">
					<div class="stats-card-header">
						<h4><?php _e( '目標觸及數', 'otz' ); ?></h4>
						<span class="loading-indicator" id="followers-loading">
							<span class="dashicons dashicons-update spin"></span>
						</span>
					</div>
					<div class="stats-card-content">
						<div class="stats-number" id="followers-count">--</div>
						<div class="stats-label"><?php _e( '可觸及用戶', 'otz' ); ?></div>
					</div>
				</div>
			</div>
		</div>

		<!-- 圖表區域 -->
		<div class="statistics-section charts-section">
			<h3><?php _e( '使用趨勢圖表', 'otz' ); ?></h3>
			<div class="charts-container">
				<div class="chart-card">
					<div class="chart-header">
						<h4><?php _e( '每日訊息發送量', 'otz' ); ?></h4>
						<div class="chart-controls">
							<select id="daily-chart-period">
								<option value="7"><?php _e( '近 7 天', 'otz' ); ?></option>
								<option value="30" selected><?php _e( '近 30 天', 'otz' ); ?></option>
								<option value="90"><?php _e( '近 90 天', 'otz' ); ?></option>
							</select>
						</div>
					</div>
					<div class="chart-content">
						<canvas id="daily-messages-chart" width="400" height="200"></canvas>
						<div class="chart-loading" id="daily-chart-loading">
							<span class="dashicons dashicons-update spin"></span>
							<p><?php _e( '載入圖表中...', 'otz' ); ?></p>
						</div>
					</div>
				</div>

				<div class="chart-card">
					<div class="chart-header">
						<h4><?php _e( '訊息類型分佈', 'otz' ); ?></h4>
					</div>
					<div class="chart-content">
						<canvas id="message-types-chart" width="400" height="200"></canvas>
						<div class="chart-loading" id="types-chart-loading">
							<span class="dashicons dashicons-update spin"></span>
							<p><?php _e( '載入圖表中...', 'otz' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- 詳細統計表格 -->
		<div class="statistics-section detailed-stats">
			<h3><?php _e( '詳細統計', 'otz' ); ?></h3>
			<div class="stats-table-container">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php _e( '日期', 'otz' ); ?></th>
							<th><?php _e( '回覆訊息', 'otz' ); ?></th>
							<th><?php _e( '推播訊息', 'otz' ); ?></th>
							<th><?php _e( '群發訊息', 'otz' ); ?></th>
							<th><?php _e( '廣播訊息', 'otz' ); ?></th>
							<th><?php _e( '總計', 'otz' ); ?></th>
						</tr>
					</thead>
					<tbody id="detailed-stats-tbody">
						<tr>
							<td colspan="6" class="text-center">
								<span class="loading-indicator">
									<span class="dashicons dashicons-update spin"></span>
									<?php _e( '載入統計數據中...', 'otz' ); ?>
								</span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- 錯誤訊息區域 -->
		<div class="statistics-section error-section" id="error-section" style="display: none;">
			<div class="notice notice-error" style="display: none;">
				<p id="error-message"></p>
				<p>
					<button type="button" class="button" id="retry-statistics">
						<?php _e( '重試', 'otz' ); ?>
					</button>
				</p>
			</div>
		</div>
	</div>
</div>

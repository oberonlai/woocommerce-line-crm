<?php
/**
 * LINE 官方帳號好友匯入燈箱模板
 *
 * @package OrderChatz
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<!-- LINE 官方帳號好友匯入燈箱 -->
<div id="sync-line-friends-modal" class="otz-modal" style="display: none;">
	<div class="otz-modal-backdrop"></div>
	<div class="otz-modal-content">
		<div class="otz-modal-header">
			<h2><?php echo esc_html__( 'Import LINE Official Account Friends', 'otz' ); ?></h2>
			<button type="button" class="otz-modal-close">&times;</button>
		</div>
		
		<div class="otz-modal-body">
			<div id="line-sync-info-step" class="line-sync-step active">
				<div class="line-sync-info-content">
					<div style="margin: 20px 0;">
						<h2><strong><?php echo esc_html__( 'Important Notice!', 'otz' ); ?></strong></h2>
						<h3 style="line-height:1.4"><span style="color:red"><?php echo esc_html__( 'Only verified LINE Official Accounts (Blue or Green Badge)', 'otz' ); ?> </span><br><?php echo esc_html__( 'can retrieve friend lists for import', 'otz' ); ?></h3>
						<p><img style="width:100%" src="https://oberonlai.blog/wp-content/uploads/2025/08/CleanShot-2025-09-02-at-13.02.22-scaled.jpg"></p>
						<a href="https://tw.linebiz.com/service/account-solutions/line-official-account/online-solutions-partner/" target="_blank" class="button button-link">
							<?php echo esc_html__( 'LINE Official Account Verification Guide', 'otz' ); ?>
						</a>
					</div>
					

				</div>
			</div>
			
			<div id="line-sync-loading-step" class="line-sync-step" style="display: none;">
				<div class="line-sync-loading-content">
					<h3><?php echo esc_html__( 'Fetching LINE friend list...', 'otz' ); ?></h3>
					<div class="progress-bar-container" style="width: 100%; height: 20px; background: #f1f1f1; border-radius: 10px; margin: 20px 0; overflow: hidden;">
						<div id="line-sync-loading-bar" class="progress-bar" style="height: 100%; background: linear-gradient(90deg, #00C300, #00A300); width: 0%; transition: width 0.3s ease; border-radius: 10px;"></div>
					</div>
					<div id="line-sync-loading-text" class="sync-status"><?php echo esc_html__( 'Connecting to LINE API...', 'otz' ); ?></div>
				</div>
			</div>
			
			<div id="line-sync-select-step" class="line-sync-step" style="display: none;">
				<div class="line-sync-select-content">
					<h3><?php echo esc_html__( 'Select friends to import', 'otz' ); ?></h3>
					
					<div class="friends-selection-header" style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 5px;">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
							<label style="font-weight: 500;">
								<input type="checkbox" id="select-all-friends" style="margin-right: 8px;" />
								<?php echo esc_html__( 'Select All', 'otz' ); ?>
							</label>
							<div id="friends-statistics" style="color: #666; font-size: 14px;">
								<!-- 統計信息將在這裡顯示 -->
							</div>
						</div>
						<div id="selection-summary" style="font-size: 14px; color: #0073aa; font-weight: 500;">
							<?php echo esc_html__( 'Selected 0 friends', 'otz' ); ?>
						</div>
					</div>
					
					<div id="friends-list-container" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; background: white;">
						<!-- 好友清單將在這裡動態載入 -->
					</div>
					
				</div>
			</div>
			
			<div id="line-sync-progress-step" class="line-sync-step" style="display: none;">
				<div class="line-sync-progress-content">
					<h3><?php echo esc_html__( 'Importing selected friends...', 'otz' ); ?></h3>
					<div class="progress-bar-container" style="width: 100%; height: 20px; background: #f1f1f1; border-radius: 10px; margin: 20px 0; overflow: hidden;">
						<div id="line-sync-progress-bar" class="progress-bar" style="height: 100%; background: linear-gradient(90deg, #00C300, #00A300); width: 0%; transition: width 0.3s ease; border-radius: 10px;"></div>
					</div>
					<div id="line-sync-status-text" class="sync-status"><?php echo esc_html__( 'Preparing...', 'otz' ); ?></div>
					<div id="line-sync-details" class="sync-details" style="margin-top: 15px; font-size: 14px; color: #666;"></div>
				</div>
			</div>
			
			<div id="line-sync-complete-step" class="line-sync-step" style="display: none;">
				<div class="line-sync-complete-content">
					<div class="dashicons dashicons-yes-alt" style="font-size: 48px; color: #46b450; margin-bottom: 20px; width:48px;"></div>
					<h3><?php echo esc_html__( 'LINE friends import completed!', 'otz' ); ?></h3>
					<div id="line-sync-results" class="sync-results" style="margin: 20px 0;">
						<!-- 結果統計將在這裡顯示 -->
					</div>
				</div>
			</div>
		</div>
		
		<div class="otz-modal-footer">
			<div id="line-sync-info-actions" class="modal-actions active">
				<button type="button" class="button button-secondary otz-modal-close"><?php echo esc_html__( 'Close', 'otz' ); ?></button>
				<button type="button" id="start-line-sync-btn" class="button button-primary">
					<span class="dashicons dashicons-admin-users" style="margin-top: 3px;"></span>
					<?php echo esc_html__( 'Get Friend List', 'otz' ); ?>
				</button>
			</div>
			
			<div id="line-sync-loading-actions" class="modal-actions" style="display: none;">
				<button type="button" id="cancel-line-loading-btn" class="button button-secondary"><?php echo esc_html__( 'Cancel', 'otz' ); ?></button>
			</div>
			
			<div id="line-sync-select-actions" class="modal-actions" style="display: none;">
				<button type="button" class="button button-secondary otz-modal-close"><?php echo esc_html__( 'Close', 'otz' ); ?></button>
				<button type="button" id="reset-line-friends-btn" class="button button-secondary" style="margin-right: 10px;">
					<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
					<?php echo esc_html__( 'Refresh', 'otz' ); ?>
				</button>
				<button type="button" id="confirm-import-btn" class="button button-primary" disabled>
					<span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
					<?php echo esc_html__( 'Import Selected Friends', 'otz' ); ?>
				</button>
			</div>
			
			<div id="line-sync-progress-actions" class="modal-actions" style="display: none;">
				<button type="button" id="cancel-line-sync-btn" class="button button-secondary"><?php echo esc_html__( 'Cancel Import', 'otz' ); ?></button>
			</div>
			
			<div id="line-sync-complete-actions" class="modal-actions" style="display: none;">
				<button type="button" class="button button-secondary otz-modal-close"><?php echo esc_html__( 'Close', 'otz' ); ?></button>
				<button type="button" id="refresh-page-btn" class="button button-primary"><?php echo esc_html__( 'Refresh Page', 'otz' ); ?></button>
			</div>
		</div>
	</div>
</div>

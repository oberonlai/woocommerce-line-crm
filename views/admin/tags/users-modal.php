<?php
/**
 * 標籤好友清單燈箱模板
 *
 * 顯示使用特定標籤的所有好友
 *
 * @package OrderChatz
 * @since 1.0.0
 */

// 防止直接存取.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<!-- 標籤好友清單燈箱 -->
<div id="tag-users-modal" class="otz-modal">
	<div class="otz-modal-backdrop"></div>
	<div class="otz-modal-content">
		<div class="otz-modal-header">
			<h2 id="tag-users-modal-title"><?php echo __( '使用此標籤的好友', 'otz' ); ?></h2>
		</div>

		<div class="otz-modal-body">
			<!-- 好友列表表格 -->
			<div id="tag-users-content" class="tag-users-table" style="display: none;">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col"><?php _e( 'LINE 顯示名稱', 'otz' ); ?></th>
							<th scope="col"><?php _e( 'WordPress 使用者', 'otz' ); ?></th>
							<th scope="col"><?php _e( '最後活動時間', 'otz' ); ?></th>
						</tr>
					</thead>
					<tbody id="tag-users-tbody">
						<!-- 好友列表將在這裡動態載入 -->
					</tbody>
				</table>
			</div>

			<!-- 載入更多中 -->
			<div id="tag-users-loading-more" class="tag-users-loading-more" style="display: none;">
				<span class="dashicons dashicons-update"></span>
				<p><?php echo __( '載入更多中...', 'otz' ); ?></p>
			</div>

			<!-- 全部載入完畢 -->
			<div id="tag-users-no-more" class="tag-users-no-more" style="display: none;">
				<?php echo __( '已載入全部好友', 'otz' ); ?>
			</div>

			<!-- 初次載入中狀態 -->
			<div id="tag-users-loading" class="tag-users-loading">
				<div class="dashicons dashicons-update-alt"></div>
				<p><?php echo __( '載入好友資料中...', 'otz' ); ?></p>
			</div>

			<!-- 空狀態 -->
			<div id="tag-users-empty" class="no-tag-users" style="display: none;">
				<div class="dashicons dashicons-groups"></div>
				<p><?php echo __( '目前沒有好友使用此標籤', 'otz' ); ?></p>
			</div>
		</div>

		<div class="otz-modal-footer">
			<div class="tag-users-stats">
				<?php _e( '已載入', 'otz' ); ?> <strong id="tag-users-loaded-count">0</strong> /
				<?php _e( '總計', 'otz' ); ?> <strong id="tag-users-total-count">--</strong> <?php _e( '人', 'otz' ); ?>
			</div>
			<button type="button" class="button button-secondary otz-modal-close">
				<?php echo __( '關閉', 'otz' ); ?>
			</button>
		</div>
	</div>
</div>

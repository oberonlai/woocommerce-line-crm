<?php
/**
 * 備註編輯頁面
 *
 * @package OrderChatz\Views\Admin\Notes
 */

// 防止直接訪問
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	
	<hr class="wp-header-end">
	
	<form method="post" action="" id="post">
		<?php wp_nonce_field( 'orderchatz_admin_action', '_wpnonce' ); ?>
		<input type="hidden" name="action" value="update_note">
		<input type="hidden" name="note_id" value="<?php echo esc_attr( $note->id ); ?>">
		
		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">
				<div id="post-body-content">
					<!-- 備註內容編輯區域 -->
					<div id="titlediv">
						<div id="titlewrap">
							<label class="screen-reader-text" id="title-prompt-text" for="title"><?php _e( '備註內容', 'otz' ); ?></label>
						</div>
					</div>
					
					<div id="postdivrich" class="postarea wp-editor-expand">
						<div id="wp-content-wrap" class="wp-core-ui wp-editor-wrap tmce-active">
							<div id="wp-content-editor-tools" class="wp-editor-tools hide-if-no-js" style="padding: 0;">
								<div id="wp-content-media-buttons" class="wp-media-buttons">
									<!-- 可以在這裡添加媒體按鈕，目前保持簡潔 -->
								</div>
							</div>
							<div id="wp-content-editor-container" class="wp-editor-container">
								<textarea 
									class="wp-editor-area" 
									rows="20" 
									cols="40" 
									name="note_content" 
									id="note_content" 
									style="width: 100%; height: 400px; font-family: Consolas, Monaco, monospace; font-size: 13px; line-height: 1.4; border: 1px solid #ddd; padding: 10px;"
									placeholder="<?php esc_attr_e( '輸入備註內容...', 'otz' ); ?>"
								><?php echo esc_textarea( $note->note ); ?></textarea>
							</div>
						</div>
					</div>
				</div>
				
				<!-- 側邊欄 -->
				<div id="postbox-container-1" class="postbox-container">
					<div id="side-sortables" class="meta-box-sortables ui-sortable">
						
						<!-- 發佈選項 -->
						<div id="submitdiv" class="postbox">
							<div class="postbox-header">
								<h2 class="hndle ui-sortable-handle"><?php _e( '儲存', 'otz' ); ?></h2>
							</div>
							<div class="inside">
								<div class="submitbox" id="submitpost">
									<div id="minor-publishing">
										<div id="minor-publishing-actions">
											<div id="preview-action">
												<a href="<?php echo admin_url( 'admin.php?page=otz-notes' ); ?>" class="button">
													<?php _e( '返回列表', 'otz' ); ?>
												</a>
											</div>
											<div class="clear"></div>
										</div>
										
										<div id="misc-publishing-actions">
											<div class="misc-pub-section misc-pub-post-status">
												<label for="post_status"><?php _e( '備註 ID', 'otz' ); ?>:</label>
												<span><strong>#<?php echo esc_html( $note->id ); ?></strong></span>
											</div>
											
											<div class="misc-pub-section curtime misc-pub-curtime">
												<span id="timestamp">
													<?php _e( '建立時間', 'otz' ); ?>: <b><?php echo mysql2date( 'Y年n月j日 G:i', $note->created_at ); ?></b>
												</span>
											</div>
											
											<?php if ( $note->display_name ) : ?>
											<div class="misc-pub-section">
												<span>
													<?php _e( '關聯客戶', 'otz' ); ?>: 
													<b>
														<a href="<?php echo admin_url( 'admin.php?page=otz-notes&action=view&line_user_id=' . urlencode( $note->line_user_id ) ); ?>">
															<?php echo esc_html( $note->display_name ); ?>
														</a>
													</b>
												</span>
											</div>
											<?php endif; ?>
										</div>
										
										<div class="clear"></div>
									</div>

									<div id="major-publishing-actions">
										<div id="delete-action">
											<a class="submitdelete deletion" 
											   href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=otz-notes&action=delete_note&note_id=' . $note->id . '&line_user_id=' . urlencode( $note->line_user_id ) ), 'orderchatz_admin_action' ); ?>"
											   onclick="return confirm('<?php esc_attr_e( '確定要刪除這個備註嗎？', 'otz' ); ?>')">
												<?php _e( '移到垃圾桶', 'otz' ); ?>
											</a>
										</div>

										<div id="publishing-action">
											<span class="spinner"></span>
											<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( '更新', 'otz' ); ?>">
											<input type="submit" name="save" id="publish" class="button button-primary button-large" value="<?php esc_attr_e( '更新', 'otz' ); ?>">
										</div>
										<div class="clear"></div>
									</div>
								</div>
							</div>
						</div>
						
					</div>
				</div>
				
			</div>
			<br class="clear">
		</div>
	</form>
</div>

<style>
/* 自訂樣式，讓介面更接近 WordPress 文章編輯器 */
.wp-editor-area {
	resize: vertical;
}

#postbox-container-1 {
	width: 280px;
}

.postbox .hndle {
	cursor: default;
}

.misc-pub-section {
	padding: 8px 10px;
	border-top: 1px solid #eee;
}

.misc-pub-section:first-child {
	border-top: none;
}

#word-count {
	font-weight: bold;
	color: #2271b1;
}

.form-table th {
	font-weight: 600;
}

@media screen and (max-width: 850px) {
	#post-body-content {
		margin-right: 0;
	}
	
	#postbox-container-1 {
		width: 100%;
		margin-top: 20px;
	}
}
</style>

<script>
jQuery(document).ready(function($) {
	// 字數統計
	function updateWordCount() {
		var text = $('#note_content').val();
		var count = text.length;
		$('#word-count').text(count);
	}
	
	$('#note_content').on('input', updateWordCount);
	
	// 自動調整文字區域高度
	$('#note_content').on('input', function() {
		this.style.height = 'auto';
		this.style.height = this.scrollHeight + 'px';
	});
	
	// 快捷鍵支援
	$('#note_content').on('keydown', function(e) {
		// Ctrl+S 或 Cmd+S 儲存
		if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
			e.preventDefault();
			$('#publish').click();
		}
	});
	
	// 表單提交時的驗證
	$('form#post').on('submit', function(e) {
		var content = $('#note_content').val().trim();
		if (content === '') {
			alert('備註內容不能為空');
			e.preventDefault();
			return false;
		}
	});
});
</script>

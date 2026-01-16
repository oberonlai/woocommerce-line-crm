<?php
/**
 * 推播活動新增/編輯視圖
 *
 * WordPress 標準 post.php 風格的編輯介面
 *
 * @package OrderChatz
 * @var array|null $campaign 活動資料（編輯模式）
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 判斷是否為編輯模式.
$is_edit_mode = ! empty( $campaign );
$campaign_id  = $is_edit_mode ? $campaign['id'] : 0;

// 準備表單資料.
$campaign_name         = $is_edit_mode && isset( $campaign['campaign_name'] ) ? $campaign['campaign_name'] : '';
$description           = $is_edit_mode && isset( $campaign['description'] ) ? $campaign['description'] : '';
$audience_type         = $is_edit_mode && isset( $campaign['audience_type'] ) ? $campaign['audience_type'] : 'all_followers';
$filter_conditions     = $is_edit_mode && isset( $campaign['filter_conditions'] ) ? $campaign['filter_conditions'] : array();
$message_type          = $is_edit_mode && isset( $campaign['message_type'] ) ? $campaign['message_type'] : 'text';
$message_content       = $is_edit_mode && isset( $campaign['message_content'] ) ? $campaign['message_content'] : '';
$notification_disabled = $is_edit_mode && isset( $campaign['notification_disabled'] ) ? (bool) $campaign['notification_disabled'] : false;
$schedule_type         = $is_edit_mode && isset( $campaign['schedule_type'] ) ? $campaign['schedule_type'] : 'immediate';
$scheduled_at          = $is_edit_mode && isset( $campaign['scheduled_at'] ) ? $campaign['scheduled_at'] : '';
$status                = $is_edit_mode && isset( $campaign['status'] ) ? $campaign['status'] : 'draft';
$category              = $is_edit_mode && isset( $campaign['category'] ) ? $campaign['category'] : '';
$tags                  = $is_edit_mode && isset( $campaign['tags'] ) && is_array( $campaign['tags'] ) ? $campaign['tags'] : array();

// 解析 message_content.
$message_text      = '';
$message_url       = '';
$video_preview_url = '';
$flex_content      = '';

// 統一轉換為陣列格式.
if ( is_string( $message_content ) ) {
	$message_content_array = json_decode( $message_content, true );
} elseif ( is_array( $message_content ) ) {
	$message_content_array = $message_content;
} else {
	$message_content_array = array();
}

// 根據訊息類型提取資料.
if ( 'text' === $message_type ) {
	$message_text = $message_content_array['text'] ?? '';
} elseif ( 'flex' === $message_type ) {
	$flex_content = $message_content_array;
} elseif ( 'image' === $message_type ) {
	$message_url = $message_content_array['url'] ?? '';
} elseif ( 'video' === $message_type ) {
	$message_url       = $message_content_array['videoUrl'] ?? '';
	$video_preview_url = $message_content_array['previewImageUrl'] ?? '';
}
?>

<div class="wrap orderchatz-broadcast-page">
	<h1 class="wp-heading-inline">
		<?php echo $is_edit_mode ? __( 'Edit', 'otz' ) : __( 'Add New', 'otz' ); ?>
	</h1>

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=otz-broadcast' ) ); ?>" class="page-title-action">
		<?php _e( 'Back to List', 'otz' ); ?>
	</a>

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=otz-broadcast&action=create' ) ); ?>" class="page-title-action button-primary">
		<?php _e( 'Add Schedule', 'otz' ); ?>
	</a>

	<hr class="wp-header-end">

	<form method="post" action="" id="broadcast-campaign-form">
		<?php wp_nonce_field( 'save_broadcast_campaign' ); ?>
		<input type="hidden" name="save_campaign" value="1">
		<?php if ( $is_edit_mode ) : ?>
			<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $campaign_id ); ?>">
		<?php endif; ?>

		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">

				<!-- 左側主內容區 -->
				<div id="post-body-content">

					<!-- 標題 -->
					<div id="titlediv">
						<div id="titlewrap">
							<label class="screen-reader-text" for="title"><?php _e( 'Campaign Name', 'otz' ); ?></label>
							<input
								type="text"
								name="campaign_name"
								size="30"
								value="<?php echo esc_attr( $campaign_name ); ?>"
								id="title"
								spellcheck="true"
								autocomplete="off"
								placeholder="<?php _e( 'Enter campaign name', 'otz' ); ?>"
								required>
						</div>
					</div>

					<!-- 描述 -->
					<div id="descriptiondiv">
						<label for="campaign_description"><?php _e( 'Campaign Description', 'otz' ); ?></label>
						<textarea
							name="campaign_description"
							id="campaign_description"
							rows="3"
							placeholder="<?php _e( 'Enter campaign description (optional)', 'otz' ); ?>"><?php echo esc_textarea( $description ); ?></textarea>
					</div>
					
					<!-- 推播設定 -->
					<div id="message-div" class="postbox">
						<h2><?php _e( 'Broadcast Settings', 'otz' ); ?></h2>
						<div class="inside">

							<!-- 訊息類型選擇 -->
							<div class="form-field">
								<label><?php _e( 'Message Type', 'otz' ); ?> <span class="required">*</span></label>
								<div class="message-type-selector">
									<label>
										<input
												type="radio"
												name="message_type"
												value="text"
												<?php checked( $message_type, 'text' ); ?>
												required>
										<?php _e( 'Text Message', 'otz' ); ?>
									</label>
									<label>
										<input
												type="radio"
												name="message_type"
												value="flex"
												<?php checked( $message_type, 'flex' ); ?>>
										<?php _e( 'Flex Message', 'otz' ); ?>
									</label>
									<label>
										<input
												type="radio"
												name="message_type"
												value="image"
												<?php checked( $message_type, 'image' ); ?>>
										<?php _e( 'Image', 'otz' ); ?>
									</label>
									<label>
										<input
												type="radio"
												name="message_type"
												value="video"
												<?php checked( $message_type, 'video' ); ?>>
										<?php _e( 'Video', 'otz' ); ?>
									</label>
								</div>
							</div>
							
							<!-- 訊息編輯器 -->
							<div class="message-editor">

								<!-- 文字訊息編輯器 -->
								<div class="message-editor-section text-message-editor <?php echo 'text' === $message_type ? 'active' : ''; ?>">
									<label for="message_text"><?php _e( 'Message Content', 'otz' ); ?> <span class="required">*</span></label>
									<textarea
											name="message_content_text"
											id="message_text"
											rows="5"
											placeholder="<?php _e( 'Enter text message to send...', 'otz' ); ?>"
										<?php echo 'text' === $message_type ? 'required' : ''; ?>><?php echo esc_textarea( $message_text ); ?></textarea>
									<div class="message-meta">
										<span class="char-count">
											<span id="char-count">0</span> / 500 <?php _e( 'characters', 'otz' ); ?>
										</span>
									</div>
								</div>
								
								<!-- Flex 訊息編輯器 -->
								<div class="message-editor-section flex-message-editor <?php echo 'flex' === $message_type ? 'active' : ''; ?>">
									<label for="message_flex"><?php _e( 'Flex Message JSON', 'otz' ); ?> <span class="required">*</span></label>
									<textarea
											name="message_content_flex"
											id="message_flex"
											rows="10"
											placeholder='<?php _e( 'Paste LINE Flex Message JSON...', 'otz' ); ?>'
										<?php echo 'flex' === $message_type ? 'required' : ''; ?>><?php echo is_array( $flex_content ) && ! empty( $flex_content ) ? esc_textarea( wp_json_encode( $flex_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) : ''; ?></textarea>
									<p class="description">
										<?php
										printf(
											__( 'Use %s to create Flex Message', 'otz' ),
											'<a href="https://developers.line.biz/flex-simulator/" target="_blank">LINE Flex Message Simulator</a>'
										);
										?>
									</p>
								</div>
								
								<!-- 圖片上傳編輯器 -->
								<div class="message-editor-section image-message-editor <?php echo 'image' === $message_type ? 'active' : ''; ?>">
									<label><?php _e( 'Upload Image', 'otz' ); ?> <span class="required">*</span></label>
									<div class="image-upload-area" id="image-upload-area">
										<span class="dashicons dashicons-format-image"></span>
										<p><?php _e( 'Click to upload image', 'otz' ); ?></p>
										<button type="button" class="button upload-button" id="upload-image-button">
											<?php _e( 'Select Image', 'otz' ); ?>
										</button>
									</div>
									<div class="image-preview" id="image-preview" <?php echo $message_url && 'image' === $message_type ? '' : 'style="display:none;"'; ?>>
										<?php if ( $message_url && 'image' === $message_type ) : ?>
											<img src="<?php echo esc_url( $message_url ); ?>" alt="">
											<button type="button" class="remove-image">×</button>
										<?php endif; ?>
									</div>
									<input
											type="hidden"
											name="message_content_image"
											id="message_image_url"
											value="<?php echo 'image' === $message_type ? esc_attr( $message_url ) : ''; ?>"
											<?php echo 'image' === $message_type ? 'required' : ''; ?>>
								</div>
								
								<!-- 影片上傳編輯器 -->
								<div class="message-editor-section video-message-editor <?php echo 'video' === $message_type ? 'active' : ''; ?>">

									<!-- 影片上傳 -->
									<div class="video-upload-wrapper">
										<label><?php _e( 'Upload Video', 'otz' ); ?> <span class="required">*</span></label>
										<div class="video-upload-area" id="video-upload-area" <?php echo $message_url && 'video' === $message_type ? 'style="display:none;"' : ''; ?>>
											<span class="dashicons dashicons-video-alt3"></span>
											<p><?php _e( 'Click to upload video', 'otz' ); ?></p>
											<button type="button" class="button upload-button" id="upload-video-button">
												<?php _e( 'Select Video', 'otz' ); ?>
											</button>
										</div>
										<div class="video-preview" id="video-preview" <?php echo $message_url && 'video' === $message_type ? '' : 'style="display:none;"'; ?>>
											<?php if ( $message_url && 'video' === $message_type ) : ?>
												<video src="<?php echo esc_url( $message_url ); ?>" controls></video>
												<button type="button" class="remove-media" data-target="video">×</button>
											<?php endif; ?>
										</div>
										<input
												type="hidden"
												name="message_content_video"
												id="message_video_url"
												value="<?php echo 'video' === $message_type ? esc_attr( $message_url ) : ''; ?>"
												<?php echo 'video' === $message_type ? 'required' : ''; ?>>
									</div>
									
									<!-- 封面圖上傳 -->
									<div class="video-preview-image-wrapper">
										<label><?php _e( 'Upload Cover Image', 'otz' ); ?> <span class="required">*</span></label>
										<div class="video-preview-image-upload-area" id="video-preview-image-upload-area" <?php echo $video_preview_url && 'video' === $message_type ? 'style="display:none;"' : ''; ?>>
											<span class="dashicons dashicons-format-image"></span>
											<p><?php _e( 'Click to upload cover image', 'otz' ); ?></p>
											<button type="button" class="button upload-button" id="upload-video-preview-image-button">
												<?php _e( 'Select Cover Image', 'otz' ); ?>
											</button>
										</div>
										<div class="video-preview-image-preview" id="video-preview-image-preview" <?php echo $video_preview_url && 'video' === $message_type ? '' : 'style="display:none;"'; ?>>
											<?php if ( $video_preview_url && 'video' === $message_type ) : ?>
												<img src="<?php echo esc_url( $video_preview_url ); ?>" alt="">
												<button type="button" class="remove-media" data-target="video-preview-image">×</button>
											<?php endif; ?>
										</div>
										<input
												type="hidden"
												name="message_content_video_preview"
												id="message_video_preview_url"
												value="<?php echo 'video' === $message_type ? esc_attr( $video_preview_url ) : ''; ?>"
												<?php echo 'video' === $message_type ? 'required' : ''; ?>>
									</div>
								</div>
							
							
							
							</div><!-- .message-editor -->
							
							<!-- 訊息選項 -->
							<div class="message-options">
								<label>
									<input
											type="checkbox"
											name="notification_disabled"
											value="1"
											<?php checked( $notification_disabled ); ?>>
									<?php _e( 'Disable push notifications', 'otz' ); ?>
								</label>
								<p class="description"><?php _e( 'Messages will not trigger notifications to avoid disturbing friends', 'otz' ); ?></p>
							</div>
							
							<!-- 測試訊息發送 -->
							<div class="test-message-section">
								<h4><?php _e( 'Test Message', 'otz' ); ?></h4>
								<div class="form-field">
									<label for="test_line_user_id"><?php _e( 'Test LINE User ID', 'otz' ); ?></label>
									<div>
										<input
												type="text"
												id="test_line_user_id"
												name="test_line_user_id"
												value="<?php echo esc_attr( get_option( 'otz_test_line_user_id', '' ) ); ?>"
												placeholder="<?php _e( 'Enter LINE User ID...', 'otz' ); ?>">
										<button type="button" class="button" id="send-test-message">
											<span class="dashicons dashicons-email"></span>
											<?php _e( 'Send Test Message', 'otz' ); ?>
										</button>

									</div>
									<p class="description"><?php _e( 'Please enter the LINE User ID to receive test message', 'otz' ); ?> <span><?php _e( 'Note! Sending test messages will also deduct from monthly quota', 'otz' ); ?></span></p>
								</div>
								<span class="test-message-status"></span>
							</div>
						
						</div><!-- .inside -->
					</div><!-- #message-div -->
					
					<!-- 受眾設定 -->
					<div id="audience-div" class="postbox">
						<h2><?php _e( 'Audience Settings', 'otz' ); ?></h2>
						<div class="inside">

							<!-- 受眾類型選擇 -->
							<div class="form-field">
								<label><?php _e( 'Audience Type', 'otz' ); ?> <span class="required">*</span></label>
								<div class="audience-type-selector">
									<div class="audience-type-option <?php echo 'all_followers' === $audience_type ? 'active' : ''; ?>">
										<label>
											<input
													type="radio"
													name="audience_type"
													value="all_followers"
													<?php checked( $audience_type, 'all_followers' ); ?>
													required>
											<strong><?php _e( 'All Followers', 'otz' ); ?></strong>
											<span class="description"><?php _e( 'Send to all LINE followers', 'otz' ); ?></span>
										</label>
									</div>
									<div class="audience-type-option <?php echo 'imported_users' === $audience_type ? 'active' : ''; ?>">
										<label>
											<input
													type="radio"
													name="audience_type"
													value="imported_users"
													<?php checked( $audience_type, 'imported_users' ); ?>>
											<strong><?php _e( 'Imported Users', 'otz' ); ?></strong>
											<span class="description"><?php _e( 'Send to users imported to database', 'otz' ); ?></span>
										</label>
									</div>
									<div class="audience-type-option <?php echo 'filtered' === $audience_type ? 'active' : ''; ?>">
										<label>
											<input
													type="radio"
													name="audience_type"
													value="filtered"
													<?php checked( $audience_type, 'filtered' ); ?>>
											<strong><?php _e( 'Filtered Users', 'otz' ); ?></strong>
											<span class="description"><?php _e( 'Send to users filtered by specific conditions', 'otz' ); ?></span>
										</label>
									</div>
								</div>
							</div>
							
							<!-- 動態篩選區（條件篩選時顯示） -->
							<div class="dynamic-filter-section <?php echo 'filtered' === $audience_type ? 'active' : ''; ?>">

								<!-- 隱藏欄位：用於傳遞初始篩選條件給前端 -->
								<?php if ( ! empty( $filter_conditions ) ) : ?>
									<input
										type="hidden"
										name="filter_conditions_data"
										value="<?php echo esc_attr( wp_json_encode( $filter_conditions ) ); ?>">
								<?php endif; ?>

								<div class="filter-groups-container" id="filter-groups-container">
									<!-- 動態篩選群組將由 JavaScript 生成 -->
									<div class="filter-group" data-group-id="0">
										<div class="group-header">
											<span class="group-label"><?php _e( 'Group', 'otz' ); ?> <span class="group-number">1</span></span>
											<button type="button" class="delete-group" title="<?php _e( 'Delete Group', 'otz' ); ?>">×</button>
										</div>
										<div class="filter-conditions" id="filter-conditions-0">
											<!-- 條件將由 JavaScript 生成 -->
										</div>
										<div class="group-actions">
											<button type="button" class="button button-small add-condition" data-group-id="0">
												<?php _e( 'Add Rule', 'otz' ); ?>
											</button>
										</div>
									</div>
								</div>

								<div class="filter-controls">
									<button type="button" class="button add-group button-primary" id="add-group">
										<?php _e( 'Add Group', 'otz' ); ?>
									</button>
								</div>
								
								<!-- 受眾計數 -->
								<div class="audience-count">
									<div class="count-display">
										<span class="count-label"><?php _e( 'Estimated Audience:', 'otz' ); ?></span>
										<span class="count-number" id="audience-count-number">0</span>
										<span class="count-unit"><?php _e( 'people', 'otz' ); ?></span>

										<!-- 剩餘推播量 -->
										<span class="quota-separator">|</span>
										<span class="quota-label"><?php _e( 'Remaining monthly quota', 'otz' ); ?></span>
										<span class="quota-number" id="quota-remaining-broadcast">--</span>
										<span class="quota-unit"><?php _e( 'messages', 'otz' ); ?></span>
									</div>
									<button type="button" class="button" id="preview-audience">
										<?php _e( 'Preview Audience', 'otz' ); ?>
									</button>
								</div>
							</div>
							
							<!-- 快速儲存區塊 -->
							<div class="quick-save-section">
								<div class="quick-save-actions">
									<input
											type="submit"
											name="save_draft"
											class="button button-large"
											value="<?php _e( 'Save', 'otz' ); ?>">
									<input
											type="submit"
											name="save_and_broadcast"
											id="quick-save-and-broadcast"
											class="button button-primary button-large"
											value="<?php _e( 'Save and Broadcast', 'otz' ); ?>"
											style="display: <?php echo 'immediate' === $schedule_type ? 'inline-block' : 'none'; ?>;">
								</div>
							</div>
						
						</div><!-- .inside -->
					</div><!-- #audience-div -->
					
					

				</div><!-- #post-body-content -->

				<!-- 右側邊欄 -->
				<div id="postbox-container-1" class="postbox-container">

					<!-- 發佈區塊 -->
					<div id="submitdiv" class="postbox">
						<h2><?php _e( 'Publish', 'otz' ); ?></h2>
						<div class="inside">
							<div class="submitbox" id="submitpost">

								<!-- 發佈按鈕 -->
								<div id="major-publishing-actions">
									<div id="publishing-action">
										<span class="spinner"></span>

										<!-- 儲存按鈕（總是顯示） -->
										<input
											type="submit"
											name="save_draft"
											id="save-draft"
											class="button button-large save-button"
											value="<?php _e( 'Save', 'otz' ); ?>">

										<!-- 儲存並推播按鈕（只在立即發送時顯示） -->
										<input
											type="submit"
											name="save_and_broadcast"
											id="save-and-broadcast"
											class="button button-primary button-large broadcast-button"
											value="<?php _e( 'Save and Broadcast', 'otz' ); ?>"
											style="display: <?php echo 'immediate' === $schedule_type ? 'inline-block' : 'none'; ?>;">
									</div>
									<div class="clear"></div>
								</div>

							</div><!-- .submitbox -->
						</div><!-- .inside -->
					</div><!-- #submitdiv -->

					<!-- 排程設定區塊 -->
					<div id="schedule-div" class="postbox">
						<h2><?php _e( 'Schedule Settings', 'otz' ); ?></h2>
						<div class="inside">
							<div id="schedule-settings">
								<div class="schedule-type-selector">
									<div class="schedule-type-option">
										<label>
											<input
												type="radio"
												name="schedule_type"
												value="immediate"
												<?php checked( $schedule_type, 'immediate' ); ?>
												class="schedule-type-radio">
											<span class="option-label">
												<?php _e( 'Send Immediately', 'otz' ); ?>
												<span class="option-description"><?php _e( 'Send broadcast immediately after saving', 'otz' ); ?></span>
											</span>
										</label>
									</div>
									<div class="schedule-type-option">
										<label>
											<input
												type="radio"
												name="schedule_type"
												value="scheduled"
												<?php checked( $schedule_type, 'scheduled' ); ?>
												class="schedule-type-radio">
											<span class="option-label">
												<?php _e( 'Scheduled Send', 'otz' ); ?>
												<span class="option-description"><?php _e( 'Send broadcast at specified time', 'otz' ); ?></span>
											</span>
										</label>
									</div>
								</div>

								<div class="scheduled-datetime <?php echo 'scheduled' === $schedule_type ? 'active' : ''; ?>">
									<label for="scheduled_at"><?php _e( 'Send Time', 'otz' ); ?></label>
									<input
										type="datetime-local"
										name="scheduled_at"
										id="scheduled_at"
										value="<?php echo esc_attr( $scheduled_at ? date( 'Y-m-d\TH:i', strtotime( $scheduled_at ) ) : '' ); ?>">
								</div>
							</div>
						</div><!-- .inside -->
					</div><!-- #schedule-div -->

					<!-- 分類區塊 -->
					<div id="category-div" class="postbox" style="display:none">
						<h2><?php _e( 'Category', 'otz' ); ?></h2>
						<div class="inside">
							<input
								type="text"
								name="campaign_category"
								id="campaign_category"
								value="<?php echo esc_attr( $category ); ?>"
								placeholder="<?php _e( 'Enter category name', 'otz' ); ?>">
							<p class="description"><?php _e( 'Used to organize and categorize broadcast campaigns', 'otz' ); ?></p>
						</div>
					</div>

					<!-- 標籤區塊 -->
					<div id="tags-div" class="postbox" style="display:none">
						<h2><?php _e( 'Tags', 'otz' ); ?></h2>
						<div class="inside">
							<div class="tag-input-wrapper">
								<input
									type="text"
									name="campaign_tags_input"
									id="campaign_tags_input"
									placeholder="<?php _e( 'Add Tag', 'otz' ); ?>">
								<button type="button" class="button add-tag-button"><?php _e( 'Add', 'otz' ); ?></button>
							</div>
							<div class="tags-list">
								<?php foreach ( $tags as $tag ) : ?>
									<span class="tag-item">
										<?php echo esc_html( $tag ); ?>
										<button type="button" class="remove-tag">×</button>
										<input type="hidden" name="campaign_tags[]" value="<?php echo esc_attr( $tag ); ?>">
									</span>
								<?php endforeach; ?>
							</div>
							<p class="description"><?php _e( 'Use commas or Enter key to separate multiple tags', 'otz' ); ?></p>
						</div>
					</div>

					<!-- 可帶入參數區塊 -->
					<div id="parameters-div" class="postbox">
						<h2><?php _e( 'Available Parameters', 'otz' ); ?></h2>
						<div class="inside">
							<p class="description"><?php _e( 'Click parameter to copy to clipboard', 'otz' ); ?></p>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo $parameters_html;
							?>
						</div>
					</div>

			<!-- 推播紀錄區塊（編輯模式） -->
			<?php if ( $is_edit_mode && ! empty( $broadcast_logs ) ) : ?>
				<div id="broadcast-logs-div" class="postbox">
					<h2><?php _e( 'Broadcast Logs', 'otz' ); ?></h2>
					<div class="inside">
						<div class="broadcast-logs-list">
							<?php foreach ( $broadcast_logs as $log ) : ?>
								<?php
								// 格式化執行時間.
								$executed_time = mysql2date( 'Y/m/d H:i', $log['executed_at'] );

								// 計算失敗數.
								$failed_count = isset( $log['failed_count'] ) ? (int) $log['failed_count'] : 0;

								// 狀態顯示文字與 CSS class.
								$status_labels = array(
									'pending' => __( 'Running', 'otz' ),
									'success' => __( 'Success', 'otz' ),
									'partial' => __( 'Partial Success', 'otz' ),
									'failed'  => __( 'Failed', 'otz' ),
								);
								$status_label  = isset( $status_labels[ $log['status'] ] ) ? $status_labels[ $log['status'] ] : $log['status'];
								?>
								<div class="log-item">
									<div class="log-header">
										<span class="log-status log-status-<?php echo esc_attr( $log['status'] ); ?>">
											<?php echo esc_html( $status_label ); ?>
										</span>
										<span class="log-time"><?php echo esc_html( '於 ' . $executed_time ); ?></span>

									</div>
									<div class="log-stats">
										<div class="stat-item">
											<span class="stat-label"><?php _e( 'Total Broadcasts', 'otz' ); ?></span>
											<span class="stat-value"><?php echo esc_html( number_format( (int) $log['target_count'] ) ); ?></span>
										</div>
										<div class="stat-item">
											<span class="stat-label"><?php _e( 'Successful', 'otz' ); ?></span>
											<span class="stat-value stat-success"><?php echo esc_html( number_format( (int) $log['success_count'] ) ); ?></span>
										</div>
										<?php if ( $failed_count > 0 ) : ?>
											<div class="stat-item">
												<span class="stat-label"><?php _e( 'Failed', 'otz' ); ?></span>
												<span class="stat-value stat-failed"><?php echo esc_html( number_format( $failed_count ) ); ?></span>
											</div>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			<?php elseif ( $is_edit_mode && empty( $broadcast_logs ) ) : ?>
				<div id="broadcast-logs-div" class="postbox">
					<h2><?php _e( 'Broadcast Logs', 'otz' ); ?></h2>
					<div class="inside">
						<p class="no-logs"><?php _e( 'No broadcast logs yet', 'otz' ); ?></p>
					</div>
				</div>
			<?php endif; ?>

				</div><!-- #postbox-container-1 -->

			</div><!-- #post-body -->

		</div><!-- #poststuff -->
	</form>
</div><!-- .wrap -->

<div class="copy-notification" id="copy-notification">
	<?php _e( 'Copied to clipboard', 'otz' ); ?>
</div>

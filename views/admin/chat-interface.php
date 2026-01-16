<?php
/**
 * OrderChatz 聊天介面模板
 *
 * 三欄式聊天介面：左側好友列表、中間聊天區域、右側客戶資訊
 *
 * @package OrderChatz
 * @since 1.0.0
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="orderchatz-chat-interface">
	<div class="chat-container" id="chat-container">
		
		<!-- 左側：好友列表面板 -->
		<div class="friend-list-panel" id="friend-list-panel">
			
			<!-- 搜尋框 -->
			<div class="friend-search-container">
				<div class="friend-search">
					<input
						type="text"
						id="friend-search"
						class="friend-search-input"
						placeholder="<?php _e( '搜尋好友...', 'otz' ); ?>"
						autocomplete="off"
					/>
					<span class="friend-search-icon dashicons dashicons-search"></span>
					<button type="button" class="friend-search-clear" id="friend-search-clear" style="display: none;">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			</div>
			
			<!-- 好友列表容器 -->
			<div class="friend-list-container">
				<div class="friend-list" id="friend-list" role="listbox" aria-label="<?php esc_attr_e( '好友列表', 'otz' ); ?>">
					<!-- 好友列表項目將由 JavaScript 動態生成 -->
					<div class="friend-list-loading">
						<p><?php _e( '載入好友列表中...', 'otz' ); ?></p>
					</div>
				</div>
			</div>
		</div>
		
		<!-- 第一個拖曳分隔器 -->
		<div class="panel-resizer" id="left-resizer" data-direction="left"></div>
		
		<!-- 中間：聊天區域面板 -->
		<div class="chat-area-panel" id="chat-area-panel">
			<!-- 聊天標題列 -->
			<div class="chat-header" id="chat-header">
				<div class="chat-header-content">
					<div class="current-friend-avatar">
						<img id="current-friend-avatar-img" src="" alt="" style="display: none;">
					</div>
					<div class="current-friend-info">
						<h3 class="current-friend-name" id="current-friend-name"></h3>
						<div class="current-friend-status" id="current-friend-status">
							<span class="status-indicator"></span>
							<span class="status-text"></span>
						</div>
					</div>
				</div>
			</div>
			
			<!-- 聊天訊息區域 -->
			<div class="chat-messages-container">
				<div class="chat-messages" id="chat-messages" role="log" aria-live="polite" aria-label="<?php esc_attr_e( '聊天訊息', 'otz' ); ?>">
					<!-- 預設提示訊息 -->
					<div class="no-chat-selected" id="no-chat-selected">
						<div class="no-chat-icon">
							<span class="dashicons dashicons-format-chat"></span>
						</div>
						<p><?php _e( '請選擇一位好友開始聊天', 'otz' ); ?></p>
					</div>
				</div>
				<!-- 重新載入對話串按鈕 (跳轉模式下顯示) -->
				<div class="" id="reload-conversation-btn-container" style="display: none;">
					<button type="button" class="button button-secondary reload-conversation-btn" id="reload-conversation-btn">
						載入先前的訊息
					</button>
				</div>
			</div>
			
			<!-- 回覆預覽區域 -->
			<div class="reply-preview-container" id="reply-preview-container" style="display: none;">
				<div class="reply-preview-content">
					<div class="reply-preview-info">
						<span class="reply-label">回覆</span>
						<span class="reply-sender-name"></span>
					</div>
					<div class="reply-preview-text"></div>
					<button class="reply-cancel-btn" type="button" title="取消回覆">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			</div>
			
			<!-- 貼圖選擇面板 -->
			<div class="sticker-picker-panel" id="sticker-picker-panel">
				<div class="sticker-picker-content">
					<!-- 分類標籤 -->
					<div class="sticker-categories" id="sticker-categories">
						<button type="button" class="sticker-category-tab active" data-category="moon">
							<?php _e( '饅頭人', 'otz' ); ?>
						</button>
						<button type="button" class="sticker-category-tab" data-category="seasonal">
							<?php _e( '休閒敬語', 'otz' ); ?>
						</button>
						<button type="button" class="sticker-category-tab" data-category="gif">
							<?php _e( '動態特別篇', 'otz' ); ?>
						</button>
					</div>

					<!-- 貼圖網格容器 -->
					<div class="sticker-grid-container" id="sticker-grid-container">
						<div class="sticker-grid" id="sticker-grid">
							<!-- 貼圖項目將由 JavaScript 動態生成 -->
							<div class="sticker-loading">
								<p><?php _e( '載入貼圖中...', 'otz' ); ?></p>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- 訊息輸入區域 -->
			<div class="chat-input-area" id="chat-input-area">
				<form class="chat-input-form" id="chat-input-form">
					<div class="input-container">
						<textarea
								id="message-input"
								class="message-input"
								placeholder="<?php _e( '輸入訊息...', 'otz' ); ?>"
								rows="1"
								maxlength="5000"
								disabled
								aria-label="<?php esc_attr_e( '訊息輸入框', 'otz' ); ?>"
						></textarea>

						<!-- 範本快速選單 -->
						<div class="template-autocomplete-dropdown" id="template-autocomplete-dropdown" style="display: none;">
							<div class="autocomplete-list" id="autocomplete-list">
								<!-- 範本項目動態生成 -->
							</div>
						</div>

						<!-- 訊息範本區域 -->
						<div class="message-templates-container" id="message-templates-container" style="display: none;">
							<div class="message-templates-list" id="message-templates-list">
								<!-- 範本項目將由 JavaScript 動態生成 -->
							</div>
						</div>

						<div class="input-wrapper">
							<div class="input-actions-left">
								<button
									type="button"
									id="image-upload-btn"
									class="action-button image-upload-btn"
									title="<?php esc_attr_e( '上傳圖片', 'otz' ); ?>"
									aria-label="<?php esc_attr_e( '上傳圖片', 'otz' ); ?>"
								>
									<span class="dashicons dashicons-camera"></span>
								</button>
								<input
									type="file"
									id="image-upload-input"
									accept="image/*"
									multiple
									style="display: none;"
								/>
								<button
									type="button"
									id="product-send-btn"
									class="action-button product-send-btn"
									title="<?php esc_attr_e( '傳送商品', 'otz' ); ?>"
									aria-label="<?php esc_attr_e( '傳送商品', 'otz' ); ?>"
								>
									<span class="dashicons dashicons-products"></span>
								</button>
								<button
									type="button"
									id="template-manage-btn"
									class="action-button template-manage-btn"
									title="<?php esc_attr_e( '範本管理', 'otz' ); ?>"
									aria-label="<?php esc_attr_e( '範本管理', 'otz' ); ?>"
								>
									<span class="dashicons dashicons-editor-table"></span>
								</button>
								<button
										type="button"
										id="sticker-picker-btn"
										class="action-button sticker-picker-btn"
										title="<?php esc_attr_e( '貼圖', 'otz' ); ?>"
										aria-label="<?php esc_attr_e( '選擇貼圖', 'otz' ); ?>"
								>
									<span class="dashicons dashicons-smiley"></span>
								</button>
								<button
									type="button"
									id="schedule-message-btn"
									class="action-button schedule-message-btn"
									title="<?php esc_attr_e( '排程訊息', 'otz' ); ?>"
									aria-label="<?php esc_attr_e( '排程訊息', 'otz' ); ?>"
								>
									<span class="dashicons dashicons-clock"></span>
								</button>
							</div>
							<div class="input-actions-right">
								<button
									type="submit"
									id="send-button"
									class="send-button"
									disabled
									aria-label="<?php esc_attr_e( '發送訊息', 'otz' ); ?>"
								>
									送出
								</button>
							</div>
						</div>
						<div class="input-info">
							<span class="input-tip">
								<?php _e( 'Enter：傳送 / Shift + Enter：換行', 'otz' ); ?>
							</span>
							<span class="char-counter">
								<span id="char-count">0</span>/500
							</span>
						</div>
					</div>
				</form>
			</div>
		</div>
		
		<!-- 第二個拖曳分隔器 -->
		<div class="panel-resizer" id="right-resizer" data-direction="right"></div>
		
		<!-- 右側：客戶資訊面板 -->
		<div class="customer-info-panel" id="customer-info-panel" style="background: #fff !important; display: flex !important; flex-direction: column !important; min-height: 100% !important; position: relative !important;">
			
			<!-- 客戶資訊容器 -->
			<div class="customer-info-container" style="flex: 1 !important; overflow-y: auto !important; position: relative !important;">
				<div class="customer-info" id="customer-info" style="padding: 0 !important;">
					<!-- 預設提示訊息 -->
					<div class="no-customer-selected" id="no-customer-selected" style="display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; height: 200px !important; text-align: center !important; color: #666 !important; padding: 20px !important;">
						<div class="no-customer-icon" style="margin-bottom: 15px !important;">
							<span class="dashicons dashicons-admin-users" style="font-size: 48px !important; color: #ccc !important;"></span>
						</div>
						<p style="margin: 0 !important; font-size: 14px !important; line-height: 1.5 !important;"><?php _e( '請選擇好友查看客戶資訊', 'otz' ); ?></p>
					</div>
				</div>
			</div>
			
			
			<!-- 客戶資訊載入指示器 -->
			<div class="chat-loading-overlay" id="customer-loading-overlay" style="display: none;">
				<div class="chat-loading-content">
					<div class="chat-loading-spinner"></div>
					<p><?php _e( '載入客戶資訊中...', 'otz' ); ?></p>
				</div>
			</div>
		</div>
	</div>
	
	<!-- 手機版切換按鈕 -->
	<div class="mobile-toggle-buttons" id="mobile-toggle-buttons">
		<button type="button" id="toggle-friends" class="mobile-toggle mobile-toggle-friends">
			<span class="dashicons dashicons-groups"></span>
			<span class="toggle-text"><?php _e( '好友', 'otz' ); ?></span>
		</button>
		<button type="button" id="toggle-customer-info" class="mobile-toggle mobile-toggle-customer">
			<span class="dashicons dashicons-admin-users"></span>
			<span class="toggle-text"><?php _e( '客戶資訊', 'otz' ); ?></span>
		</button>
	</div>
	
	<!-- 手機版遮罩 -->
	<div class="mobile-overlay" id="mobile-overlay"></div>
</div>

<!-- 範本管理浮動視窗模板 -->
<div id="template-manage-modal" class="template-manage-modal" style="display: none;">
	<div class="modal-backdrop"></div>
	<div class="modal-container">
		<div class="modal-header">
			<h3 class="modal-title"><?php _e( '訊息範本管理', 'otz' ); ?></h3>
			<button type="button" class="modal-close" aria-label="<?php esc_attr_e( '關閉', 'otz' ); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="modal-content">

			<!-- 範本列表區域 -->
			<div class="template-list-section" id="template-list-section">
				<div class="template-list-header">
					<h4><?php _e( '現有範本', 'otz' ); ?></h4>
					<button type="button" class="button button-primary template-add-new-btn" id="template-add-new-btn">
						<?php _e( '新增', 'otz' ); ?>
					</button>
				</div>

				<div class="template-list-container">
					<table class="template-list-table" id="template-list-table">
						<thead>
							<tr>
								<th><?php _e( '快速代碼', 'otz' ); ?></th>
								<th><?php _e( '範本內容', 'otz' ); ?></th>
								<th><?php _e( '操作', 'otz' ); ?></th>
							</tr>
						</thead>
						<tbody id="template-list-tbody">
							<!-- 範本列表項目將由 JavaScript 動態生成 -->
						</tbody>
					</table>
				</div>
			</div>

			<!-- 新增/編輯範本表單 -->
			<div class="template-form-section" id="template-form-section" style="display: none;">
				<div class="template-form-header">
					<h4 id="template-form-title"><?php _e( '新增範本', 'otz' ); ?></h4>
				</div>

				<form class="template-form" id="template-form">
					<input type="hidden" id="template-form-id" value="">

					<div class="template-form-field">
						<label for="template-form-code"><?php _e( '快速代碼', 'otz' ); ?> <span class="required">*</span></label>
						<input type="text" id="template-form-code" class="template-form-input" placeholder="<?php esc_attr_e( '例如：thank', 'otz' ); ?>" maxlength="50" required>
						<p class="template-form-help"><?php _e( '只能包含英數字和底線，用於 #代碼 快速輸入', 'otz' ); ?></p>
						<div class="template-form-error" id="template-form-code-error" style="display: none;"></div>
					</div>

					<div class="template-form-field">
						<label for="template-form-content"><?php _e( '範本內容', 'otz' ); ?> <span class="required">*</span></label>
						<textarea id="template-form-content" class="template-form-input" placeholder="<?php esc_attr_e( '輸入範本內容...', 'otz' ); ?>" rows="4" maxlength="500" required></textarea>
						<div class="template-form-char-count">
							<span id="template-form-char-count">0</span>/500
						</div>
						<div class="template-form-error" id="template-form-content-error" style="display: none;"></div>
					</div>

					<div class="template-form-actions">
						<button type="submit" class="button button-primary template-form-save-btn" id="template-form-save-btn">
							<?php _e( '儲存範本', 'otz' ); ?>
						</button>
						<button type="button" class="button template-form-cancel-btn">
							<?php _e( '取消', 'otz' ); ?>
						</button>
					</div>
				</form>
			</div>

		</div>
	</div>
</div>

<!-- 訊息範本，供 JavaScript 使用 -->
<script type="text/template" id="friend-item-template">
	<div class="friend-item"
			data-friend-id="{friendId}"
			data-group-id="{groupId}"
			role="option"
			tabindex="0">
		<div class="friend-avatar">
			<img src="{avatar}" class="avatar" loading="lazy" decoding="async">
		</div>
		<div class="friend-info">
			<div class="friend-name-row">
				<h4 class="friend-name">
					{name}
					<span class="friend-group-icon {groupIconClass}" title="{groupIconTitle}">
						<span class="dashicons dashicons-groups"></span>
					</span>
				</h4>
				<span class="last-message-time">{lastMessageTime}</span>
			</div>
			<div class="friend-message-row">
				<p class="last-message">{lastMessage}</p>
				<div class="unread-badge {unreadClass}">{unreadCount}</div>
			</div>
		</div>
	</div>
</script>

<script type="text/template" id="message-bubble-template">
	<div class="chat-message {messageType}" data-message-id="{messageId}">
		<div class="message-content">
			<div class="message-bubble {messageType}">
				<div class="message-text">{content}</div>
			</div>
			<div class="message-meta">
				<span class="message-sender">{sender}</span>
				<span class="message-time">{timestamp}</span>
			</div>
		</div>
	</div>
</script>

<script type="text/template" id="customer-basic-info-template">
	<div class="customer-basic info-section">
		<div class="info-header collapsible-header" data-section="basic-info">
			<span class="dashicons dashicons-menu"></span>
			<h4><?php _e( '基本資訊', 'otz' ); ?></h4>
			<span class="toggle-icon dashicons dashicons-arrow-up-alt2"></span>
		</div>
		<div class="info-content collapsible-content" data-section="basic-info">
			<div class="info-row">
				<label><?php _e( '姓名', 'otz' ); ?>:</label>
				<span>{name}</span>
			</div>
			<div class="info-row">
				<label><?php _e( 'Email', 'otz' ); ?>:</label>
				<span>{email}</span>
			</div>
			<div class="info-row">
				<label><?php _e( '電話', 'otz' ); ?>:</label>
				<span>{phone}</span>
			</div>
			<div class="info-row">
				<label><?php _e( '加入日期', 'otz' ); ?>:</label>
				<span>{joinDate}</span>
			</div>
		</div>
	</div>
</script>

<script type="text/template" id="customer-orders-template">
	<div class="customer-orders info-section">
		<div class="info-header collapsible-header" data-section="orders">
			<span class="dashicons dashicons-menu"></span>
			<h4><?php _e( '訂單記錄', 'otz' ); ?></h4>
			<span class="toggle-icon dashicons dashicons-arrow-up-alt2"></span>
		</div>
		<div class="orders-content collapsible-content" data-section="orders">
			<!-- 訂單搜尋框 -->
			<div class="order-search-container">
				<div class="order-search">
					<input
						type="text"
						class="order-search-input"
						placeholder="<?php _e( '搜尋訂單編號...', 'otz' ); ?>"
						autocomplete="off"
					/>
					<button type="button" class="order-search-btn">
						<span class="dashicons dashicons-search"></span>
					</button>
				</div>
			</div>
			
			<!-- 訂單列表容器 -->
			<div class="orders-list-container">
				{ordersHtml}
				
				<!-- 載入更多按鈕 -->
				<div class="load-more-orders" style="display: none;">
					<button type="button" class="button load-more-orders-btn">
						<?php _e( '載入更多訂單', 'otz' ); ?>
					</button>
				</div>
				
				<!-- 載入狀態 -->
				<div class="orders-loading" style="display: none;">
					<div class="loading-spinner"></div>
					<p><?php _e( '載入訂單中...', 'otz' ); ?></p>
				</div>
			</div>
		</div>
	</div>
</script>

<script type="text/template" id="customer-notes-template">
	<div class="customer-notes info-section">
		<div class="info-header collapsible-header" data-section="notes">
			<span class="dashicons dashicons-menu"></span>
			<h4><?php _e( '備註', 'otz' ); ?></h4>
			<span class="toggle-icon dashicons dashicons-arrow-up-alt2"></span>
		</div>
		<div class="notes-content collapsible-content" data-section="notes">
			{notesHtml}
		</div>
	</div>
</script>

<script type="text/template" id="customer-tags-template">
	<div class="customer-tags info-section">
		<div class="info-header collapsible-header" data-section="tags">
			<span class="dashicons dashicons-menu"></span>
			<h4><?php _e( '標籤', 'otz' ); ?></h4>
			<span class="toggle-icon dashicons dashicons-arrow-up-alt2"></span>
		</div>
		<div class="tags-content collapsible-content" data-section="tags">
			{tagsHtml}
		</div>
	</div>
</script>

<!-- 訂單詳細資訊浮動視窗模板 -->
<div id="order-detail-modal" class="order-detail-modal" style="display: none;">
	<div class="modal-backdrop"></div>
	<div class="modal-container">
		<div class="modal-header">
			<h3 class="modal-title">訂單詳細資訊</h3>
			<button type="button" class="modal-close" aria-label="關閉">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="modal-content">
			<div class="order-detail-loading">
				<div class="loading-spinner"></div>
				<p>載入訂單頁面中...</p>
			</div>
			<div class="order-detail-iframe-container" style="display: none;">
				<iframe id="order-detail-iframe" src="" frameborder="0" style="width: 100%; height: 89vh; border: none;"></iframe>
			</div>
		</div>
	</div>
</div>

<!-- 商品選擇浮動視窗模板 -->
<div id="product-select-modal" class="product-select-modal" style="display: none;">
	<div class="modal-backdrop"></div>
	<div class="modal-container">
		<div class="modal-header">
			<h3 class="modal-title"><?php _e( '傳送商品資訊', 'otz' ); ?></h3>
			<button type="button" class="modal-close" aria-label="<?php esc_attr_e( '關閉', 'otz' ); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="modal-content">
			<div class="product-search-container">
				<label for="product-search-select"><?php _e( '搜尋商品：', 'otz' ); ?></label>
				<select id="product-search-select" class="product-search-select" style="width: 100%;">
					<option value=""><?php _e( '請輸入商品名稱進行搜尋...', 'otz' ); ?></option>
				</select>
			</div>
			<div class="modal-actions" style="margin-top: 20px; text-align: right;">
				<button type="button" class="button button-secondary modal-cancel"><?php _e( '取消', 'otz' ); ?></button>
				<button type="button" class="button button-primary product-send-confirm" disabled><?php _e( '傳送', 'otz' ); ?></button>
			</div>
		</div>
	</div>
</div>

<!-- 排程訊息管理浮動視窗模板 -->
<div id="message-cron-modal" class="message-cron-modal" style="display: none;">
	<div class="modal-backdrop"></div>
	<div class="modal-container">
		<div class="modal-header">
			<h3 class="modal-title"><?php _e( '排程訊息管理', 'otz' ); ?></h3>
			<button type="button" class="modal-close" aria-label="<?php esc_attr_e( '關閉', 'otz' ); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="modal-content">

			<!-- 排程列表區域 -->
			<div class="cron-list-section" id="cron-list-section">
				<div class="cron-list-header">
					<h4><?php _e( '排程列表', 'otz' ); ?></h4>
					<button type="button" class="button button-primary cron-add-new-btn" id="cron-add-new-btn">
						<?php _e( '新增排程', 'otz' ); ?>
					</button>
				</div>

				<div class="cron-list-container">
					<div class="cron-list-loading" id="cron-list-loading" style="display: none;">
						<div class="loading-spinner"></div>
						<p><?php _e( '載入排程列表中...', 'otz' ); ?></p>
					</div>

					<div class="cron-list-empty" id="cron-list-empty" style="display: none;">
						<div class="empty-icon">
							<span class="dashicons dashicons-clock"></span>
						</div>
						<p><?php _e( '目前沒有排程訊息', 'otz' ); ?></p>
					</div>

					<table class="cron-list-table" id="cron-list-table" style="display: none;">
						<thead>
							<tr>
								<th><?php _e( '訊息內容', 'otz' ); ?></th>
								<th><?php _e( '預計發送時間', 'otz' ); ?></th>
								<th><?php _e( '實際發送時間', 'otz' ); ?></th>
								<th><?php _e( '狀態', 'otz' ); ?></th>
								<th><?php _e( '操作', 'otz' ); ?></th>
							</tr>
						</thead>
						<tbody id="cron-list-tbody">
							<!-- 排程列表項目將由 JavaScript 動態生成 -->
						</tbody>
					</table>
				</div>
			</div>

			<!-- 新增/編輯排程表單 -->
			<div class="cron-form-section" id="cron-form-section" style="display: none;">
				<div class="cron-form-header">
					<h4 id="cron-form-title"><?php _e( '新增排程', 'otz' ); ?></h4>
					<button type="button" class="button cron-form-back-btn"><?php _e( '← 返回列表', 'otz' ); ?></button>
				</div>

				<form class="cron-form" id="cron-form">
					<input type="hidden" id="cron-form-id" value="">

					<div class="cron-form-field">
						<label for="cron-form-message-type"><?php _e( '訊息類型', 'otz' ); ?> <span class="required">*</span></label>
						<div class="message-type-selector">
							<label class="message-type-option">
								<input type="radio" name="cron_message_type" value="text" checked>
								<span class="dashicons dashicons-edit"></span>
								<?php _e( '文字', 'otz' ); ?>
							</label>
							<label class="message-type-option">
								<input type="radio" name="cron_message_type" value="image">
								<span class="dashicons dashicons-format-image"></span>
								<?php _e( '圖片', 'otz' ); ?>
							</label>
							<label class="message-type-option">
								<input type="radio" name="cron_message_type" value="video">
								<span class="dashicons dashicons-format-video"></span>
								<?php _e( '影片', 'otz' ); ?>
							</label>
							<label class="message-type-option">
								<input type="radio" name="cron_message_type" value="file">
								<span class="dashicons dashicons-media-archive"></span>
								<?php _e( '檔案', 'otz' ); ?>
							</label>
						</div>
					</div>

					<!-- 文字訊息編輯器 -->
					<div class="cron-form-field message-editor text-editor">
						<label for="cron-form-content"><?php _e( '訊息內容', 'otz' ); ?> <span class="required">*</span></label>
						<textarea
							id="cron-form-content"
							class="cron-form-input"
							placeholder="<?php esc_attr_e( '輸入要排程發送的訊息內容...', 'otz' ); ?>"
							rows="4"
							maxlength="5000"
							required
						></textarea>
						<div class="cron-form-char-count">
							<span id="cron-form-char-count">0</span>/5000
						</div>
						<div class="cron-form-error" id="cron-form-content-error" style="display: none;"></div>
					</div>

					<!-- 檔案上傳編輯器 -->
					<div class="cron-form-field message-editor file-editor" style="display: none;">
						<label><?php _e( '選擇檔案', 'otz' ); ?> <span class="required">*</span></label>
						<div class="file-upload-container">
							<input type="file" id="cron-form-file" class="cron-form-file-input" style="display: none;">
							<button type="button" class="button button-secondary cron-file-select-btn">
								<span class="dashicons dashicons-cloud-upload"></span>
								<?php _e( '選擇檔案', 'otz' ); ?>
							</button>
							<div class="file-upload-info">
								<small class="drag-drop-hint"><?php _e( '或拖曳檔案到此處', 'otz' ); ?></small>
								<br>
								<small class="file-size-limit"></small>
							</div>
						</div>
						<div class="file-preview-container" style="display: none;">
							<div class="file-preview">
								<div class="file-info">
									<span class="file-name"></span>
									<span class="file-size"></span>
								</div>
								<button type="button" class="cron-file-remove-btn">
									<span class="dashicons dashicons-no-alt"></span>
								</button>
							</div>
							<div class="upload-progress" style="display: none;">
								<div class="upload-progress-bar">
									<div class="upload-progress-fill"></div>
								</div>
								<span class="upload-progress-text">上傳中...</span>
							</div>
						</div>
						<input type="hidden" id="cron-form-file-url" value="">
						<input type="hidden" id="cron-form-file-name" value="">
						<div class="cron-form-error" id="cron-form-file-error" style="display: none;"></div>
					</div>
						<div class="cron-form-field-wrapper">
							<div class="cron-form-field">
								<label for="cron-form-type"><?php _e( '排程類型', 'otz' ); ?> <span class="required">*</span></label>
								<select id="cron-form-type" class="cron-form-input" required>
									<option value="once"><?php _e( '單次排程', 'otz' ); ?></option>
									<option value="recurring"><?php _e( '重複排程', 'otz' ); ?></option>
								</select>
							</div>
							
							<div class="cron-form-field cron-recurring-field" id="cron-recurring-field" style="display: none;">
								<label for="cron-form-interval"><?php _e( '重複間隔', 'otz' ); ?> <span class="required">*</span></label>
								<select id="cron-form-interval" class="cron-form-input">
									<option value="daily"><?php _e( '每日', 'otz' ); ?></option>
									<option value="weekly"><?php _e( '每週', 'otz' ); ?></option>
									<option value="monthly"><?php _e( '每月', 'otz' ); ?></option>
								</select>
							</div>
							
							<div class="cron-form-field" id="cron-date-field">
								<label for="cron-form-date"><?php _e( '排程日期', 'otz' ); ?> <span class="required">*</span></label>
								<input
										type="date"
										id="cron-form-date"
										class="cron-form-input"
								>
							</div>

							<!-- 每週選擇器 -->
							<div class="cron-form-field cron-weekly-field" id="cron-weekly-field" style="display: none;">
								<label for="cron-form-weekday"><?php _e( '星期', 'otz' ); ?> <span class="required">*</span></label>
								<select id="cron-form-weekday" class="cron-form-input">
									<option value="1"><?php _e( '週一', 'otz' ); ?></option>
									<option value="2"><?php _e( '週二', 'otz' ); ?></option>
									<option value="3"><?php _e( '週三', 'otz' ); ?></option>
									<option value="4"><?php _e( '週四', 'otz' ); ?></option>
									<option value="5"><?php _e( '週五', 'otz' ); ?></option>
									<option value="6"><?php _e( '週六', 'otz' ); ?></option>
									<option value="0"><?php _e( '週日', 'otz' ); ?></option>
								</select>
							</div>

							<!-- 每月選擇器 -->
							<div class="cron-form-field cron-monthly-field" id="cron-monthly-field" style="display: none;">
								<label for="cron-form-day"><?php _e( '每月幾日', 'otz' ); ?> <span class="required">*</span></label>
								<select id="cron-form-day" class="cron-form-input">
									<?php for ( $day = 1; $day <= 31; $day++ ) : ?>
										<option value="<?php echo $day; ?>"><?php echo $day; ?><?php _e( '日', 'otz' ); ?></option>
									<?php endfor; ?>
								</select>
							</div>
							
							<div class="cron-form-field">
								<label for="cron-form-time"><?php _e( '排程時間', 'otz' ); ?> <span class="required">*</span></label>
								<input
										type="time"
										id="cron-form-time"
										class="cron-form-input"
										required
								>
							</div>
						</div>
					<div class="cron-form-actions">
						<button type="submit" class="button button-primary cron-form-save-btn" id="cron-form-save-btn">
							<?php _e( '儲存排程', 'otz' ); ?>
						</button>
						<button type="button" class="button cron-form-cancel-btn">
							<?php _e( '取消', 'otz' ); ?>
						</button>
					</div>
				</form>
			</div>

		</div>
	</div>
</div>


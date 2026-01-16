<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
	<meta name="robots" content="noindex, nofollow">
	<meta name="format-detection" content="telephone=no">
	<title><?php echo esc_html__( 'OrderChatz 行動聊天', 'otz' ); ?> - <?php bloginfo( 'name' ); ?></title>
	
	<!-- Prevent caching -->
	<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
	<meta http-equiv="Pragma" content="no-cache">
	<meta http-equiv="Expires" content="0">
	
	<!-- PWA meta tags -->
	<meta name="theme-color" content="#007cba">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="default">
	<meta name="apple-mobile-web-app-title" content="OrderChatz">
	
	<!-- Favicon -->
	<link rel="icon" href="<?php echo esc_url( OTZ_PLUGIN_URL . '/assets/img/otz-icon.png' ); ?>">
	
	<?php
	// Load assets through our asset manager
	do_action( 'otz_frontend_chat_enqueue_assets' );

	// Output enqueued assets (protected by whitelist)
	wp_head();
	?>
</head>
<body class="otz-frontend-chat">
	<div id="otz-mobile-chat-app" class="otz-mobile-app">
		
		<!-- Loading Screen -->
		<div id="otz-loading-screen" class="otz-loading-screen">
			<div class="otz-loading-spinner">
				<div class="otz-spinner"></div>
				<p><?php esc_html_e( '載入中...', 'otz' ); ?></p>
			</div>
		</div>
		
		<!-- Main Content Area = 直接使用後台完整結構 -->
		<div id="otz-main-content" class="otz-main-content" style="display: none;">
			<?php
			// 引用後台完整的聊天介面結構
			require __DIR__ . '/../admin/chat-interface.php';
			?>
		</div>
		
		<!-- Mobile Bottom Navigation -->
		<div class="otz-bottom-navigation">
			<button type="button" class="otz-tab-btn active" data-panel="friends">
				<span class="dashicons dashicons-groups"></span>
				<span class="tab-label"><?php esc_html_e( '好友', 'otz' ); ?></span>
			</button>
			<button type="button" class="otz-tab-btn" data-panel="chat" disabled>
				<span class="dashicons dashicons-format-chat"></span>
				<span class="tab-label"><?php esc_html_e( '聊天', 'otz' ); ?></span>
			</button>
			<button type="button" class="otz-tab-btn" data-panel="customer" disabled>
				<span class="dashicons dashicons-admin-users"></span>
				<span class="tab-label"><?php esc_html_e( '資訊', 'otz' ); ?></span>
			</button>
<!--			<button type="button" class="otz-tab-btn" data-panel="settings">-->
<!--				<span class="dashicons dashicons-admin-generic"></span>-->
<!--				<span class="tab-label">--><?php // esc_html_e( '設定', 'otz' ); ?><!--</span>-->
<!--			</button>-->
		</div>
		
		<!-- Network Status Indicator -->
		<div id="otz-network-status" class="otz-network-status" style="display: none;">
			<span class="dashicons dashicons-warning"></span>
			<span class="status-text"><?php esc_html_e( '網路連線中斷', 'otz' ); ?></span>
		</div>
		
		<!-- Desktop Warning -->
		<div class="otz-desktop-warning">
			<div class="warning-content">
				<h2><?php esc_html_e( '此頁面專為行動裝置設計', 'otz' ); ?></h2>
				<p><?php esc_html_e( '請使用螢幕寬度小於 1024px 的裝置訪問，或調整瀏覽器視窗大小。', 'otz' ); ?></p>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=order-chatz-chat' ) ); ?>" class="button">
					<?php esc_html_e( '前往桌面版聊天室', 'otz' ); ?>
				</a></p>
			</div>
		</div>
		
		<!-- Push Notification Subscription Prompt -->
		<div id="otz-subscription-prompt" class="otz-subscription-prompt" style="display: none;">
			<div class="otz-prompt-overlay">
				<div class="otz-prompt-content">
					<div class="otz-prompt-header">
						<h3><?php esc_html_e( '開啟推播通知', 'otz' ); ?></h3>
						<button class="otz-prompt-close" id="otz-prompt-close">×</button>
					</div>
					<div class="otz-prompt-body">
						<p><?php esc_html_e( '開啟推播通知後，您將能夠：', 'otz' ); ?></p>
						<ul>
							<li><?php esc_html_e( '即時收到新訊息通知', 'otz' ); ?></li>
							<li><?php esc_html_e( '即使關閉網頁也能收到提醒', 'otz' ); ?></li>
							<li><?php esc_html_e( '在手機鎖定畫面直接查看訊息', 'otz' ); ?></li>
							<li><?php esc_html_e( '快速回應客戶，提升服務品質', 'otz' ); ?></li>
							<li><?php esc_html_e( '您隨時可以在設定中關閉推播通知', 'otz' ); ?></li>
						</ul>
					</div>
					<div class="otz-prompt-actions">
						<button class="otz-prompt-btn secondary" id="otz-prompt-later"><?php esc_html_e( '稍後再說', 'otz' ); ?></button>
						<button class="otz-prompt-btn primary" id="otz-prompt-enable"><?php esc_html_e( '立即開啟', 'otz' ); ?></button>
					</div>
				</div>
			</div>
		</div>

		<!-- Push Notification Status Indicator -->
		<div id="otz-push-status-indicator" class="otz-push-status-indicator hidden" style="display: none;">
			<div class="otz-status-content">
				<div class="otz-status-icon">📱</div>
				<div class="otz-status-text">
					<span class="otz-status-message"></span>
				</div>
				<div class="otz-status-actions">
					<button class="otz-status-action-btn" id="otz-status-action"><?php esc_html_e( '啟用', 'otz' ); ?></button>
					<button class="otz-status-close-btn" id="otz-status-close">×</button>
				</div>
			</div>
		</div>

		<!-- Push Notification Permission Guide -->
		<div id="otz-permission-guide" class="otz-permission-guide" style="display: none;">
			<div class="otz-guide-overlay">
				<div class="otz-guide-content">
					<div class="otz-guide-header">
						<h3><?php esc_html_e( '如何開啟推播通知', 'otz' ); ?></h3>
						<button class="otz-guide-close" id="otz-guide-close">×</button>
					</div>
					<div class="otz-guide-body">
						<p><?php esc_html_e( '請按照以下步驟開啟推播通知：', 'otz' ); ?></p>
						<div class="otz-guide-steps">
							<div class="otz-guide-step">
								<strong><?php esc_html_e( '步驟 1：', 'otz' ); ?></strong>
								<p><?php esc_html_e( '點選瀏覽器網址列左側的「鎖頭」或「資訊」圖示', 'otz' ); ?></p>
							</div>
							<div class="otz-guide-step">
								<strong><?php esc_html_e( '步驟 2：', 'otz' ); ?></strong>
								<p><?php esc_html_e( '找到「通知」或「Notifications」選項', 'otz' ); ?></p>
							</div>
							<div class="otz-guide-step">
								<strong><?php esc_html_e( '步驟 3：', 'otz' ); ?></strong>
								<p><?php esc_html_e( '將設定改為「允許」或「Allow」', 'otz' ); ?></p>
							</div>
							<div class="otz-guide-step">
								<strong><?php esc_html_e( '步驟 4：', 'otz' ); ?></strong>
								<p><?php esc_html_e( '重新整理頁面即可完成設定', 'otz' ); ?></p>
							</div>
						</div>
					</div>
					<div class="otz-guide-actions">
						<button class="otz-guide-btn" id="otz-guide-understand"><?php esc_html_e( '我知道了', 'otz' ); ?></button>
					</div>
				</div>
			</div>
		</div>

		<!-- PWA Install Banner -->
		<div id="otz-install-banner" class="otz-install-banner" style="display: none;">
			<div class="otz-install-content">
				<div class="otz-install-icon">
					<img src="<?php echo esc_url( OTZ_PLUGIN_URL . '/assets/img/otz-icon-192.png' ); ?>" alt="OrderChatz" class="otz-install-icon-img">
				</div>
				<div class="otz-install-text">
					<h3><?php esc_html_e( '安裝 OrderChatz', 'otz' ); ?></h3>
					<p><?php esc_html_e( '將 OrderChatz 新增至主畫面，享受更快速的存取體驗', 'otz' ); ?></p>
				</div>
				<div class="otz-install-actions">
					<button class="otz-install-btn" id="otz-install-now"><?php esc_html_e( '安裝', 'otz' ); ?></button>
					<button class="otz-install-close" id="otz-install-close" aria-label="<?php esc_attr_e( '關閉', 'otz' ); ?>">×</button>
				</div>
			</div>
		</div>
	</div>
	
	<?php
	// Initialize mobile chat specific JavaScript
	do_action( 'otz_frontend_chat_footer_scripts' );

	// Output footer scripts (protected by whitelist)
	wp_footer();
	?>
	
	<!-- Prevent default form submissions -->
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		// Prevent form submissions from reloading the page
		document.addEventListener('submit', function(e) {
			e.preventDefault();
		});
		
		// Basic app initialization
		if (typeof window.otzMobileChat !== 'undefined') {
			window.otzMobileChat.init();
		}
	});
	</script>
</body>
</html>

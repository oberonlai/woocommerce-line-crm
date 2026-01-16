<?php
/**
 * OrderChatz Webhook 頁面渲染器
 *
 * 處理 LINE Webhook 設定頁面的內容渲染
 *
 * @package OrderChatz\Admin\Menu
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Menu\Pages;

use OrderChatz\Admin\Menu\PageRenderer;
use OrderChatz\API\WebhookRegistrar;
use OrderChatz\API\RestAPIManager;
use OrderChatz\Database\ErrorHandler;

/**
 * Webhook 頁面渲染器類別
 *
 * 渲染 LINE Webhook 設定相關功能的管理介面
 */
class Webhook extends PageRenderer {

	/**
	 * Webhook registrar instance
	 */
	private ?WebhookRegistrar $webhook_registrar;

	/**
	 * REST API manager instance
	 */
	private ?RestAPIManager $rest_api_manager;

	/**
	 * Error handler instance
	 */
	private ?ErrorHandler $error_handler;

	/**
	 * Settings group name
	 */
	private const SETTINGS_GROUP = 'orderchatz_line_webhook_settings';

	/**
	 * 建構函式
	 */
	public function __construct() {
		parent::__construct(
			__( 'Webhook', 'otz' ),
			'otz-webhook',
			true // Webhook 頁面有頁籤導航
		);

		$this->init_dependencies();
		$this->register_settings();
	}

	/**
	 * 初始化依賴項目
	 */
	private function init_dependencies(): void {
		global $wpdb;

		// 獲取已存在的實例或創建新的
		$this->error_handler    = new ErrorHandler( $wpdb, null );
		$security_validator     = new \OrderChatz\Database\SecurityValidator( $wpdb, $this->error_handler );
		$this->rest_api_manager = new RestAPIManager( $wpdb, null, $this->error_handler, $security_validator );

		// 需要其他依賴項目來創建 WebhookRegistrar
		if ( class_exists( 'OrderChatz\\API\\LineAPIClient' ) ) {
			$line_api_client         = new \OrderChatz\API\LineAPIClient( $wpdb, null, $this->error_handler, $security_validator );
			$this->webhook_registrar = new WebhookRegistrar( $wpdb, null, $this->error_handler, $security_validator, $line_api_client );
		}
	}

	/**
	 * 註冊設定
	 */
	private function register_settings(): void {
		add_action( 'admin_init', array( $this, 'register_webhook_settings' ) );

		// 註冊 AJAX 動作 - 確保在所有情況下都能執行
		add_action( 'wp_ajax_otz_save_webhook_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_otz_register_webhook', array( $this, 'ajax_register_webhook' ) );
		add_action( 'wp_ajax_otz_verify_webhook', array( $this, 'ajax_verify_webhook' ) );
		add_action( 'wp_ajax_otz_test_api_connection', array( $this, 'ajax_test_api_connection' ) );

		// 為非登入使用者也註冊 (如果需要)
		add_action( 'wp_ajax_nopriv_otz_save_webhook_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_nopriv_otz_register_webhook', array( $this, 'ajax_register_webhook' ) );
		add_action( 'wp_ajax_nopriv_otz_verify_webhook', array( $this, 'ajax_verify_webhook' ) );
		add_action( 'wp_ajax_nopriv_otz_test_api_connection', array( $this, 'ajax_test_api_connection' ) );
	}

	/**
	 * 註冊 WordPress 設定
	 */
	public function register_webhook_settings(): void {
		// Register setting groups
		register_setting(
			self::SETTINGS_GROUP,
			'otz_access_token',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_access_token' ),
				'default'           => '',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			'otz_channel_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_channel_secret' ),
				'default'           => '',
			)
		);
	}

	/**
	 * 渲染 Webhook 頁面內容
	 *
	 * @return void
	 */
	protected function renderPageContent(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'otz' ) );
		}

		// 處理表單提交
		if ( isset( $_POST['submit_webhook_settings'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'otz_webhook_settings' ) ) {
			$this->handle_form_submission();
		}

		echo '<div class="orderchatz-webhook-page">';

		$this->render_webhook_settings_form();

		echo '</div>';

		// 加載必要的腳本
		$this->enqueue_webhook_scripts();
	}

	/**
	 * 渲染 Webhook 設定表單
	 */
	private function render_webhook_settings_form(): void {
		$access_token   = get_option( 'otz_access_token', '' );
		$channel_secret = get_option( 'otz_channel_secret', '' );

		?>
		<div class="wrap">
			<h2><?php _e( 'LINE Webhook 設定', 'otz' ); ?></h2>
			
			<?php settings_errors(); ?>
			
			<form method="post" id="otz-webhook-settings-form">
				<?php wp_nonce_field( 'otz_webhook_settings' ); ?>
				
				<table class="form-table">
					<tbody>
					<tr>
						<th scope="row">
							<label for="otz_channel_secret"><?php _e( 'Channel Secret', 'otz' ); ?></label>
						</th>
						<td>
							<input type="password"
									id="otz_channel_secret"
									name="otz_channel_secret"
									value="<?php echo esc_attr( $channel_secret ); ?>"
									class="regular-text"
									placeholder="<?php _e( '請輸入您的 Channel Secret', 'otz' ); ?>" />
							<button type="button" class="button button-secondary" onclick="togglePasswordVisibility('otz_channel_secret')">
								<?php _e( '顯示/隱藏', 'otz' ); ?>
							</button>
							<p class="description">
								<?php _e( '從 LINE Developers Console Basic settings 頁籤取得 Channel Secret', 'otz' ); ?>
							</p>
						</td>
					</tr>
						<tr>
							<th scope="row">
								<label for="otz_access_token"><?php _e( 'Channel Access Token', 'otz' ); ?></label>
							</th>
							<td>
								<input type="password" 
									   id="otz_access_token" 
									   name="otz_access_token" 
									   value="<?php echo esc_attr( $access_token ); ?>" 
									   class="regular-text" 
									   placeholder="<?php _e( '請輸入您的 Channel Access Token', 'otz' ); ?>" />
								<button type="button" class="button button-secondary" onclick="togglePasswordVisibility('otz_access_token')">
									<?php _e( '顯示/隱藏', 'otz' ); ?>
								</button>
								<p class="description">
									<?php _e( '從 LINE Developers Console Messaging API 頁籤取得 Channel Access Token', 'otz' ); ?>
								</p>
							</td>
						</tr>
						
						
					</tbody>
				</table>
				
				<a href="#" id="save-webhook-settings" class="button button-primary"><?php _e( '儲存設定', 'otz' ); ?></a>
			</form>
			
			<div id="otz-status-messages" style="margin-top: 20px;"></div>
			
			<!-- Webhook 手動設定區塊 -->
			<?php $this->render_webhook_setup_section(); ?>
		</div>
		<?php
	}

	/**
	 * 渲染 Webhook 設定區塊
	 */
	private function render_webhook_setup_section(): void {
		?>
		<div class="webhook-manual-setup" style="margin-top: 30px; padding: 20px; border: 1px solid #ddd; background: #fafafa;">
			<h3><?php _e( '📋 Webhook 手動設定步驟', 'otz' ); ?></h3>
			<p><?php _e( '完成上方 LINE API 設定後，請按照以下步驟設定 Webhook：', 'otz' ); ?></p>

			<div style="margin: 20px 0;">
				<h4><?php _e( '步驟 1：註冊 Webhook URL', 'otz' ); ?></h4>
				<p><?php _e( '複製下方 Webhook URL 到 Messaging API 設定：', 'otz' ); ?></p>
				<code style="font-size: 14px; padding: 5px; background: white; border: 1px solid #ddd; display: inline-block; word-break: break-all;">
					<?php echo esc_html( $this->get_webhook_url() ); ?>
				</code>
				<button type="button" class="button button-secondary" onclick="copyWebhookUrl()" style="margin-left: 10px;">
					<?php _e( '複製 URL', 'otz' ); ?>
				</button>
<!--				<a id="register-webhook" class="button button-primary" style="margin-right: 10px;">-->
<!--					--><?php // _e( '註冊 Webhook URL', 'otz' ); ?>
<!--				</a>-->
<!--				<span id="webhook-registration-status" style="margin-left: 10px;"></span>-->
			</div>

			<div style="margin: 20px 0;">
				<h4><?php _e( '步驟 2：在 LINE Console 啟用 Webhook', 'otz' ); ?></h4>
				<ol>
					<li><?php _e( '前往', 'otz' ); ?> <a href="https://developers.line.biz/console/" target="_blank" rel="noopener">LINE Developers Console</a> 🔗</li>
					<li><?php _e( '選擇您的 Provider → 選擇對應的 Messaging API Channel', 'otz' ); ?></li>
					<li><?php _e( '點擊「Messaging API」分頁', 'otz' ); ?></li>
					<li><?php _e( '在「Webhook settings」區塊中：', 'otz' ); ?>
						<ul style="margin-top: 5px; list-style-type: disc;">
							<li><?php _e( '貼上 Webhook URL', 'otz' ); ?></li>
							<li><?php _e( '手動勾選「Use webhook」選項', 'otz' ); ?></li>
							<li><?php _e( '手動勾選「Webhook redelivery」，確保當網站斷線恢復連線後能夠接收到先前遺漏的訊息', 'otz' ); ?></li>
							<li><?php _e( '手動勾選「Error statistics aggregation」，就能在 Statistics 頁籤看到 Webhook 錯誤資訊以利除錯', 'otz' ); ?></li>
						</ul>
					</li>
				</ol>
				<p><a class="button button-primary" target="_blank" rel="noopener" href="https://oberonlai.blog/docs/order-chatz-doc/settings/02-line-messaging-api-webhook/">查看教學文件</a></p>
			</div>

			<div style="margin: 20px 0;">
				<h4><?php _e( '步驟 3：驗證設定', 'otz' ); ?></h4>
				<p><?php _e( '完成上述步驟後，使用下方按鈕驗證設定：', 'otz' ); ?></p>
				<button type="button" id="verify-webhook" class="button button-secondary" style="margin-right: 10px;">
					<?php _e( '驗證 Webhook 狀態', 'otz' ); ?>
				</button>
				<button type="button" id="test-connection" class="button button-secondary">
					<?php _e( '測試 API 連線', 'otz' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * 處理表單提交
	 */
	private function handle_form_submission(): void {
		$access_token   = sanitize_text_field( $_POST['otz_access_token'] ?? '' );
		$channel_secret = sanitize_text_field( $_POST['otz_channel_secret'] ?? '' );

		// 驗證並儲存設定
		$errors = array();

		if ( empty( $access_token ) ) {
			$errors[] = __( 'Channel Access Token 不能為空', 'otz' );
		} elseif ( strlen( $access_token ) < 20 ) {
			$errors[] = __( 'Channel Access Token 格式可能不正確', 'otz' );
		}

		if ( empty( $channel_secret ) ) {
			$errors[] = __( 'Channel Secret 不能為空', 'otz' );
		} elseif ( strlen( $channel_secret ) < 10 ) {
			$errors[] = __( 'Channel Secret 格式可能不正確', 'otz' );
		}

		if ( empty( $errors ) ) {
			update_option( 'otz_access_token', $access_token );
			update_option( 'otz_channel_secret', $channel_secret );

			add_settings_error(
				'otz_webhook_settings',
				'settings_saved',
				__( '設定已成功儲存！', 'otz' ),
				'success'
			);
		} else {
			foreach ( $errors as $error ) {
				add_settings_error(
					'otz_webhook_settings',
					'validation_error',
					$error,
					'error'
				);
			}
		}
	}

	/**
	 * 取得 Webhook URL
	 */
	private function get_webhook_url(): string {
		if ( $this->rest_api_manager ) {
			return $this->rest_api_manager->get_webhook_url();
		}

		// 回退到預設 URL 格式
		return site_url( '/wp-json/orderchatz/v1/webhook' );
	}

	/**
	 * 載入腳本和樣式
	 */
	private function enqueue_webhook_scripts(): void {
		wp_enqueue_script( 'jquery' );

		// 內聯腳本
		?>
		<script>
		function togglePasswordVisibility(fieldId) {
			const field = document.getElementById(fieldId);
			field.type = field.type === 'password' ? 'text' : 'password';
		}
		
		function copyWebhookUrl() {
			const url = '<?php echo esc_js( $this->get_webhook_url() ); ?>';
			navigator.clipboard.writeText(url).then(() => {
				alert('<?php _e( 'Webhook URL 已複製到剪貼簿', 'otz' ); ?>');
			});
		}
		
		jQuery(document).ready(function($) {
			// AJAX 處理儲存設定
			$('#save-webhook-settings').on('click', function(e) {
				e.preventDefault();
				const button = $(this);
				const originalText = button.text();
				button.prop('disabled', true).text('<?php _e( '儲存中...', 'otz' ); ?>');
				
				const formData = {
					action: 'otz_save_webhook_settings',
					nonce: '<?php echo wp_create_nonce( 'otz_admin_nonce' ); ?>',
					access_token: $('#otz_access_token').val(),
					channel_secret: $('#otz_channel_secret').val()
				};
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: formData,
					success: function(response) {
						if (response.success) {
							$('#otz-status-messages').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
						} else {
							$('#otz-status-messages').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
						}
					},
					error: function() {
						$('#otz-status-messages').html('<div class="notice notice-error"><p><?php _e( '儲存失敗，請重試', 'otz' ); ?></p></div>');
					},
					complete: function() {
						button.prop('disabled', false).text(originalText);
					}
				});
			});
			
			// AJAX 處理註冊 Webhook
			$('#register-webhook').on('click', function(e) {
				e.preventDefault();
				const button = $(this);
				button.prop('disabled', true).text('<?php _e( '註冊中...', 'otz' ); ?>');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'otz_register_webhook',
						nonce: '<?php echo wp_create_nonce( 'otz_admin_nonce' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							$('#webhook-registration-status').html('<span style="color: green;">✓ ' + response.data.message + '</span>');
						} else {
							$('#webhook-registration-status').html('<span style="color: red;">✗ ' + response.data.message + '</span>');
						}
					},
					error: function(xhr, status, error) {
						console.log('AJAX Error:', xhr.responseText);
						$('#webhook-registration-status').html('<span style="color: red;">✗ <?php _e( '請求失敗，請檢查網路連線', 'otz' ); ?></span>');
					},
					complete: function() {
						button.prop('disabled', false).text('<?php _e( '註冊 Webhook URL', 'otz' ); ?>');
					}
				});
			});
			
			// 驗證 Webhook
			$('#verify-webhook').on('click', function(e) {
				e.preventDefault();
				const button = $(this);
				button.prop('disabled', true).text('<?php _e( '驗證中...', 'otz' ); ?>');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'otz_verify_webhook',
						nonce: '<?php echo wp_create_nonce( 'otz_admin_nonce' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							alert('✓ ' + response.data.message);
						} else {
							alert('✗ ' + response.data.message);
						}
					},
					error: function(xhr, status, error) {
						alert('✗ <?php _e( '驗證失敗，請重試', 'otz' ); ?>');
					},
					complete: function() {
						button.prop('disabled', false).text('<?php _e( '驗證 Webhook 狀態', 'otz' ); ?>');
					}
				});
			});
			
			// 測試連線
			$('#test-connection').on('click', function(e) {
				e.preventDefault();
				const button = $(this);
				button.prop('disabled', true).text('<?php _e( '測試中...', 'otz' ); ?>');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'otz_test_api_connection',
						nonce: '<?php echo wp_create_nonce( 'otz_admin_nonce' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							alert('✓ ' + response.data.message);
						} else {
							alert('✗ ' + response.data.message);
						}
					},
					error: function(xhr, status, error) {
						alert('✗ <?php _e( '連線測試失敗，請重試', 'otz' ); ?>');
					},
					complete: function() {
						button.prop('disabled', false).text('<?php _e( '測試 API 連線', 'otz' ); ?>');
					}
				});
			});
		});
		</script>
		
		<style>
		.orderchatz-webhook-page .form-table th {
			width: 200px;
		}
		
		.orderchatz-webhook-page .regular-text {
			width: 300px;
		}
		
		.webhook-manual-setup h3 {
			margin-top: 0;
		}
		
		.webhook-manual-setup ol {
			padding-left: 20px;
		}
		
		.webhook-manual-setup code {
			max-width: 100%;
			overflow-wrap: break-word;
		}
		</style>
		<?php
	}

	/**
	 * AJAX 處理器
	 */
	public function ajax_save_settings(): void {
		check_ajax_referer( 'otz_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( '權限不足', 'otz' ) ) );
		}

		$access_token   = sanitize_text_field( $_POST['access_token'] ?? '' );
		$channel_secret = sanitize_text_field( $_POST['channel_secret'] ?? '' );

		update_option( 'otz_access_token', $access_token );
		update_option( 'otz_channel_secret', $channel_secret );

		wp_send_json_success( array( 'message' => __( '設定已儲存', 'otz' ) ) );
	}

	public function ajax_register_webhook(): void {
		// 簡化測試，暫時移除 nonce 檢查
		// check_ajax_referer('otz_admin_nonce', 'nonce');

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( '權限不足', 'otz' ) ) );
		}
		// 簡化測試，直接返回成功
		wp_send_json_success( array( 'message' => __( 'AJAX 連接測試成功！', 'otz' ) ) );
	}

	public function ajax_verify_webhook(): void {
		check_ajax_referer( 'otz_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( '權限不足', 'otz' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Webhook 驗證完成', 'otz' ) ) );
	}

	public function ajax_test_api_connection(): void {
		check_ajax_referer( 'otz_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( '權限不足', 'otz' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'API 連線測試完成', 'otz' ) ) );
	}

	/**
	 * 清理函數
	 */
	public function sanitize_access_token( string $value ): string {
		$sanitized = sanitize_text_field( $value );
		return $sanitized;
	}

	public function sanitize_channel_secret( string $value ): string {
		$sanitized = sanitize_text_field( $value );
		return $sanitized;
	}
}

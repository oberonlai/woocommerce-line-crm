<?php
/**
 * OrderChatz 管理選單系統主控制器
 *
 * 負責統籌所有選單相關功能，包括選單註冊、權限檢查、頁面渲染等
 *
 * @package OrderChatz\Admin\Menu
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Menu;

use OrderChatz\Util\Logger;
use OrderChatz\Admin\Menu\Pages\{
	Chat,
	Broadcast,
	Bot,
	Friends,
	Tags,
	Notes,
	Webhook,
	Export,
	Authorization,
	Statistics,
	Subscribers
};

/**
 * 管理選單系統主控制器類別
 *
 * 統籌所有選單相關功能，初始化子元件，處理 WordPress hook 註冊
 */
class AdminMenuManager {
	/**
	 * 選單註冊器實例
	 *
	 * @var MenuRegistrar
	 */
	private MenuRegistrar $menuRegistrar;

	/**
	 * 頁籤導航渲染器實例
	 *
	 * @var TabNavigationRenderer
	 */
	private TabNavigationRenderer $tabRenderer;

	/**
	 * 權限驗證器實例
	 *
	 * @var PermissionValidator
	 */
	private PermissionValidator $permissionValidator;

	/**
	 * 安全驗證器實例
	 *
	 * @var SecurityValidator
	 */
	private SecurityValidator $securityValidator;

	/**
	 * 建構函式
	 *
	 * 初始化所有依賴的子元件
	 */
	public function __construct() {
		$this->permissionValidator = new PermissionValidator();
		$this->securityValidator   = new SecurityValidator();
		$this->menuRegistrar       = new MenuRegistrar( $this->permissionValidator );
		$this->tabRenderer         = new TabNavigationRenderer();

		// 立即實例化 WebhookPageRenderer 以註冊 AJAX 動作
		$this->initializePageRenderersForAjax();
	}

	/**
	 * 初始化管理選單系統
	 *
	 * 註冊所有必要的 WordPress hooks 和初始化設定
	 *
	 * @return void
	 */
	public function init(): void {
		$this->registerHooks();
	}

	/**
	 * 註冊 WordPress hooks
	 *
	 * 註冊所有必要的 WordPress actions 和 filters
	 *
	 * @return void
	 */
	public function registerHooks(): void {
		add_action( 'admin_menu', array( $this, 'handleMenuRegistration' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAssets' ) );
	}

	/**
	 * 處理選單註冊
	 *
	 * 檢查權限並註冊主選單和子選單
	 *
	 * @return void
	 */
	public function handleMenuRegistration(): void {
		try {
			if ( ! $this->permissionValidator->validateUserPermission() ) {
				return;
			}

			$this->menuRegistrar->registerMainMenu();
			$this->menuRegistrar->registerSubMenus();

		} catch ( \Exception $e ) {
			Logger::error(
				'選單註冊失敗',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);
		}
	}

	/**
	 * 渲染指定頁面
	 *
	 * 根據頁面類型渲染對應的管理頁面內容
	 *
	 * @param string $page 頁面 slug
	 * @return void
	 */
	public function renderPage( string $page ): void {
		// 防止同一頁面重複渲染
		static $rendered_pages = array();

		// 清理頁面參數
		$page = sanitize_key( $page );

		// 標準化頁面 slug（主選單 order-chatz 對應到 chat）
		$normalizedPage = ( $page === 'order-chatz' ) ? 'chat' : $page;

		if ( isset( $rendered_pages[ $normalizedPage ] ) ) {
			return;
		}

		$rendered_pages[ $normalizedPage ] = true;

		try {
			// 驗證頁面存在
			$allowedPages = $this->menuRegistrar->getMenuPages();
			if ( ! array_key_exists( $normalizedPage, $allowedPages ) && $page !== 'order-chatz' ) {
				wp_die( __( '無效的頁面參數。', 'otz' ) );
			}

			// 綜合安全驗證
			$requestData   = array_merge( $_GET, $_POST );
			$securityCheck = $this->securityValidator->validatePageAccess( $normalizedPage, $requestData );

			// if ( ! $securityCheck['valid'] ) {
			// $this->handleSecurityViolation( $normalizedPage, $securityCheck['errors'] );
			// return;
			// }

			// 渲染頁面
			$this->renderPageContent( $normalizedPage );

		} catch ( \Exception $e ) {
			Logger::error(
				'頁面渲染失敗',
				array(
					'page'            => $page,
					'normalized_page' => $normalizedPage,
					'error'           => $e->getMessage(),
					'trace'           => $e->getTraceAsString(),
				)
			);
			wp_die( __( '頁面載入時發生錯誤。', 'otz' ) );
		}
	}

	/**
	 * 載入管理頁面資源
	 *
	 * 只在 OrderChatz 頁面載入相關的 CSS 和 JavaScript
	 *
	 * @param string $hookSuffix 頁面 hook suffix
	 * @return void
	 */
	public function enqueueAssets( string $hookSuffix ): void {
		if ( ! $this->isOrderChatzPage( $hookSuffix ) ) {
			return;
		}

		// 載入 CSS
		wp_enqueue_style(
			'orderchatz-admin-menu',
			OTZ_PLUGIN_URL . 'assets/css/admin-menu.css',
			array( 'woocommerce_admin_styles' ),
			OTZ_VERSION
		);

		// 載入 JavaScript
		wp_enqueue_script(
			'orderchatz-admin-menu',
			OTZ_PLUGIN_URL . 'assets/js/admin-menu.js',
			array( 'jquery', 'woocommerce_admin' ),
			OTZ_VERSION,
			true
		);
	}

	/**
	 * 檢查是否為 OrderChatz 頁面
	 *
	 * @param string $hookSuffix 頁面 hook suffix
	 * @return bool
	 */
	private function isOrderChatzPage( string $hookSuffix ): bool {
		return strpos( $hookSuffix, 'order-chatz' ) !== false;
	}

	/**
	 * 初始化需要 AJAX 功能的頁面渲染器
	 *
	 * 確保有 AJAX 動作的頁面渲染器在插件初始化時就被創建
	 *
	 * @return void
	 */
	private function initializePageRenderersForAjax(): void {
		// 創建 WebhookPageRenderer 以註冊 AJAX 動作
		new Webhook();

		// 創建 Friends 實例以註冊 admin_init hook
		new Friends();

		// 創建 Export 實例以註冊 AJAX 動作
		new Export();

		// 註冊 Friends 頁面的 AJAX handlers
		Friends::init_ajax_handlers();
	}

	/**
	 * 渲染頁面內容
	 *
	 * 根據頁面類型使用對應的頁面渲染器
	 *
	 * @param string $page 頁面 slug
	 * @return void
	 */
	private function renderPageContent( string $page ): void {
		$renderer = $this->getPageRenderer( $page );

		if ( $renderer ) {
			$renderer->render();
		} else {
			$this->renderFallbackPage( $page );
		}
	}

	/**
	 * 取得頁面渲染器實例
	 *
	 * @param string $page 頁面 slug
	 * @return PageRenderer|null
	 */
	private function getPageRenderer( string $page ): ?PageRenderer {
		// 靜態存儲已創建的渲染器實例，避免重複創建
		static $renderers = array();

		// 標準化頁面 slug（主選單和子選單都使用同一個聊天渲染器）
		$normalizedPage = ( $page === 'order-chatz' ) ? 'chat' : $page;

		// 如果已經創建過該頁面的渲染器，直接返回
		if ( isset( $renderers[ $normalizedPage ] ) ) {
			return $renderers[ $normalizedPage ];
		}

		// 創建新的渲染器實例
		switch ( $normalizedPage ) {
			case 'chat':
				$renderers[ $normalizedPage ] = new Chat();
				break;
			case 'otz-broadcast':
				$renderers[ $normalizedPage ] = new Broadcast();
				break;
			case 'otz-bot':
				$renderers[ $normalizedPage ] = new Bot();
				break;
			case 'otz-friends':
				$renderers[ $normalizedPage ] = new Friends();
				break;
			case 'otz-tags':
				$renderers[ $normalizedPage ] = new Tags();
				break;
			case 'otz-notes':
				$renderers[ $normalizedPage ] = new Notes();
				break;
			case 'otz-webhook':
				$renderers[ $normalizedPage ] = new Webhook();
				break;
			case 'otz-export':
				$renderers[ $normalizedPage ] = new Export();
				break;
			case 'otz-authorization':
				$renderers[ $normalizedPage ] = new Authorization();
				break;
			case 'otz-statistics':
				$renderers[ $normalizedPage ] = new Statistics();
				break;
			case 'otz-subscribers':
				$renderers[ $normalizedPage ] = new Subscribers();
				break;
			default:
				return null;
		}

		return $renderers[ $normalizedPage ];
	}

	/**
	 * 渲染後備頁面
	 *
	 * 當沒有對應的頁面渲染器時使用
	 *
	 * @param string $page 頁面 slug
	 * @return void
	 */
	private function renderFallbackPage( string $page ): void {
		echo '<div class="wrap">';

		// 獲取頁面配置
		$pageConfig = $this->menuRegistrar->getMenuPages()[ $page ] ?? array();
		$pageTitle  = $pageConfig['title'] ?? ucfirst( $page );

		// 渲染頁面標題
		echo '<h1>' . esc_html( $pageTitle ) . '</h1>';

		// 檢查是否需要頁籤導航
		if ( $this->tabRenderer->isTabbed( $page ) ) {
			echo $this->tabRenderer->renderTabNavigation( $page );
		}

		// 渲染頁面內容區域
		echo '<div class="orderchatz-page-content">';
		echo '<div class="notice notice-info inline">';
		/* translators: %s: 頁面標題. */
		echo '<p>' . sprintf( __( '這是 %s 頁面的內容區域，準備未來功能實作。', 'otz' ), esc_html( $pageTitle ) ) . '</p>';
		echo '</div>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * 處理安全違規
	 *
	 * @param string $page 頁面 slug
	 * @param array  $errors 錯誤列表
	 * @return void
	 */
	private function handleSecurityViolation( string $page, array $errors ): void {
		// 記錄安全事件
		$this->securityValidator->logSecurityEvent(
			'page_access_violation',
			array(
				'page'   => $page,
				'errors' => $errors,
			)
		);

		// 根據錯誤類型決定回應
		if ( in_array( 'insufficient_permissions', $errors ) ) {
			$this->permissionValidator->handleUnauthorizedAccess();
		} elseif ( in_array( 'csrf_token_invalid', $errors ) ) {
			wp_die(
				__( '安全驗證失敗，請重新整理頁面後再試。', 'otz' ),
				__( '安全錯誤', 'otz' ),
				array( 'response' => 403 )
			);
		} elseif ( in_array( 'ip_not_allowed', $errors ) ) {
			wp_die(
				__( '您的 IP 地址不在允許的範圍內。', 'otz' ),
				__( '存取拒絕', 'otz' ),
				array( 'response' => 403 )
			);
		} else {
			wp_die(
				__( '安全驗證失敗，無法存取此頁面。', 'otz' ),
				__( '安全錯誤', 'otz' ),
				array( 'response' => 403 )
			);
		}
	}


}

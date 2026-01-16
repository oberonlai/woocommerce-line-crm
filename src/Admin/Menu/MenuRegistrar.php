<?php
/**
 * OrderChatz 選單註冊器
 *
 * 負責註冊 WordPress 主選單和所有子選單項目
 *
 * @package OrderChatz\Admin\Menu
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Menu;

use OrderChatz\Util\Logger;
use OrderChatz\Services\UnreadCountService;

/**
 * 選單註冊器類別
 *
 * 處理 WordPress 選單的註冊、配置和回呼函數生成
 */
class MenuRegistrar {
	/**
	 * 主選單 slug
	 *
	 * @var string
	 */
	private const MAIN_MENU_SLUG = 'order-chatz';

	/**
	 * 選單基本權限
	 *
	 * @var string
	 */
	private const MENU_CAPABILITY = 'manage_woocommerce';

	/**
	 * 權限驗證器實例
	 *
	 * @var PermissionValidator
	 */
	private PermissionValidator $permissionValidator;

	/**
	 * 未讀計數服務實例
	 *
	 * @var UnreadCountService
	 */
	private UnreadCountService $unreadCountService;

	/**
	 * 選單項目配置
	 *
	 * @var array
	 */
	private array $menuPages = array();

	/**
	 * 建構函式
	 *
	 * @param PermissionValidator $permissionValidator 權限驗證器實例
	 */
	public function __construct( PermissionValidator $permissionValidator ) {
		$this->permissionValidator = $permissionValidator;
		$this->unreadCountService  = new UnreadCountService();
		// 延遲初始化選單配置，避免在 plugins_loaded 階段過早使用翻譯函數.
	}

	/**
	 * 確保選單配置已初始化
	 *
	 * 延遲初始化選單配置，確保在 init 鉤子之後才使用翻譯函數
	 *
	 * @return void
	 */
	private function ensureMenuPagesInitialized(): void {
		if ( empty( $this->menuPages ) ) {
			$this->initializeMenuPages();
		}
	}

	/**
	 * 註冊主選單
	 *
	 * 在 WordPress 管理介面側邊欄註冊 OrderChatz 主選單
	 *
	 * @return string 主選單的 slug
	 */
	public function registerMainMenu(): string {
		// 確保選單配置已初始化.
		$this->ensureMenuPagesInitialized();

		try {
			// 只在 OrderChatz 相關頁面才計算未讀數.
			$unread_count = $this->isOrderChatzPage()
				? $this->unreadCountService->getTotalUnreadCount()
				: $this->getQuickUnreadCount();

			// 準備選單標題（包含 badge）.
			$menu_title = $this->buildMenuTitleWithBadge( __( 'OrderChatz', 'otz' ), $unread_count );

			add_menu_page(
				__( 'OrderChatz', 'otz' ),                    // 頁面標題
				$menu_title,                                // 選單標題（包含 badge）
				self::MENU_CAPABILITY,                      // 所需權限
				self::MAIN_MENU_SLUG,                       // 選單 slug
				array( $this, 'renderChatPage' ),                  // 回呼函數 (預設顯示聊天頁面)
				'dashicons-format-chat',                    // 圖示
				30                                          // 位置
			);

			return self::MAIN_MENU_SLUG;

		} catch ( \Exception $e ) {
			Logger::error(
				'主選單註冊失敗',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);
			throw $e;
		}
	}

	/**
	 * 註冊所有子選單
	 *
	 * 註冊 8 個功能頁面的子選單項目
	 *
	 * @return array 註冊的子選單配置陣列
	 */
	public function registerSubMenus(): array {
		// 確保選單配置已初始化.
		$this->ensureMenuPagesInitialized();

		$registeredMenus = array();

		try {
			$isFirst = true;

			foreach ( $this->menuPages as $slug => $config ) {
				if ( $isFirst ) {
					// 第一個子選單使用主選單的 slug 來替換預設項目
					$hookSuffix = add_submenu_page(
						self::MAIN_MENU_SLUG,                  // 父選單 slug
						$config['title'],                      // 頁面標題
						$config['title'],                      // 選單標題
						$this->permissionValidator->getPageRequiredCapability( $slug ), // 所需權限
						self::MAIN_MENU_SLUG,                  // 使用主選單的 slug
						$this->createPageCallback( $slug )       // 回呼函數
					);
					$isFirst    = false;
				} else {
					// 其他子選單正常註冊
					$hookSuffix = add_submenu_page(
						self::MAIN_MENU_SLUG,                  // 父選單 slug
						$config['title'],                      // 頁面標題
						$config['title'],                      // 選單標題
						$this->permissionValidator->getPageRequiredCapability( $slug ), // 所需權限
						$slug,                                 // 選單 slug
						$this->createPageCallback( $slug )       // 回呼函數
					);
				}

				$registeredMenus[ $slug ] = array(
					'config'      => $config,
					'hook_suffix' => $hookSuffix,
				);
			}

			return $registeredMenus;

		} catch ( \Exception $e ) {
			Logger::error(
				'子選單註冊失敗',
				array(
					'error'           => $e->getMessage(),
					'completed_menus' => array_keys( $registeredMenus ),
				)
			);
			throw $e;
		}
	}

	/**
	 * 取得所有選單頁面配置
	 *
	 * @return array 選單頁面配置陣列
	 */
	public function getMenuPages(): array {
		return $this->menuPages;
	}

	/**
	 * 建立頁面回呼函數
	 *
	 * 為指定的頁面 slug 建立對應的回呼函數
	 *
	 * @param string $pageSlug 頁面 slug
	 * @return callable 頁面回呼函數
	 */
	public function createPageCallback( string $pageSlug ): callable {
		return function() use ( $pageSlug ) {
			global $orderChatzAdminMenuManager;

			if ( $orderChatzAdminMenuManager instanceof AdminMenuManager ) {
				$orderChatzAdminMenuManager->renderPage( $pageSlug );
			} else {
				Logger::error(
					'AdminMenuManager 實例不存在',
					array(
						'page_slug'       => $pageSlug,
						'global_var_type' => gettype( $orderChatzAdminMenuManager ),
					)
				);
				wp_die( __( '系統初始化錯誤。', 'otz' ) );
			}
		};
	}

	/**
	 * 渲染聊天頁面
	 *
	 * 主選單的預設頁面回呼函數
	 *
	 * @return void
	 */
	public function renderChatPage(): void {

		global $orderChatzAdminMenuManager;

		if ( $orderChatzAdminMenuManager instanceof AdminMenuManager ) {
			$orderChatzAdminMenuManager->renderPage( 'chat' );
		} else {
			Logger::error(
				'AdminMenuManager 實例不存在 (聊天頁面)',
				array(
					'global_var_type' => gettype( $orderChatzAdminMenuManager ),
				)
			);
			wp_die( __( '系統初始化錯誤。', 'otz' ) );
		}
	}

	/**
	 * 檢查選單頁面是否存在
	 *
	 * @param string $pageSlug 頁面 slug
	 * @return bool 頁面是否存在
	 */
	public function pageExists( string $pageSlug ): bool {
		return array_key_exists( $pageSlug, $this->menuPages );
	}

	/**
	 * 取得選單頁面標題
	 *
	 * @param string $pageSlug 頁面 slug
	 * @return string 頁面標題，如果頁面不存在則回傳空字串
	 */
	public function getPageTitle( string $pageSlug ): string {
		return $this->menuPages[ $pageSlug ]['title'] ?? '';
	}

	/**
	 * 初始化選單頁面配置
	 *
	 * 根據授權狀態設定功能頁面配置
	 *
	 * @return void
	 */
	private function initializeMenuPages(): void {
		// 顯示完整選單
			// 已授權時顯示完整選單
			$this->menuPages = array(
				'chat'            => array(
					'title'      => __( '聊天', 'otz' ),
					'capability' => self::MENU_CAPABILITY,
					'has_tabs'   => false,
					'icon'       => 'dashicons-format-chat',
				),
				'otz-broadcast'   => array(
					'title'      => __( '推播', 'otz' ),
					'capability' => self::MENU_CAPABILITY,
					'has_tabs'   => false,
					'icon'       => 'dashicons-megaphone',
				),
				'otz-bot'         => array(
					'title'      => __( 'Bot', 'otz' ),
					'capability' => self::MENU_CAPABILITY,
					'has_tabs'   => true,
					'icon'       => 'dashicons-admin-generic',
				),
				'otz-friends'     => array(
					'title'      => __( '好友', 'otz' ),
					'capability' => self::MENU_CAPABILITY,
					'has_tabs'   => true,
					'icon'       => 'dashicons-groups',
				),
				'otz-tags'        => array(
					'title'      => __( '標籤', 'otz' ),
					'capability' => self::MENU_CAPABILITY,
					'has_tabs'   => true,
					'icon'       => 'dashicons-tag',
				),
				'otz-notes'       => array(
					'title'      => __( '備註', 'otz' ),
					'capability' => self::MENU_CAPABILITY,
					'has_tabs'   => true,
					'icon'       => 'dashicons-edit-large',
				),
				'otz-webhook'     => array(
					'title'      => __( 'Webhook', 'otz' ),
					'capability' => 'manage_options',
					'has_tabs'   => true,
					'icon'       => 'dashicons-admin-settings',
				),
				'otz-export'      => array(
					'title'      => __( '匯出', 'otz' ),
					'capability' => self::MENU_CAPABILITY,
					'has_tabs'   => true,
					'icon'       => 'dashicons-download',
				),
				'otz-statistics'  => array(
					'title'      => __( '統計', 'otz' ),
					'capability' => self::MENU_CAPABILITY,
					'has_tabs'   => false,
					'icon'       => 'dashicons-chart-bar',
				),
				'otz-subscribers' => array(
					'title'      => __( '訂閱管理', 'otz' ),
					'capability' => self::MENU_CAPABILITY,
					'has_tabs'   => true,
					'icon'       => 'dashicons-bell',
				),
			);

		// 允許其他外掛擴展選單項目
		$this->menuPages = apply_filters( 'orderchatz_menu_pages', $this->menuPages );
	}

	/**
	 * 建立包含 badge 的選單標題
	 *
	 * @param string $title 原始選單標題
	 * @param int    $count 未讀數量
	 * @return string 包含 badge 的選單標題 HTML
	 */
	private function buildMenuTitleWithBadge( string $title, int $count ): string {
		if ( $count > 0 ) {
			return sprintf(
				'%s <span class="awaiting-mod">%d</span>',
				esc_html( $title ),
				min( $count, 999 ) // 限制最大顯示數字為 999
			);
		}

		return esc_html( $title );
	}

	/**
	 * 取得未讀計數服務實例
	 *
	 * @return UnreadCountService
	 */
	public function getUnreadCountService(): UnreadCountService {
		return $this->unreadCountService;
	}

	/**
	 * 檢查是否為 OrderChatz 相關頁面
	 *
	 * @return bool
	 */
	private function isOrderChatzPage(): bool {
		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}

		$page = ( isset( $_GET['page'] ) ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		// 檢查是否為 OrderChatz 相關頁面.
		return strpos( $page, 'orderchatz' ) === 0 || strpos( $page, 'otz-' ) === 0;
	}

	/**
	 * 快速取得未讀計數（僅從快取）
	 *
	 * @return int
	 */
	private function getQuickUnreadCount(): int {
		$cached = get_transient( 'orderchatz_total_unread_count' );
		return $cached !== false ? (int) $cached : 0;
	}


}

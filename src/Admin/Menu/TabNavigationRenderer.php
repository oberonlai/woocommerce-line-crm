<?php
/**
 * OrderChatz 頁籤導航渲染器
 *
 * 負責渲染 WooCommerce 風格的頁籤導航元件
 *
 * @package OrderChatz\Admin\Menu
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Menu;

/**
 * 頁籤導航渲染器類別
 *
 * 處理頁籤導航的 HTML 生成和狀態管理
 */
class TabNavigationRenderer {
	/**
	 * 有頁籤導航的頁面配置（page slug）
	 *
	 * @var array<string>
	 */
	private array $tabbedPages = array(
		'order-chatz',
		'otz-broadcast',
		'otz-bot',
		'otz-friends',
		'otz-tags',
		'otz-notes',
		'otz-webhook',
		'otz-export',
		'otz-statistics',
		'otz-subscribers',
		'otz-license',
	);

	/**
	 * 渲染頁籤導航
	 *
	 * 生成 WooCommerce 風格的頁籤導航 HTML
	 *
	 * @param string $currentPage 當前頁面 slug
	 * @return string 頁籤導航 HTML
	 */
	public function renderTabNavigation( string $currentPage ): string {
		if ( ! $this->isTabbed( $currentPage ) ) {
			return '';
		}

		return $this->generateTabHTML( $this->get_tabbed_pages_with_titles(), $currentPage );
	}

	/**
	 * 取得有頁籤的頁面清單（含翻譯後的標題）
	 *
	 * @return array<string, string> 頁面 slug 和標題的對應陣列
	 */
	public function getTabbedPages(): array {
		return $this->get_tabbed_pages_with_titles();
	}

	/**
	 * 取得頁面標題對照表（含翻譯）
	 *
	 * @return array<string, string> 頁面 slug 和翻譯後標題的對應陣列
	 */
	private function get_tabbed_pages_with_titles(): array {
		return array(
			'order-chatz'     => __( 'Chat', 'otz' ),
			'otz-broadcast'   => __( 'Broadcast', 'otz' ),
			'otz-bot'         => __( 'Bot', 'otz' ),
			'otz-friends'     => __( 'Friends', 'otz' ),
			'otz-tags'        => __( 'Tags', 'otz' ),
			'otz-notes'       => __( 'Notes', 'otz' ),
			'otz-webhook'     => __( 'Webhook', 'otz' ),
			'otz-export'      => __( 'Export', 'otz' ),
			'otz-statistics'  => __( 'Statistics', 'otz' ),
			'otz-subscribers' => __( 'Subscriber Management', 'otz' ),
			'otz-license'     => __( 'License', 'otz' ),
		);
	}

	/**
	 * 檢查頁面是否有頁籤導航
	 *
	 * 聊天和推播頁面本身不顯示頁籤，但會出現在其他頁面的頁籤導航中
	 *
	 * @param string $page 頁面 slug
	 * @return bool 是否有頁籤導航
	 */
	public function isTabbed( string $page ): bool {
		// 聊天和推播頁面本身不顯示頁籤.
		if ( in_array( $page, array( 'chat', 'order-chatz' ), true ) ) {
			return false;
		}

		return in_array( $page, $this->tabbedPages, true );
	}

	/**
	 * 生成頁籤 HTML
	 *
	 * @param array  $tabs 頁籤配置陣列
	 * @param string $current 當前頁面 slug
	 * @return string 頁籤 HTML
	 */
	private function generateTabHTML( array $tabs, string $current ): string {
		$html = '<nav class="nav-tab-wrapper woo-nav-tab-wrapper">';

		foreach ( $tabs as $slug => $title ) {
			$url         = admin_url( 'admin.php?page=' . $slug );
			$activeClass = $this->getActiveClass( $slug, $current );

			$html .= sprintf(
				'<a href="%s" class="nav-tab%s" data-tab="%s">%s</a>',
				esc_url( $url ),
				$activeClass,
				esc_attr( $slug ),
				esc_html( $title )
			);
		}

		$html .= '</nav>';

		return $html;
	}

	/**
	 * 取得 active 狀態 CSS 類別
	 *
	 * @param string $tab 頁籤 slug
	 * @param string $current 當前頁面 slug
	 * @return string CSS 類別字串
	 */
	private function getActiveClass( string $tab, string $current ): string {
		return $tab === $current ? ' nav-tab-active' : '';
	}

	/**
	 * 清除頁籤導航快取
	 *
	 * @param string $page 特定頁面的快取，如果為空則清除所有.
	 * @return void
	 */
	public function clearTabCache( string $page = '' ): void {
		if ( ! empty( $page ) ) {
			delete_transient( "orderchatz_tab_nav_{$page}" );
		} else {
			// 清除所有頁籤快取.
			foreach ( $this->tabbedPages as $pageSlug ) {
				delete_transient( "orderchatz_tab_nav_{$pageSlug}" );
			}
		}
	}
}

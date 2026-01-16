<?php
/**
 * OrderChatz 頁面渲染器基礎類別
 *
 * 提供所有管理頁面渲染器的抽象基礎實作
 *
 * @package OrderChatz\Admin\Menu
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Menu;

use OrderChatz\Util\Logger;

/**
 * 頁面渲染器抽象基礎類別
 *
 * 定義所有頁面渲染器的共同介面和基礎功能
 */
abstract class PageRenderer {
	/**
	 * 頁面標題
	 *
	 * @var string
	 */
	protected string $pageTitle;

	/**
	 * 頁面 slug
	 *
	 * @var string
	 */
	protected string $pageSlug;

	/**
	 * 是否有頁籤導航
	 *
	 * @var bool
	 */
	protected bool $hasTabNavigation;

	/**
	 * 頁籤導航渲染器實例
	 *
	 * @var TabNavigationRenderer|null
	 */
	protected ?TabNavigationRenderer $tabRenderer = null;

	/**
	 * 建構函式
	 *
	 * @param string $pageTitle 頁面標題
	 * @param string $pageSlug 頁面 slug
	 * @param bool   $hasTabNavigation 是否有頁籤導航
	 */
	public function __construct( string $pageTitle, string $pageSlug, bool $hasTabNavigation = false ) {
		$this->pageTitle        = $pageTitle;
		$this->pageSlug         = $pageSlug;
		$this->hasTabNavigation = $hasTabNavigation;

		if ( $this->hasTabNavigation ) {
			$this->tabRenderer = new TabNavigationRenderer();
		}
	}

	/**
	 * 渲染完整頁面
	 *
	 * 統籌整個頁面的渲染流程
	 *
	 * @return void
	 */
	final public function render(): void {
		// 防止重複渲染整個頁面
		static $rendered_pages = array();
		$render_key            = get_class( $this ) . '_' . $this->pageSlug;

		if ( isset( $rendered_pages[ $render_key ] ) ) {
			return;
		}

		$rendered_pages[ $render_key ] = true;

		try {
			echo '<div class="wrap orderchatz-admin-page">';

			$this->renderPageHeader();

			if ( $this->hasTabNavigation && $this->tabRenderer ) {
				$this->renderTabNavigation();
			}

			echo '<div class="orderchatz-page-content-wrapper">';
			$this->renderPageContent();
			echo '</div>';

			echo '</div>';

		} catch ( \Exception $e ) {
			Logger::error(
				'頁面渲染失敗',
				array(
					'page_slug'  => $this->pageSlug,
					'page_title' => $this->pageTitle,
					'error'      => $e->getMessage(),
					'trace'      => $e->getTraceAsString(),
				)
			);

			$this->renderErrorPage( $e );
		}
	}

	/**
	 * 渲染頁面標題區域
	 *
	 * @return void
	 */
	protected function renderPageHeader(): void {
		echo '<h1 class="wp-heading-inline">' . esc_html( $this->pageTitle ) . '</h1>';
	}

	/**
	 * 渲染頁籤導航
	 *
	 * @return void
	 */
	protected function renderTabNavigation(): void {
		if ( ! $this->tabRenderer ) {
			return;
		}

		echo $this->tabRenderer->renderTabNavigation( $this->pageSlug );
	}

	/**
	 * 渲染頁面內容
	 *
	 * 子類別必須實作此方法來定義具體的頁面內容
	 *
	 * @return void
	 */
	abstract protected function renderPageContent(): void;

	/**
	 * 渲染頁面頁腳
	 *
	 * @return void
	 */
	protected function renderPageFooter(): void {
		echo '<div class="orderchatz-page-footer">';
		echo '<p class="description">';
		/* translators: 1: 外掛版本號碼, 2: 開發者名稱連結. */
		echo sprintf(
			__( 'OrderChatz %1$s - 由 %2$s 提供技術支援', 'otz' ),
			OTZ_VERSION,
			'<a href="https://oberonlai.blog" target="_blank">Oberon Lai</a>'
		);
		echo '</p>';
		echo '</div>';
	}

	/**
	 * 渲染錯誤頁面
	 *
	 * 當頁面渲染過程發生錯誤時顯示的錯誤頁面
	 *
	 * @param \Exception $exception 例外實例
	 * @return void
	 */
	protected function renderErrorPage( \Exception $exception ): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $this->pageTitle ) . '</h1>';
		echo '<div class="notice notice-error">';
		echo '<p><strong>' . __( '頁面載入錯誤', 'otz' ) . '</strong></p>';
		echo '<p>' . __( '很抱歉，載入此頁面時發生錯誤。請稍後再試，或聯繫系統管理員。', 'otz' ) . '</p>';

		echo '</div>';
		echo '</div>';
	}

	/**
	 * 取得頁面標題
	 *
	 * @return string
	 */
	public function getPageTitle(): string {
		return $this->pageTitle;
	}

	/**
	 * 取得頁面 slug
	 *
	 * @return string
	 */
	public function getPageSlug(): string {
		return $this->pageSlug;
	}

	/**
	 * 檢查是否有頁籤導航
	 *
	 * @return bool
	 */
	public function hasTabNavigation(): bool {
		return $this->hasTabNavigation;
	}

	/**
	 * 渲染空的內容區域
	 *
	 * 提供給子類別使用的通用空內容渲染方法
	 *
	 * @param string $message 顯示的訊息
	 * @return void
	 */
	protected function renderEmptyContent( string $message = '' ): void {
		$defaultMessage = sprintf(
			__( '這是 %s 頁面的內容區域，準備未來功能實作。', 'otz' ),
			$this->pageTitle
		);

		$displayMessage = $message ?: $defaultMessage;

		echo '<div class="orderchatz-empty-content">';
		echo '<div class="notice notice-info inline">';
		echo '<p>' . esc_html( $displayMessage ) . '</p>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * 渲染頁面動作按鈕
	 *
	 * @param array $actions 動作按鈕配置陣列
	 * @return void
	 */
	protected function renderPageActions( array $actions = array() ): void {
		if ( empty( $actions ) ) {
			return;
		}

		echo '<div class="orderchatz-page-actions">';

		foreach ( $actions as $action ) {
			$url    = $action['url'] ?? '#';
			$text   = $action['text'] ?? '';
			$class  = $action['class'] ?? 'button-secondary';
			$target = isset( $action['external'] ) && $action['external'] ? ' target="_blank"' : '';

			printf(
				'<a href="%s" class="button %s"%s>%s</a> ',
				esc_url( $url ),
				esc_attr( $class ),
				$target,
				esc_html( $text )
			);
		}

		echo '</div>';
	}

	/**
	 * 渲染 CSRF Token 隱藏欄位
	 *
	 * @return void
	 */
	protected function renderCsrfTokenField(): void {
		wp_nonce_field( 'orderchatz_admin_action', '_wpnonce', true, true );
	}

	/**
	 * 渲染安全表單
	 *
	 * @param string $action 表單動作
	 * @param string $method 請求方法
	 * @param array  $attributes 額外屬性
	 * @return void
	 */
	protected function renderSecureForm( string $action, string $method = 'post', array $attributes = array() ): void {
		$defaultAttributes = array(
			'class'  => 'orderchatz-secure-form',
			'method' => strtolower( $method ),
			'action' => esc_url( $action ),
		);

		$attributes = array_merge( $defaultAttributes, $attributes );

		echo '<form';
		foreach ( $attributes as $key => $value ) {
			printf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}
		echo '>';

		// 自動加入 CSRF 保護
		$this->renderCsrfTokenField();

		// 加入 referer 欄位
		wp_referer_field( true );
	}

	/**
	 * 結束安全表單
	 *
	 * @return void
	 */
	protected function endSecureForm(): void {
		echo '</form>';
	}
}

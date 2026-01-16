<?php
/**
 * OrderChatz Iframe 處理器
 *
 * 處理 iframe 中的 WordPress 管理頁面顯示
 *
 * @package OrderChatz\Admin
 * @since 1.0.0
 */

namespace OrderChatz\Admin;

/**
 * Iframe 處理器類別
 *
 * 當檢測到 otz_iframe=1 參數時，隱藏 WordPress 管理介面的不必要元素
 */
class IframeHandler {

	/**
	 * 初始化 iframe 處理器
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'handleIframeRequest' ) );
	}

	/**
	 * 處理 iframe 請求
	 */
	public function handleIframeRequest() {
		// 檢查是否是 iframe 請求
		if ( ! $this->isIframeRequest() ) {
			return;
		}

		// 添加 CSS 隱藏不必要的元素
		add_action( 'admin_head', array( $this, 'addIframeStyles' ) );

		// 移除管理頁面的額外元素
		add_action( 'admin_head', array( $this, 'removeAdminElements' ) );

		// 添加 JavaScript 處理
		add_action( 'admin_footer', array( $this, 'addIframeScripts' ) );
	}

	/**
	 * 檢查是否是 iframe 請求
	 *
	 * @return bool
	 */
	private function isIframeRequest() {
		return isset( $_GET['otz_iframe'] ) && $_GET['otz_iframe'] == '1';
	}

	/**
	 * 添加 iframe 專用樣式
	 */
	public function addIframeStyles() {
		?>
		<style type="text/css">
			/* 隱藏 WordPress 管理工具列 */
			#wpadminbar {
				display: none !important;
			}
			
			/* 隱藏管理選單 */
			#adminmenumain,
			#adminmenuback,
			#adminmenuwrap {
				display: none !important;
			}
			
			/* 隱藏頁尾 */
			#wpfooter {
				display: none !important;
			}
			
			/* 調整主要內容區域 */
			#wpcontent {
				margin-left: 0 !important;
				padding-left: 20px !important;
			}
			
			/* 調整容器 */
			#wpbody-content {
				padding-bottom: 0 !important;
			}
			
			/* 隱藏屏幕選項和說明選項 */
			#screen-meta,
			#contextual-help-link-wrap,
			#screen-options-link-wrap {
				display: none !important;
			}
			
			/* 調整頁面標題 */
			.wp-heading-inline {
				margin-top: 10px !important;
			}
			
			/* 隱藏一些不必要的按鈕和元素 */
			.page-title-action,
			#message,
			.notice,
			.updated,
			.wrap h1.wp-heading-inline {
				display: none !important;
			}
			
			/* 調整 body 的上邊距 */
			body.wp-admin {
				margin-top: 0 !important;
				padding-top: 0 !important;
			}
			
			/* 確保內容區域填滿 */
			#wpwrap {
				margin-top: 0 !important;
			}
			
			/* 隱藏頁面頂部的導航標籤 */
			.wrap h1.wp-heading-inline + .page-title-action,
			.subsubsub {
				display: none !important;
			}
			
			/* 優化表單顯示 */
			#post {
				margin-top: 10px !important;
			}
			
			/* 隱藏發布框中的一些功能 */
			#minor-publishing-actions,
			.misc-pub-section.misc-pub-post-status,
			.misc-pub-section.misc-pub-visibility,
			.woocommerce-layout__activity-panel-wrapper,
			.woocommerce-layout__header-wrapper{
				display: none !important;
			}
			
			#wpbody {
				margin-top: -20px !important;
			}
			
			/* 隱藏 WooCommerce 特有的動作按鈕 */
			#woocommerce-order-actions .inside .wc-order-bulk-actions,
			.wc-order-preview {
				display: none !important;
			}
		</style>
		<?php
	}

	/**
	 * 移除管理頁面元素
	 */
	public function removeAdminElements() {
		// 移除管理頁面的一些 hooks
		remove_action( 'admin_bar_menu', 'wp_admin_bar_updates_menu', 70 );
		remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );

		// 移除一些不必要的 meta boxes (對於 WooCommerce 訂單頁面)
		if ( $this->isWooCommerceOrderPage() ) {
			$this->removeWooCommerceOrderElements();
		}
	}

	/**
	 * 檢查是否是 WooCommerce 訂單頁面
	 *
	 * @return bool
	 */
	private function isWooCommerceOrderPage() {
		global $post;

		if ( ! $post ) {
			return false;
		}

		// 檢查是否是 shop_order 類型的編輯頁面
		return $post->post_type === 'shop_order' &&
			   isset( $_GET['action'] ) &&
			   $_GET['action'] === 'edit';
	}

	/**
	 * 移除 WooCommerce 訂單頁面的不必要元素
	 */
	private function removeWooCommerceOrderElements() {
		// 這裡可以添加特定於 WooCommerce 訂單頁面的調整
		add_action(
			'add_meta_boxes',
			function() {
				// 可以移除一些不必要的 meta boxes
				// remove_meta_box( 'submitdiv', 'shop_order', 'side' );
			},
			999
		);
	}

	/**
	 * 添加 iframe 專用 JavaScript
	 */
	public function addIframeScripts() {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// 隱藏可能動態生成的元素
			$('#screen-meta').hide();
			$('.notice, .updated, .error').hide();
			
			// 調整頁面布局
			$('body').addClass('otz-iframe-mode');
			
			// 移除可能的頁面跳轉
			$('a[href*="edit.php"]').each(function() {
				if ($(this).attr('href').indexOf('post_type=shop_order') !== -1) {
					$(this).attr('target', '_parent');
				}
			});
			
			// 處理表單提交，確保在父視窗中進行
			$('#post').on('submit', function() {
				// 可以在這裡添加表單提交的處理邏輯
			});
			
			// 隱藏可能出現的通知和動作按鈕（延遲執行確保 DOM 完全載入）
			setTimeout(function() {
				$('.notice, .updated, .error, #message').hide();
			}, 500);
			
			// 監聽 DOM 變化，確保動態添加的元素也被隱藏
			if (window.MutationObserver) {
				var observer = new MutationObserver(function(mutations) {
					mutations.forEach(function(mutation) {
						if (mutation.type === 'childList') {
							$(mutation.addedNodes).find('#postbox-container-1, .order-actions, .order-status').hide();
						}
					});
				});
				
				observer.observe(document.body, {
					childList: true,
					subtree: true
				});
			}
		});
		</script>
		<?php
	}
}

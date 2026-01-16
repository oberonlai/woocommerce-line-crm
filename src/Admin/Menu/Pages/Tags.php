<?php
/**
 * OrderChatz 標籤頁面渲染器
 *
 * 處理標籤管理頁面的內容渲染
 *
 * @package OrderChatz\Admin\Menu
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Menu\Pages;

use OrderChatz\Admin\Menu\PageRenderer;
use OrderChatz\Admin\Lists\TagsListTable;
use OrderChatz\Database\Tag;

/**
 * 標籤頁面渲染器類別
 *
 * 渲染好友標籤管理相關功能的管理介面
 */
class Tags extends PageRenderer {

	/**
	 * 標籤列表表格實例
	 *
	 * @var TagsListTable
	 */
	private $tags_table;

	/**
	 * 建構函式
	 */
	public function __construct() {
		parent::__construct(
			__( '標籤', 'otz' ),
			'otz-tags',
			true // 標籤頁面有頁籤導航.
		);

		$this->tags_table = new TagsListTable();
	}

	/**
	 * 渲染標籤頁面內容
	 *
	 * @return void
	 */
	protected function renderPageContent(): void {
		// 載入資產.
		$this->enqueue_assets();

		// 處理表單提交.
		$this->handle_actions();

		// 顯示操作訊息.
		$this->show_messages();

		// 渲染左右分欄介面.
		$this->render_tags_page();

		// 渲染好友清單燈箱.
		$this->render_users_modal();
	}

	/**
	 * 處理各種操作
	 */
	private function handle_actions() {
		if ( isset( $_POST['action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_POST['action'] ) );

			switch ( $action ) {
				case 'add_tag':
					$this->handle_add_tag();
					break;
				case 'edit_tag':
					$this->handle_edit_tag();
					break;
			}
		}

		if ( isset( $_GET['action'] ) ) {
			$action = ( isset( $_GET['action'] ) ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

			if ( $action === 'delete' && isset( $_GET['tag_name'] ) ) {
				$this->handle_delete_tag();
			}
		}
	}

	/**
	 * 處理新增標籤
	 */
	private function handle_add_tag() {
		check_admin_referer( 'orderchatz_admin_action' );

		$tag_name = ( isset( $_POST['tag_name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['tag_name'] ) ) : '';

		if ( empty( $tag_name ) ) {
			wp_redirect( admin_url( 'admin.php?page=otz-tags&message=empty_name' ) );
			exit;
		}

		global $wpdb;
		$user_tags_table = $wpdb->prefix . 'otz_user_tags';

		// 檢查標籤是否已存在.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$user_tags_table} WHERE tag_name = %s",
				$tag_name
			)
		);

		if ( $exists ) {
			wp_redirect( admin_url( 'admin.php?page=otz-tags&message=tag_exists' ) );
			exit;
		}

		// 建立新標籤（空的好友列表）.
		$result = $wpdb->insert(
			$user_tags_table,
			array(
				'tag_name'      => $tag_name,
				'line_user_ids' => wp_json_encode( array(), JSON_UNESCAPED_UNICODE ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);

		if ( $result ) {
			wp_redirect( admin_url( 'admin.php?page=otz-tags&message=added' ) );
		} else {
			wp_redirect( admin_url( 'admin.php?page=otz-tags&message=error' ) );
		}
		exit;
	}

	/**
	 * 處理編輯標籤
	 */
	private function handle_edit_tag() {
		check_admin_referer( 'orderchatz_admin_action' );

		$old_tag_name = ( isset( $_POST['old_tag_name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['old_tag_name'] ) ) : '';
		$new_tag_name = ( isset( $_POST['tag_name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['tag_name'] ) ) : '';

		if ( empty( $new_tag_name ) || empty( $old_tag_name ) ) {
			wp_redirect( admin_url( 'admin.php?page=otz-tags&message=empty_name' ) );
			exit;
		}

		// 如果名稱沒有改變,直接返回.
		if ( $old_tag_name === $new_tag_name ) {
			wp_redirect( admin_url( 'admin.php?page=otz-tags&message=no_change' ) );
			exit;
		}

		// 使用 Database\Tag 重新命名標籤.
		$tag_db = new Tag();
		$result = $tag_db->rename_tag( $old_tag_name, $new_tag_name );

		if ( $result ) {
			wp_redirect( admin_url( 'admin.php?page=otz-tags&message=updated' ) );
		} else {
			wp_redirect( admin_url( 'admin.php?page=otz-tags&message=error' ) );
		}
		exit;
	}

	/**
	 * 處理刪除標籤
	 */
	private function handle_delete_tag() {
		$tag_name = ( isset( $_GET['tag_name'] ) ) ? sanitize_text_field( wp_unslash( $_GET['tag_name'] ) ) : '';
		$nonce    = ( isset( $_GET['_wpnonce'] ) ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'orderchatz_admin_action' ) ) {
			wp_die( __( '安全驗證失敗', 'otz' ) );
		}

		// 使用 Database\Tag 刪除標籤.
		$tag_db = new Tag();
		$result = $tag_db->delete_tag( $tag_name );

		if ( $result ) {
			wp_redirect( admin_url( 'admin.php?page=otz-tags&message=deleted' ) );
		} else {
			wp_redirect( admin_url( 'admin.php?page=otz-tags&message=error' ) );
		}
		exit;
	}

	/**
	 * 載入資產（CSS 和 JavaScript）
	 */
	private function enqueue_assets() {
		// 載入 CSS.
		wp_enqueue_style(
			'otz-tags',
			OTZ_PLUGIN_URL . 'assets/css/admin/tags.css',
			array(),
			'1.0.1'
		);

		// 載入 JavaScript.
		wp_enqueue_script(
			'otz-tags',
			OTZ_PLUGIN_URL . 'assets/js/admin/tags.js',
			array( 'jquery' ),
			'1.0.1',
			true
		);

		// 傳遞 AJAX 參數和多語系字串.
		wp_localize_script(
			'otz-tags',
			'otzTags',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'orderchatz_admin_action' ),
				'adminChatUrl' => admin_url( 'admin.php?page=order-chatz' ),
				'i18n'         => array(
					'addTag'       => __( '新增標籤', 'otz' ),
					'editTag'      => __( '編輯標籤', 'otz' ),
					'updateTag'    => __( '更新標籤', 'otz' ),
					'usersWithTag' => __( '使用此標籤的好友', 'otz' ),
					'loadError'    => __( '載入好友資料失敗，請稍後再試', 'otz' ),
				),
			)
		);
	}

	/**
	 * 渲染標籤管理頁面（左右分欄）
	 */
	private function render_tags_page() {
		?>
		<div class="wrap">

			<div id="col-container" class="wp-clearfix">
				<!-- 左側：新增/編輯標籤表單 -->
				<div id="col-left">
					<div class="col-wrap">
						<?php $this->render_tag_form(); ?>
					</div>
				</div>

				<!-- 右側：標籤列表 -->
				<div id="col-right">
					<div class="col-wrap">
						<?php $this->render_tags_list(); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * 渲染標籤表單（新增/編輯）
	 */
	private function render_tag_form() {
		?>
		<div class="form-wrap">
			<h2><?php _e( '新增標籤', 'otz' ); ?></h2>

			<form method="post" action="" id="tag-form" class="validate">
				<?php wp_nonce_field( 'orderchatz_admin_action' ); ?>
				<input type="hidden" name="action" value="add_tag">

				<div class="form-field form-required">
					<label for="tag_name"><?php _e( '標籤名稱', 'otz' ); ?></label>
					<input type="text" id="tag_name" name="tag_name" required aria-required="true">
					<p><?php _e( '請輸入標籤名稱', 'otz' ); ?></p>
				</div>

				<p class="submit">
					<div class="button-group">
						<button type="submit" class="button button-primary" id="submit-tag-btn">
							<?php _e( '新增標籤', 'otz' ); ?>
						</button>
						<button type="button" class="button button-secondary" id="cancel-edit-tag" style="display: none;">
							<?php _e( '取消', 'otz' ); ?>
						</button>
					</div>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * 渲染標籤列表
	 */
	private function render_tags_list() {
		$this->tags_table->prepare_items();
		?>
		<h2 class="wp-heading-inline"><?php _e( '標籤列表', 'otz' ); ?></h2>
		<?php $this->tags_table->search_box( __( '搜尋標籤', 'otz' ), 'tags' ); ?>

		<form method="post">
			<?php
			// 添加 nonce 用於批量操作.
			wp_nonce_field( 'orderchatz_admin_action' );
			$this->tags_table->display();
			?>
		</form>
		<?php
	}

	/**
	 * 渲染好友清單燈箱
	 */
	private function render_users_modal() {
		include OTZ_PLUGIN_DIR . 'views/admin/tags/users-modal.php';
	}

	/**
	 * 顯示操作訊息
	 */
	private function show_messages() {
		if ( ! isset( $_GET['message'] ) ) {
			return;
		}

		$message = ( isset( $_GET['message'] ) ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
		$class   = 'notice notice-success is-dismissible';
		$text    = '';

		switch ( $message ) {
			case 'added':
				$text = __( '標籤新增成功', 'otz' );
				break;
			case 'updated':
				$text = __( '標籤更新成功', 'otz' );
				break;
			case 'deleted':
				$text = __( '標籤刪除成功', 'otz' );
				break;
			case 'not_found':
				$class = 'notice notice-error is-dismissible';
				$text  = __( '找不到指定的標籤', 'otz' );
				break;
			case 'error':
				$class = 'notice notice-error is-dismissible';
				$text  = __( '操作失敗', 'otz' );
				break;
			case 'empty_name':
				$class = 'notice notice-error is-dismissible';
				$text  = __( '標籤名稱不能為空', 'otz' );
				break;
			case 'tag_exists':
				$class = 'notice notice-error is-dismissible';
				$text  = __( '標籤已存在', 'otz' );
				break;
			case 'no_change':
				$class = 'notice notice-info is-dismissible';
				$text  = __( '標籤名稱沒有變更', 'otz' );
				break;
		}

		if ( $text ) {
			echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $text ) . '</p></div>';
		}
	}

}

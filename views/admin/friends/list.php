<?php
/**
 * 好友列表主頁面模板
 *
 * @var Friends_List_Table $list_table 列表表格物件
 * @package OrderChatz
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
		<!-- 同步按鈕區域 -->
		<div class="" style="margin-bottom: 10px;">
			<button type="button" id="sync-members-btn" class="button button-primary">
				<?php echo __( '匯入網站會員 ( OrderNotify 專用 )', 'otz' ); ?>
			</button>
			<button type="button" id="sync-line-friends-btn" class="button button-primary" style="margin-left: 10px;">
				<?php echo __( '匯入 LINE 官方帳號好友 ( 藍盾或綠盾專用 ) ', 'otz' ); ?>
			</button>
		</div>
		<!-- 獨立的搜尋表單 -->
		<form method="get" style="margin-bottom: 0;">
			<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>">
			<div style="margin-top:-5px;">
				<?php $list_table->search_box( __( '搜尋好友', 'otz' ), 'friends' ); ?>
			</div>
		</form>
		
	</div>

	

	<!-- 批次操作表單 -->
	<form method="post">
		<?php
		// 添加 nonce 欄位用於批次操作安全驗證
		wp_nonce_field( 'bulk-friends' );

		// 在表格上方添加批次操作
		echo '<div class="tablenav top">';
		echo '<div class="alignleft actions bulkactions">';
		$list_table->bulk_actions();
		echo '</div>';
		$list_table->extra_tablenav( 'top' );
		echo '<br class="clear" />';
		echo '</div>';
		?>
		
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<?php $list_table->print_column_headers(); ?>
				</tr>
			</thead>
			<tbody id="the-list">
				<?php $list_table->display_rows(); ?>
			</tbody>
			<tfoot>
				<tr>
					<?php $list_table->print_column_headers( false ); ?>
				</tr>
			</tfoot>
		</table>
		
		<?php
		// 底部導航
		echo '<div class="tablenav bottom">';
		echo '<div class="alignleft actions bulkactions">';
		$list_table->bulk_actions();
		echo '</div>';
		$list_table->extra_tablenav( 'bottom' );
		$list_table->pagination( 'bottom' );
		echo '<br class="clear" />';
		echo '</div>';
		?>
	</form>
</div>

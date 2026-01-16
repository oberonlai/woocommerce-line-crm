<?php
/**
 * 客戶備註查看/編輯頁面
 *
 * @package OrderChatz\Views\Admin\Notes
 */

// 防止直接訪問
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<div class="customer-info-card" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
		<h2><?php _e( '客戶資訊', 'otz' ); ?></h2>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php _e( '顯示名稱', 'otz' ); ?>:</th>
					<td><?php echo esc_html( $user->display_name ?: __( '無名稱', 'otz' ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php _e( 'LINE User ID', 'otz' ); ?>:</th>
					<td><code><?php echo esc_html( $user->line_user_id ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php _e( 'WordPress 使用者', 'otz' ); ?>:</th>
					<td>
						<?php
						if ( $user->wp_user_id ) {
							$wp_user = get_user_by( 'id', $user->wp_user_id );
							if ( $wp_user ) {
								printf(
									'<a href="%s" target="_blank">%s (%s)</a>',
									admin_url( 'user-edit.php?user_id=' . $wp_user->ID ),
									esc_html( $wp_user->display_name ),
									esc_html( $wp_user->user_email )
								);
							} else {
								echo '<span style="color: #d63638;">' . __( '使用者不存在', 'otz' ) . '</span>';
							}
						} else {
							echo '<span class="description">' . __( '未綁定', 'otz' ) . '</span>';
						}
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e( '狀態', 'otz' ); ?>:</th>
					<td>
						<?php
						$status_labels = array(
							'active'     => __( '活躍', 'otz' ),
							'blocked'    => __( '封鎖', 'otz' ),
							'unfollowed' => __( '已取消追蹤', 'otz' ),
						);
						echo esc_html( $status_labels[ $user->status ] ?? $user->status );
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e( '最後活動', 'otz' ); ?>:</th>
					<td>
						<?php
						if ( $user->last_active ) {
							echo mysql2date( 'Y-m-d H:i:s', $user->last_active );
						} elseif ( $user->followed_at ) {
							echo mysql2date( 'Y-m-d H:i:s', $user->followed_at ) . ' (' . __( '加入時間', 'otz' ) . ')';
						} else {
							echo '<span class="description">' . __( '無記錄', 'otz' ) . '</span>';
						}
						?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	
	<!-- 歷史備註列表 -->
	<div class="notes-history">
		<h2><?php _e( '備註記錄', 'otz' ); ?></h2>
		
		<?php if ( empty( $notes ) ) : ?>
			<div class="no-notes" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin: 20px 0;">
				<p class="description"><?php _e( '目前沒有備註記錄', 'otz' ); ?></p>
			</div>
		<?php else : ?>
			<div class="notes-table-container" style="margin: 20px 0;">
				<table class="wp-list-table widefat fixed striped notes-table">
					<thead>
						<tr>
							<th style="width: 60%;"><?php _e( '備註內容', 'otz' ); ?></th>
							<th style="width: 15%;"><?php _e( '建立者', 'otz' ); ?></th>
							<th style="width: 15%;"><?php _e( '建立時間', 'otz' ); ?></th>
							<th style="width: 10%;"><?php _e( '操作', 'otz' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $notes as $note ) : ?>
							<tr id="note-row-<?php echo $note->id; ?>">
								<td style="vertical-align: top;">
									<div class="note-text" id="note-text-<?php echo $note->id; ?>">
										<div style="line-height: 1.4; max-height: 100px; overflow-y: auto;">
											<?php echo \OrderChatz\Util\Helper::convert_links_to_html( $note->note ); ?>
										</div>
									</div>
									<div class="note-edit-form" id="note-edit-<?php echo $note->id; ?>" style="display: none;">
										<form method="post" action="">
											<?php wp_nonce_field( 'orderchatz_admin_action' ); ?>
											<input type="hidden" name="action" value="edit_note">
											<input type="hidden" name="note_id" value="<?php echo $note->id; ?>">
											<input type="hidden" name="line_user_id" value="<?php echo esc_attr( $user->line_user_id ); ?>">
											<textarea name="note_content" rows="4" style="width: 100%; margin-bottom: 10px; font-size: 12px;"><?php echo esc_textarea( $note->note ); ?></textarea>
											<div class="note-edit-actions">
												<button type="submit" class="button button-primary button-small"><?php _e( '儲存', 'otz' ); ?></button>
												<button type="button" class="button button-small cancel-edit" data-note-id="<?php echo $note->id; ?>"><?php _e( '取消', 'otz' ); ?></button>
											</div>
										</form>
									</div>
								</td>
								<td style="vertical-align: top;">
									<?php echo esc_html( $note->created_by_name ?: __( '系統', 'otz' ) ); ?>
								</td>
								<td style="vertical-align: top;">
									<div style="font-size: 12px;">
										<?php echo mysql2date( 'Y-m-d', $note->created_at ); ?><br>
										<span style="color: #666;"><?php echo mysql2date( 'H:i:s', $note->created_at ); ?></span>
									</div>
								</td>
								<td style="vertical-align: top;">
									<div class="note-actions" style="display:flex">
										<button type="button" style="margin-right:4px" class="button button-small edit-note" data-note-id="<?php echo $note->id; ?>"><?php _e( '編輯', 'otz' ); ?></button>
										<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=otz-notes&action=delete_note&note_id=' . $note->id . '&line_user_id=' . urlencode( $user->line_user_id ) ), 'orderchatz_admin_action' ); ?>" 
										   class="button button-small" 
										   style="color: #d63638; border-color: #d63638;"
										   onclick="return confirm('<?php esc_attr_e( '確定要刪除這個備註嗎？', 'otz' ); ?>')"><?php _e( '刪除', 'otz' ); ?></a>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<!-- 新增備註表單 -->
	<div class="notes-add-form">
		<h2><?php _e( '新增備註', 'otz' ); ?></h2>
		
		<form method="post" action="">
			<?php wp_nonce_field( 'orderchatz_admin_action' ); ?>
			<input type="hidden" name="action" value="add_note">
			<input type="hidden" name="line_user_id" value="<?php echo esc_attr( $user->line_user_id ); ?>">
			
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<label for="notes"><?php _e( '備註內容', 'otz' ); ?></label>
						</th>
						<td>
							<textarea id="notes" name="notes" rows="5" cols="50" class="large-text" 
									  placeholder="<?php esc_attr_e( '請輸入新的客戶備註...', 'otz' ); ?>"></textarea>
							<p class="description"><?php _e( '在此記錄與此客戶的互動備註、重要資訊等', 'otz' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
			
			<?php submit_button( __( '新增備註', 'otz' ) ); ?>
		</form>
	</div>
	
	<a href="<?php echo admin_url( 'admin.php?page=otz-notes' ); ?>" class="button">
		<?php _e( '返回備註列表', 'otz' ); ?>
	</a>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// 編輯備註
	$('.edit-note').on('click', function() {
		var noteId = $(this).data('note-id');
		$('#note-text-' + noteId).hide();
		$('#note-edit-' + noteId).show();
		$(this).hide();
	});

	// 取消編輯
	$('.cancel-edit').on('click', function() {
		var noteId = $(this).data('note-id');
		$('#note-text-' + noteId).show();
		$('#note-edit-' + noteId).hide();
		$('.edit-note[data-note-id="' + noteId + '"]').show();
	});
});
</script>

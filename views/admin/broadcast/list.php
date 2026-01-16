<?php
/**
 * 推播活動列表視圖
 *
 * @package OrderChatz
 * @var \OrderChatz\Admin\Lists\BroadcastListTable $list_table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<?php $list_table->views(); ?>

<form method="post">
	<?php wp_nonce_field( 'bulk-broadcasts' ); ?>
	<input type="hidden" name="page" value="otz-broadcast">
	<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=otz-broadcast&action=create' ) ); ?>" class="page-title-action">
			<?php echo esc_html__( '新增推播', 'otz' ); ?>
		</a>
		<?php
		$list_table->search_box( __( '搜尋', 'otz' ), 'broadcast' );
		?>
	</div>
	<?php
	$list_table->display();
	?>
</form>

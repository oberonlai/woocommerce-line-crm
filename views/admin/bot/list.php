<?php
/**
 * Bot Skill 列表視圖
 *
 * @package OrderChatz
 * @var \OrderChatz\Admin\Lists\BotListTable $list_table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<?php $list_table->views(); ?>

<form method="post">
	<?php wp_nonce_field( 'bulk-bots' ); ?>
	<input type="hidden" name="page" value="otz-bot">
	<div style="display:block; margin-top:10px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=otz-bot&action=create' ) ); ?>" class="page-title-action">
			<?php echo esc_html__( 'Add New Bot', 'otz' ); ?>
		</a>
		<?php
		$list_table->search_box( __( 'Search', 'otz' ), 'bot' );
		?>
	</div>
	<?php
	$list_table->display();
	?>
</form>

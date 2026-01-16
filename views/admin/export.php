<?php
/**
 * OrderChatz 匯出頁面模板
 *
 * @package OrderChatz\Views\Admin
 * @since 1.0.0
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="orderchatz-export-page">
	
	<!-- 訊息資料匯出區塊 -->
	<div class="export-section">
		<h2><?php echo esc_html__( '訊息資料匯出', 'otz' ); ?></h2>
		<div class="export-card">
			
			<div class="form-group">
				<label for="export-date-range"><?php echo esc_html__( '選擇日期範圍', 'otz' ); ?></label>
				<div class="date-range-inputs">
					<input type="date" id="export-start-date" name="start_date" max="<?php echo esc_attr( gmdate('Y-m-d') ); ?>">
					<span class="date-separator">至</span>
					<input type="date" id="export-end-date" name="end_date" max="<?php echo esc_attr( gmdate('Y-m-d') ); ?>">
				</div>
				<p class="description"><?php echo esc_html__( '選擇要匯出的訊息日期範圍（最多 31 天）', 'otz' ); ?></p>
			</div>
			
			<div class="form-group">
				<label for="export-format"><?php echo esc_html__( '匯出格式', 'otz' ); ?></label>
				<select id="export-format" name="export_format">
					<option value="csv">CSV</option>
					<option value="json">JSON</option>
				</select>
			</div>
			
			<div class="form-actions">
				<button type="button" id="btn-export-messages" class="button button-primary">
					<?php echo esc_html__( '匯出訊息', 'otz' ); ?>
				</button>
				<span class="export-status"></span>
			</div>
			
		</div>
	</div>
	
	<!-- 存儲分析區塊 -->
	<div class="export-section">
		<h2><?php echo esc_html__( '存儲分析', 'otz' ); ?></h2>
		<div class="export-card">
			
			<div class="storage-info" id="storage-info">
				<div class="loading"><?php echo esc_html__( '載入存儲資訊中...', 'otz' ); ?></div>
			</div>
			
			<div class="form-actions">
				<button type="button" id="btn-refresh-storage" class="button">
					<?php echo esc_html__( '重新整理', 'otz' ); ?>
				</button>
			</div>
			
		</div>
	</div>
	
	<!-- 檔案管理區塊 -->
	<div class="export-section">
		<h2><?php echo esc_html__( '檔案管理', 'otz' ); ?></h2>
		<div class="export-card">
			
			<div class="file-info" id="file-info">
				<div class="loading"><?php echo esc_html__( '載入檔案資訊中...', 'otz' ); ?></div>
			</div>
			
			<div class="form-actions">
				<button type="button" id="btn-download-uploads" class="button button-secondary">
					<?php echo esc_html__( '打包下載', 'otz' ); ?>
				</button>
				<button type="button" id="btn-clear-uploads" class="button button-delete">
					<?php echo esc_html__( '清除檔案', 'otz' ); ?>
				</button>
			</div>
			
		</div>
	</div>
	
</div>
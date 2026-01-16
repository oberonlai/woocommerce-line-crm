<?php
/**
 * Message Import Modal Template
 *
 * @package OrderChatz
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<!-- Message Import Modal -->
<div id="message-import-modal" class="otz-modal" style="display: none;">
	<div class="otz-modal-backdrop"></div>
	<div class="otz-modal-content">
		<div class="otz-modal-header">
			<h2><?php echo esc_html__( 'Import LINE Message History', 'otz' ); ?></h2>
			<button type="button" class="otz-modal-close">&times;</button>
		</div>

		<div class="otz-modal-body">
			<!-- Step 1: File Upload -->
			<div id="upload-step" class="import-step active">
				<div class="upload-content">
					<div class="friend-info" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
						<h2 style="margin: 0 0 10px 0;"><?php echo esc_html__( 'Import Target', 'otz' ); ?></h2>
						<div class="friend-details">
							<span id="friend-display-name" style="font-weight: 600;"></span>
							<span id="friend-line-id" style="color: #666; font-size: 13px; margin-left: 10px;"></span>
						</div>
					</div>

					<div class="upload-instructions" style="margin-bottom: 20px;">
						<h2><?php echo esc_html__( 'Upload Instructions', 'otz' ); ?></h2>
						<ul>
							<li><?php echo esc_html__( 'Please upload CSV file exported from LINE', 'otz' ); ?></li>
							<li><?php echo esc_html__( 'File size limit: 10MB', 'otz' ); ?></li>
							<li style="color:red;font-weight:bold"><?php echo esc_html__( 'Message limit: 5000 records. Files exceeding this limit cannot be imported, please split the file manually', 'otz' ); ?></li>
							<li><?php echo esc_html__( 'Supported format: .csv files only', 'otz' ); ?></li>
						</ul>
					</div>

					<div class="file-upload-area">
						<div id="file-drop-zone" class="file-drop-zone">
							<div class="drop-zone-content">
								<div class="dashicons dashicons-upload" style="width:45px; height: 45px; font-size: 48px; color: #ccc; margin-bottom: 15px;"></div>
								<p class="drop-zone-text"><?php echo esc_html__( 'Drag file here or click to select file', 'otz' ); ?></p>
								<input type="file" id="csv-file-input" accept=".csv" style="display: none;">
								<button type="button" id="select-file-btn" class="button button-secondary">
									<?php echo esc_html__( 'Select File', 'otz' ); ?>
								</button>
							</div>
						</div>

						<div id="file-selected-info" class="file-selected-info" style="display: none;">
							<div class="file-info">
								<span class="dashicons dashicons-media-document"></span>
								<span id="selected-file-name"></span>
								<span id="selected-file-size"></span>
								<button type="button" id="remove-file-btn" class="button-link"><?php echo esc_html__( 'Remove', 'otz' ); ?></button>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Step 2: Preview Messages -->
			<div id="preview-step" class="import-step" style="display: none;">
				<div class="preview-content">
					<div id="preview-loading" style="text-align: center; padding: 40px;">
						<div class="dashicons dashicons-update-alt" style="width: auto; height: auto; font-size: 24px; animation: spin 1s linear infinite;"></div>
						<p><?php echo esc_html__( 'Parsing CSV file...', 'otz' ); ?></p>
					</div>

					<div id="preview-results" style="display: none;">
						<div class="preview-header">
							<h4><?php echo esc_html__( 'Message Preview', 'otz' ); ?></h4>
							<div id="preview-statistics" class="statistics-info">
								<!-- Statistics will be displayed here -->
							</div>
						</div>

						<div class="filter-controls" style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 4px;">
							<div class="filter-row">
								<label><?php echo esc_html__( 'Date Range:', 'otz' ); ?></label>
								<input type="date" id="filter-start-date" style="margin-right: 10px;">
								<span><?php echo esc_html__( 'to', 'otz' ); ?></span>
								<input type="date" id="filter-end-date" style="margin-left: 10px;">
							</div>
							<div class="filter-row" style="margin-top: 10px;">
								<label><?php echo esc_html__( 'Keyword:', 'otz' ); ?></label>
								<input type="text" id="filter-keyword" placeholder="<?php echo esc_attr__( 'Search message content...', 'otz' ); ?>" style="margin-left: 14px; width: 200px;">
							</div>
							<div class="filter-row" style="margin-top: 10px;">
								<label><?php echo esc_html__( 'Sender:', 'otz' ); ?></label>
								<label style="margin-left: 15px;">
									<input type="checkbox" id="filter-user" checked> <?php echo esc_html__( 'Friend Messages', 'otz' ); ?>
								</label>
								<label style="margin-left: 15px;">
									<input type="checkbox" id="filter-account" checked> <?php echo esc_html__( 'Official Account Messages', 'otz' ); ?>
								</label>
								<button type="button" id="apply-filter-btn" class="button button-primary" style="margin-left: 15px;">
									<?php echo esc_html__( 'Apply Filter', 'otz' ); ?>
								</button>
								<button type="button" id="reset-filter-btn" class="button button-secondary" >
									<?php echo esc_html__( 'Reset', 'otz' ); ?>
								</button>
							</div>
						</div>

						<div class="selection-controls" style="margin: 15px 0;">
							<label>
								<input type="checkbox" id="select-all-messages" style="margin-right: 5px;">
								<?php echo esc_html__( 'Select All', 'otz' ); ?>
							</label>
							<span id="selection-count" style="margin-left: 15px; color: #666;"></span>
						</div>

						<div id="messages-list" class="messages-preview-list">
							<!-- Message list will be displayed here -->
						</div>

						<div id="pagination-controls" class="pagination-controls">
							<!-- Pagination controls will be displayed here -->
						</div>
					</div>

					<div id="preview-error" style="display: none; text-align: center; padding: 40px;">
						<div class="dashicons dashicons-warning" style="width:45px; height: 45px; font-size: 48px; color: #dc3232; margin-bottom: 15px;"></div>
						<p id="error-message" style="color: #dc3232;"></p>
					</div>
				</div>
			</div>

			<!-- Step 3: Import Progress -->
			<div id="import-step" class="import-step" style="display: none;">
				<div class="import-content">
					<h3><?php echo esc_html__( 'Importing messages...', 'otz' ); ?></h3>
					<div class="progress-bar-container" style="width: 100%; height: 20px; background: #f1f1f1; border-radius: 10px; margin: 20px 0; overflow: hidden;">
						<div id="import-progress-bar" class="progress-bar" style="height: 100%; background: linear-gradient(90deg, #0073aa, #00a0d2); width: 0%; transition: width 0.3s ease; border-radius: 10px;"></div>
					</div>
					<div id="import-status-text" class="import-status"><?php echo esc_html__( 'Preparing...', 'otz' ); ?></div>
					<div id="import-details" class="import-details" style="margin-top: 15px; font-size: 14px; color: #666;"></div>
				</div>
			</div>

			<!-- Step 4: Complete -->
			<div id="complete-step" class="import-step" style="display: none;">
				<div class="complete-content">
					<div style="display: flex; justify-content: center; align-items: center; flex-wrap: wrap">
						<div class="dashicons dashicons-yes-alt" style="width:45px; height: 45px; font-size: 48px; color: #46b450; margin-bottom: 10px;"></div>
						<h2 style="display: block; width:100%;text-align:center;margin:0;"><?php echo esc_html__( 'Execution Completed', 'otz' ); ?></h2>
					</div>
					<div id="import-results" class="import-results" style="margin: 20px 0;">
						<!-- Result statistics will be displayed here -->
					</div>
				</div>
			</div>
		</div>

		<div class="otz-modal-footer">
			<!-- Upload Step Buttons -->
			<div id="upload-actions" class="modal-actions active">
				<button type="button" class="button button-secondary otz-modal-close"><?php echo esc_html__( 'Cancel', 'otz' ); ?></button>
				<button type="button" id="upload-and-preview-btn" class="button button-primary" disabled>
					<span class="dashicons dashicons-search" style="margin-top: 3px;"></span>
					<?php echo esc_html__( 'Preview Messages', 'otz' ); ?>
				</button>
			</div>

			<!-- Preview Step Buttons -->
			<div id="preview-actions" class="modal-actions" style="display: none;">
				<button type="button" class="button button-secondary" id="back-to-upload-btn"><?php echo esc_html__( 'Previous', 'otz' ); ?></button>
				<button type="button" id="start-import-btn" class="button button-primary" disabled>
					<span class="dashicons dashicons-download" style="margin-top: 5px;"></span>
					<?php echo esc_html__( 'Start Import', 'otz' ); ?>
				</button>
			</div>

			<!-- Import Progress Buttons -->
			<div id="import-actions" class="modal-actions" style="display: none;">
				<button type="button" id="cancel-import-btn" class="button button-secondary"><?php echo esc_html__( 'Cancel Import', 'otz' ); ?></button>
			</div>

			<!-- Complete Step Buttons -->
			<div id="complete-actions" class="modal-actions" style="display: none;">
				<button type="button" class="button button-secondary otz-modal-close"><?php echo esc_html__( 'Close', 'otz' ); ?></button>
				<button type="button" id="continue-import-btn" class="button button-primary">
					<span class="dashicons dashicons-upload" style="margin-top: 3px;"></span>
					<?php echo esc_html__( 'Continue Import', 'otz' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>

<?php
/**
 * Sync Members Modal Template
 *
 * @package OrderChatz
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<!-- Sync Members Modal -->
<div id="sync-members-modal" class="otz-modal" style="display: none;">
	<div class="otz-modal-backdrop"></div>
	<div class="otz-modal-content">
		<div class="otz-modal-header">
			<h2><?php echo esc_html__( 'Import Site Members (For OrderNotify only)', 'otz' ); ?></h2>
			<button type="button" class="otz-modal-close">&times;</button>
		</div>
		
		<div class="otz-modal-body">
			<div id="sync-info-step" class="sync-step active">
				<div class="sync-info-content">
					<ul style="text-align: left; margin: 20px 0; padding-left: 20px;">
						<li><?php echo esc_html__( 'Import site members to friend list', 'otz' ); ?></li>
						<li><?php echo esc_html__( 'Automatically match members who have logged in with LINE', 'otz' ); ?></li>
						<li><?php echo esc_html__( 'Only members who logged in via LINE in OrderNotify plugin can directly send messages', 'otz' ); ?></li>
						<li><?php echo esc_html__( 'Members who have not logged in with LINE will not appear in the chat list', 'otz' ); ?></li>
					</ul>
				</div>
			</div>
			
			<div id="sync-selection-step" class="sync-step" style="display: none;">
				<div class="sync-selection-content">
					<h3><?php echo esc_html__( 'Select members to import', 'otz' ); ?></h3>
					<p><?php echo esc_html__( 'Below are members with LINE connection information, please select members to import:', 'otz' ); ?></p>

					<div class="selection-controls" style="margin: 15px 0; text-align: left;">
						<label style="margin-right: 15px;">
							<input type="checkbox" id="select-all-users" style="margin-right: 5px;">
							<?php echo esc_html__( 'Select All', 'otz' ); ?>
						</label>
						<div class="selection-summary" style="display: flex; align-items: center; gap: 15px; font-size: 14px; color: #666;">
							<span id="selected-count"><?php echo esc_html__( 'Selected: 0 members', 'otz' ); ?></span>
						</div>
					</div>
					
					<div id="users-loading" style="text-align: center; padding: 40px;">
						<div class="dashicons dashicons-update-alt" style="font-size: 24px; animation: spin 1s linear infinite;"></div>
						<p><?php echo esc_html__( 'Loading member data...', 'otz' ); ?></p>
					</div>

					<div id="users-list" class="users-selection-list" style="display: none; max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
						<!-- User list will be dynamically loaded here -->
					</div>
					
					<div id="no-users-message" style="display: none; text-align: center; padding: 40px; color: #666;">
						<div class="dashicons dashicons-info" style="font-size: 24px; margin-bottom: 10px;"></div>
						<p><?php echo esc_html__( 'No members found with LINE connection information', 'otz' ); ?></p>
						<div class="help-text" style="margin-top: 15px; font-size: 13px; color: #888;">
							<p><?php echo esc_html__( 'Possible reasons:', 'otz' ); ?></p>
							<ul style="text-align: left; display: inline-block; margin: 10px 0;">
								<li><?php echo esc_html__( 'No members have logged in via LINE through OrderNotify yet', 'otz' ); ?></li>
								<li><?php echo esc_html__( "Member's LINE connection information has expired or is invalid", 'otz' ); ?></li>
								<li><?php echo esc_html__( 'No related member records in database', 'otz' ); ?></li>
							</ul>
						</div>
					</div>
				</div>
			</div>
			
			<div id="sync-progress-step" class="sync-step" style="display: none;">
				<div class="sync-progress-content">
					<h3><?php echo esc_html__( 'Synchronizing member data...', 'otz' ); ?></h3>
					<div class="progress-bar-container" style="width: 100%; height: 20px; background: #f1f1f1; border-radius: 10px; margin: 20px 0; overflow: hidden;">
						<div id="sync-progress-bar" class="progress-bar" style="height: 100%; background: linear-gradient(90deg, #0073aa, #00a0d2); width: 0%; transition: width 0.3s ease; border-radius: 10px;"></div>
					</div>
					<div id="sync-status-text" class="sync-status"><?php echo esc_html__( 'Preparing...', 'otz' ); ?></div>
					<div id="sync-details" class="sync-details" style="margin-top: 15px; font-size: 14px; color: #666;"></div>
				</div>
			</div>
			
			<div id="sync-complete-step" class="sync-step" style="display: none;">
				<div class="sync-complete-content">
					<div class="dashicons dashicons-yes-alt" style="font-size: 48px; color: #46b450; margin-bottom: 20px;"></div>
					<h3><?php echo esc_html__( 'Synchronization completed!', 'otz' ); ?></h3>
					<div id="sync-results" class="sync-results" style="margin: 20px 0;">
						<!-- Result statistics will be displayed here -->
					</div>
				</div>
			</div>
		</div>
		
		<div class="otz-modal-footer">
			<div id="sync-info-actions" class="modal-actions active">
				<button type="button" class="button button-secondary otz-modal-close"><?php echo esc_html__( 'Cancel', 'otz' ); ?></button>
				<button type="button" id="next-to-selection-btn" class="button button-primary">
					<span class="dashicons dashicons-admin-users" style="margin-top: 3px;"></span>
					<?php echo esc_html__( 'Select Members', 'otz' ); ?>
				</button>
			</div>

			<div id="sync-selection-actions" class="modal-actions" style="display: none;">
				<button type="button" class="button button-secondary" id="back-to-info-btn"><?php echo esc_html__( 'Previous', 'otz' ); ?></button>
				<button type="button" id="start-sync-btn" class="button button-primary" disabled>
					<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
					<?php echo esc_html__( 'Start Import', 'otz' ); ?>
				</button>
			</div>

			<div id="sync-progress-actions" class="modal-actions" style="display: none;">
				<button type="button" id="cancel-sync-btn" class="button button-secondary"><?php echo esc_html__( 'Cancel Sync', 'otz' ); ?></button>
			</div>

			<div id="sync-complete-actions" class="modal-actions" style="display: none;">
				<button type="button" class="button button-secondary otz-modal-close"><?php echo esc_html__( 'Close', 'otz' ); ?></button>
				<button type="button" id="refresh-page-btn" class="button button-primary"><?php echo esc_html__( 'Refresh Page', 'otz' ); ?></button>
			</div>
		</div>
	</div>
</div>

<?php
/**
 * Bot 編輯頁面
 *
 * @package OrderChatz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 判斷是否為編輯模式.
$is_edit_mode = ! empty( $bot );
$bot_id       = $is_edit_mode ? $bot['id'] : 0;

// 準備表單資料.
$name        = $is_edit_mode && isset( $bot['name'] ) ? $bot['name'] : '';
$description = $is_edit_mode && isset( $bot['description'] ) ? $bot['description'] : '';
$keywords    = $is_edit_mode && isset( $bot['keywords'] ) && is_array( $bot['keywords'] ) ? $bot['keywords'] : array();
$action_type = $is_edit_mode && isset( $bot['action_type'] ) ? $bot['action_type'] : 'ai';
$api_key     = $is_edit_mode && isset( $bot['api_key'] ) ? $bot['api_key'] : '';
// 遮蔽 API 金鑰顯示,只顯示前 10 個字元.
$api_key_display = '';
$has_api_key     = false;
if ( ! empty( $api_key ) && strlen( $api_key ) > 10 ) {
	$api_key_display = substr( $api_key, 0, 10 ) . str_repeat( '*', strlen( $api_key ) - 10 );
	$has_api_key     = true;
} elseif ( ! empty( $api_key ) ) {
	$api_key_display = $api_key;
	$has_api_key     = true;
}
$model           = $is_edit_mode && isset( $bot['model'] ) ? $bot['model'] : '';
$system_prompt   = $is_edit_mode && isset( $bot['system_prompt'] ) ? $bot['system_prompt'] : '';
$handoff_message = $is_edit_mode && isset( $bot['handoff_message'] ) ? $bot['handoff_message'] : '已為您轉接真人客服，稍後將有專人為您服務。';
$function_tools  = $is_edit_mode && isset( $bot['function_tools'] ) && is_array( $bot['function_tools'] ) ? $bot['function_tools'] : array();
$quick_replies   = $is_edit_mode && isset( $bot['quick_replies'] ) && is_array( $bot['quick_replies'] ) ? $bot['quick_replies'] : array();
$priority        = $is_edit_mode && isset( $bot['priority'] ) ? $bot['priority'] : 0;
$status          = $is_edit_mode && isset( $bot['status'] ) ? $bot['status'] : 'active';
$trigger_count   = $is_edit_mode && isset( $bot['trigger_count'] ) ? $bot['trigger_count'] : 0;
$total_tokens    = $is_edit_mode && isset( $bot['total_tokens'] ) ? $bot['total_tokens'] : 0;
$avg_response    = $is_edit_mode && isset( $bot['avg_response_time'] ) ? $bot['avg_response_time'] : 0;
?>

<div class="wrap orderchatz-bot-page">
	<h1 class="wp-heading-inline">
		<?php echo $is_edit_mode ? esc_html__( 'Edit', 'otz' ) : esc_html__( 'Add', 'otz' ); ?>
	</h1>

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=otz-bot' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Back to List', 'otz' ); ?>
	</a>
	
	<?php if ( $is_edit_mode ) : ?>
	
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=otz-bot&action=create' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New Bot', 'otz' ); ?>
	</a>
	
	<?php endif; ?>

	<hr class="wp-header-end">

	<form method="post" action="" id="bot-form">
		<?php wp_nonce_field( 'save_bot' ); ?>
		<input type="hidden" name="save_bot" value="1">
		<?php if ( $is_edit_mode ) : ?>
			<input type="hidden" name="bot_id" value="<?php echo esc_attr( $bot_id ); ?>">
		<?php endif; ?>

		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">

				<!-- 左側主內容區 -->
				<div id="post-body-content">

					<!-- 標題 -->
					<div id="titlediv">
						<div id="titlewrap">
							<label class="screen-reader-text" for="title"><?php esc_html_e( 'Bot Name', 'otz' ); ?></label>
							<input
								type="text"
								name="bot_name"
								size="30"
								value="<?php echo esc_attr( $name ); ?>"
								id="title"
								spellcheck="true"
								autocomplete="off"
								placeholder="<?php esc_attr_e( 'Enter bot name', 'otz' ); ?>"
								required>
						</div>
					</div>

					<!-- 描述 -->
					<div id="descriptiondiv">
						<label for="bot_description"><?php esc_html_e( 'Description', 'otz' ); ?></label>
						<textarea
							name="bot_description"
							id="bot_description"
							rows="3"
							placeholder="<?php esc_attr_e( 'Enter description (optional)', 'otz' ); ?>"><?php echo esc_textarea( $description ); ?></textarea>
					</div>

					<!-- 關鍵字設定 -->
					<div id="keywords-div" class="postbox">
						<h2><?php esc_html_e( 'Keywords', 'otz' ); ?></h2>
						<div class="inside">
							<div class="form-field">
								<label><?php esc_html_e( 'Trigger Keywords', 'otz' ); ?> <span class="required">*</span></label>
								<div class="tag-input-wrapper">
									<input
										type="text"
										id="keyword-input"
										placeholder="<?php esc_attr_e( 'Add keyword', 'otz' ); ?>">
									<button type="button" class="button button-primary add-keyword-button" style="width:100px"><?php esc_html_e( 'Add', 'otz' ); ?></button>
								</div>
								<div class="keywords-list">
									<?php foreach ( $keywords as $keyword ) : ?>
										<span class="keyword-item">
											<?php echo esc_html( $keyword ); ?>
											<button type="button" class="remove-keyword">×</button>
											<input type="hidden" name="bot_keywords[]" value="<?php echo esc_attr( $keyword ); ?>">
										</span>
									<?php endforeach; ?>
								</div>
								<p class="description"><?php esc_html_e( 'Keywords that trigger this bot', 'otz' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Action Type -->
					<div id="action-type-div" class="postbox">
						<h2><?php esc_html_e( 'Action Type', 'otz' ); ?></h2>
						<div class="inside">
							<div class="action-type-selector">
								<label>
									<input
										type="radio"
										name="action_type"
										value="ai"
										<?php checked( $action_type, 'ai' ); ?>
										class="action-type-radio"
										required>
									<span class="option-label">
										<?php esc_html_e( 'AI-powered automatic response', 'otz' ); ?>
									</span>
								</label>
								<label>
									<input
										type="radio"
										name="action_type"
										value="human"
										<?php checked( $action_type, 'human' ); ?>
										class="action-type-radio">
									<span class="option-label">
										<?php esc_html_e( 'Fixed content response ( disable AI mode )', 'otz' ); ?>
									</span>
								</label>
							</div>
						</div>
					</div>

					<!-- Handoff Message 設定 -->
					<div id="handoff-message-div" class="postbox <?php echo 'human' === $action_type ? 'active' : ''; ?>">
						<h2><?php esc_html_e( 'Fixed content message', 'otz' ); ?></h2>
						<div class="inside">
							<div class="form-field">
								<label for="handoff_message">
									<?php esc_html_e( 'Message to Customer', 'otz' ); ?>
								</label>
								<textarea
									name="handoff_message"
									id="handoff_message"
									rows="3"
									placeholder="<?php esc_attr_e( 'Enter handoff message...', 'otz' ); ?>"><?php echo esc_textarea( $handoff_message ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'This message will be sent when the keywords were triggered.', 'otz' ); ?>
								</p>
							</div>
						</div>
					</div>

					<!-- AI 設定區塊 -->
					<div id="ai-settings-div" class="postbox <?php echo 'ai' === $action_type ? 'active' : ''; ?>">
						<h2><?php esc_html_e( 'AI Settings', 'otz' ); ?></h2>
						<div class="inside">

							<!-- API Key -->
							<div class="form-field">
								<label for="api_key"><?php esc_html_e( 'API Key', 'otz' ); ?></label>
								<input
									type="text"
									name="api_key"
									id="api_key"
									value="<?php echo esc_attr( $api_key_display ); ?>"
									placeholder="<?php echo $has_api_key ? esc_attr__( 'Click to enter new API key', 'otz' ) : esc_attr__( 'Enter API key', 'otz' ); ?>"
									data-is-masked="<?php echo $has_api_key ? 'true' : 'false'; ?>"
									data-original-masked="<?php echo esc_attr( $api_key_display ); ?>">
								<p class="description">
									<?php
									echo wp_kses(
										sprintf(
											/* translators: %1$s: OpenAI API link, %2$s: Anthropic API link */
											__( 'Enter your AI provider API key. Get your key from: <a href="%1$s" target="_blank">OpenAI</a>', 'otz' ),
											'https://platform.openai.com/api-keys',
										),
										array(
											'a' => array(
												'href'   => array(),
												'target' => array(),
											),
										)
									);
									?>
								</p>
							</div>

							<!-- Model -->
							<div class="form-field">
								<label for="model"><?php esc_html_e( 'Model', 'otz' ); ?></label>
								<select name="model" id="model">
									<option value="" <?php selected( $model, '' ); ?>><?php esc_html_e( 'Select Model', 'otz' ); ?></option>
									<option value="gpt-5-nano" <?php selected( $model, 'gpt-5-nano' ); ?>>gpt-5-nano <?php echo esc_html__( '(Reasoning Model, Slower Response)', 'otz' ); ?></option>
									<option value="gpt-5-mini" <?php selected( $model, 'gpt-5-mini' ); ?>>gpt-5-mini <?php echo esc_html__( '(Reasoning Model, Slower Response)', 'otz' ); ?></option>
									<option value="gpt-4.1-nano" <?php selected( $model, 'gpt-4.1-nano' ); ?>>gpt-4.1-nano</option>
									<option value="gpt-4.1-mini" <?php selected( $model, 'gpt-4.1-mini' ); ?>>gpt-4.1-mini</option>
								</select>
								<p class="description">
									<?php
									echo wp_kses(
										sprintf(
											/* translators: %1$s: Model pricing link */
											__( 'Select the AI model to use. Different models have different capabilities and pricing. <a href="%1$s" target="_blank">View pricing details</a>', 'otz' ),
											'https://oberonlai.blog/order-chatz-bot'
										),
										array(
											'a' => array(
												'href'   => array(),
												'target' => array(),
											),
										)
									);
									?>
								</p>
							</div>

							<!-- System Prompt -->
							<div class="form-field">
								<label for="system_prompt"><?php esc_html_e( 'System Prompt', 'otz' ); ?></label>
								<textarea
									name="system_prompt"
									id="system_prompt"
									rows="8"
									placeholder="<?php esc_attr_e( 'Enter system prompt...', 'otz' ); ?>"><?php echo esc_textarea( $system_prompt ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Define the AI\'s role, personality, and behavioral guidelines. This sets the context for all interactions. Example: "You are a helpful customer service assistant for an e-commerce store."', 'otz' ); ?></p>
							</div>

						</div>
					</div>

					<!-- Function Tools -->
					<div id="function-tools-div" class="postbox <?php echo 'ai' === $action_type ? 'active' : ''; ?>">
						<h2><?php esc_html_e( 'Function Tools', 'otz' ); ?></h2>
						<div class="inside">
							<p class="description"><?php esc_html_e( 'Select AI tools to enable', 'otz' ); ?></p>
							<div class="function-tools-list">
								<?php
								$available_tools = array(
									'customer_orders'  => __( 'Query Customer Orders (Historical Orders)', 'otz' ),
									'customer_info'    => __( 'Query Customer Info (Basic Information)', 'otz' ),
									'product_info'     => __( 'Query Product Info (Product Details)', 'otz' ),
									'custom_post_type' => __( 'Query Custom Post Type (Specific Post Type)', 'otz' ),
								);

								foreach ( $available_tools as $tool_key => $tool_label ) :
									// 判斷是否啟用（相容新舊資料結構）.
									if ( 'custom_post_type' === $tool_key ) {
										$is_enabled = isset( $function_tools[ $tool_key ] ) && ( is_array( $function_tools[ $tool_key ] ) ? ! empty( $function_tools[ $tool_key ]['enabled'] ) : $function_tools[ $tool_key ] );
									} else {
										$is_enabled = isset( $function_tools[ $tool_key ] ) && $function_tools[ $tool_key ];
									}
									?>
									<div class="function-tool-item <?php echo 'custom_post_type' === $tool_key ? 'has-sub-options' : ''; ?>">
										<label class="toggle-switch">
											<input
												type="checkbox"
												name="function_tools[<?php echo esc_attr( $tool_key ); ?>]"
												value="1"
												id="tool-<?php echo esc_attr( $tool_key ); ?>"
												<?php checked( $is_enabled ); ?>
												<?php echo 'custom_post_type' === $tool_key ? 'class="custom-post-type-toggle"' : ''; ?>>
											<span class="toggle-slider"></span>
										</label>
										<span class="tool-label"><?php echo esc_html( $tool_label ); ?></span>

										
									</div>
								<?php endforeach; ?>
								<?php if ( 'custom_post_type' === $tool_key ) : ?>
									<?php
									// 取得已選擇的 Post Type.
									$selected_post_types = array();
									if ( isset( $function_tools['custom_post_type'] ) && is_array( $function_tools['custom_post_type'] ) && ! empty( $function_tools['custom_post_type']['post_types'] ) ) {
										$selected_post_types = $function_tools['custom_post_type']['post_types'];
									}
									?>
									<div class="post-type-selector-wrapper" style="<?php echo $is_enabled ? '' : 'display: none;'; ?>">
										<select
												name="custom_post_types[]"
												id="custom-post-types"
												class="post-type-selector"
												multiple="multiple"
												style="width: 100%;">
											<?php foreach ( $available_post_types as $post_type ) : ?>
												<option
														value="<?php echo esc_attr( $post_type['value'] ); ?>"
														<?php echo in_array( $post_type['value'], $selected_post_types, true ) ? 'selected' : ''; ?>>
													<?php echo esc_html( $post_type['label'] ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										<p class="description"><?php esc_html_e( 'Select which post types the AI can query', 'otz' ); ?></p>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<!-- Quick Replies -->
					<div id="quick-replies-div" class="postbox">
						<h2><?php esc_html_e( 'Quick Replies', 'otz' ); ?></h2>
						<div class="inside">
							<p class="description">
								<?php esc_html_e( 'Pre-set questions for quick user access', 'otz' ); ?>
								<br>
								<span style="color: #d63638;"><?php esc_html_e( 'Each question must be 15 characters or less (exceeding items will be filtered)', 'otz' ); ?></span>
							</p>
							<div class="quick-replies-list" id="quick-replies-list">
								<?php if ( ! empty( $quick_replies ) ) : ?>
									<?php foreach ( $quick_replies as $reply ) : ?>
										<div class="quick-reply-item">
											<input
												type="text"
												name="quick_replies[]"
												class="quick-reply-input"
												value="<?php echo esc_attr( $reply ); ?>"
												placeholder="<?php esc_attr_e( 'Enter question...', 'otz' ); ?>"
												maxlength="15"
												data-max-length="15">
											<span class="char-counter"><?php echo esc_html( mb_strlen( $reply, 'UTF-8' ) ); ?>/15</span>
											<button type="button" class="button remove-reply">−</button>
										</div>
									<?php endforeach; ?>
								<?php else : ?>
									<div class="quick-reply-item">
										<input
											type="text"
											name="quick_replies[]"
											class="quick-reply-input"
											value=""
											placeholder="<?php esc_attr_e( 'Enter question...', 'otz' ); ?>"
											maxlength="15"
											data-max-length="15">
										<span class="char-counter">0/15</span>
										<button type="button" class="button remove-reply">−</button>
									</div>
								<?php endif; ?>
							</div>
							<button type="button" class="button add-reply-button" id="add-reply">
								+ <?php esc_html_e( 'Add Question', 'otz' ); ?>
							</button>
						</div>
					</div>

				</div><!-- #post-body-content -->

				<!-- 右側邊欄 -->
				<div id="postbox-container-1" class="postbox-container">

					<!-- 發佈區塊 -->
					<div id="submitdiv" class="postbox">
						<h2><?php esc_html_e( 'Publish', 'otz' ); ?></h2>
						<div class="inside">
							<div class="submitbox" id="submitpost">
								<div id="major-publishing-actions">
									<div id="publishing-action">
										<span class="spinner"></span>
										<input
											type="submit"
											name="save_bot"
											id="publish"
											class="button button-primary button-large"
											value="<?php echo $is_edit_mode ? esc_attr__( 'Update', 'otz' ) : esc_attr__( 'Save', 'otz' ); ?>">
									</div>
									<div class="clear"></div>
								</div>
							</div>
						</div>
					</div>

					<!-- 狀態設定 -->
					<div id="status-div" class="postbox">
						<h2><?php esc_html_e( 'Status', 'otz' ); ?></h2>
						<div class="inside">
							<select name="bot_status" id="bot_status">
								<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'otz' ); ?></option>
								<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'otz' ); ?></option>
							</select>
						</div>
					</div>

					<!-- 優先序 -->
					<div id="priority-div" class="postbox">
						<h2><?php esc_html_e( 'Priority', 'otz' ); ?></h2>
						<div class="inside">
							<input
								type="number"
								name="bot_priority"
								id="bot_priority"
								value="<?php echo esc_attr( $priority ); ?>"
								min="0"
								step="1">
							<p class="description"><?php esc_html_e( 'Lower number = higher priority', 'otz' ); ?></p>
						</div>
					</div>

					<!-- 統計資訊 (編輯模式) -->
					<?php if ( $is_edit_mode ) : ?>
						<div id="statistics-div" class="postbox">
							<h2><?php esc_html_e( 'Statistics', 'otz' ); ?></h2>
							<div class="inside">
								<div class="stat-item">
									<span class="stat-label"><?php esc_html_e( 'Trigger Count', 'otz' ); ?></span>
									<span class="stat-value"><?php echo esc_html( number_format( $trigger_count ) ); ?></span>
								</div>
								<div class="stat-item">
									<span class="stat-label"><?php esc_html_e( 'Total Tokens', 'otz' ); ?></span>
									<span class="stat-value"><?php echo esc_html( number_format( $total_tokens ) ); ?></span>
								</div>
								<div class="stat-item">
									<span class="stat-label"><?php esc_html_e( 'Avg Response Time', 'otz' ); ?></span>
									<span class="stat-value"><?php echo esc_html( number_format( $avg_response, 2 ) ); ?>s</span>
								</div>
							</div>
						</div>
					<?php endif; ?>

				</div><!-- #postbox-container-1 -->

			</div><!-- #post-body -->
		</div><!-- #poststuff -->
	</form>
</div><!-- .wrap -->

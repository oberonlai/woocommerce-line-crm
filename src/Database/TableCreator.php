<?php

declare(strict_types=1);

namespace OrderChatz\Database;

use OrderChatz\Util\Logger;

/**
 * Table Creator Class
 *
 * Handles the creation of database tables with SQL DDL statements.
 * Separated from main Installer for better code organization.
 *
 * @package    OrderChatz
 * @subpackage Database
 * @since      1.0.3
 */
class TableCreator {

	/**
	 * WordPress database abstraction layer
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Logger instance
	 *
	 * @var \WC_Logger|null
	 */
	private ?\WC_Logger $logger;

	/**
	 * Error handler instance
	 *
	 * @var ErrorHandler|null
	 */
	private ?ErrorHandler $error_handler;

	/**
	 * Constructor
	 *
	 * @param \wpdb             $wpdb WordPress database object
	 * @param \WC_Logger|null   $logger Logger instance
	 * @param ErrorHandler|null $error_handler Error handler instance
	 */
	public function __construct( \wpdb $wpdb, ?\WC_Logger $logger = null, ?ErrorHandler $error_handler = null ) {
		$this->wpdb          = $wpdb;
		$this->logger        = $logger;
		$this->error_handler = $error_handler;
	}

	/**
	 * Create LINE users table
	 * Updated for v1.0.4 to include LINE webhook integration fields
	 * Updated for v1.1.4 to include tags JSON field for tag history
	 *
	 * @param string $table_name Full table name with prefix
	 * @return bool True on success, false on failure
	 */
	public function create_users_table( string $table_name ): bool {
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			line_user_id VARCHAR(64) NULL COMMENT 'LINE User ID',
			wp_user_id BIGINT UNSIGNED NULL COMMENT 'WordPress User ID (if linked)',
			display_name VARCHAR(255) NULL COMMENT 'LINE Display Name',
			avatar_url VARCHAR(255) NULL COMMENT 'LINE Avatar URL',
			source_type ENUM('user','group','room') NOT NULL DEFAULT 'user' COMMENT 'Source type: user, group, or room',
			group_id VARCHAR(64) NULL COMMENT 'Group/Room ID if applicable',
			followed_at DATETIME NULL COMMENT 'Timestamp when user followed the bot',
			unfollowed_at DATETIME NULL COMMENT 'Timestamp when user unfollowed the bot',
			status ENUM('active','blocked','unfollowed') NOT NULL DEFAULT 'active' COMMENT 'User status',
			linked_at DATETIME NOT NULL COMMENT 'Account linking timestamp',
			last_active DATETIME NULL COMMENT 'Last activity timestamp',
			read_time DATETIME NULL COMMENT '最後已讀時間',
			notes TEXT NULL COMMENT '客戶備註',
			tags JSON NULL COMMENT '標籤歷史記錄',
			UNIQUE KEY uk_line_user_id (line_user_id),
			KEY idx_wp_user_id (wp_user_id),
			KEY idx_last_active (last_active),
			KEY idx_read_time (read_time),
			KEY idx_group_id (group_id),
			KEY idx_status_type (status, source_type)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		COMMENT='LINE Users and WordPress User Binding with Webhook and Tag History Support';";

		return $this->execute_table_creation( $table_name, $sql );
	}

	/**
	 * Create user tags table
	 *
	 * 標籤主表：每個標籤一筆記錄，用 JSON 陣列儲存使用此標籤的所有 LINE User ID.
	 *
	 * @param string $table_name Full table name with prefix
	 * @return bool True on success, false on failure
	 */
	public function create_user_tags_table( string $table_name ): bool {
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			tag_name VARCHAR(50) NOT NULL UNIQUE COMMENT '標籤名稱（唯一）',
			line_user_ids JSON NULL COMMENT '使用此標籤的 LINE User ID 陣列',
			created_at DATETIME NOT NULL COMMENT '標籤建立時間',
			KEY idx_tag_name (tag_name),
			KEY idx_created_at (created_at)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		COMMENT='標籤主表 - 記錄每個標籤有哪些使用者';";

		return $this->execute_table_creation( $table_name, $sql );
	}

	/**
	 * Create user notes table
	 *
	 * @param string $table_name Full table name with prefix
	 * @return bool True on success, false on failure
	 */
	public function create_user_notes_table( string $table_name ): bool {
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			line_user_id VARCHAR(64) NOT NULL COMMENT 'LINE User ID',
			note TEXT NOT NULL COMMENT 'Customer service note content',
			category VARCHAR(50) NULL COMMENT '備註分類',
			related_product_id BIGINT UNSIGNED NULL COMMENT 'WooCommerce 產品 ID',
			related_message JSON NULL COMMENT '關聯訊息資料JSON格式',
			created_by BIGINT UNSIGNED NULL COMMENT 'Staff member who created the note',
			created_at DATETIME NOT NULL COMMENT 'Note creation timestamp',
			KEY idx_line_user_id (line_user_id),
			KEY idx_created_at (created_at),
			KEY idx_created_by (created_by),
			KEY idx_category (category),
			KEY idx_category_user (category, line_user_id),
			KEY idx_related_product_id (related_product_id),
			KEY idx_product_user (related_product_id, line_user_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		COMMENT='Customer Service Notes for LINE Users with Category and Message Relations Support';";

		return $this->execute_table_creation( $table_name, $sql );
	}


	/**
	 * Build SQL DDL for monthly message table creation
	 *
	 * Updated to support LINE webhook integration with event_id for idempotency
	 * and additional fields for source tracking and raw payload storage.
	 *
	 * @param string $table_name Full table name with prefix
	 * @return string Complete SQL DDL statement
	 */
	public function build_monthly_message_table_sql( string $table_name ): string {
		return "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			event_id VARCHAR(64) NOT NULL UNIQUE COMMENT 'LINE Event ID for idempotency',
			line_user_id VARCHAR(64) NOT NULL COMMENT 'LINE User ID from LINE Platform',
			source_type ENUM('user','group','room') NOT NULL COMMENT 'Message source type',
			sender_type ENUM('user','account','bot') NOT NULL COMMENT 'Message sender: User, Official Account, or Bot',
			sender_name VARCHAR(255) NULL COMMENT 'Sender display name',
			group_id VARCHAR(64) NULL COMMENT 'Group/Room ID if applicable',
			sent_date DATE NOT NULL COMMENT 'Message sent date (YYYY-MM-DD)',
			sent_time TIME NOT NULL COMMENT 'Message sent time (HH:MM:SS)',
			message_type VARCHAR(50) NOT NULL DEFAULT 'text' COMMENT 'Message type: text, image, sticker, etc.',
			message_content LONGTEXT NULL COMMENT 'Message content or metadata JSON',
			reply_token VARCHAR(255) NULL COMMENT 'LINE Reply Token for responses',
			quote_token VARCHAR(255) NULL COMMENT 'LINE quote token for replying to this message',
			quoted_message_id VARCHAR(255) NULL COMMENT 'ID of the message being replied to',
			line_message_id VARCHAR(255) NULL COMMENT 'LINE message ID for correlation with replies',
			raw_payload LONGTEXT NULL COMMENT 'Complete event payload for debugging',
			created_by BIGINT UNSIGNED NULL COMMENT 'WordPress user who created this record',
			created_at DATETIME NOT NULL COMMENT 'Record creation timestamp',
			KEY idx_event_id (event_id) COMMENT 'Index for idempotency checks',
			KEY idx_user_date_time (line_user_id, sent_date, sent_time) COMMENT 'Primary composite index for time-ordered queries',
			KEY idx_source_type (source_type) COMMENT 'Index for filtering by source type',
			KEY idx_group_id (group_id) COMMENT 'Index for group/room queries',
			KEY idx_sent_date (sent_date) COMMENT 'Index for date-based queries',
			KEY idx_created_at (created_at) COMMENT 'Index for record creation tracking'
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
		COMMENT='Monthly Message Partition Table - " . esc_sql( str_replace( $this->wpdb->prefix . 'otz_messages_', '', $table_name ) ) . "';";
	}

	/**
	 * Execute table creation using dbDelta
	 *
	 * @param string $table_name The table name
	 * @param string $sql        The SQL DDL statement
	 * @return bool True on success, false on failure
	 */
	public function execute_table_creation( string $table_name, string $sql ): bool {
		try {
			// Ensure dbDelta function is available
			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			$result = dbDelta( $sql );

			// Check if table was created successfully
			if ( $this->table_exists( $table_name ) ) {
				return true;
			} else {
				Logger::error( "Failed to create table: {$table_name}" );
				return false;
			}
		} catch ( \Exception $e ) {
			Logger::error( "Exception creating table {$table_name}: " . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Create broadcast campaigns table
	 *
	 * @param string $table_name Full table name with prefix
	 * @return bool True on success, false on failure
	 */
	public function create_broadcast_table( string $table_name ): bool {
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			campaign_name VARCHAR(255) NOT NULL COMMENT '活動名稱',
			description TEXT NULL COMMENT 'Campaign 描述或備註',
			audience_type ENUM('all_followers', 'imported_users', 'filtered') NOT NULL
				COMMENT 'all_followers=所有追蹤者, imported_users=otz_users表中的用戶, filtered=條件篩選',
			filter_conditions JSON NULL COMMENT '篩選條件JSON格式',
			message_type ENUM('text', 'image', 'video', 'flex') NOT NULL COMMENT '訊息類型',
			message_content LONGTEXT NOT NULL COMMENT '訊息內文JSON格式',
			notification_disabled BOOLEAN DEFAULT FALSE COMMENT '是否靜音推播',
			schedule_type ENUM('immediate', 'scheduled') DEFAULT 'immediate'
				COMMENT 'immediate=立即, scheduled=排程',
			scheduled_at DATETIME NULL COMMENT '排程時間',
			action_id BIGINT UNSIGNED NULL COMMENT 'Action Scheduler 任務ID',
			status ENUM('draft', 'published') DEFAULT 'draft' COMMENT 'draft=草稿, published=已發布',
			last_execution_status ENUM('pending', 'success', 'partial', 'failed') NULL
				COMMENT '最後執行狀態（快取）',
			category VARCHAR(50) NULL COMMENT 'Campaign 分類',
			tags JSON NULL COMMENT '標籤陣列',
			sort_order INT DEFAULT 0 COMMENT '排序權重',
			copied_from BIGINT UNSIGNED NULL COMMENT '複製來源 Campaign ID',
			created_by BIGINT UNSIGNED NOT NULL COMMENT '建立者',
			created_at DATETIME NOT NULL COMMENT '建立時間',
			updated_by BIGINT UNSIGNED NULL COMMENT '更新者',
			updated_at DATETIME NULL COMMENT '更新時間'
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		COMMENT='推播活動設定表';";

		return $this->execute_table_creation( $table_name, $sql );
	}

	/**
	 * Create broadcast logs table
	 *
	 * @param string $table_name Full table name with prefix
	 * @return bool True on success, false on failure
	 */
	public function create_broadcast_logs_table( string $table_name ): bool {
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			campaign_id BIGINT UNSIGNED NOT NULL COMMENT '關聯的 Campaign ID',
			executed_at DATETIME NOT NULL COMMENT '執行時間',
			executed_by BIGINT UNSIGNED NOT NULL COMMENT '執行者（0=系統排程）',
			execution_type ENUM('manual', 'scheduled') DEFAULT 'manual' COMMENT '手動/排程',
			campaign_name_snapshot VARCHAR(255) NOT NULL COMMENT 'Campaign 名稱快照',
			audience_type_snapshot VARCHAR(50) NOT NULL COMMENT '受眾類型快照',
			filter_snapshot JSON NULL COMMENT '篩選條件快照',
			message_snapshot JSON NOT NULL COMMENT '訊息內容快照',
			target_count INT UNSIGNED NOT NULL COMMENT '目標發送人數',
			success_count INT UNSIGNED NULL COMMENT '成功數量（需額外 API 查詢更新）',
			failed_count INT UNSIGNED NULL COMMENT '失敗數量（需額外 API 查詢更新）',
			status ENUM('pending', 'success', 'partial', 'failed') DEFAULT 'pending'
				COMMENT 'pending=執行中, success=成功, partial=部分成功, failed=失敗',
			error_message TEXT NULL COMMENT '錯誤訊息',
			KEY idx_campaign_id (campaign_id),
			KEY idx_executed_at (executed_at),
			KEY idx_status (status),
			KEY idx_campaign_executed (campaign_id, executed_at),
			CONSTRAINT fk_broadcast_logs_campaign
				FOREIGN KEY (campaign_id)
				REFERENCES {$this->wpdb->prefix}otz_broadcast(id)
				ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		COMMENT='推播發送記錄表';";

		return $this->execute_table_creation( $table_name, $sql );
	}

	/**
	 * Create push notification subscribers table
	 *
	 * @param string $table_name Full table name with prefix
	 * @return bool True on success, false on failure
	 */
	public function create_subscribers_table( string $table_name ): bool {
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			wp_user_id BIGINT UNSIGNED NOT NULL COMMENT 'WordPress 使用者 ID',
			line_user_id VARCHAR(64) NULL COMMENT 'LINE 使用者 ID (可選)',
			endpoint VARCHAR(500) NOT NULL COMMENT '推播端點 URL',
			p256dh_key VARCHAR(255) NOT NULL COMMENT 'P256DH 公鑰',
			auth_key VARCHAR(255) NOT NULL COMMENT '驗證金鑰',
			user_agent TEXT NULL COMMENT '瀏覽器 User Agent',
			device_type VARCHAR(50) NULL COMMENT '裝置類型 (mobile/desktop/tablet)',
			subscribed_at DATETIME NOT NULL COMMENT '訂閱時間',
			last_used_at DATETIME NULL COMMENT '最後使用時間',
			status ENUM('active','inactive','expired') DEFAULT 'active' COMMENT '訂閱狀態',
			UNIQUE KEY uk_endpoint (endpoint),
			KEY idx_wp_user_id (wp_user_id),
			KEY idx_line_user_id (line_user_id),
			KEY idx_status (status),
			KEY idx_subscribed_at (subscribed_at),
			KEY idx_last_used_at (last_used_at)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		COMMENT='PWA 推播通知訂閱資料表';";

		return $this->execute_table_creation( $table_name, $sql );
	}

	/**
	 * Create message templates table
	 *
	 * @param string $table_name Full table name with prefix
	 * @return bool True on success, false on failure
	 */
	public function create_templates_table( string $table_name ): bool {
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			content TEXT NOT NULL COMMENT '範本內容',
			code VARCHAR(50) NOT NULL COMMENT '快速代碼，用於快速輸入',
			created_at DATETIME NOT NULL COMMENT '範本建立時間',
			UNIQUE KEY uk_code (code),
			KEY idx_created_at (created_at)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		COMMENT='客服訊息範本資料表';";

		return $this->execute_table_creation( $table_name, $sql );
	}

	/**
	 * Create cron messages table
	 *
	 * @param string $table_name Full table name with prefix
	 * @return bool True on success, false on failure
	 */
	public function create_cron_messages_table( string $table_name ): bool {
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			action_id BIGINT UNSIGNED NOT NULL COMMENT 'WordPress Action Scheduler ID',
			line_user_id VARCHAR(64) NOT NULL COMMENT 'LINE User ID',
			source_type ENUM('user','group','room') NOT NULL COMMENT 'Message source type',
			group_id VARCHAR(64) NULL COMMENT 'Group/Room ID if applicable',
			message_type VARCHAR(50) NOT NULL DEFAULT 'text' COMMENT 'Message type',
			message_content LONGTEXT NULL COMMENT 'Message content or metadata JSON',
			schedule LONGTEXT NOT NULL COMMENT '排程規則 JSON 格式',
			created_by BIGINT UNSIGNED NOT NULL COMMENT 'WordPress user who created this schedule',
			created_at DATETIME NOT NULL COMMENT 'Schedule creation timestamp',
			status ENUM('pending','processing','completed','cancelled','failed') DEFAULT 'pending' COMMENT 'Schedule status',
			KEY idx_action_id (action_id),
			KEY idx_line_user_id (line_user_id),
			KEY idx_source_type (source_type),
			KEY idx_group_id (group_id),
			KEY idx_status (status),
			KEY idx_created_by (created_by),
			KEY idx_created_at (created_at)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		COMMENT='預約排程訊息資料表';";

		return $this->execute_table_creation( $table_name, $sql );
	}

	/**
	 * Create bot table
	 *
	 * @param string $table_name Full table name with prefix
	 * @return bool True on success, false on failure
	 */
	public function create_bot_table( string $table_name ): bool {
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			name VARCHAR(255) NOT NULL COMMENT 'AI 機器人名稱',
			description TEXT NULL COMMENT 'AI 機器人描述',
			keywords JSON NOT NULL COMMENT '觸發關鍵字 JSON 陣列',
			action_type ENUM('ai','human') NOT NULL DEFAULT 'ai' COMMENT '動作類型: ai=啟用AI, human=切換真人客服',
			api_key VARCHAR(255) NULL COMMENT 'OpenAI API Key',
			model VARCHAR(100) NULL DEFAULT 'gpt-4' COMMENT 'OpenAI 模型名稱',
			system_prompt TEXT NULL COMMENT 'AI 系統提示詞',
			handoff_message TEXT NULL COMMENT '真人客服轉接訊息',
			function_tools JSON NULL COMMENT 'Function Calling 工具設定 JSON',
			quick_replies JSON NULL COMMENT '快速回覆選項 JSON 陣列',
			trigger_count INT UNSIGNED DEFAULT 0 COMMENT '觸發次數統計',
			total_tokens BIGINT UNSIGNED DEFAULT 0 COMMENT '累積使用 token 數',
			avg_response_time DECIMAL(8,2) NULL COMMENT '平均回應時間(秒)',
			priority INT NOT NULL DEFAULT 100 COMMENT '優先順序,數字越小優先級越高',
			status ENUM('active','inactive') NOT NULL DEFAULT 'active' COMMENT '啟用狀態',
			created_at DATETIME NOT NULL COMMENT '建立時間',
			updated_at DATETIME NULL COMMENT '更新時間',
			KEY idx_status_priority (status, priority),
			KEY idx_action_type (action_type),
			KEY idx_created_at (created_at)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		COMMENT='AI 機器人設定表';";

		return $this->execute_table_creation( $table_name, $sql );
	}

	/**
	 * Check if a table exists in the database
	 *
	 * @param string $table_name Full table name.
	 * @return bool True if table exists, false otherwise
	 */
	public function table_exists( string $table_name ): bool {
		$query = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
		return $this->wpdb->get_var( $query ) === $table_name;
	}
}

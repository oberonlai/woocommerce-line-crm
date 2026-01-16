<?php

declare(strict_types=1);

namespace OrderChatz\Services\Bot;

use OrderChatz\Database\Bot\Bot;
use OrderChatz\Database\Message\TableMessage;
use OrderChatz\Database\DynamicTableManager;
use OrderChatz\Ajax\Message\LineApiService;
use OrderChatz\Util\Logger;

/**
 * Bot Responder Service
 *
 * 負責處理 AI 自動回應的核心業務邏輯，包含：
 * - AI 對話流程管理（委派關鍵字比對給 BotMatcher）
 * - OpenAI API 整合與對話歷史管理
 * - 訊息發送與記錄
 * - 統計資訊更新
 *
 * @package    OrderChatz
 * @subpackage Services\Bot
 * @since      1.1.6
 */
class BotResponder {

	/**
	 * WordPress 資料庫抽象層
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Bot 資料庫操作類別
	 *
	 * @var Bot
	 */
	private Bot $bot_db;

	/**
	 * Bot 比對服務
	 *
	 * @var BotMatcher
	 */
	private BotMatcher $bot_matcher;

	/**
	 * Message 資料庫操作類別
	 *
	 * @var TableMessage
	 */
	private TableMessage $message_db;

	/**
	 * LINE API 服務
	 *
	 * @var LineApiService
	 */
	private LineApiService $line_api_service;

	/**
	 * Dynamic Table Manager
	 *
	 * @var DynamicTableManager
	 */
	private DynamicTableManager $table_manager;

	/**
	 * 對話歷史快取
	 *
	 * @var array
	 */
	private array $conversation_cache = array();

	/**
	 * 建構子
	 *
	 * @param \wpdb               $wpdb WordPress 資料庫物件.
	 * @param Bot                 $bot_db Bot 資料庫操作類別.
	 * @param BotMatcher          $bot_matcher Bot 比對服務.
	 * @param TableMessage        $message_db Message 資料庫操作類別.
	 * @param LineApiService      $line_api_service LINE API 服務.
	 * @param DynamicTableManager $table_manager Dynamic Table Manager.
	 */
	public function __construct(
		\wpdb $wpdb,
		Bot $bot_db,
		BotMatcher $bot_matcher,
		TableMessage $message_db,
		LineApiService $line_api_service,
		DynamicTableManager $table_manager
	) {
		$this->wpdb             = $wpdb;
		$this->bot_db           = $bot_db;
		$this->bot_matcher      = $bot_matcher;
		$this->message_db       = $message_db;
		$this->line_api_service = $line_api_service;
		$this->table_manager    = $table_manager;
	}

	/**
	 * 處理使用者訊息
	 *
	 * 主要入口方法，判斷是否需要觸發機器人回應.
	 *
	 * @param int      $user_id OTZ 使用者 ID.
	 * @param string   $line_user_id LINE 使用者 ID.
	 * @param string   $message_text 使用者訊息內容.
	 * @param string   $reply_token LINE reply token.
	 * @param int|null $event_timestamp Event timestamp（可選，毫秒級）.
	 * @return array 處理結果 ['triggered' => bool, 'bot_id' => int|null, 'response' => string|null].
	 */
	public function handle_user_message( int $user_id, string $line_user_id, string $message_text, string $reply_token, ?int $event_timestamp = null ): array {
		try {
			// 優先檢查關鍵字觸發（使用 BotMatcher）.
			$matched_bot = $this->bot_matcher->match_keyword( $message_text );

			if ( $matched_bot ) {
				return $this->handle_bot_action( $user_id, $line_user_id, $matched_bot, $message_text, $reply_token, $event_timestamp );
			}

			// 如果沒有關鍵字匹配，檢查是否已啟用 AI（使用 BotMatcher）.
			$bot_enabled = $this->bot_matcher->is_bot_enabled_for_user( $user_id );

			if ( $bot_enabled ) {
				return $this->handle_ai_response( $user_id, $line_user_id, $message_text, $reply_token, null, $event_timestamp );
			}

			// 沒有觸發任何 Bot.
			return array(
				'triggered' => false,
				'bot_id'    => null,
				'response'  => null,
			);

		} catch ( \Exception $e ) {
			Logger::error(
				'Error in BotResponder::handle_user_message: ' . $e->getMessage(),
				array(
					'user_id'      => $user_id,
					'message_text' => $message_text,
					'exception'    => $e->getMessage(),
				),
				'otz'
			);

			return array(
				'triggered' => false,
				'bot_id'    => null,
				'response'  => null,
				'error'     => $e->getMessage(),
			);
		}
	}


	/**
	 * 處理機器人動作
	 *
	 * 根據 action_type 執行對應動作（啟用 AI 或切換真人客服）.
	 *
	 * @param int      $user_id OTZ 使用者 ID.
	 * @param string   $line_user_id LINE 使用者 ID.
	 * @param array    $bot 機器人資料.
	 * @param string   $message_text 使用者訊息內容.
	 * @param string   $reply_token LINE reply token.
	 * @param int|null $event_timestamp Event timestamp（可選，毫秒級）.
	 * @return array 處理結果.
	 */
	private function handle_bot_action( int $user_id, string $line_user_id, array $bot, string $message_text, string $reply_token, ?int $event_timestamp = null ): array {
		// 處理 Bot 觸發（委派給 BotMatcher，會自動更新 bot_status 與 trigger_count）.
		$trigger_result = $this->bot_matcher->handle_bot_trigger(
			(int) $bot['id'],
			$user_id,
			$bot['action_type']
		);

		if ( ! $trigger_result ) {
			Logger::error(
				'Failed to handle bot trigger',
				array(
					'bot_id'      => $bot['id'],
					'user_id'     => $user_id,
					'action_type' => $bot['action_type'],
				),
				'otz'
			);
			return array(
				'triggered' => false,
				'bot_id'    => $bot['id'],
				'response'  => null,
				'error'     => 'Failed to handle bot trigger',
			);
		}

		if ( 'ai' === $bot['action_type'] ) {
			// 立即處理 AI 回應.
			return $this->handle_ai_response( $user_id, $line_user_id, $message_text, $reply_token, $bot, $event_timestamp );

		} elseif ( 'human' === $bot['action_type'] ) {
			// 使用資料庫設定的轉接訊息，若無則使用預設值.
			$response_message = ! empty( $bot['handoff_message'] )
				? $bot['handoff_message']
				: '已為您轉接真人客服，稍後將有專人為您服務。';

			// 加入機器人名稱前綴.
			$bot_name         = '🤖 ' . $bot['name'] ?? 'AI Bot';
			$response_message = "【{$bot_name}】{$response_message}";

			// 發送 LINE 訊息並取得 quote_token，包含 quick replies（如果有設定）.
			$quick_replies = ! empty( $bot['quick_replies'] ) && is_array( $bot['quick_replies'] ) ? $bot['quick_replies'] : null;
			$send_result   = $this->send_line_message( $reply_token, $response_message, $quick_replies );

			// 儲存訊息到資料庫.
			if ( $send_result['success'] ) {
				$this->save_bot_message(
					$line_user_id,
					$response_message,
					(int) $bot['id'],
					$bot,
					0,  // tokens_used (系統訊息不計算 token).
					0.0,  // response_time.
					$send_result['quote_token'] ?? null,
					$event_timestamp
				);
			}

			return array(
				'triggered' => true,
				'bot_id'    => $bot['id'],
				'response'  => $response_message,
				'action'    => 'human',
			);
		}

		return array(
			'triggered' => false,
			'bot_id'    => null,
			'response'  => null,
		);
	}

	/**
	 * 處理 AI 回應
	 *
	 * 整合 OpenAI API 產生 AI 回應並發送給使用者.
	 *
	 * @param int        $user_id OTZ 使用者 ID.
	 * @param string     $line_user_id LINE 使用者 ID.
	 * @param string     $message_text 使用者訊息內容.
	 * @param string     $reply_token LINE reply token.
	 * @param array|null $bot 機器人資料（可選，用於指定特定 Bot）.
	 * @param int|null   $event_timestamp Event timestamp（可選，毫秒級）.
	 * @return array 處理結果.
	 */
	private function handle_ai_response( int $user_id, string $line_user_id, string $message_text, string $reply_token, ?array $bot = null, ?int $event_timestamp = null ): array {
		try {
			// 如果沒有指定 Bot，優先使用 transient 中記錄的 Bot.
			if ( null === $bot ) {
				$transient_key = "otz_conversation_{$line_user_id}";
				$cache         = get_transient( $transient_key );

				// 檢查 transient 中是否有記錄的 bot_id.
				if ( $cache && isset( $cache['bot_id'] ) && is_numeric( $cache['bot_id'] ) ) {
					$cached_bot = $this->bot_db->get_bot( (int) $cache['bot_id'] );

					// 驗證該 Bot 是否仍然啟用且為 AI 類型.
					if ( $cached_bot && 'active' === $cached_bot['status'] && 'ai' === $cached_bot['action_type'] ) {
						$bot = $cached_bot;
					}
				}

				// 如果 transient 中沒有記錄或 Bot 已停用，取得預設 AI Bot.
				if ( null === $bot ) {
					$bot = $this->get_default_ai_bot();
					if ( ! $bot ) {
						return array(
							'triggered' => false,
							'bot_id'    => null,
							'response'  => null,
						);
					}
				}
			}

			// 驗證 Bot 設定.
			if ( empty( $bot['api_key'] ) ) {
				Logger::error(
					"Bot {$bot['id']} has no API key configured",
					array( 'bot_id' => $bot['id'] ),
					'otz'
				);
				return array(
					'triggered' => false,
					'bot_id'    => $bot['id'],
					'response'  => null,
					'error'     => 'Bot API key not configured',
				);
			}

			// 準備執行上下文.
			$context = array(
				'line_user_id' => $line_user_id,
			);

			// 初始化 OpenAI Client.
			$openai_client = new OpenAIClient( $this->wpdb, $bot['api_key'], $context );

			// 建立對話歷史.
			$messages = $this->build_conversation_history( $line_user_id, $message_text, $bot );

			// 準備 OpenAI 請求參數.
			$options = array(
				'model' => $bot['model'] ?? 'gpt-3.5-turbo',
				'tools' => $bot['function_tools'] ?? null,
			);

			// 記錄開始時間.
			$start_time = microtime( true );

			// 顯示 loading indicator（60 秒最大值）.
			$this->line_api_service->show_loading_indicator( $line_user_id, 240 );

			// 發送請求到 OpenAI.
			$response = $openai_client->send_message( $messages, $options );

			// 計算回應時間.
			$response_time = microtime( true ) - $start_time;

			if ( ! $response['success'] ) {
				Logger::error(
					'OpenAI API request failed',
					array(
						'bot_id'  => $bot['id'],
						'user_id' => $user_id,
						'error'   => $response['error'],
					),
					'otz'
				);

				// 發送友善的錯誤訊息給使用者.
				$bot_name      = '🤖 ' .$bot['name'] ?? 'AI Bot';
				$error_message = "【{$bot_name}】抱歉，目前系統繁忙，請稍後再試。";
				$this->send_line_message( $reply_token, $error_message, null );

				return array(
					'triggered' => false,
					'bot_id'    => $bot['id'],
					'response'  => null,
					'error'     => $response['error']['message'] ?? 'OpenAI API error',
				);
			}

			$ai_message_raw = $response['data']['message'];

			// 加入機器人名稱前綴（僅用於顯示）.
			$bot_name           = '🤖 ' .$bot['name'] ?? 'AI Bot';
			$ai_message_display = "【{$bot_name}】{$ai_message_raw}";

			$usage = $response['data']['usage'];

			// 發送訊息到 LINE 並取得 quote_token，包含 quick replies（如果有設定）.
			$quick_replies = ! empty( $bot['quick_replies'] ) && is_array( $bot['quick_replies'] ) ? $bot['quick_replies'] : null;
			$send_result   = $this->send_line_message( $reply_token, $ai_message_display, $quick_replies );

			// 儲存 Bot 回應訊息到資料庫.
			$this->save_bot_message(
				$line_user_id,
				$ai_message_display,
				(int) $bot['id'],
				$bot,
				$usage['total_tokens'] ?? 0,
				$response_time,
				$send_result['quote_token'] ?? null,
				$event_timestamp
			);

			// 更新 Bot 統計資訊.
			$this->update_bot_statistics( (int) $bot['id'], $usage['total_tokens'] ?? 0, $response_time );

			// 更新對話歷史快取（使用原始訊息，不含前綴）.
			$this->update_conversation_cache( $line_user_id, $message_text, $ai_message_raw, (int) $bot['id'] );

			return array(
				'triggered'     => true,
				'bot_id'        => $bot['id'],
				'response'      => $ai_message_display,
				'action'        => 'ai',
				'tokens_used'   => $usage['total_tokens'] ?? 0,
				'response_time' => $response_time,
			);

		} catch ( \Exception $e ) {
			Logger::error(
				'Error in handle_ai_response: ' . $e->getMessage(),
				array(
					'user_id'      => $user_id,
					'bot_id'       => $bot['id'] ?? null,
					'message_text' => $message_text,
					'exception'    => $e->getMessage(),
				),
				'otz'
			);

			return array(
				'triggered' => false,
				'bot_id'    => $bot['id'] ?? null,
				'response'  => null,
				'error'     => $e->getMessage(),
			);
		}
	}

	/**
	 * 取得預設 AI Bot
	 *
	 * 取得優先順序最高且 action_type 為 'ai' 的機器人.
	 *
	 * @return array|null Bot 資料或 null.
	 */
	private function get_default_ai_bot(): ?array {
		$active_bots = $this->bot_db->get_bots_by_status(
			'active',
			array(
				'order_by' => 'priority',
				'order'    => 'ASC',
				'limit'    => 1,
			)
		);

		if ( empty( $active_bots ) ) {
			return null;
		}

		$bot = $active_bots[0];

		// 確保是 AI 類型.
		if ( 'ai' !== $bot['action_type'] ) {
			return null;
		}

		return $bot;
	}

	/**
	 * 建立對話歷史
	 *
	 * 從資料庫載入最近的對話記錄，組成 OpenAI messages 格式.
	 *
	 * @param string $line_user_id LINE 使用者 ID.
	 * @param string $current_message 當前使用者訊息.
	 * @param array  $bot Bot 資料.
	 * @return array OpenAI messages 格式的對話陣列.
	 */
	private function build_conversation_history( string $line_user_id, string
	$current_message, array $bot ): array {
		$messages = array();

		// 加入 system prompt
		if ( ! empty( $bot['system_prompt'] ) ) {
			// 取得當前時間資訊（使用 WordPress 設定的時區）.
			$current_date     = wp_date( 'Y-m-d' );
			$current_datetime = wp_date( 'Y-m-d H:i:s' );
			$timezone_string  = wp_timezone_string();

			$enhanced_prompt = "CURRENT DATE AND TIME INFORMATION:\n" .
			                   "- Today's Date: {$current_date}\n" .
			                   "- Current DateTime: {$current_datetime}\n" .
			                   "- Timezone: {$timezone_string}\n" .
			                   "- When user mentions relative time (e.g., '今年', '上個月', 'this year', 'last month'), " .
			                   "calculate the date range based on the current date above.\n\n" .
			                   $bot['system_prompt'] . "\n\n" .
			                   "CRITICAL: When presenting function data, you must format it in a user-friendly way, " .
			                   "Present data in natural language with proper structure and use the language which user use, but keep all values verbatim.\n\n" .
			                   "IMPORTANT: When tools are available, you MUST call them before attempting to answer. " .
			                   "DO NOT ask users for information that you can retrieve using tools. " .
			                   "Always prioritize tool usage over conversation.\n\n" .
			                   "Empty Results Handling:\n" .
			                   "1. When customer_orders returns empty results (orders: [], total: 0), " .
			                   "respond with: You don't have any orders now." .
			                   "Please contact us if you have any questions.'\n" .
			                   "2. When customer_info returns no data, suggest member registration or " .
			                   "offer to connect with customer service.\n" .
			                   "3. When product_info or custom_post_type returns errors with no results, " .
			                   "explain the reason clearly and suggest alternative search terms.\n\n" .
			                   "Out of Scope Questions:\n" .
			                   "- If the user asks questions that are NOT related to available functions/tools, " .
			                   "respond politely you don't know.'\n" .
			                   "- DO NOT make up information or provide uncertain answers.\n" .
			                   "- DO NOT attempt to answer questions outside your knowledge scope.\n" .
			                   "- Always be honest about limitations and redirect to human support.\n\n" .
			                   "Customer Service Escalation:\n" .
			                   "- Offer human service connection when: no order records found, " .
			                   "member data issues, or user explicitly requests human help.\n";

			$messages[] = array(
				'role'    => 'system',
				'content' => $enhanced_prompt,
			);
		}

		// ✅ 優先從 Transient 載入快取（包含完整的 function calling 上下文）
		$transient_key = "otz_conversation_{$line_user_id}";
		$cache = get_transient( $transient_key );

		if ( $cache && ! empty( $cache ) ) {

			// 支援新格式（包含 bot_id 和 messages）和舊格式（直接是訊息陣列）.
			if ( isset( $cache['messages'] ) && is_array( $cache['messages'] ) ) {
				// 新格式：['bot_id' => int, 'messages' => array].
				$messages = array_merge( $messages, $cache['messages'] );
				$this->conversation_cache[ $line_user_id ] = $cache;
			} else {
				// 舊格式：直接是訊息陣列，向下相容.
				$messages = array_merge( $messages, $cache );
				$this->conversation_cache[ $line_user_id ] = $cache;
			}

		} else {
			// Fallback: 從資料庫載入最近 10 則訊息.
			$recent_messages = $this->message_db->query_messages(
				$line_user_id, '', 10 );

			foreach ( array_reverse( $recent_messages ) as $msg ) {
				$role       = ( 'user' === $msg->sender_type ) ? 'user' :
					'assistant';
				$messages[] = array(
					'role'    => $role,
					'content' => $msg->message_content,
				);
			}
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $current_message,
		);

		return $messages;
	}

	/**
	 * 更新對話歷史快取
	 *
	 * 將最新的對話加入快取，保留最近 10 輪對話.
	 *
	 * @param string $line_user_id LINE 使用者 ID.
	 * @param string $user_message 使用者訊息.
	 * @param string $ai_message AI 回應.
	 * @param int    $bot_id Bot ID.
	 * @return void
	 */
	private function update_conversation_cache( string $line_user_id, string
	$user_message, string $ai_message, int $bot_id ): void {
		try {

			$transient_key = "otz_conversation_{$line_user_id}";
			$cache = get_transient( $transient_key );

			if ( false === $cache || ! is_array( $cache ) ) {
				$cache = array(
					'bot_id'   => $bot_id,
					'messages' => array(),
				);
			}

			// 更新 bot_id（如果關鍵字重新觸發了新的 Bot）.
			$cache['bot_id'] = $bot_id;

			// 確保 messages 陣列存在.
			if ( ! isset( $cache['messages'] ) || ! is_array( $cache['messages'] ) ) {
				$cache['messages'] = array();
			}

			$cache['messages'][] = array(
				'role'    => 'user',
				'content' => $user_message,
			);

			$cache['messages'][] = array(
				'role'    => 'assistant',
				'content' => $ai_message,
			);

			if ( count( $cache['messages'] ) > 6 ) {
				$cache['messages'] = array_slice( $cache['messages'], -6 );
			}

			set_transient( $transient_key, $cache, 15 * MINUTE_IN_SECONDS );
			$this->conversation_cache[ $line_user_id ] = $cache;

		} catch ( \Exception $e ) {
			Logger::error(
				'Failed to update conversation cache: ' . $e->getMessage(),
				array(
					'line_user_id' => $line_user_id,
					'exception'    => $e->getMessage(),
				),
				'otz'
			);
		}
	}

	/**
	 * 發送 LINE 訊息
	 *
	 * @param string     $reply_token LINE reply token.
	 * @param string     $message 訊息內容.
	 * @param array|null $quick_replies Quick reply 選項陣列.
	 * @return array 包含 success 和 quote_token 的結果陣列.
	 */
	private function send_line_message( string $reply_token, string $message, ?array $quick_replies = null ): array {
		try {
			$result = $this->line_api_service->sendReplyMessage( $reply_token, $message, null, $quick_replies );

			if ( ! isset( $result['success'] ) || ! $result['success'] ) {
				Logger::error(
					'Failed to send LINE message',
					array(
						'reply_token' => $reply_token,
						'error'       => $result['error'] ?? 'Unknown error',
					),
					'otz'
				);
				return array(
					'success'     => false,
					'quote_token' => null,
				);
			}

			return array(
				'success'     => true,
				'quote_token' => $result['quote_token'] ?? null,
			);

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception in send_line_message: ' . $e->getMessage(),
				array(
					'reply_token' => $reply_token,
					'exception'   => $e->getMessage(),
				),
				'otz'
			);
			return array(
				'success'     => false,
				'quote_token' => null,
			);
		}
	}

	/**
	 * 儲存 Bot 回應訊息到資料庫
	 *
	 * @param string      $line_user_id LINE 使用者 ID.
	 * @param string      $message_text Bot 回應內容.
	 * @param int         $bot_id Bot ID.
	 * @param array       $bot Bot 資料.
	 * @param int         $tokens_used 使用的 tokens 數量.
	 * @param float       $response_time 回應時間（秒）.
	 * @param string|null $quote_token Quote token（可選）.
	 * @param int|null    $event_timestamp Event timestamp（可選，毫秒級）.
	 * @return bool 成功返回 true，失敗返回 false.
	 */
	private function save_bot_message(
		string $line_user_id,
		string $message_text,
		int $bot_id,
		array $bot,
		int $tokens_used,
		float $response_time,
		?string $quote_token = null,
		?int $event_timestamp = null
	): bool {
		try {
			// 生成唯一 event_id.
			$event_id = 'bot_' . uniqid() . '_' . time();

			// 計算 sent_date 和 sent_time.
			if ( null !== $event_timestamp ) {
				// 使用 event timestamp，確保 Bot 回應時間不早於使用者訊息.
				$base_timestamp = max(
					(int) ( $event_timestamp / 1000 ),  // 使用者訊息時間（毫秒轉秒）.
					time()  // 當前伺服器時間.
				);
			} else {
				// 如果沒有 event timestamp，使用當前時間.
				$base_timestamp = time();
			}

			$sent_date = wp_date( 'Y-m-d', $base_timestamp );
			$sent_time = wp_date( 'H:i:s', $base_timestamp + 1 );

			// 確定月分表名稱.
			$table_suffix = wp_date( 'Y_m', $base_timestamp );

			// 確保月份表存在.
			if ( ! $this->table_manager->create_monthly_message_table( $table_suffix ) ) {
				Logger::error(
					'Failed to create monthly message table',
					array(
						'table_suffix' => $table_suffix,
						'bot_id'       => $bot_id,
						'line_user_id' => $line_user_id,
					),
					'otz'
				);
				return false;
			}

			$table_name = $this->wpdb->prefix . 'otz_messages_' . $table_suffix;

			// 準備訊息資料.
			$message_data = array(
				'event_id'          => sanitize_text_field( $event_id ),
				'line_user_id'      => sanitize_text_field( $line_user_id ),
				'source_type'       => 'account',
				'sender_type'       => 'bot',
				'sender_name'       => sanitize_text_field( $bot['name'] ?? 'AI Bot' ),
				'group_id'          => null,
				'sent_date'         => $sent_date,
				'sent_time'         => $sent_time,
				'message_type'      => 'text',
				'message_content'   => $message_text,
				'reply_token'       => null,
				'quote_token'       => $quote_token,
				'quoted_message_id' => null,
				'line_message_id'   => null,
				'raw_payload'       => wp_json_encode(
					array(
						'bot_id'        => $bot_id,
						'model'         => $bot['model'] ?? 'gpt-3.5-turbo',
						'tokens_used'   => $tokens_used,
						'response_time' => $response_time,
					),
					JSON_UNESCAPED_UNICODE
				),
				'created_at'        => wp_date( 'Y-m-d H:i:s' ),
			);

			// 插入訊息到資料庫.
			$result = $this->wpdb->insert(
				$table_name,
				$message_data,
				array(
					'%s', // event_id.
					'%s', // line_user_id.
					'%s', // source_type.
					'%s', // sender_type.
					'%s', // sender_name.
					'%s', // group_id.
					'%s', // sent_date.
					'%s', // sent_time.
					'%s', // message_type.
					'%s', // message_content.
					'%s', // reply_token.
					'%s', // quote_token.
					'%s', // quoted_message_id.
					'%s', // line_message_id.
					'%s', // raw_payload.
					'%s', // created_at.
				)
			);

			if ( false === $result ) {
				Logger::error(
					'Failed to save bot message to database',
					array(
						'bot_id'       => $bot_id,
						'line_user_id' => $line_user_id,
						'table_name'   => $table_name,
						'wpdb_error'   => $this->wpdb->last_error,
					),
					'otz'
				);
				return false;
			}

			return true;

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception in save_bot_message: ' . $e->getMessage(),
				array(
					'bot_id'       => $bot_id,
					'line_user_id' => $line_user_id,
					'exception'    => $e->getMessage(),
				),
				'otz'
			);
			return false;
		}
	}

	/**
	 * 更新 Bot 統計資訊
	 *
	 * 更新 trigger_count、total_tokens、avg_response_time.
	 *
	 * @param int   $bot_id Bot ID.
	 * @param int   $tokens_used 本次使用的 tokens.
	 * @param float $response_time 本次回應時間（秒）.
	 * @return void
	 */
	private function update_bot_statistics( int $bot_id, int $tokens_used, float $response_time ): void {
		try {
			// 取得當前 Bot 資料.
			$bot = $this->bot_db->get_bot( $bot_id );
			if ( ! $bot ) {
				Logger::error(
					"Bot {$bot_id} not found for statistics update",
					array( 'bot_id' => $bot_id ),
					'otz'
				);
				return;
			}

			// 計算新的統計值.
			$new_total_tokens = ( $bot['total_tokens'] ?? 0 ) + $tokens_used;

			// 計算平均回應時間.
			// 注意：trigger_count 已經在 handle_bot_trigger() 中被更新，這裡直接使用.
			$current_count = $bot['trigger_count'] ?? 1;
			$old_avg       = $bot['avg_response_time'] ?? 0.0;

			if ( $current_count > 1 ) {
				// 從當前平均值反推前 N-1 次的總時間，加上這次時間，再除以當前次數.
				$new_avg_response_time = ( ( $old_avg * ( $current_count - 1 ) ) + $response_time ) / $current_count;
			} else {
				// 第一次回應，直接使用當前時間.
				$new_avg_response_time = $response_time;
			}

			// 更新 Bot.
			$this->bot_db->save_bot(
				array(
					'id'                => $bot_id,
					'total_tokens'      => $new_total_tokens,
					'avg_response_time' => $new_avg_response_time,
				)
			);

		} catch ( \Exception $e ) {
			Logger::error(
				'Error updating bot statistics: ' . $e->getMessage(),
				array(
					'bot_id'    => $bot_id,
					'exception' => $e->getMessage(),
				),
				'otz'
			);
		}
	}

}

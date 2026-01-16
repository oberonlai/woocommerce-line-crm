<?php

declare(strict_types=1);

namespace OrderChatz\Services\Bot;

use OrderChatz\Util\Logger;

/**
 * OpenAI API Client
 *
 * 處理與 OpenAI API 的通訊,包含模型查詢、訊息發送、Function Calling 與 token 計算.
 * 支援完整的錯誤處理、重試機制與統計功能.
 *
 * @package    OrderChatz
 * @subpackage Services\Bot
 * @since      1.1.6
 */
class OpenAIClient {

	/**
	 * OpenAI API Base URL
	 *
	 * @var string
	 */
	private const API_BASE_URL = 'https://api.openai.com';

	/**
	 * API endpoints
	 *
	 * @var array
	 */
	private const ENDPOINTS = array(
		'chat'   => '/v1/chat/completions',
		'models' => '/v1/models',
		'verify' => '/v1/models',
	);

	/**
	 * Request timeout in seconds
	 *
	 * @var int
	 */
	private const REQUEST_TIMEOUT = 60;

	/**
	 * Maximum retry attempts for failed requests
	 *
	 * @var int
	 */
	private const MAX_RETRIES = 3;

	/**
	 * Maximum function calling depth
	 *
	 * @var int
	 */
	private const MAX_FUNCTION_CALLING_DEPTH = 5;

	/**
	 * API Key for OpenAI
	 *
	 * @var string|null
	 */
	private ?string $api_key = null;

	/**
	 * WordPress database abstraction layer
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Function Registry
	 *
	 * @var FunctionRegistry
	 */
	private FunctionRegistry $function_registry;

	/**
	 * Execution context (contains line_user_id, etc.)
	 *
	 * @var array
	 */
	private array $context = array();

	/**
	 * Constructor
	 *
	 * @param \wpdb       $wpdb WordPress database object.
	 * @param string|null $api_key OpenAI API Key (optional).
	 * @param array       $context Execution context (optional).
	 */
	public function __construct( \wpdb $wpdb, ?string $api_key = null, array $context = array() ) {
		$this->wpdb              = $wpdb;
		$this->api_key           = $api_key;
		$this->context           = $context;
		$this->function_registry = new FunctionRegistry( $wpdb );
	}

	/**
	 * 設定 API Key
	 *
	 * @param string $api_key OpenAI API Key.
	 * @return void
	 */
	public function set_api_key( string $api_key ): void {
		$this->api_key = $api_key;
	}

	/**
	 * 發送訊息到 OpenAI Chat API
	 *
	 * @param array $messages 對話歷史陣列.
	 * @param array $options 選項 (model, temperature, max_tokens, tools).
	 * @return array 回應資料包含訊息、統計資訊或錯誤.
	 */
	public function send_message( array $messages, array $options = array() ): array {
		try {
			if ( ! $this->api_key ) {
				return $this->format_error_response( 'API Key not configured', 401 );
			}

			if ( empty( $messages ) ) {
				return $this->format_error_response( 'Messages array is empty', 400 );
			}

			// 記錄開始時間.
			$start_time = microtime( true );

			$params = wp_parse_args( $options );

			$body = array(
				'model'    => $params['model'],
				'messages' => $messages,
			);

			if ( str_contains( $params['model'], 'gpt-5' ) ) {
				$body['reasoning_effort'] = 'minimal';
			}

			if ( ! empty( $params['tools'] ) && is_array( $params['tools'] ) ) {
				$body['tools'] = $this->format_tools_for_api( $params['tools'] );
			}

			$endpoint = self::API_BASE_URL . self::ENDPOINTS['chat'];
			$response = $this->make_api_request( 'POST', $endpoint, $body );

			// 計算回應時間.
			$response_time = microtime( true ) - $start_time;

			if ( $response['success'] && isset( $response['data'] ) ) {
				$data = $response['data'];

				// 檢查是否需要執行 function calling.
				if ( isset( $data['choices'][0]['message']['tool_calls'] ) ) {
					$depth = (int) ( $params['_function_calling_depth'] ?? 0 );
					return $this->handle_function_calling( $messages, $data, $params, $response_time, $depth );
				}

				// 一般回應.
				$result = array(
					'message'       => $data['choices'][0]['message']['content'] ?? '',
					'usage'         => $data['usage'] ?? array(),
					'response_time' => $response_time,
					'model'         => $data['model'] ?? $params['model'],
					'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'stop',
				);

				return $this->format_success_response( $result );
			}

			return $response;

		} catch ( \Exception $e ) {
			Logger::error(
				'Failed to send message to OpenAI: ' . $e->getMessage(),
				array(
					'exception' => $e->getMessage(),
					'messages'  => $messages,
				),
				'otz'
			);

			return $this->format_error_response( 'Failed to send message', 500 );
		}
	}

	/**
	 * 計算文字的 token 數量
	 *
	 * 使用簡化的估算方式:1 token ≈ 4 characters (英文) 或 1.5 characters (中文).
	 *
	 * @param string $text 要計算的文字.
	 * @param string $model 模型名稱 (預留參數).
	 * @return int Token 數量估算值.
	 */
	public function count_tokens( string $text, string $model = 'gpt-3.5-turbo' ): int {
		// 簡化的 token 計算:偵測中英文比例.
		$chinese_chars = preg_match_all( '/[\x{4e00}-\x{9fa5}]/u', $text );
		$total_chars   = mb_strlen( $text );
		$english_chars = $total_chars - $chinese_chars;

		// 中文: 1.5 字元/token, 英文: 4 字元/token.
		$estimated_tokens = (int) ( ( $chinese_chars / 1.5 ) + ( $english_chars / 4 ) );

		return max( 1, $estimated_tokens );
	}

	/**
	 * 驗證 API Key 是否有效
	 *
	 * @return bool 是否有效.
	 */
	public function verify_api_key(): bool {
		try {
			if ( ! $this->api_key ) {
				Logger::error( 'OpenAI API Key not configured', array(), 'otz' );
				return false;
			}

			$endpoint = self::API_BASE_URL . self::ENDPOINTS['verify'];
			$response = $this->make_api_request( 'GET', $endpoint );

			if ( $response['success'] ) {
				Logger::info( 'OpenAI API Key verified successfully', array(), 'otz' );
				return true;
			}

			Logger::error(
				'OpenAI API Key verification failed',
				array( 'response' => $response ),
				'otz'
			);

			return false;

		} catch ( \Exception $e ) {
			Logger::error(
				'OpenAI API Key verification exception: ' . $e->getMessage(),
				array( 'exception' => $e->getMessage() ),
				'otz'
			);

			return false;
		}
	}

	/**
	 * 執行 Function Calling
	 *
	 * @param string $function_name 函式名稱.
	 * @param array  $arguments 函式參數.
	 * @return array 執行結果.
	 */
	public function execute_function( string $function_name, array $arguments ): array {
		return $this->function_registry->execute( $function_name, $arguments, $this->context );
	}

	/**
	 * 發送 HTTP 請求到 OpenAI API
	 *
	 * @param string     $method HTTP 方法.
	 * @param string     $endpoint API endpoint URL.
	 * @param array|null $body 請求主體資料.
	 * @return array 回應資料.
	 */
	private function make_api_request( string $method, string $endpoint, ?array $body = null ): array {

		set_time_limit( 90 );

		$headers = array(
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type'  => 'application/json',
		);

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => self::REQUEST_TIMEOUT,
		);

		if ( $body && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $body, JSON_UNESCAPED_UNICODE );
		}

		// 重試邏輯.
		$last_error = null;
		for ( $attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++ ) {
			$response = wp_remote_request( $endpoint, $args );

			if ( is_wp_error( $response ) ) {
				$last_error = $response->get_error_message();
				Logger::error(
					"OpenAI API request attempt {$attempt} failed: {$last_error}",
					array(
						'endpoint' => $endpoint,
						'method'   => $method,
					),
					'otz'
				);

				if ( $attempt < self::MAX_RETRIES ) {
					sleep( $attempt ); // Exponential backoff.
					continue;
				}
				break;
			}

			// 解析回應.
			$status_code   = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			if ( $status_code >= 200 && $status_code < 300 ) {
				$data = json_decode( $response_body, true );
				return $this->format_success_response( $data ?? array() );
			}

			// 處理 OpenAI API 錯誤.
			$error_data    = json_decode( $response_body, true );
			$error_message = $this->parse_openai_error( $status_code, $error_data );

			// 不重試客戶端錯誤 (4xx).
			if ( $status_code >= 400 && $status_code < 500 ) {
				Logger::error(
					"OpenAI API client error: {$error_message}",
					array(
						'endpoint'    => $endpoint,
						'status_code' => $status_code,
						'error_data'  => $error_data,
					),
					'otz'
				);
				return $this->format_error_response( $error_message, $status_code );
			}

			// 重試伺服器錯誤 (5xx).
			if ( $status_code >= 500 && $attempt < self::MAX_RETRIES ) {
				Logger::error(
					"OpenAI API server error on attempt {$attempt}: {$error_message}",
					array(
						'endpoint'    => $endpoint,
						'status_code' => $status_code,
					),
					'otz'
				);
				sleep( $attempt );
				continue;
			}

			// 最後一次嘗試失敗.
			Logger::error(
				"OpenAI API request failed after {$attempt} attempts: {$error_message}",
				array(
					'endpoint'    => $endpoint,
					'status_code' => $status_code,
					'error_data'  => $error_data,
				),
				'otz'
			);
			return $this->format_error_response( $error_message, $status_code );
		}

		// 所有重試都失敗.
		return $this->format_error_response(
			$last_error ?? 'Request failed after maximum retries',
			500
		);
	}

	/**
	 * 解析 OpenAI API 錯誤回應
	 *
	 * @param int        $status_code HTTP status code.
	 * @param array|null $error_data 錯誤回應資料.
	 * @return string 使用者友善的錯誤訊息.
	 */
	private function parse_openai_error( int $status_code, ?array $error_data ): string {
		$status_messages = array(
			400 => 'Bad Request - Invalid request format',
			401 => 'Unauthorized - Invalid API key',
			403 => 'Forbidden - Insufficient permissions',
			404 => 'Not Found - Resource does not exist',
			429 => 'Too Many Requests - Rate limit exceeded',
			500 => 'Internal Server Error - OpenAI service error',
			502 => 'Bad Gateway - OpenAI service unavailable',
			503 => 'Service Unavailable - OpenAI maintenance',
		);

		$default_message = $status_messages[ $status_code ] ?? 'Unknown error occurred';

		if ( is_array( $error_data ) && isset( $error_data['error']['message'] ) ) {
			return sanitize_text_field( $error_data['error']['message'] );
		}

		return $default_message;
	}

	/**
	 * 格式化成功回應
	 *
	 * @param array $data 回應資料.
	 * @return array 格式化的回應.
	 */
	private function format_success_response( array $data ): array {
		return array(
			'success' => true,
			'data'    => $data,
		);
	}

	/**
	 * 格式化錯誤回應
	 *
	 * @param string $message 錯誤訊息.
	 * @param int    $code 錯誤代碼.
	 * @return array 格式化的錯誤回應.
	 */
	private function format_error_response( string $message, int $code ): array {
		return array(
			'success' => false,
			'error'   => array(
				'message' => $message,
				'code'    => $code,
			),
		);
	}

	/**
	 * 格式化 tools 為 OpenAI API 格式
	 *
	 * @param array $tools 工具設定陣列.
	 * @return array 格式化的 tools.
	 */
	private function format_tools_for_api( array $tools ): array {
		return $this->function_registry->get_tools_definition( $tools );
	}

	/**
	 * 處理 Function Calling 流程
	 *
	 * @param array $messages 原始訊息陣列.
	 * @param array $response_data OpenAI 回應資料.
	 * @param array $params 請求參數.
	 * @param float $initial_response_time 初始回應時間.
	 * @param int   $depth 當前遞迴深度（預設為 0）.
	 * @return array 最終回應.
	 */
	private function handle_function_calling( array $messages, array $response_data, array $params, float $initial_response_time, int $depth = 0 ): array {
		// 檢查遞迴深度限制.
		if ( $depth >= self::MAX_FUNCTION_CALLING_DEPTH ) {
			Logger::warning(
				'Maximum function calling depth reached',
				array(
					'depth'     => $depth,
					'max_depth' => self::MAX_FUNCTION_CALLING_DEPTH,
					'model'     => $params['model'] ?? 'unknown',
				),
				'otz'
			);

			// 返回友善的錯誤訊息.
			return $this->format_success_response(
				array(
					'message'       => '抱歉，處理過程過於複雜，請嘗試簡化您的問題或分開提問。',
					'usage'         => array(
						'total_tokens' => 0,
					),
					'response_time' => microtime( true ) - $initial_response_time,
					'model'         => $params['model'] ?? 'gpt-3.5-turbo',
					'finish_reason' => 'max_depth_reached',
				)
			);
		}

		$tool_calls = $response_data['choices'][0]['message']['tool_calls'];

		// 將助手的訊息加入對話歷史.
		$messages[] = $response_data['choices'][0]['message'];

		// 執行每個 function call.
		foreach ( $tool_calls as $tool_call ) {
			$function_name = $tool_call['function']['name'];
			$arguments     = json_decode( $tool_call['function']['arguments'], true );

			// 執行函式.
			$function_result = $this->execute_function( $function_name, $arguments );

			// 將函式結果加入對話.
			$messages[] = array(
				'role'         => 'tool',
				'tool_call_id' => $tool_call['id'],
				'content'      => wp_json_encode( $function_result, JSON_UNESCAPED_UNICODE ),
			);
		}

		// 再次呼叫 API 取得最終回應，傳遞遞增的深度.
		$params['_function_calling_depth'] = $depth + 1;
		$final_response                    = $this->send_message( $messages, $params );

		// 加總回應時間.
		if ( $final_response['success'] && isset( $final_response['data']['response_time'] ) ) {
			$final_response['data']['response_time'] += $initial_response_time;
		}

		return $final_response;
	}
}

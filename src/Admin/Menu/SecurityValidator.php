<?php
/**
 * OrderChatz 安全驗證器
 *
 * 提供完整的安全驗證功能，包含 CSRF 防護、輸入驗證、IP 檢查等
 *
 * @package OrderChatz\Admin\Menu
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Menu;

use OrderChatz\Util\Logger;

/**
 * 安全驗證器類別
 *
 * 處理所有安全相關的驗證和防護機制
 */
class SecurityValidator {
	/**
	 * CSRF Token 有效期限 (秒)
	 */
	private const NONCE_LIFETIME = 3600; // 1 小時

	/**
	 * 最大嘗試次數
	 */
	private const MAX_ATTEMPTS = 5;

	/**
	 * 嘗試鎖定時間 (秒)
	 */
	private const LOCKOUT_DURATION = 900; // 15 分鐘

	/**
	 * 驗證頁面存取權限和安全性
	 *
	 * @param string $page 頁面 slug
	 * @param array  $requestData 請求資料
	 * @return array 驗證結果
	 */
	public function validatePageAccess( string $page, array $requestData = array() ): array {
		$result = array(
			'valid'    => false,
			'errors'   => array(),
			'warnings' => array(),
		);

		try {
			// 1. 基本權限檢查
			if ( ! $this->checkUserPermissions( $page ) ) {
				$result['errors'][] = 'insufficient_permissions';
				return $result;
			}

			// 2. IP 白名單檢查 (如果設定)
			if ( ! $this->checkIPWhitelist() ) {
				$result['errors'][] = 'ip_not_allowed';
				return $result;
			}

			// 3. 請求頻率限制檢查
			if ( ! $this->checkRateLimit() ) {
				$result['errors'][] = 'rate_limit_exceeded';
				return $result;
			}

			// 4. 輸入資料驗證
			$inputValidation = $this->validateInputData( $requestData );
			if ( ! $inputValidation['valid'] ) {
				$result['errors'] = array_merge( $result['errors'], $inputValidation['errors'] );
				return $result;
			}

			// 5. CSRF 檢查 (只有在有 POST 資料且有相關動作時才檢查，排除 GET 操作)
			if ( ! empty( $_POST ) && isset( $_POST['action'] ) && ! $this->isGetAction( $requestData ) && ! $this->verifyCsrfToken( $requestData ) ) {
				$result['errors'][] = 'csrf_token_invalid';
				return $result;
			}

			$result['valid'] = true;

		} catch ( \Exception $e ) {
			Logger::error(
				'安全驗證失敗',
				array(
					'page'    => $page,
					'error'   => $e->getMessage(),
					'user_id' => get_current_user_id(),
					'ip'      => $this->getUserIP(),
				)
			);
			$result['errors'][] = 'validation_error';
		}

		return $result;
	}

	/**
	 * 檢查使用者權限
	 *
	 * @param string $page 頁面 slug
	 * @return bool
	 */
	private function checkUserPermissions( string $page ): bool {
		// 基本權限檢查
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		// 主選單 slug 對應到聊天頁面權限
		if ( $page === 'order-chatz' ) {
			return true; // 聊天頁面使用基本權限
		}

		// 頁面特定權限
		$pagePermissions = array(
			'webhook' => 'manage_options',
			'export'  => 'export_data',
		);

		if ( isset( $pagePermissions[ $page ] ) ) {
			return current_user_can( $pagePermissions[ $page ] );
		}

		return true;
	}

	/**
	 * 檢查 IP 白名單
	 *
	 * @return bool
	 */
	private function checkIPWhitelist(): bool {
		$whitelist = get_option( 'orderchatz_ip_whitelist', array() );

		if ( empty( $whitelist ) ) {
			return true; // 如果沒有設定白名單，則允許所有 IP
		}

		$userIP = $this->getUserIP();
		return in_array( $userIP, $whitelist );
	}

	/**
	 * 檢查請求頻率限制
	 *
	 * @return bool
	 */
	private function checkRateLimit(): bool {
		// 對於管理員在管理界面的正常使用，不應用請求頻率限制
		if ( is_admin() && current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		$userIP = $this->getUserIP();
		$userId = get_current_user_id();
		$key    = 'orderchatz_rate_limit_' . md5( $userIP . $userId );

		$attempts = get_transient( $key ) ?: 0;

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			Logger::warning(
				'請求頻率限制觸發',
				array(
					'ip'       => $userIP,
					'user_id'  => $userId,
					'attempts' => $attempts,
				)
			);
			return false;
		}

		// 增加嘗試次數
		set_transient( $key, $attempts + 1, self::LOCKOUT_DURATION );

		return true;
	}

	/**
	 * 驗證輸入資料
	 *
	 * @param array $data 輸入資料
	 * @return array 驗證結果
	 */
	public function validateInputData( array $data ): array {
		$result = array(
			'valid'     => true,
			'errors'    => array(),
			'sanitized' => array(),
		);

		foreach ( $data as $key => $value ) {
			// 跳過系統欄位和標準 WordPress 參數
			$skipFields = array(
				'_wpnonce',
				'_wp_http_referer',
				'action',
				'page',
				'tab',
				// WordPress admin 常見參數
				'post_type',
				'taxonomy',
				'tag_ID',
				'cat_ID',
				'delete_count',
				'orderby',
				'order',
				'post_status',
				'all_posts',
				'detached',
				'mode',
				'error',
				'deleted',
				'ids',
				'message',
				'updated',
				// OrderChatz 特定參數
				'settings-updated',
				'kinsta-cache-cleared',
			);

			if ( in_array( $key, $skipFields ) ) {
				$result['sanitized'][ $key ] = $value;
				continue;
			}

			// 只對表單提交資料進行嚴格驗證
			if ( ! empty( $_POST ) ) {
				// 基本安全檢查
				$validationResult = $this->validateSingleInput( $key, $value );

				if ( ! $validationResult['valid'] ) {
					$result['valid']    = false;
					$result['errors'][] = "invalid_input_{$key}";

					Logger::warning(
						'輸入資料驗證失敗',
						array(
							'field'   => $key,
							'error'   => $validationResult['error'],
							'user_id' => get_current_user_id(),
						)
					);
				} else {
					$result['sanitized'][ $key ] = $validationResult['sanitized'];
				}
			} else {
				// GET 請求較寬鬆處理
				$result['sanitized'][ $key ] = sanitize_text_field( $value );
			}
		}

		return $result;
	}

	/**
	 * 驗證單一輸入欄位
	 *
	 * @param string $key 欄位名稱
	 * @param mixed  $value 欄位值
	 * @return array 驗證結果
	 */
	private function validateSingleInput( string $key, $value ): array {
		$result = array(
			'valid'     => true,
			'sanitized' => $value,
			'error'     => null,
		);

		try {
			// 檢查惡意模式
			if ( $this->containsMaliciousPatterns( $value ) ) {
				$result['valid'] = false;
				$result['error'] = 'malicious_pattern_detected';
				return $result;
			}

			// 根據欄位類型進行特定驗證
			switch ( $key ) {
				case 'email':
					if ( ! is_email( $value ) ) {
						$result['valid'] = false;
						$result['error'] = 'invalid_email';
					} else {
						$result['sanitized'] = sanitize_email( $value );
					}
					break;

				case 'url':
					if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
						$result['valid'] = false;
						$result['error'] = 'invalid_url';
					} else {
						$result['sanitized'] = esc_url_raw( $value );
					}
					break;

				case 'phone':
					$result['sanitized'] = preg_replace( '/[^0-9+\-\(\)\s]/', '', $value );
					break;

				default:
					if ( is_string( $value ) ) {
						$result['sanitized'] = sanitize_text_field( $value );
					} elseif ( is_array( $value ) ) {
						$result['sanitized'] = array_map( 'sanitize_text_field', $value );
					}
					break;
			}
		} catch ( \Exception $e ) {
			$result['valid'] = false;
			$result['error'] = 'validation_exception';
		}

		return $result;
	}

	/**
	 * 檢查是否包含惡意模式
	 *
	 * @param mixed $value 要檢查的值
	 * @return bool
	 */
	private function containsMaliciousPatterns( $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$maliciousPatterns = array(
			// SQL Injection
			'/(\bunion\s+select)|(\bdrop\s+table)|(\bselect\s+.*\bfrom\s+)/i',
			// XSS
			'/<script[^>]*>.*?<\/script>/is',
			'/javascript:/i',
			'/on\w+\s*=/i',
			// Path Traversal
			'/\.\.\//',
			// PHP Code Injection
			'/<\?php|<\?=/i',
			// Command Injection
			'/;\s*(cat|ls|pwd|whoami|id|uname)/i',
		);

		foreach ( $maliciousPatterns as $pattern ) {
			if ( preg_match( $pattern, $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 判斷是否為 GET 操作
	 *
	 * @param array $data 請求資料
	 * @return bool
	 */
	private function isGetAction( array $data ): bool {
		// 檢查是否為 GET 請求的操作（如單筆刪除）
		$getActions = array(
			'delete',           // 單筆刪除
			'edit',             // 編輯
			'view',             // 查看
		);

		// 如果 action 參數是透過 GET 傳遞的，則認為是 GET 操作
		if ( isset( $_GET['action'] ) && in_array( $_GET['action'], $getActions ) ) {
			return true;
		}

		return false;
	}

	/**
	 * 驗證 CSRF Token
	 *
	 * @param array $data 請求資料
	 * @return bool
	 */
	public function verifyCsrfToken( array $data ): bool {
		if ( ! isset( $data['_wpnonce'] ) ) {
			return false;
		}

		// 支援多種 nonce 驗證，確保相容性
		$valid_nonces = array(
			'orderchatz_admin_action',    // 主要 admin action
			'bulk-訂閱者',                // 訂閱者 List table bulk actions
			'bulk-標籤',                  // 標籤 List table bulk actions
			'delete_subscriber',          // 單筆刪除訂閱者
			'send_test_notification',     // 測試通知
		);

		foreach ( $valid_nonces as $nonce_action ) {
			if ( wp_verify_nonce( $data['_wpnonce'], $nonce_action ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 生成 CSRF Token
	 *
	 * @return string
	 */
	public function generateCsrfToken(): string {
		return wp_create_nonce( 'orderchatz_admin_action' );
	}

	/**
	 * 取得使用者 IP
	 *
	 * @return string
	 */
	private function getUserIP(): string {
		$ipKeys = array(
			'HTTP_CF_CONNECTING_IP',     // Cloudflare
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $ipKeys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = $_SERVER[ $key ];
				// 處理多個 IP 的情況
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}

				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}

	/**
	 * 記錄安全事件
	 *
	 * @param string $event 事件類型
	 * @param array  $context 上下文資料
	 * @return void
	 */
	public function logSecurityEvent( string $event, array $context = array() ): void {
		$defaultContext = array(
			'user_id'     => get_current_user_id(),
			'ip'          => $this->getUserIP(),
			'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
			'timestamp'   => current_time( 'mysql' ),
			'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
		);

		Logger::warning( "安全事件: {$event}", array_merge( $defaultContext, $context ) );
	}

	/**
	 * 檢查是否為可疑活動
	 *
	 * @param array $context 上下文資料
	 * @return bool
	 */
	public function isSuspiciousActivity( array $context = array() ): bool {
		$suspiciousIndicators = 0;

		// 檢查多個失敗嘗試
		$userIP     = $this->getUserIP();
		$failureKey = 'orderchatz_failures_' . md5( $userIP );
		$failures   = get_transient( $failureKey ) ?: 0;

		if ( $failures >= 3 ) {
			$suspiciousIndicators++;
		}

		// 檢查是否為已知惡意 IP
		if ( $this->isKnownMaliciousIP( $userIP ) ) {
			$suspiciousIndicators++;
		}

		// 檢查請求時間模式 (機器人通常請求很快)
		if ( isset( $context['request_interval'] ) && $context['request_interval'] < 1 ) {
			$suspiciousIndicators++;
		}

		return $suspiciousIndicators >= 2;
	}

	/**
	 * 檢查是否為已知惡意 IP
	 *
	 * @param string $ip IP 地址
	 * @return bool
	 */
	private function isKnownMaliciousIP( string $ip ): bool {
		$blacklist = get_option( 'orderchatz_ip_blacklist', array() );
		return in_array( $ip, $blacklist );
	}

	/**
	 * 重設請求頻率限制
	 *
	 * @param string|null $identifier 識別符 (IP 或使用者 ID)
	 * @return void
	 */
	public function resetRateLimit( ?string $identifier = null ): void {
		if ( ! $identifier ) {
			$identifier = $this->getUserIP() . get_current_user_id();
		}

		$key = 'orderchatz_rate_limit_' . md5( $identifier );
		delete_transient( $key );
	}
}

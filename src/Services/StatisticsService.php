<?php
/**
 * LINE Messaging API 統計服務
 *
 * 處理 LINE Messaging API 的統計數據獲取和分析
 *
 * @package OrderChatz\Services
 * @since 1.0.0
 */

namespace OrderChatz\Services;

use Exception;

/**
 * 統計服務類別
 *
 * 提供 LINE Messaging API 相關的統計功能
 */
class StatisticsService {

	/**
	 * Channel Access Token
	 *
	 * @var string
	 */
	private string $channel_access_token;

	/**
	 * LINE API Base URL
	 *
	 * @var string
	 */
	private string $api_base_url = 'https://api.line.me/v2/bot';

	/**
	 * Logger instance
	 *
	 * @var \WC_Logger
	 */
	private $logger;

	/**
	 * 建構函式
	 */
	public function __construct() {
		$this->channel_access_token = get_option( 'otz_access_token' );

		$this->logger = wc_get_logger();
	}

	/**
	 * 取得訊息配額資訊
	 *
	 * @return array
	 */
	public function getMessageQuota(): array {
		try {
			$response = $this->makeApiRequest( '/message/quota' );

			if ( $response['success'] ) {
				return array(
					'success' => true,
					'data'    => array(
						'type'  => $response['data']['type'] ?? 'none',
						'value' => $response['data']['value'] ?? 0,
					),
				);
			}

			return array(
				'success' => false,
				'message' => $response['message'] ?? '取得配額失敗',
			);

		} catch ( Exception $e ) {
			$this->logger->error( 'Failed to get message quota: ' . $e->getMessage(), array( 'source' => 'orderchatz' ) );
			return array(
				'success' => false,
				'message' => '系統錯誤：' . $e->getMessage(),
			);
		}
	}

	/**
	 * 取得本月已使用的訊息數量
	 *
	 * @return array
	 */
	public function getMessageConsumption(): array {
		try {
			$response = $this->makeApiRequest( '/message/quota/consumption' );

			if ( $response['success'] ) {
				return array(
					'success' => true,
					'data'    => array(
						'totalUsage' => $response['data']['totalUsage'] ?? 0,
					),
				);
			}

			return array(
				'success' => false,
				'message' => $response['message'] ?? '取得使用量失敗',
			);

		} catch ( Exception $e ) {
			$this->logger->error( 'Failed to get message consumption: ' . $e->getMessage(), array( 'source' => 'orderchatz' ) );
			return array(
				'success' => false,
				'message' => '系統錯誤：' . $e->getMessage(),
			);
		}
	}

	/**
	 * 取得好友數量統計
	 *
	 * @param string $date 日期 (YYYYMMDD 格式)
	 * @return array
	 */
	public function getFriendsStatistics( string $date = '' ): array {
		try {
			if ( empty( $date ) ) {
				$date = date( 'Ymd' );
			}

			$response = $this->makeApiRequest( "/insight/followers?date={$date}" );

			if ( $response['success'] ) {
				return array(
					'success' => true,
					'data'    => array(
						'followers'       => $response['data']['followers'] ?? 0,
						'targetedReaches' => $response['data']['targetedReaches'] ?? 0,
						'blocks'          => $response['data']['blocks'] ?? 0,
					),
				);
			}

			return array(
				'success' => false,
				'message' => $response['message'] ?? '取得好友統計失敗',
			);

		} catch ( Exception $e ) {
			$this->logger->error( 'Failed to get friends statistics: ' . $e->getMessage(), array( 'source' => 'orderchatz' ) );
			return array(
				'success' => false,
				'message' => '系統錯誤：' . $e->getMessage(),
			);
		}
	}

	/**
	 * 取得回覆訊息發送統計
	 *
	 * @param string $date 日期 (YYYYMMDD 格式)
	 * @return array
	 */
	public function getReplyMessageDelivery( string $date = '' ): array {
		try {
			if ( empty( $date ) ) {
				$date = date( 'Ymd' );
			}

			$response = $this->makeApiRequest( "/message/delivery/reply?date={$date}" );

			if ( $response['success'] ) {
				return array(
					'success' => true,
					'data'    => array(
						'status'  => $response['data']['status'] ?? 'unknown',
						'success' => $response['data']['success'] ?? 0,
					),
				);
			}

			return array(
				'success' => false,
				'message' => $response['message'] ?? '取得回覆訊息統計失敗',
			);

		} catch ( Exception $e ) {
			$this->logger->error( 'Failed to get reply message delivery: ' . $e->getMessage(), array( 'source' => 'orderchatz' ) );
			return array(
				'success' => false,
				'message' => '系統錯誤：' . $e->getMessage(),
			);
		}
	}

	/**
	 * 取得推播訊息發送統計
	 *
	 * @param string $date 日期 (YYYYMMDD 格式)
	 * @return array
	 */
	public function getPushMessageDelivery( string $date = '' ): array {
		try {
			if ( empty( $date ) ) {
				$date = date( 'Ymd' );
			}

			$response = $this->makeApiRequest( "/message/delivery/push?date={$date}" );

			if ( $response['success'] ) {
				return array(
					'success' => true,
					'data'    => array(
						'status'  => $response['data']['status'] ?? 'unknown',
						'success' => $response['data']['success'] ?? 0,
					),
				);
			}

			return array(
				'success' => false,
				'message' => $response['message'] ?? '取得推播訊息統計失敗',
			);

		} catch ( Exception $e ) {
			$this->logger->error( 'Failed to get push message delivery: ' . $e->getMessage(), array( 'source' => 'orderchatz' ) );
			return array(
				'success' => false,
				'message' => '系統錯誤：' . $e->getMessage(),
			);
		}
	}

	/**
	 * 取得群發訊息發送統計
	 *
	 * @param string $date 日期 (YYYYMMDD 格式)
	 * @return array
	 */
	public function getMulticastMessageDelivery( string $date = '' ): array {
		try {
			if ( empty( $date ) ) {
				$date = date( 'Ymd' );
			}

			$response = $this->makeApiRequest( "/message/delivery/multicast?date={$date}" );

			if ( $response['success'] ) {
				return array(
					'success' => true,
					'data'    => array(
						'status'  => $response['data']['status'] ?? 'unknown',
						'success' => $response['data']['success'] ?? 0,
					),
				);
			}

			return array(
				'success' => false,
				'message' => $response['message'] ?? '取得群發訊息統計失敗',
			);

		} catch ( Exception $e ) {
			$this->logger->error( 'Failed to get multicast message delivery: ' . $e->getMessage(), array( 'source' => 'orderchatz' ) );
			return array(
				'success' => false,
				'message' => '系統錯誤：' . $e->getMessage(),
			);
		}
	}

	/**
	 * 取得廣播訊息發送統計
	 *
	 * @param string $date 日期 (YYYYMMDD 格式)
	 * @return array
	 */
	public function getBroadcastMessageDelivery( string $date = '' ): array {
		try {
			if ( empty( $date ) ) {
				$date = date( 'Ymd' );
			}

			$response = $this->makeApiRequest( "/message/delivery/broadcast?date={$date}" );

			if ( $response['success'] ) {
				return array(
					'success' => true,
					'data'    => array(
						'status'  => $response['data']['status'] ?? 'unknown',
						'success' => $response['data']['success'] ?? 0,
					),
				);
			}

			return array(
				'success' => false,
				'message' => $response['message'] ?? '取得廣播訊息統計失敗',
			);

		} catch ( Exception $e ) {
			$this->logger->error( 'Failed to get broadcast message delivery: ' . $e->getMessage(), array( 'source' => 'orderchatz' ) );
			return array(
				'success' => false,
				'message' => '系統錯誤：' . $e->getMessage(),
			);
		}
	}

	/**
	 * 取得多天的統計數據
	 *
	 * @param int $days 天數
	 * @return array
	 */
	public function getMultiDayStatistics( int $days = 30 ): array {
		$statistics = array();
		$today      = new \DateTime();

		for ( $i = 0; $i < $days; $i++ ) {
			$date = clone $today;
			$date->sub( new \DateInterval( "P{$i}D" ) );
			$dateString = $date->format( 'Ymd' );

			// 取得當天的各項統計
			$reply     = $this->getReplyMessageDelivery( $dateString );
			$push      = $this->getPushMessageDelivery( $dateString );
			$multicast = $this->getMulticastMessageDelivery( $dateString );
			$broadcast = $this->getBroadcastMessageDelivery( $dateString );

			$statistics[] = array(
				'date'      => $date->format( 'Y-m-d' ),
				'reply'     => $reply['success'] ? $reply['data']['success'] : 0,
				'push'      => $push['success'] ? $push['data']['success'] : 0,
				'multicast' => $multicast['success'] ? $multicast['data']['success'] : 0,
				'broadcast' => $broadcast['success'] ? $broadcast['data']['success'] : 0,
			);

			// 避免超過 API 限制，加入短暫延遲
			usleep( 100000 ); // 100ms
		}

		return array_reverse( $statistics ); // 最舊的日期在前
	}

	/**
	 * 發送 API 請求
	 *
	 * @param string $endpoint API 端點
	 * @param string $method HTTP 方法
	 * @param array  $data 請求數據
	 * @return array
	 */
	private function makeApiRequest( string $endpoint, string $method = 'GET', array $data = array() ): array {
		if ( empty( $this->channel_access_token ) ) {
			return array(
				'success' => false,
				'message' => 'Channel Access Token 未設定',
			);
		}

		$url = $this->api_base_url . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->channel_access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $data ) && $method !== 'GET' ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$status_code  = wp_remote_retrieve_response_code( $response );
		$body         = wp_remote_retrieve_body( $response );
		$decoded_body = json_decode( $body, true );

		if ( $status_code >= 200 && $status_code < 300 ) {
			return array(
				'success' => true,
				'data'    => $decoded_body,
			);
		}

		return array(
			'success' => false,
			'message' => $decoded_body['message'] ?? "API 錯誤 (HTTP {$status_code})",
			'details' => $decoded_body,
		);
	}

	/**
	 * 檢查 API 設定是否完整
	 *
	 * @return bool
	 */
	public function isConfigured(): bool {
		return ! empty( $this->channel_access_token );
	}

	/**
	 * 取得統計摘要
	 *
	 * @return array
	 */
	public function getStatisticsSummary(): array {
		$today = date( 'Ymd' );

		// 平行取得各項統計
		$quota       = $this->getMessageQuota();
		$consumption = $this->getMessageConsumption();
		$friends     = $this->getFriendsStatistics( $today );
		$reply       = $this->getReplyMessageDelivery( $today );
		$push        = $this->getPushMessageDelivery( $today );
		$multicast   = $this->getMulticastMessageDelivery( $today );
		$broadcast   = $this->getBroadcastMessageDelivery( $today );

		return array(
			'quota'       => $quota,
			'consumption' => $consumption,
			'friends'     => $friends,
			'messages'    => array(
				'reply'     => $reply,
				'push'      => $push,
				'multicast' => $multicast,
				'broadcast' => $broadcast,
			),
		);
	}
}

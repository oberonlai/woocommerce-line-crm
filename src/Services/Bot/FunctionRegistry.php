<?php
/**
 * Function Calling 註冊器
 *
 * 管理所有可用的 OpenAI Function Calling 函式.
 * 負責註冊、取得與執行函式.
 *
 * @package OrderChatz\Services\Bot
 * @since 1.1.6
 */

declare(strict_types=1);

namespace OrderChatz\Services\Bot;

use OrderChatz\Services\Bot\Functions\FunctionInterface;
use OrderChatz\Services\Bot\Functions\CustomerOrders;
use OrderChatz\Services\Bot\Functions\CustomerInfo;
use OrderChatz\Services\Bot\Functions\ProductInfo;
use OrderChatz\Services\Bot\Functions\CustomPostType;
use OrderChatz\Database\User;
use OrderChatz\Util\Logger;

/**
 * Function Registry 類別
 */
class FunctionRegistry {

	/**
	 * 已註冊的函式
	 *
	 * @var array<string, FunctionInterface>
	 */
	private array $functions = array();

	/**
	 * 工具設定快取（儲存完整的設定資訊，包含 custom_post_type 的 post_types）
	 *
	 * @var array
	 */
	private array $tools_config = array();

	/**
	 * WordPress 資料庫抽象層
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * User 資料表處理類別
	 *
	 * @var User
	 */
	private User $user;

	/**
	 * 建構子
	 *
	 * @param \wpdb $wpdb WordPress 資料庫物件.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
		$this->user = new User( $wpdb );
		$this->register_default_functions();
	}

	/**
	 * 註冊預設函式
	 *
	 * @return void
	 */
	private function register_default_functions(): void {
		$this->register( new CustomerOrders( $this->wpdb, $this->user ) );
		$this->register( new CustomerInfo( $this->wpdb, $this->user ) );
		$this->register( new ProductInfo( $this->wpdb ) );
		$this->register( new CustomPostType( $this->wpdb ) );
	}

	/**
	 * 註冊函式
	 *
	 * @param FunctionInterface $function 函式實例.
	 * @return void
	 */
	public function register( FunctionInterface $function ): void {
		$name                     = $function->get_name();
		$this->functions[ $name ] = $function;
	}

	/**
	 * 取得函式
	 *
	 * @param string $name 函式名稱.
	 * @return FunctionInterface|null 函式實例或 null.
	 */
	public function get( string $name ): ?FunctionInterface {
		return $this->functions[ $name ] ?? null;
	}

	/**
	 * 取得所有函式
	 *
	 * @return array<string, FunctionInterface>
	 */
	public function get_all(): array {
		return $this->functions;
	}

	/**
	 * 取得已啟用的函式（根據 tools 設定）
	 *
	 * @param array $enabled_tools 啟用的工具陣列（key => bool 或 array）.
	 * @return array<string, FunctionInterface>
	 */
	public function get_enabled( array $enabled_tools ): array {
		$enabled_functions = array();

		// 儲存完整的工具設定以供後續使用.
		$this->tools_config = $enabled_tools;

		foreach ( $enabled_tools as $tool_name => $tool_config ) {
			// 處理 boolean 格式（舊格式，大部分工具使用）.
			if ( is_bool( $tool_config ) ) {
				if ( ! $tool_config ) {
					continue;
				}
			}

			// 處理 array 格式（新格式，如 custom_post_type）.
			if ( is_array( $tool_config ) ) {
				if ( empty( $tool_config['enabled'] ) ) {
					continue;
				}
			}

			// 加入已啟用的函式.
			if ( isset( $this->functions[ $tool_name ] ) ) {
				$enabled_functions[ $tool_name ] = $this->functions[ $tool_name ];
			}
		}

		return $enabled_functions;
	}

	/**
	 * 執行函式
	 *
	 * @param string $name 函式名稱.
	 * @param array  $arguments 函式參數.
	 * @param array  $context 執行上下文（包含 line_user_id 等資訊）.
	 * @return array 執行結果.
	 */
	public function execute( string $name, array $arguments, array $context = array() ): array {
		$function = $this->get( $name );

		if ( ! $function ) {
			Logger::error(
				"Function not found: {$name}",
				array( 'function' => $name ),
				'otz'
			);

			return array(
				'success' => false,
				'error'   => "Unknown function: {$name}",
			);
		}

		// 對於 custom_post_type，注入允許的 post_types 設定.
		if ( 'custom_post_type' === $name && isset( $this->tools_config['custom_post_type'] ) ) {
			$config = $this->tools_config['custom_post_type'];
			if ( is_array( $config ) && isset( $config['post_types'] ) ) {
				$arguments['_allowed_post_types'] = $config['post_types'];
			}
		}

		// 自動注入 customer_id（從 context 中的 line_user_id）.
		if ( isset( $context['line_user_id'] ) && ! isset( $arguments['customer_id'] ) ) {
			$functions_need_customer_id = array( 'customer_orders', 'customer_info' );

			if ( in_array( $name, $functions_need_customer_id, true ) ) {
				$arguments['customer_id'] = $context['line_user_id'];
			}
		}

		// 驗證參數.
		if ( ! $function->validate( $arguments ) ) {
			Logger::error(
				"Function validation failed: {$name}",
				array(
					'function'  => $name,
					'arguments' => $arguments,
				),
				'otz'
			);

			return array(
				'success' => false,
				'error'   => 'Invalid function arguments',
			);
		}

		// 執行函式.
		return $function->execute( $arguments );
	}

	/**
	 * 取得 OpenAI API 格式的 tools 定義
	 *
	 * @param array $enabled_tools 啟用的工具陣列（key => bool）.
	 * @return array Tools 定義陣列.
	 */
	public function get_tools_definition( array $enabled_tools ): array {
		$tools             = array();
		$enabled_functions = $this->get_enabled( $enabled_tools );

		foreach ( $enabled_functions as $function ) {
			$description = $function->get_description();

			// 針對 custom_post_type，動態調整 description 以包含允許的 post types.
			if ( 'custom_post_type' === $function->get_name() && isset( $enabled_tools['custom_post_type'] ) ) {
				$config = $enabled_tools['custom_post_type'];
				if ( is_array( $config ) && isset( $config['post_types'] ) && ! empty( $config['post_types'] ) ) {
					$allowed_types = implode( ', ', $config['post_types'] );
					$description  .= ' Available post types: ' . $allowed_types . '. IMPORTANT: Only use these specific post types when calling this function.';
				}
			}

			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => $function->get_name(),
					'description' => $description,
					'parameters'  => $function->get_parameters_schema(),
				),
			);
		}

		return $tools;
	}

	/**
	 * 檢查函式是否存在
	 *
	 * @param string $name 函式名稱.
	 * @return bool 存在時返回 true.
	 */
	public function has( string $name ): bool {
		return isset( $this->functions[ $name ] );
	}

	/**
	 * 取消註冊函式
	 *
	 * @param string $name 函式名稱.
	 * @return void
	 */
	public function unregister( string $name ): void {
		if ( isset( $this->functions[ $name ] ) ) {
			unset( $this->functions[ $name ] );

			Logger::info(
				"Function unregistered: {$name}",
				array( 'function' => $name ),
				'otz'
			);
		}
	}
}

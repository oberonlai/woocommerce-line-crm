<?php
/**
 * 篩選條件註冊器
 *
 * 管理所有可用的篩選條件策略.
 *
 * @package OrderChatz\Services\Broadcast
 * @since 1.1.3
 */

declare(strict_types=1);

namespace OrderChatz\Services\Broadcast;

use OrderChatz\Services\Broadcast\Conditions\BillingAddress;
use OrderChatz\Services\Broadcast\Conditions\OrderProduct;
use OrderChatz\Services\Broadcast\Conditions\OrderProductCategory;
use OrderChatz\Services\Broadcast\Conditions\OrderProductTag;
use OrderChatz\Services\Broadcast\Conditions\ShippingAddress;
use OrderChatz\Services\Broadcast\Conditions\UserBinding;
use OrderChatz\Services\Broadcast\Conditions\UserTag;
use OrderChatz\Services\Broadcast\Conditions\UserTagCount;

/**
 * 篩選條件註冊器類別
 */
class FilterConditionRegistry {

	/**
	 * 已註冊的條件策略
	 *
	 * @var array<string, FilterConditionInterface>
	 */
	private static array $conditions = array();

	/**
	 * 註冊篩選條件
	 *
	 * @param FilterConditionInterface $condition 條件策略實例.
	 * @return void
	 */
	public static function register( FilterConditionInterface $condition ): void {
		self::$conditions[ $condition->get_type() ] = $condition;
	}

	/**
	 * 取得條件實例
	 *
	 * @param string $type 條件類型.
	 * @return FilterConditionInterface|null 條件實例，不存在時返回 null.
	 */
	public static function get( string $type ): ?FilterConditionInterface {
		return self::$conditions[ $type ] ?? null;
	}

	/**
	 * 取得所有已註冊的條件
	 *
	 * @return array<string, FilterConditionInterface> 條件陣列.
	 */
	public static function get_all(): array {
		return self::$conditions;
	}

	/**
	 * 初始化預設條件
	 *
	 * @return void
	 */
	public static function init_default_conditions(): void {
		// 註冊所有預設條件.
		self::register( new BillingAddress() );
		self::register( new OrderProduct() );
		self::register( new OrderProductCategory() );
		self::register( new OrderProductTag() );
		self::register( new ShippingAddress() );
		self::register( new UserBinding() );
		self::register( new UserTag() );
		self::register( new UserTagCount() );

		// 允許第三方透過 Hook 註冊自訂條件.
		do_action( 'otz_register_filter_conditions' );
	}

	/**
	 * 清空所有已註冊的條件（主要用於測試）
	 *
	 * @return void
	 */
	public static function clear(): void {
		self::$conditions = array();
	}
}

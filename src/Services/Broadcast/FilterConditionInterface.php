<?php
/**
 * 篩選條件介面
 *
 * 定義所有篩選條件策略必須實作的方法.
 *
 * @package OrderChatz\Services\Broadcast
 * @since 1.1.3
 */

declare(strict_types=1);

namespace OrderChatz\Services\Broadcast;

/**
 * 篩選條件介面
 */
interface FilterConditionInterface {

	/**
	 * 取得條件類型標識符
	 *
	 * @return string 條件類型（例如：'order_product_name', 'user_tag'）.
	 */
	public function get_type(): string;

	/**
	 * 建構 SQL 條件
	 *
	 * @param array  $condition 條件資料（包含 field, operator, value）.
	 * @param object $wpdb      WordPress 資料庫物件.
	 * @param array  $joins     JOIN 條件陣列（引用傳遞）.
	 *
	 * @return string|null 條件 SQL，無效時返回 null.
	 */
	public function build_sql( array $condition, object $wpdb, array &$joins ): ?string;

	/**
	 * 驗證條件資料
	 *
	 * @param array $condition 條件資料.
	 * @return bool 驗證通過返回 true.
	 */
	public function validate( array $condition ): bool;

	/**
	 * 取得支援的操作符列表
	 *
	 * @return array 操作符陣列（例如：['equals', 'greater_than', 'contains']）.
	 */
	public function get_supported_operators(): array;

	/**
	 * 取得條件所屬群組
	 *
	 * @return string 群組標識符（'order' 或 'user'）.
	 */
	public function get_group(): string;

	/**
	 * 取得前端 UI 配置
	 *
	 * 返回此條件類型的前端顯示配置，包含：
	 * - type: 條件類型標識符
	 * - label: 顯示名稱
	 * - icon: Dashicons 圖示類別
	 * - operators: 支援的操作符配置
	 * - value_component: 前端 Value Renderer 名稱
	 * - value_config: Value Renderer 的配置參數
	 *
	 * @return array UI 配置陣列.
	 */
	public function get_ui_config(): array;
}

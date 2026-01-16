<?php
/**
 * Function Calling 介面
 *
 * 定義所有 OpenAI Function Calling 函式必須實作的方法.
 *
 * @package OrderChatz\Services\Bot\Functions
 * @since 1.1.6
 */

declare(strict_types=1);

namespace OrderChatz\Services\Bot\Functions;

/**
 * Function Calling 介面
 */
interface FunctionInterface {

	/**
	 * 取得函式名稱
	 *
	 * @return string 函式名稱（例如：'customer_orders', 'product_info'）.
	 */
	public function get_name(): string;

	/**
	 * 取得函式描述
	 *
	 * @return string 函式功能描述,供 AI 理解使用時機.
	 */
	public function get_description(): string;

	/**
	 * 取得參數 Schema
	 *
	 * 返回符合 OpenAI Function Calling 格式的參數定義.
	 *
	 * @return array 參數 Schema 陣列.
	 */
	public function get_parameters_schema(): array;

	/**
	 * 執行函式
	 *
	 * @param array $arguments 函式參數.
	 * @return array 執行結果（包含 success 和 data/error）.
	 */
	public function execute( array $arguments ): array;

	/**
	 * 驗證參數
	 *
	 * @param array $arguments 函式參數.
	 * @return bool 驗證通過返回 true.
	 */
	public function validate( array $arguments ): bool;
}

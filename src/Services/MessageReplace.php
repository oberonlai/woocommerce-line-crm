<?php
/**
 * OrderChatz 訊息參數替換服務
 *
 * 處理推播訊息中的動態參數替換功能.
 *
 * @package OrderChatz\Services
 * @since 1.1.3
 */

declare(strict_types=1);

namespace OrderChatz\Services;

use OrderChatz\Database\User;
use WP_User;

/**
 * 訊息參數替換類別
 */
class MessageReplace {

	/**
	 * User 資料庫類別
	 *
	 * @var User
	 */
	private User $user_db;

	/**
	 * 建構子
	 */
	public function __construct() {
		global $wpdb;
		$this->user_db = new User( $wpdb );
	}

	/**
	 * 替換訊息中的使用者變數
	 *
	 * @param string $message      原始訊息內容.
	 * @param string $line_user_id LINE 使用者 ID.
	 * @return string 替換後的訊息內容.
	 */
	public function replace_message( string $message, string $line_user_id ): string {
		// 如果訊息中沒有參數標籤，直接返回.
		if ( strpos( $message, '{{' ) === false ) {
			return $message;
		}

		// 取得使用者完整資料.
		$user_data = $this->get_user_data( $line_user_id );

		// 使用正則表達式匹配所有 {{parameter}} 格式的參數（支援字母、數字、底線）.
		$pattern = '/\{\{([a-z0-9_]+)\}\}/i';

		$replaced_message = preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $user_data ) {
				$parameter = $matches[1];
				return $this->get_parameter_value( $parameter, $user_data );
			},
			$message
		);

		return $replaced_message ?? $message;
	}

	/**
	 * 取得使用者完整資料
	 *
	 * @param string $line_user_id LINE 使用者 ID.
	 * @return array 使用者資料陣列.
	 */
	private function get_user_data( string $line_user_id ): array {
		$user_data = array();

		// 1. 從 otz_users 取得 LINE 使用者資料和 wp_user_id.
		$otz_user = $this->user_db->get_user_with_wp_user_id( $line_user_id );

		if ( ! $otz_user ) {
			return $user_data;
		}

		// 加入 LINE 使用者資料.
		$user_data['line_user_id']      = $otz_user['line_user_id'];
		$user_data['line_display_name'] = $otz_user['line_display_name'];

		// 2. 如果有綁定 WordPress 使用者，取得 WordPress 使用者資料.
		if ( ! empty( $otz_user['wp_user_id'] ) ) {
			$wp_user_id = intval( $otz_user['wp_user_id'] );
			$wp_user    = get_user_by( 'id', $wp_user_id );

			if ( $wp_user instanceof WP_User ) {
				// 保存 wp_user_id 供後續 meta 查詢使用.
				$user_data['wp_user_id'] = $wp_user_id;

				// WordPress 基本資料.
				$user_data['user_login']   = $wp_user->user_login;
				$user_data['display_name'] = $wp_user->display_name;
				$user_data['user_email']   = $wp_user->user_email;
				$user_data['user_role']    = ! empty( $wp_user->roles ) ? $wp_user->roles[0] : '';

				// 3. 取得 WooCommerce 帳單地址資料.
				$billing_fields = array(
					'billing_first_name',
					'billing_last_name',
					'billing_company',
					'billing_address_1',
					'billing_address_2',
					'billing_city',
					'billing_state',
					'billing_postcode',
					'billing_country',
					'billing_email',
					'billing_phone',
				);

				foreach ( $billing_fields as $field ) {
					$value               = get_user_meta( $wp_user->ID, $field, true );
					$user_data[ $field ] = ! empty( $value ) ? $value : '';
				}

				// 4. 取得 WooCommerce 運送地址資料.
				$shipping_fields = array(
					'shipping_first_name',
					'shipping_last_name',
					'shipping_company',
					'shipping_address_1',
					'shipping_address_2',
					'shipping_city',
					'shipping_state',
					'shipping_postcode',
					'shipping_country',
				);

				foreach ( $shipping_fields as $field ) {
					$value               = get_user_meta( $wp_user->ID, $field, true );
					$user_data[ $field ] = ! empty( $value ) ? $value : '';
				}
			}
		}

		/**
		 * 過濾推播訊息使用者資料
		 *
		 * 允許其他模組加入自訂使用者資料
		 *
		 * @param array  $user_data    使用者資料陣列.
		 * @param string $line_user_id LINE 使用者 ID.
		 *
		 * @since 1.1.3
		 */
		return apply_filters( 'otz_broadcast_user_data', $user_data, $line_user_id );
	}

	/**
	 * 取得參數對應的值
	 *
	 * 使用三階段查詢策略：
	 * 1. 優先使用預定義的使用者資料.
	 * 2. 找不到時自動查詢 wp_usermeta 表.
	 * 3. 還是找不到才返回橫線.
	 *
	 * @param string $parameter 參數名稱.
	 * @param array  $user_data 使用者資料陣列.
	 * @return string 參數值，如果不存在則返回橫線（-）.
	 */
	private function get_parameter_value( string $parameter, array $user_data ): string {
		// 步驟 1：檢查參數是否存在於預定義使用者資料中.
		if ( isset( $user_data[ $parameter ] ) && $user_data[ $parameter ] !== '' ) {
			return $user_data[ $parameter ];
		}

		// 步驟 2：嘗試從 wp_usermeta 查詢（需要有 WordPress 使用者綁定）.
		if ( ! empty( $user_data['wp_user_id'] ) ) {
			$meta_value = get_user_meta( $user_data['wp_user_id'], $parameter, true );

			// 找到有效的 meta 值.
			if ( $meta_value !== '' && false !== $meta_value ) {
				// 如果是陣列，轉換為 JSON 字串（某些 meta 可能是序列化陣列）.
				if ( is_array( $meta_value ) ) {
					return wp_json_encode( $meta_value, JSON_UNESCAPED_UNICODE );
				}

				return strval( $meta_value );
			}
		}

		// 步驟 3：參數不存在或為空，返回橫線.
		return '-';
	}

	/**
	 * 取得支援的參數列表
	 *
	 * @return array 支援的參數列表，格式為 [參數名稱 => 說明].
	 */
	public function get_supported_parameters(): array {
		return array(
			// LINE user data.
			'line_display_name'   => __( 'LINE Display Name', 'otz' ),
			'line_user_id'        => __( 'LINE User ID', 'otz' ),

			// WordPress user data.
			'user_login'          => __( 'Username', 'otz' ),
			'display_name'        => __( 'Display Name', 'otz' ),
			'user_email'          => __( 'Email', 'otz' ),
			'user_role'           => __( 'User Role', 'otz' ),

			// Billing address.
			'billing_first_name'  => __( 'Billing - First Name', 'otz' ),
			'billing_last_name'   => __( 'Billing - Last Name', 'otz' ),
			'billing_company'     => __( 'Billing - Company', 'otz' ),
			'billing_address_1'   => __( 'Billing - Address Line 1', 'otz' ),
			'billing_address_2'   => __( 'Billing - Address Line 2', 'otz' ),
			'billing_city'        => __( 'Billing - City', 'otz' ),
			'billing_state'       => __( 'Billing - State', 'otz' ),
			'billing_postcode'    => __( 'Billing - Postcode', 'otz' ),
			'billing_country'     => __( 'Billing - Country', 'otz' ),
			'billing_email'       => __( 'Billing - Email', 'otz' ),
			'billing_phone'       => __( 'Billing - Phone', 'otz' ),

			// Shipping address.
			'shipping_first_name' => __( 'Shipping - First Name', 'otz' ),
			'shipping_last_name'  => __( 'Shipping - Last Name', 'otz' ),
			'shipping_company'    => __( 'Shipping - Company', 'otz' ),
			'shipping_address_1'  => __( 'Shipping - Address Line 1', 'otz' ),
			'shipping_address_2'  => __( 'Shipping - Address Line 2', 'otz' ),
			'shipping_city'       => __( 'Shipping - City', 'otz' ),
			'shipping_state'      => __( 'Shipping - State', 'otz' ),
			'shipping_postcode'   => __( 'Shipping - Postcode', 'otz' ),
			'shipping_country'    => __( 'Shipping - Country', 'otz' ),
		);
	}

	/**
	 * 取得包含分組資訊的參數列表（支援 hook 擴充）
	 *
	 * @return array 參數列表，格式為 [參數名稱 => ['label' => 說明, 'group' => 群組名稱]].
	 */
	public function get_parameters_with_groups(): array {
		$parameters = array(
			// LINE user data.
			'line_display_name'   => array(
				'label' => __( 'LINE Display Name', 'otz' ),
				'group' => __( 'LINE User Data', 'otz' ),
			),
			'line_user_id'        => array(
				'label' => __( 'LINE User ID', 'otz' ),
				'group' => __( 'LINE User Data', 'otz' ),
			),

			// WordPress user data.
			'user_login'          => array(
				'label' => __( 'Username', 'otz' ),
				'group' => __( 'WordPress User Data', 'otz' ),
			),
			'display_name'        => array(
				'label' => __( 'Display Name', 'otz' ),
				'group' => __( 'WordPress User Data', 'otz' ),
			),
			'user_email'          => array(
				'label' => __( 'Email', 'otz' ),
				'group' => __( 'WordPress User Data', 'otz' ),
			),
			'user_role'           => array(
				'label' => __( 'User Role', 'otz' ),
				'group' => __( 'WordPress User Data', 'otz' ),
			),

			// Billing address.
			'billing_first_name'  => array(
				'label' => __( 'First Name', 'otz' ),
				'group' => __( 'Billing Address', 'otz' ),
			),
			'billing_last_name'   => array(
				'label' => __( 'Last Name', 'otz' ),
				'group' => __( 'Billing Address', 'otz' ),
			),
			'billing_company'     => array(
				'label' => __( 'Company', 'otz' ),
				'group' => __( 'Billing Address', 'otz' ),
			),
			'billing_address_1'   => array(
				'label' => __( 'Address Line 1', 'otz' ),
				'group' => __( 'Billing Address', 'otz' ),
			),
			'billing_address_2'   => array(
				'label' => __( 'Address Line 2', 'otz' ),
				'group' => __( 'Billing Address', 'otz' ),
			),
			'billing_city'        => array(
				'label' => __( 'City', 'otz' ),
				'group' => __( 'Billing Address', 'otz' ),
			),
			'billing_state'       => array(
				'label' => __( 'State', 'otz' ),
				'group' => __( 'Billing Address', 'otz' ),
			),
			'billing_postcode'    => array(
				'label' => __( 'Postcode', 'otz' ),
				'group' => __( 'Billing Address', 'otz' ),
			),
			'billing_country'     => array(
				'label' => __( 'Country', 'otz' ),
				'group' => __( 'Billing Address', 'otz' ),
			),
			'billing_email'       => array(
				'label' => __( 'Email', 'otz' ),
				'group' => __( 'Billing Address', 'otz' ),
			),
			'billing_phone'       => array(
				'label' => __( 'Phone', 'otz' ),
				'group' => __( 'Billing Address', 'otz' ),
			),

			// Shipping address.
			'shipping_first_name' => array(
				'label' => __( 'First Name', 'otz' ),
				'group' => __( 'Shipping Address', 'otz' ),
			),
			'shipping_last_name'  => array(
				'label' => __( 'Last Name', 'otz' ),
				'group' => __( 'Shipping Address', 'otz' ),
			),
			'shipping_company'    => array(
				'label' => __( 'Company', 'otz' ),
				'group' => __( 'Shipping Address', 'otz' ),
			),
			'shipping_address_1'  => array(
				'label' => __( 'Address Line 1', 'otz' ),
				'group' => __( 'Shipping Address', 'otz' ),
			),
			'shipping_address_2'  => array(
				'label' => __( 'Address Line 2', 'otz' ),
				'group' => __( 'Shipping Address', 'otz' ),
			),
			'shipping_city'       => array(
				'label' => __( 'City', 'otz' ),
				'group' => __( 'Shipping Address', 'otz' ),
			),
			'shipping_state'      => array(
				'label' => __( 'State', 'otz' ),
				'group' => __( 'Shipping Address', 'otz' ),
			),
			'shipping_postcode'   => array(
				'label' => __( 'Postcode', 'otz' ),
				'group' => __( 'Shipping Address', 'otz' ),
			),
			'shipping_country'    => array(
				'label' => __( 'Country', 'otz' ),
				'group' => __( 'Shipping Address', 'otz' ),
			),
		);

		/**
		 * 過濾推播訊息參數列表
		 *
		 * 允許其他模組加入自訂參數
		 *
		 * @param array $parameters 參數列表.
		 *
		 * @since 1.1.3
		 */
		return apply_filters( 'otz_broadcast_message_parameters', $parameters );
	}

	/**
	 * 渲染參數列表 DOM
	 *
	 * @return string 參數列表 HTML.
	 */
	public function render_parameters_list(): string {
		$parameters = $this->get_parameters_with_groups();

		// 將參數按群組分類.
		$grouped_parameters = array();
		foreach ( $parameters as $param_name => $param_data ) {
			$group                                       = $param_data['group'];
			$grouped_parameters[ $group ][ $param_name ] = $param_data['label'];
		}

		// 生成 HTML.
		$html = '<ul class="parameters-list">' . "\n";

		foreach ( $grouped_parameters as $group_name => $group_params ) {
			// 群組標題.
			$html .= "\t" . '<li class="parameter-group-title">' . esc_html( $group_name ) . '</li>' . "\n";

			// 群組參數.
			foreach ( $group_params as $param_name => $param_label ) {
				$param_tag = '{{' . $param_name . '}}';
				$html     .= "\t" . '<li class="parameter-item" data-param="' . esc_attr( $param_tag ) . '">' . "\n";
				$html     .= "\t\t" . esc_html( $param_label ) . "\n";
				$html     .= "\t" . '</li>' . "\n";
			}
		}

		$html .= '</ul>' . "\n";

		return $html;
	}
}

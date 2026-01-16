<?php

declare(strict_types=1);

namespace OrderChatz\Database\Message;

use OrderChatz\Database\DynamicTableManager;
use OrderChatz\Util\Logger;

/**
 * Message Import Table Manager Class
 *
 * 專門處理 LINE 聊天記錄匯入的類別，負責從 CSV 檔案匯入歷史訊息至月份分表
 *
 * @package    OrderChatz
 * @subpackage Database
 * @since      1.0.18
 */
class TableMessageImport {

	/**
	 * WordPress database abstraction layer
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Dynamic table manager instance
	 *
	 * @var DynamicTableManager
	 */
	private DynamicTableManager $table_manager;

	/**
	 * Constructor
	 *
	 * @param \wpdb               $wpdb WordPress database object
	 * @param DynamicTableManager $table_manager Dynamic table manager instance
	 */
	public function __construct( \wpdb $wpdb, DynamicTableManager $table_manager ) {
		$this->wpdb          = $wpdb;
		$this->table_manager = $table_manager;
	}

	/**
	 * 預覽 CSV 訊息（僅解析一次）
	 *
	 * @param string $csv_path CSV 檔案路徑
	 * @param string $line_user_id LINE 使用者 ID
	 * @param array  $options 選項參數
	 * @return array 預覽結果含完整解析資料
	 */
	public function preview_csv_messages( string $csv_path, string $line_user_id, array $options = array() ): array {
		$result = array(
			'success'        => false,
			'messages'       => array(),
			'pagination'     => array(),
			'statistics'     => array(),
			'parsed_data'    => array(),
			'error_messages' => array(),
		);

		try {
			if ( ! file_exists( $csv_path ) ) {
				$result['error_messages'][] = "CSV 檔案不存在: {$csv_path}";
				return $result;
			}

			$parse_result = $this->parse_csv_file( $csv_path );

			if ( ! $parse_result['success'] ) {
				$result['error_type']       = $parse_result['error_type'];
				$result['error_messages'][] = $parse_result['message'];
				return $result;
			}

			$csv_records = $parse_result['records'];
			if ( empty( $csv_records ) ) {
				$result['error_messages'][] = 'CSV 檔案內容為空或格式錯誤';
				return $result;
			}

			// 保存完整解析結果供前端暫存
			$result['parsed_data'] = $csv_records;

			// 應用篩選器
			$filtered_records = $this->apply_filters( $csv_records, $options );

			// 計算統計資料
			$result['statistics'] = $this->calculate_statistics( $filtered_records );

			// 應用分頁
			$page     = isset( $options['page'] ) ? max( 1, intval( $options['page'] ) ) : 1;
			$per_page = isset( $options['per_page'] ) ? max( 1, intval( $options['per_page'] ) ) : 20;
			$offset   = ( $page - 1 ) * $per_page;

			$paginated_records = array_slice( $filtered_records, $offset, $per_page );

			$result['pagination'] = array(
				'current_page' => $page,
				'per_page'     => $per_page,
				'total_items'  => count( $filtered_records ),
				'total_pages'  => ceil( count( $filtered_records ) / $per_page ),
			);

			// 格式化預覽資料
			foreach ( $paginated_records as $index => $record ) {
				$message_data = $this->convert_line_record_to_db_format( $record, $line_user_id );

				$preview_item = array(
					'index'              => $offset + $index,
					'sender_type'        => $record['sender_type'],
					'sender_name'        => $record['sender_name'],
					'sent_date'          => str_replace( '/', '-', $record['sent_date'] ),
					'sent_time'          => $record['sent_time'],
					'message_content'    => $record['message_content'],
					'formatted_datetime' => $record['sent_date'] . ' ' . $record['sent_time'],
				);

				// 檢查是否重複
				$target_table = $this->get_target_month_table( $message_data['sent_date'] );
				$year_month   = date( 'Y_m', strtotime( $message_data['sent_date'] ) );

				if ( $this->table_manager->monthly_message_table_exists( $year_month ) ) {
					$preview_item['is_duplicate'] = $this->is_duplicate_message( $target_table, $message_data );
				} else {
					$preview_item['is_duplicate'] = false;
				}

				$result['messages'][] = $preview_item;
			}

			$result['success'] = true;

		} catch ( \Exception $e ) {
			$result['error_messages'][] = "預覽過程發生錯誤: {$e->getMessage()}";
			Logger::error( "LINE 聊天記錄預覽失敗: {$e->getMessage()}" );
		}

		return $result;
	}

	/**
	 * 匯入已解析的訊息資料
	 *
	 * @param array  $parsed_messages 已解析的訊息陣列
	 * @param string $line_user_id LINE 使用者 ID
	 * @param array  $options 選項參數
	 * @return array 匯入結果統計
	 */
	public function import_parsed_messages( array $parsed_messages, string $line_user_id, array $options = array() ): array {
		$result = array(
			'success'         => false,
			'total_processed' => 0,
			'imported'        => 0,
			'skipped'         => 0,
			'errors'          => 0,
			'error_messages'  => array(),
		);

		try {
			if ( empty( $parsed_messages ) ) {
				$result['error_messages'][] = '沒有要匯入的訊息資料';
				return $result;
			}

			// 如果有指定選擇的索引，只處理選擇的項目
			if ( isset( $options['selected_indices'] ) && is_array( $options['selected_indices'] ) ) {
				$selected_messages = array();
				foreach ( $options['selected_indices'] as $index ) {
					if ( isset( $parsed_messages[ $index ] ) ) {
						$selected_messages[] = $parsed_messages[ $index ];
					}
				}
				$parsed_messages = $selected_messages;
			}

			$result['total_processed'] = count( $parsed_messages );

			// 處理每筆記錄
			foreach ( $parsed_messages as $index => $record ) {
				try {
					$message_data = $this->convert_line_record_to_db_format( $record, $line_user_id );

					// 實際寫入資料庫
					$target_table = $this->get_target_month_table( $message_data['sent_date'] );
					$year_month   = date( 'Y_m', strtotime( $message_data['sent_date'] ) );

					if ( ! $this->ensure_month_table_exists( $year_month ) ) {
						$result['errors']++;
						$result['error_messages'][] = '第 ' . ( $index + 1 ) . " 行：無法建立月份表 {$year_month}";
						continue;
					}

					if ( $this->is_duplicate_message( $target_table, $message_data ) ) {
						$result['skipped']++;
						continue;
					}

					$inserted = $this->wpdb->insert(
						$target_table,
						$message_data,
						array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
					);

					if ( $inserted ) {
						$result['imported']++;
					} else {
						$result['errors']++;
						$result['error_messages'][] = '第 ' . ( $index + 1 ) . ' 行：資料庫插入失敗';
					}
				} catch ( \Exception $e ) {
					$result['errors']++;
					$result['error_messages'][] = '第 ' . ( $index + 1 ) . " 行：{$e->getMessage()}";
				}
			}

			$result['success'] = true;

		} catch ( \Exception $e ) {
			$result['error_messages'][] = "匯入過程發生錯誤: {$e->getMessage()}";
			Logger::error( "LINE 聊天記錄匯入失敗: {$e->getMessage()}" );
		}

		return $result;
	}


	/**
	 * 解析 CSV 檔案內容（限制最多 5000 筆）
	 *
	 * @param string $csv_path CSV 檔案路徑
	 * @return array 解析結果
	 */
	private function parse_csv_file( string $csv_path ): array {
		$records     = array();
		$max_records = 5000;

		if ( ( $handle = fopen( $csv_path, 'r' ) ) !== false ) {
			$line_number = 0;

			while ( ( $data = fgetcsv( $handle, 0, ',' ) ) !== false ) {
				$line_number++;

				// 跳過前3行元數據
				if ( $line_number <= 3 ) {
					continue;
				}

				// 第4行是標題
				if ( $line_number === 4 ) {
					continue;
				}

				// 第5行開始是實際資料
				if ( count( $data ) >= 5 ) {
					$records[] = array(
						'sender_type'     => $data[0] ?? '',
						'sender_name'     => $data[1] ?? '',
						'sent_date'       => $data[2] ?? '',
						'sent_time'       => $data[3] ?? '',
						'message_content' => $data[4] ?? '',
					);

					// 達到 5000 筆限制
					if ( count( $records ) >= $max_records ) {
						// 檢查是否還有更多資料
						$has_more = ( fgetcsv( $handle, 0, ',' ) !== false );
						fclose( $handle );

						if ( $has_more ) {
							return array(
								'success'    => false,
								'error_type' => 'too_many_records',
								'message'    => '檔案包含超過 5000 筆訊息。請使用 LINE 匯出功能選擇較小的日期範圍重新下載。',
							);
						}
						break;
					}
				}
			}

			fclose( $handle );
		}

		return array(
			'success' => true,
			'records' => $records,
		);
	}

	/**
	 * 將 LINE 記錄轉換為資料庫格式
	 *
	 * @param array  $line_data LINE 匯出資料
	 * @param string $line_user_id LINE 使用者 ID
	 * @return array 資料庫格式的訊息資料
	 */
	private function convert_line_record_to_db_format( array $line_data, string $line_user_id ): array {
		// 轉換日期格式 YYYY/MM/DD → YYYY-MM-DD
		$sent_date = str_replace( '/', '-', $line_data['sent_date'] );

		// 轉換傳送者類型 User/Account → user/account
		$sender_type = strtolower( $line_data['sender_type'] );

		// 處理 sender_name - Account 類型且為 Unknown 時改為 Import
		$sender_name = $line_data['sender_name'];
		if ( $sender_type === 'account' && $sender_name === 'Unknown' ) {
			$sender_name = 'Import';
		}

		// 生成唯一的 event_id
		$event_id = 'import_' . uniqid() . '_' . time();

		return array(
			'event_id'          => $event_id,
			'line_user_id'      => $line_user_id,
			'source_type'       => 'user',
			'sender_type'       => $sender_type,
			'sender_name'       => $sender_name,
			'group_id'          => null,
			'sent_date'         => $sent_date,
			'sent_time'         => $line_data['sent_time'],
			'message_type'      => 'text',
			'message_content'   => $line_data['message_content'],
			'reply_token'       => null,
			'quote_token'       => null,
			'quoted_message_id' => null,
			'line_message_id'   => null,
			'raw_payload'       => null,
			'created_by'        => get_current_user_id(),
			'created_at'        => current_time( 'mysql' ),
		);
	}

	/**
	 * 取得目標月份表名稱
	 *
	 * @param string $sent_date 訊息發送日期 (YYYY-MM-DD)
	 * @return string 月份表名稱
	 */
	private function get_target_month_table( string $sent_date ): string {
		$year_month = date( 'Y_m', strtotime( $sent_date ) );
		return $this->wpdb->prefix . 'otz_messages_' . $year_month;
	}

	/**
	 * 確保月份表存在
	 *
	 * @param string $year_month 年月格式 (YYYY_MM)
	 * @return bool 是否成功確保表存在
	 */
	private function ensure_month_table_exists( string $year_month ): bool {
		if ( $this->table_manager->monthly_message_table_exists( $year_month ) ) {
			return true;
		}

		return $this->table_manager->create_monthly_message_table( $year_month );
	}

	/**
	 * 檢查是否為重複訊息
	 *
	 * @param string $table_name 目標表名稱
	 * @param array  $message_data 訊息資料
	 * @return bool 是否為重複訊息
	 */
	private function is_duplicate_message( string $table_name, array $message_data ): bool {
		$sql = "SELECT COUNT(*) FROM {$table_name}
				WHERE sent_date = %s
				AND sent_time = %s
				AND sender_name = %s
				AND message_content = %s
				AND line_user_id = %s";

		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				$sql,
				$message_data['sent_date'],
				$message_data['sent_time'],
				$message_data['sender_name'],
				$message_data['message_content'],
				$message_data['line_user_id']
			)
		);

		return $count > 0;
	}

	/**
	 * 應用篩選條件
	 *
	 * @param array $records CSV 記錄陣列
	 * @param array $options 篩選選項
	 * @return array 篩選後的記錄陣列
	 */
	private function apply_filters( array $records, array $options ): array {
		$filtered_records = $records;

		// 日期篩選
		if ( isset( $options['start_date'] ) && ! empty( $options['start_date'] ) ) {
			$start_date       = str_replace( '-', '/', $options['start_date'] );
			$filtered_records = array_filter(
				$filtered_records,
				function( $record ) use ( $start_date ) {
					return strtotime( $record['sent_date'] ) >= strtotime( $start_date );
				}
			);
		}

		if ( isset( $options['end_date'] ) && ! empty( $options['end_date'] ) ) {
			$end_date         = str_replace( '-', '/', $options['end_date'] );
			$filtered_records = array_filter(
				$filtered_records,
				function( $record ) use ( $end_date ) {
					return strtotime( $record['sent_date'] ) <= strtotime( $end_date );
				}
			);
		}

		// 時間篩選
		if ( isset( $options['start_time'] ) && ! empty( $options['start_time'] ) ) {
			$start_time       = $options['start_time'];
			$filtered_records = array_filter(
				$filtered_records,
				function( $record ) use ( $start_time ) {
					return strtotime( $record['sent_time'] ) >= strtotime( $start_time );
				}
			);
		}

		if ( isset( $options['end_time'] ) && ! empty( $options['end_time'] ) ) {
			$end_time         = $options['end_time'];
			$filtered_records = array_filter(
				$filtered_records,
				function( $record ) use ( $end_time ) {
					return strtotime( $record['sent_time'] ) <= strtotime( $end_time );
				}
			);
		}

		// 傳送者類型篩選
		if ( isset( $options['sender_types'] ) && is_array( $options['sender_types'] ) && ! empty( $options['sender_types'] ) ) {
			$sender_types     = array_map( 'strtolower', $options['sender_types'] );
			$filtered_records = array_filter(
				$filtered_records,
				function( $record ) use ( $sender_types ) {
					return in_array( strtolower( $record['sender_type'] ), $sender_types, true );
				}
			);
		}

		// 重新索引陣列以保持連續的數字索引
		return array_values( $filtered_records );
	}

	/**
	 * 計算訊息統計資料
	 *
	 * @param array $records 記錄陣列
	 * @return array 統計資料
	 */
	private function calculate_statistics( array $records ): array {
		$statistics = array(
			'total_messages'   => count( $records ),
			'user_messages'    => 0,
			'account_messages' => 0,
			'date_range'       => array(),
		);

		$dates = array();

		foreach ( $records as $record ) {
			// 統計傳送者類型
			if ( strtolower( $record['sender_type'] ) === 'user' ) {
				$statistics['user_messages']++;
			} elseif ( strtolower( $record['sender_type'] ) === 'account' ) {
				$statistics['account_messages']++;
			}

			// 收集日期用於計算日期範圍
			$dates[] = strtotime( $record['sent_date'] );
		}

		// 計算日期範圍
		if ( ! empty( $dates ) ) {
			$min_date                 = min( $dates );
			$max_date                 = max( $dates );
			$statistics['date_range'] = array(
				'start' => date( 'Y-m-d', $min_date ),
				'end'   => date( 'Y-m-d', $max_date ),
			);
		}

		return $statistics;
	}
}

<?php

declare(strict_types=1);

namespace OrderChatz\Util;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * Time Utility Class
 *
 * 處理 WordPress 時區轉換和時間操作的工具類別
 *
 * @package    OrderChatz
 * @subpackage Util
 * @since      1.1.0
 */
class Time {

	/**
	 * 取得 WordPress 時區物件
	 *
	 * @return DateTimeZone WordPress 時區物件
	 */
	public static function get_wp_timezone(): DateTimeZone {
		return wp_timezone();
	}

	/**
	 * 將本地時間轉換為 UTC timestamp
	 *
	 * @param string $local_datetime 本地時間字串 (Y-m-d H:i:s)
	 * @return int|false UTC timestamp 或失敗時返回 false
	 */
	public static function convert_local_to_utc( string $local_datetime ) {
		try {
			$wp_timezone = self::get_wp_timezone();
			$local_date  = new DateTime( $local_datetime, $wp_timezone );

			return $local_date->getTimestamp();

		} catch ( Exception $e ) {
			wc_get_logger()->error(
				'Time conversion error: ' . $e->getMessage(),
				array( 'source' => 'otz-time' )
			);
			return false;
		}
	}

	/**
	 * 將 UTC timestamp 轉換為本地時間字串
	 *
	 * @param int    $utc_timestamp UTC timestamp
	 * @param string $format        輸出格式，預設為 'Y-m-d H:i:s'
	 * @return string|false 本地時間字串或失敗時返回 false
	 */
	public static function convert_utc_to_local( int $utc_timestamp, string $format = 'Y-m-d H:i:s' ) {
		try {
			$utc_date    = new DateTime( '@' . $utc_timestamp );
			$wp_timezone = self::get_wp_timezone();

			$utc_date->setTimezone( $wp_timezone );

			return $utc_date->format( $format );

		} catch ( Exception $e ) {
			wc_get_logger()->error(
				'Time conversion error: ' . $e->getMessage(),
				array( 'source' => 'otz-time' )
			);
			return false;
		}
	}

	/**
	 * 驗證排程時間格式
	 *
	 * @param string $datetime 時間字串
	 * @return bool 驗證結果
	 */
	public static function validate_schedule_time( string $datetime ): bool {
		try {
			$date = DateTime::createFromFormat( 'Y-m-d H:i', $datetime );

			if ( ! $date || $date->format( 'Y-m-d H:i' ) !== $datetime ) {
				return false;
			}

			// 檢查是否為未來時間.
			$now           = new DateTime( 'now', self::get_wp_timezone() );
			$schedule_date = new DateTime( $datetime, self::get_wp_timezone() );

			return $schedule_date > $now;

		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * 格式化顯示時間
	 *
	 * @param int $timestamp UTC timestamp
	 * @return string 格式化後的時間字串
	 */
	public static function format_display_time( int $timestamp ): string {
		$local_time = self::convert_utc_to_local( $timestamp );

		if ( $local_time === false ) {
			return '時間錯誤';
		}

		try {
			$wp_timezone = self::get_wp_timezone();
			$date_obj    = new DateTime( $local_time, $wp_timezone );
			$now         = new DateTime( 'now', $wp_timezone );

			$diff = $now->getTimestamp() - $date_obj->getTimestamp();

			// 過去時間
			if ( $diff > 0 ) {
				if ( $diff < 60 ) {
					return '剛剛';
				} elseif ( $diff < 3600 ) {
					return floor( $diff / 60 ) . ' 分鐘前';
				} elseif ( $diff < 86400 ) {
					return floor( $diff / 3600 ) . ' 小時前';
				} else {
					return $date_obj->format( 'm/d H:i' );
				}
			}

			// 未來時間
			$future_diff = abs( $diff );
			if ( $future_diff < 3600 ) {
				return floor( $future_diff / 60 ) . ' 分鐘後';
			} elseif ( $future_diff < 86400 ) {
				return floor( $future_diff / 3600 ) . ' 小時後';
			} else {
				return $date_obj->format( 'm/d H:i' );
			}
		} catch ( Exception $e ) {
			return $local_time;
		}
	}

	/**
	 * 取得當前本地時間
	 *
	 * @param string $format 輸出格式，預設為 'Y-m-d H:i:s'
	 * @return string 當前本地時間字串
	 */
	public static function get_current_local_time( string $format = 'Y-m-d H:i:s' ): string {
		return wp_date( $format );
	}

	/**
	 * 計算兩個時間之間的間隔秒數
	 *
	 * @param string $start_time 開始時間 (Y-m-d H:i:s)
	 * @param string $end_time   結束時間 (Y-m-d H:i:s)
	 * @return int|false 間隔秒數或失敗時返回 false
	 */
	public static function calculate_interval_seconds( string $start_time, string $end_time ) {
		try {
			$wp_timezone = self::get_wp_timezone();
			$start_date  = new DateTime( $start_time, $wp_timezone );
			$end_date    = new DateTime( $end_time, $wp_timezone );

			return $end_date->getTimestamp() - $start_date->getTimestamp();

		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * 將間隔字串轉換為秒數
	 *
	 * @param string $interval 間隔字串 ('daily', 'weekly', 'monthly')
	 * @return int|false 秒數或失敗時返回 false
	 */
	public static function interval_to_seconds( string $interval ) {
		$intervals = array(
			'daily'   => DAY_IN_SECONDS,
			'weekly'  => WEEK_IN_SECONDS,
			'monthly' => MONTH_IN_SECONDS,
		);

		return $intervals[ $interval ] ?? false;
	}

	/**
	 * 檢查時間是否在有效範圍內（未來一年內）
	 *
	 * @param string $datetime 時間字串 (Y-m-d H:i:s)
	 * @return bool 檢查結果
	 */
	public static function is_valid_schedule_range( string $datetime ): bool {
		try {
			$wp_timezone    = self::get_wp_timezone();
			$schedule_date  = new DateTime( $datetime, $wp_timezone );
			$now            = new DateTime( 'now', $wp_timezone );
			$one_year_later = new DateTime( '+1 year', $wp_timezone );

			return $schedule_date > $now && $schedule_date <= $one_year_later;

		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * 解析排程 JSON 中的時間資料
	 *
	 * @param array $schedule_data 排程資料陣列
	 * @return int|false 計算出的 UTC timestamp 或失敗時返回 false
	 */
	public static function parse_schedule_timestamp( array $schedule_data ) {
		if ( ! isset( $schedule_data['sent_date'] ) || ! isset( $schedule_data['sent_time'] ) ) {
			return false;
		}

		$datetime_string = $schedule_data['sent_date'] . ' ' . $schedule_data['sent_time'];

		if ( ! self::validate_schedule_time( $datetime_string ) ) {
			return false;
		}

		return self::convert_local_to_utc( $datetime_string );
	}

	/**
	 * 計算重複排程的首次執行時間
	 *
	 * @param array $schedule_data 排程資料陣列
	 * @return int|false 計算出的 UTC timestamp 或失敗時返回 false
	 */
	public static function calculate_recurring_first_run( array $schedule_data ) {
		// 驗證必要欄位.
		if ( ! isset( $schedule_data['sent_time'] ) || ! isset( $schedule_data['interval'] ) ) {
			return false;
		}

		$sent_time = $schedule_data['sent_time'];
		$interval  = $schedule_data['interval'];

		// 驗證時間格式 (HH:MM).
		if ( ! preg_match( '/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $sent_time ) ) {
			return false;
		}

		try {
			$wp_timezone = self::get_wp_timezone();
			$now         = new DateTime( 'now', $wp_timezone );

			switch ( $interval ) {
				case 'daily':
					// 每日：今天指定時間，如果已過則明天.
					$target_time = clone $now;
					list( $hour, $minute ) = explode( ':', $sent_time );
					$target_time->setTime( intval( $hour ), intval( $minute ), 0 );

					// 如果今天的時間已經過了，設定為明天.
					if ( $target_time <= $now ) {
						$target_time->modify( '+1 day' );
					}
					break;

				case 'weekly':
					// 每週：下一個指定星期幾的時間.
					if ( ! isset( $schedule_data['weekday'] ) || ! is_numeric( $schedule_data['weekday'] ) ) {
						return false;
					}

					$weekday = intval( $schedule_data['weekday'] );
					if ( $weekday < 0 || $weekday > 6 ) {
						return false;
					}

					list( $hour, $minute ) = explode( ':', $sent_time );
					$target_time = clone $now;
					$target_time->setTime( intval( $hour ), intval( $minute ), 0 );

					// 計算到下一個指定星期幾的天數.
					$current_weekday = intval( $target_time->format( 'w' ) );
					$days_to_add     = ( $weekday - $current_weekday + 7 ) % 7;

					// 如果是今天但時間已過，則加7天到下週.
					if ( $days_to_add === 0 && $target_time <= $now ) {
						$days_to_add = 7;
					}

					if ( $days_to_add > 0 ) {
						$target_time->modify( "+{$days_to_add} days" );
					}
					break;

				case 'monthly':
					// 每月：下個月指定日期的時間.
					if ( ! isset( $schedule_data['day_of_month'] ) || ! is_numeric( $schedule_data['day_of_month'] ) ) {
						return false;
					}

					$day_of_month = intval( $schedule_data['day_of_month'] );
					if ( $day_of_month < 1 || $day_of_month > 31 ) {
						return false;
					}

					list( $hour, $minute ) = explode( ':', $sent_time );
					$target_time = clone $now;
					$target_time->setTime( intval( $hour ), intval( $minute ), 0 );
					$target_time->setDate( intval( $target_time->format( 'Y' ) ), intval( $target_time->format( 'n' ) ), $day_of_month );

					// 如果這個月的指定日期已經過了，或者日期無效，設定為下個月.
					if ( $target_time <= $now || intval( $target_time->format( 'j' ) ) !== $day_of_month ) {
						$target_time->modify( 'first day of next month' );
						$target_time->setDate( intval( $target_time->format( 'Y' ) ), intval( $target_time->format( 'n' ) ), $day_of_month );

						// 如果下個月也沒有這個日期（如2月30日），再往後推一個月.
						if ( intval( $target_time->format( 'j' ) ) !== $day_of_month ) {
							$target_time->modify( 'first day of next month' );
							$target_time->setDate( intval( $target_time->format( 'Y' ) ), intval( $target_time->format( 'n' ) ), min( $day_of_month, intval( $target_time->format( 't' ) ) ) );
						}
					}
					break;

				default:
					return false;
			}

			return $target_time->getTimestamp();

		} catch ( Exception $e ) {
			wc_get_logger()->error(
				'Recurring schedule calculation error: ' . $e->getMessage(),
				array( 'source' => 'otz-time' )
			);
			return false;
		}
	}

	/**
	 * 驗證重複排程資料
	 *
	 * @param array $schedule_data 排程資料陣列
	 * @return bool 驗證結果
	 */
	public static function validate_recurring_schedule( array $schedule_data ): bool {
		// 檢查基本欄位.
		if ( ! isset( $schedule_data['sent_time'] ) || ! isset( $schedule_data['interval'] ) ) {
			return false;
		}

		$sent_time = $schedule_data['sent_time'];
		$interval  = $schedule_data['interval'];

		// 驗證時間格式.
		if ( ! preg_match( '/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $sent_time ) ) {
			return false;
		}

		// 驗證間隔類型.
		$valid_intervals = array( 'daily', 'weekly', 'monthly' );
		if ( ! in_array( $interval, $valid_intervals, true ) ) {
			return false;
		}

		// 根據間隔類型驗證額外欄位.
		switch ( $interval ) {
			case 'weekly':
				if ( ! isset( $schedule_data['weekday'] ) || ! is_numeric( $schedule_data['weekday'] ) ) {
					return false;
				}
				$weekday = intval( $schedule_data['weekday'] );
				if ( $weekday < 0 || $weekday > 6 ) {
					return false;
				}
				break;

			case 'monthly':
				if ( ! isset( $schedule_data['day_of_month'] ) || ! is_numeric( $schedule_data['day_of_month'] ) ) {
					return false;
				}
				$day_of_month = intval( $schedule_data['day_of_month'] );
				if ( $day_of_month < 1 || $day_of_month > 31 ) {
					return false;
				}
				break;
		}

		return true;
	}

}

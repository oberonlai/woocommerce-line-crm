<?php

declare(strict_types=1);

namespace OrderChatz\Database;

use OrderChatz\Util\Logger;

/**
 * Tag Database Class
 *
 * 處理標籤的 CRUD 操作，負責同步 wp_otz_user_tags 和 wp_otz_users.tags 兩個表的資料.
 *
 * @package    OrderChatz
 * @subpackage Database
 * @since      1.1.4
 */
class Tag {

	/**
	 * WordPress database abstraction layer
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * User tags table name
	 *
	 * @var string
	 */
	private string $user_tags_table;

	/**
	 * Users table name
	 *
	 * @var string
	 */
	private string $users_table;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb            = $wpdb;
		$this->user_tags_table = $wpdb->prefix . 'otz_user_tags';
		$this->users_table     = $wpdb->prefix . 'otz_users';
	}

	/**
	 * 新增標籤到使用者
	 *
	 * 同時更新：
	 * 1. wp_otz_user_tags.line_user_ids JSON 陣列
	 * 2. wp_otz_users.tags JSON 陣列
	 *
	 * @param string $line_user_id LINE User ID.
	 * @param string $tag_name     標籤名稱.
	 * @return bool True on success, false on failure.
	 */
	public function add_tag_to_user( string $line_user_id, string $tag_name ): bool {
		try {
			$this->wpdb->query( 'START TRANSACTION' );

			// ========== 步驟一：更新 wp_otz_user_tags ==========

			// 檢查標籤是否存在.
			$tag = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT id, line_user_ids FROM `{$this->user_tags_table}` WHERE tag_name = %s",
					$tag_name
				)
			);

			if ( $tag ) {
				// 標籤存在，更新 line_user_ids.
				$user_ids = json_decode( $tag->line_user_ids ?? '[]', true );
				if ( ! is_array( $user_ids ) ) {
					$user_ids = array();
				}

				// 新增使用者 ID（去重：每個 ID 只出現一次）.
				if ( ! in_array( $line_user_id, $user_ids, true ) ) {
					$user_ids[] = $line_user_id;

					$result = $this->wpdb->update(
						$this->user_tags_table,
						array( 'line_user_ids' => wp_json_encode( $user_ids, JSON_UNESCAPED_UNICODE ) ),
						array( 'id' => $tag->id ),
						array( '%s' ),
						array( '%d' )
					);

					if ( false === $result ) {
						throw new \Exception( '更新標籤失敗: ' . $this->wpdb->last_error );
					}
				}
			} else {
				// 標籤不存在，建立新標籤.
				$result = $this->wpdb->insert(
					$this->user_tags_table,
					array(
						'tag_name'      => $tag_name,
						'line_user_ids' => wp_json_encode( array( $line_user_id ), JSON_UNESCAPED_UNICODE ),
						'created_at'    => current_time( 'mysql' ),
					),
					array( '%s', '%s', '%s' )
				);

				if ( ! $result ) {
					throw new \Exception( '建立標籤失敗: ' . $this->wpdb->last_error );
				}
			}

			// ========== 步驟二：更新 wp_otz_users.tags ==========

			$user = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT tags FROM `{$this->users_table}` WHERE line_user_id = %s",
					$line_user_id
				)
			);

			if ( ! $user ) {
				throw new \Exception( '找不到使用者' );
			}

			// 解析現有標籤.
			$tags = json_decode( $user->tags ?? '[]', true );
			if ( ! is_array( $tags ) ) {
				$tags = array();
			}

			// 新增標籤記錄.
			$tags[] = array(
				'tag_name'  => $tag_name,
				'tagged_at' => current_time( 'mysql' ),
			);

			$result = $this->wpdb->update(
				$this->users_table,
				array( 'tags' => wp_json_encode( $tags, JSON_UNESCAPED_UNICODE ) ),
				array( 'line_user_id' => $line_user_id ),
				array( '%s' ),
				array( '%s' )
			);

			if ( false === $result ) {
				throw new \Exception( '更新使用者標籤失敗: ' . $this->wpdb->last_error );
			}

			$this->wpdb->query( 'COMMIT' );
			return true;

		} catch ( \Exception $e ) {
			$this->wpdb->query( 'ROLLBACK' );
			Logger::error( '新增標籤失敗: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * 移除使用者的標籤（移除最新的一筆）
	 *
	 * @param string $line_user_id LINE User ID.
	 * @param string $tag_name     標籤名稱.
	 * @return bool True on success, false on failure.
	 */
	public function remove_tag_from_user( string $line_user_id, string $tag_name ): bool {
		try {
			$this->wpdb->query( 'START TRANSACTION' );

			// ========== 步驟一：從 wp_otz_users.tags 移除最後一筆標籤 ==========

			$user = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT tags FROM `{$this->users_table}` WHERE line_user_id = %s",
					$line_user_id
				)
			);

			if ( ! $user ) {
				throw new \Exception( '找不到使用者' );
			}

			// 解析現有標籤.
			$tags = json_decode( $user->tags ?? '[]', true );
			if ( ! is_array( $tags ) ) {
				$tags = array();
			}

			// 從後往前找到最後一個符合的標籤並移除.
			$tag_found = false;
			for ( $i = count( $tags ) - 1; $i >= 0; $i-- ) {
				if ( isset( $tags[ $i ]['tag_name'] ) && $tags[ $i ]['tag_name'] === $tag_name ) {
					unset( $tags[ $i ] );
					$tag_found = true;
					break;
				}
			}

			if ( ! $tag_found ) {
				throw new \Exception( '使用者沒有此標籤' );
			}

			$tags = array_values( $tags ); // 重新索引.

			// 更新使用者的 tags.
			$result = $this->wpdb->update(
				$this->users_table,
				array( 'tags' => wp_json_encode( $tags, JSON_UNESCAPED_UNICODE ) ),
				array( 'line_user_id' => $line_user_id ),
				array( '%s' ),
				array( '%s' )
			);

			if ( false === $result ) {
				throw new \Exception( '更新使用者標籤失敗: ' . $this->wpdb->last_error );
			}

			// ========== 步驟二：檢查使用者是否還有該標籤 ==========

			// 檢查更新後的 tags 中是否還有該標籤.
			$has_tag = false;
			foreach ( $tags as $tag ) {
				if ( isset( $tag['tag_name'] ) && $tag['tag_name'] === $tag_name ) {
					$has_tag = true;
					break;
				}
			}

			// ========== 步驟三：如果使用者已經沒有該標籤了，從 wp_otz_user_tags 移除 ==========

			if ( ! $has_tag ) {
				$tag_record = $this->wpdb->get_row(
					$this->wpdb->prepare(
						"SELECT id, line_user_ids FROM `{$this->user_tags_table}` WHERE tag_name = %s",
						$tag_name
					)
				);

				if ( $tag_record ) {
					// 從 line_user_ids 中移除該使用者 ID.
					$user_ids = json_decode( $tag_record->line_user_ids ?? '[]', true );
					if ( ! is_array( $user_ids ) ) {
						$user_ids = array();
					}

					// 移除該使用者 ID.
					$key = array_search( $line_user_id, $user_ids, true );
					if ( false !== $key ) {
						unset( $user_ids[ $key ] );
						$user_ids = array_values( $user_ids ); // 重新索引.
					}

					// 更新 line_user_ids (即使為空陣列也保留標籤記錄).
					$result = $this->wpdb->update(
						$this->user_tags_table,
						array( 'line_user_ids' => wp_json_encode( $user_ids, JSON_UNESCAPED_UNICODE ) ),
						array( 'id' => $tag_record->id ),
						array( '%s' ),
						array( '%d' )
					);

					if ( false === $result ) {
						throw new \Exception( '更新標籤失敗: ' . $this->wpdb->last_error );
					}
				}
			}

			$this->wpdb->query( 'COMMIT' );
			return true;

		} catch ( \Exception $e ) {
			$this->wpdb->query( 'ROLLBACK' );
			Logger::error( '移除標籤失敗: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * 取得某個標籤的所有使用者
	 *
	 * @param string $tag_name 標籤名稱.
	 * @return array LINE User ID 陣列（可能包含重複）.
	 */
	public function get_users_by_tag( string $tag_name ): array {
		$tag = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT line_user_ids FROM `{$this->user_tags_table}` WHERE tag_name = %s",
				$tag_name
			)
		);

		if ( ! $tag || empty( $tag->line_user_ids ) ) {
			return array();
		}

		$user_ids = json_decode( $tag->line_user_ids ?? '[]', true );
		return is_array( $user_ids ) ? $user_ids : array();
	}

	/**
	 * 取得某個標籤的唯一使用者列表
	 *
	 * @param string $tag_name 標籤名稱.
	 * @return array 唯一的 LINE User ID 陣列.
	 */
	public function get_unique_users_by_tag( string $tag_name ): array {
		$all_users = $this->get_users_by_tag( $tag_name );
		return array_values( array_unique( $all_users ) );
	}

	/**
	 * 統計某個標籤被貼了幾次
	 *
	 * @param string $tag_name 標籤名稱.
	 * @return int 次數.
	 */
	public function count_tag_usage( string $tag_name ): int {
		$users = $this->get_users_by_tag( $tag_name );
		return count( $users );
	}

	/**
	 * 取得某個使用者的所有標籤
	 *
	 * @param string $line_user_id LINE User ID.
	 * @return array 標籤陣列 [{"tag_name": "VIP", "tagged_at": "..."}].
	 */
	public function get_user_tags( string $line_user_id ): array {
		$user = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT tags FROM `{$this->users_table}` WHERE line_user_id = %s",
				$line_user_id
			)
		);

		if ( ! $user || empty( $user->tags ) ) {
			return array();
		}

		$tags = json_decode( $user->tags ?? '[]', true );
		return is_array( $tags ) ? $tags : array();
	}

	/**
	 * 取得某個使用者的唯一標籤列表（不含重複）
	 *
	 * @param string $line_user_id LINE User ID.
	 * @return array 唯一標籤名稱陣列.
	 */
	public function get_unique_user_tags( string $line_user_id ): array {
		$tags      = $this->get_user_tags( $line_user_id );
		$tag_names = array();

		foreach ( $tags as $tag ) {
			if ( isset( $tag['tag_name'] ) ) {
				$tag_names[] = $tag['tag_name'];
			}
		}

		return array_values( array_unique( $tag_names ) );
	}

	/**
	 * 統計某個使用者某個標籤被貼了幾次
	 *
	 * @param string $line_user_id LINE User ID.
	 * @param string $tag_name     標籤名稱.
	 * @return int 次數.
	 */
	public function count_user_tag( string $line_user_id, string $tag_name ): int {
		$tags  = $this->get_user_tags( $line_user_id );
		$count = 0;

		foreach ( $tags as $tag ) {
			if ( isset( $tag['tag_name'] ) && $tag['tag_name'] === $tag_name ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * 取得所有標籤列表
	 *
	 * @return array 標籤陣列 [{"tag_name": "VIP", "user_count": 5, "total_usage": 10, "created_at": "..."}].
	 */
	public function get_all_tags(): array {
		$results = $this->wpdb->get_results(
			"SELECT tag_name, line_user_ids, created_at FROM `{$this->user_tags_table}` ORDER BY tag_name ASC"
		);

		$tags = array();
		foreach ( $results as $row ) {
			$user_ids = json_decode( $row->line_user_ids ?? '[]', true );
			if ( ! is_array( $user_ids ) ) {
				$user_ids = array();
			}

			$tags[] = array(
				'tag_name'    => $row->tag_name,
				'user_count'  => count( array_unique( $user_ids ) ), // 唯一使用者數.
				'total_usage' => count( $user_ids ),                 // 總使用次數.
				'created_at'  => $row->created_at,
			);
		}

		return $tags;
	}

	/**
	 * 取得所有標籤名稱列表(用於下拉選單)
	 *
	 * @return array 標籤名稱陣列 ['VIP', 'tag1', 'tag2', ...].
	 */
	public function get_all_tags_for_select2(): array {
		$all_tags  = $this->get_all_tags();
		$tag_names = array();

		foreach ( $all_tags as $tag ) {
			$tag_names[ $tag['tag_name'] ] = $tag['tag_name'];
		}

		return $tag_names;
	}

	/**
	 * 刪除標籤
	 *
	 * 注意：這會從所有使用者中移除該標籤
	 *
	 * @param string $tag_name 標籤名稱.
	 * @return bool True on success, false on failure.
	 */
	public function delete_tag( string $tag_name ): bool {
		try {
			$this->wpdb->query( 'START TRANSACTION' );

			// ========== 步驟一：取得所有使用此標籤的使用者 ==========

			$user_ids = $this->get_users_by_tag( $tag_name );

			// ========== 步驟二：從 wp_otz_user_tags 刪除標籤 ==========

			$result = $this->wpdb->delete(
				$this->user_tags_table,
				array( 'tag_name' => $tag_name ),
				array( '%s' )
			);

			if ( false === $result ) {
				throw new \Exception( '刪除標籤失敗: ' . $this->wpdb->last_error );
			}

			// ========== 步驟三：從所有使用者的 tags JSON 中移除該標籤 ==========

			$unique_user_ids = array_unique( $user_ids );

			foreach ( $unique_user_ids as $line_user_id ) {
				$user = $this->wpdb->get_row(
					$this->wpdb->prepare(
						"SELECT tags FROM `{$this->users_table}` WHERE line_user_id = %s",
						$line_user_id
					)
				);

				if ( ! $user ) {
					continue;
				}

				$tags = json_decode( $user->tags ?? '[]', true );
				if ( ! is_array( $tags ) ) {
					continue;
				}

				// 移除所有該標籤的記錄.
				$tags = array_filter(
					$tags,
					function( $tag ) use ( $tag_name ) {
						return ! isset( $tag['tag_name'] ) || $tag['tag_name'] !== $tag_name;
					}
				);

				$tags = array_values( $tags ); // 重新索引.

				$this->wpdb->update(
					$this->users_table,
					array( 'tags' => wp_json_encode( $tags, JSON_UNESCAPED_UNICODE ) ),
					array( 'line_user_id' => $line_user_id ),
					array( '%s' ),
					array( '%s' )
				);
			}

			$this->wpdb->query( 'COMMIT' );
			return true;

		} catch ( \Exception $e ) {
			$this->wpdb->query( 'ROLLBACK' );
			Logger::error( '刪除標籤失敗: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * 根據時間刪除特定的標籤記錄
	 *
	 * 只刪除指定時間戳記的標籤記錄,而非刪除最新的一筆.
	 *
	 * @param string $line_user_id LINE User ID.
	 * @param string $tag_name     標籤名稱.
	 * @param string $tagged_at    貼標籤的時間 (格式: Y-m-d H:i:s).
	 * @return bool True on success, false on failure.
	 */
	public function remove_tag_by_time( string $line_user_id, string $tag_name, string $tagged_at ): bool {
		try {
			$this->wpdb->query( 'START TRANSACTION' );

			// ========== 步驟一:從 wp_otz_users.tags 移除指定時間的標籤 ==========

			$user = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT tags FROM `{$this->users_table}` WHERE line_user_id = %s",
					$line_user_id
				)
			);

			if ( ! $user ) {
				throw new \Exception( '找不到使用者' );
			}

			// 解析現有標籤.
			$tags = json_decode( $user->tags ?? '[]', true );
			if ( ! is_array( $tags ) ) {
				$tags = array();
			}

			// 找到並移除指定時間的標籤記錄.
			$tag_found = false;
			foreach ( $tags as $key => $tag ) {
				if ( isset( $tag['tag_name'] ) && $tag['tag_name'] === $tag_name &&
					isset( $tag['tagged_at'] ) && $tag['tagged_at'] === $tagged_at ) {
					unset( $tags[ $key ] );
					$tag_found = true;
					break;
				}
			}

			if ( ! $tag_found ) {
				throw new \Exception( '找不到指定的標籤記錄' );
			}

			$tags = array_values( $tags ); // 重新索引.

			// 更新使用者的 tags.
			$result = $this->wpdb->update(
				$this->users_table,
				array( 'tags' => wp_json_encode( $tags, JSON_UNESCAPED_UNICODE ) ),
				array( 'line_user_id' => $line_user_id ),
				array( '%s' ),
				array( '%s' )
			);

			if ( false === $result ) {
				throw new \Exception( '更新使用者標籤失敗: ' . $this->wpdb->last_error );
			}

			// ========== 步驟二:檢查使用者是否還有該標籤 ==========

			// 檢查更新後的 tags 中是否還有該標籤.
			$has_tag = false;
			foreach ( $tags as $tag ) {
				if ( isset( $tag['tag_name'] ) && $tag['tag_name'] === $tag_name ) {
					$has_tag = true;
					break;
				}
			}

			// ========== 步驟三:如果使用者已經沒有該標籤了,從 wp_otz_user_tags 移除 ==========

			if ( ! $has_tag ) {
				$tag_record = $this->wpdb->get_row(
					$this->wpdb->prepare(
						"SELECT id, line_user_ids FROM `{$this->user_tags_table}` WHERE tag_name = %s",
						$tag_name
					)
				);

				if ( $tag_record ) {
					// 從 line_user_ids 中移除該使用者 ID.
					$user_ids = json_decode( $tag_record->line_user_ids ?? '[]', true );
					if ( ! is_array( $user_ids ) ) {
						$user_ids = array();
					}

					// 移除該使用者 ID.
					$key = array_search( $line_user_id, $user_ids, true );
					if ( false !== $key ) {
						unset( $user_ids[ $key ] );
						$user_ids = array_values( $user_ids ); // 重新索引.
					}

					// 更新 line_user_ids (即使為空陣列也保留標籤記錄).
					$result = $this->wpdb->update(
						$this->user_tags_table,
						array( 'line_user_ids' => wp_json_encode( $user_ids, JSON_UNESCAPED_UNICODE ) ),
						array( 'id' => $tag_record->id ),
						array( '%s' ),
						array( '%d' )
					);

					if ( false === $result ) {
						throw new \Exception( '更新標籤失敗: ' . $this->wpdb->last_error );
					}
				}
			}

			$this->wpdb->query( 'COMMIT' );
			return true;

		} catch ( \Exception $e ) {
			$this->wpdb->query( 'ROLLBACK' );
			Logger::error( '根據時間刪除標籤失敗: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * 重新命名標籤
	 *
	 * @param string $old_tag_name 舊標籤名稱.
	 * @param string $new_tag_name 新標籤名稱.
	 * @return bool True on success, false on failure.
	 */
	public function rename_tag( string $old_tag_name, string $new_tag_name ): bool {
		try {
			$this->wpdb->query( 'START TRANSACTION' );

			// ========== 步驟一：檢查新標籤名稱是否已存在 ==========

			$existing = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(*) FROM `{$this->user_tags_table}` WHERE tag_name = %s",
					$new_tag_name
				)
			);

			if ( $existing > 0 ) {
				throw new \Exception( '新標籤名稱已存在' );
			}

			// ========== 步驟二：取得所有使用此標籤的使用者 ==========

			$user_ids = $this->get_users_by_tag( $old_tag_name );

			// ========== 步驟三：更新 wp_otz_user_tags ==========

			$result = $this->wpdb->update(
				$this->user_tags_table,
				array( 'tag_name' => $new_tag_name ),
				array( 'tag_name' => $old_tag_name ),
				array( '%s' ),
				array( '%s' )
			);

			if ( false === $result ) {
				throw new \Exception( '更新標籤名稱失敗: ' . $this->wpdb->last_error );
			}

			// ========== 步驟四：更新所有使用者的 tags JSON ==========

			$unique_user_ids = array_unique( $user_ids );

			foreach ( $unique_user_ids as $line_user_id ) {
				$user = $this->wpdb->get_row(
					$this->wpdb->prepare(
						"SELECT tags FROM `{$this->users_table}` WHERE line_user_id = %s",
						$line_user_id
					)
				);

				if ( ! $user ) {
					continue;
				}

				$tags = json_decode( $user->tags ?? '[]', true );
				if ( ! is_array( $tags ) ) {
					continue;
				}

				// 更新所有該標籤的名稱.
				foreach ( $tags as &$tag ) {
					if ( isset( $tag['tag_name'] ) && $tag['tag_name'] === $old_tag_name ) {
						$tag['tag_name'] = $new_tag_name;
					}
				}

				$this->wpdb->update(
					$this->users_table,
					array( 'tags' => wp_json_encode( $tags, JSON_UNESCAPED_UNICODE ) ),
					array( 'line_user_id' => $line_user_id ),
					array( '%s' ),
					array( '%s' )
				);
			}

			$this->wpdb->query( 'COMMIT' );
			return true;

		} catch ( \Exception $e ) {
			$this->wpdb->query( 'ROLLBACK' );
			Logger::error( '重新命名標籤失敗: ' . $e->getMessage() );
			return false;
		}
	}
}

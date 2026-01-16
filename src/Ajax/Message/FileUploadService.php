<?php
/**
 * 檔案上傳服務
 *
 * 處理所有檔案上傳相關的業務邏輯
 *
 * @package OrderChatz\Ajax\Message
 * @since 1.0.0
 */

namespace OrderChatz\Ajax\Message;

use Exception;

class FileUploadService {

	/**
	 * 安全性驗證文件
	 *
	 * @param array  $file 檔案資料.
	 * @param string $file_type 檔案類型.
	 * @throws Exception 當檔案不符合安全要求時拋出例外.
	 * @return void
	 */
	public function validateFileSecurely( $file, $file_type ) {
		// 檢查文件名稱，防止惡意文件名.
		$file_name            = $file['name'];
		$dangerous_extensions = array( '.exe', '.bat', '.cmd', '.com', '.pif', '.scr', '.vbs', '.js', '.jar', '.php' );
		$file_extension       = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

		if ( in_array( '.' . $file_extension, $dangerous_extensions ) ) {
			throw new Exception( '禁止上傳可執行文件或腳本文件' );
		}

		// 檢查文件名長度.
		if ( strlen( $file_name ) > 255 ) {
			throw new Exception( '文件名稱過長，請縮短文件名稱' );
		}

		// 檢查特殊字符.
		if ( preg_match( '/[<>:"|?*\x00-\x1f]/', $file_name ) ) {
			throw new Exception( '文件名稱包含不允許的字符' );
		}

		// 檢查文件大小限制.
		$max_size = $this->getMaxFileSize( $file_type );
		if ( $file['size'] > $max_size ) {
			$size_mb = round( $max_size / 1024 / 1024 );
			throw new Exception( "文件大小超過限制 ({$size_mb}MB)" );
		}

		// 檢查空文件.
		if ( $file['size'] === 0 ) {
			throw new Exception( '不能上傳空文件' );
		}

		// 檢查文件類型.
		if ( $file_type === 'compressed' ) {
			$allowed_types      = array( 'application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed', 'application/vnd.rar', 'application/x-rar' );
			$allowed_extensions = array( 'zip', 'rar' );
		} elseif ( $file_type === 'video' ) {
			$allowed_types      = array( 'video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/ogg' );
			$allowed_extensions = array( 'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'ogv', 'm4v' );
		} elseif ( $file_type === 'image' ) {
			$allowed_types      = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
			$allowed_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );
		} else {
			throw new Exception( '不支援的文件類型' );
		}

		$has_valid_mime      = in_array( $file['type'], $allowed_types );
		$has_valid_extension = in_array( $file_extension, $allowed_extensions );

		if ( ! $has_valid_mime && ! $has_valid_extension ) {
			throw new Exception( '不支援的文件格式' );
		}
	}

	/**
	 * 獲取文件大小限制
	 *
	 * @param string $file_type 檔案類型.
	 * @return int 檔案大小限制（位元組）.
	 */
	public function getMaxFileSize( $file_type ) {
		switch ( $file_type ) {
			case 'image':
				return 5 * 1024 * 1024; // 5MB
			case 'video':
				return 50 * 1024 * 1024; // 50MB
			case 'compressed':
			default:
				return 20 * 1024 * 1024; // 20MB
		}
	}

	/**
	 * 上傳圖片檔案
	 *
	 * @param array $file 檔案資料.
	 * @return array 上傳結果.
	 * @throws Exception 當上傳失敗時拋出例外.
	 */
	public function uploadImage( $file ) {
		$this->validateFileSecurely( $file, 'image' );

		$upload_dir = wp_upload_dir();
		$chat_dir   = $upload_dir['basedir'] . '/order-chatz/images/';
		$chat_url   = $upload_dir['baseurl'] . '/order-chatz/images/';

		if ( ! file_exists( $chat_dir ) ) {
			wp_mkdir_p( $chat_dir );
		}

		$extension = pathinfo( $file['name'], PATHINFO_EXTENSION );
		$filename  = 'chat_' . time() . '_' . wp_generate_password( 8, false ) . '.' . $extension;
		$file_path = $chat_dir . $filename;

		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
			throw new Exception( '圖片保存失敗' );
		}

		$image_url = set_url_scheme( $chat_url . $filename, 'https' );

		return array(
			'image_url' => $image_url,
			'filename'  => $filename,
			'message'   => '圖片上傳成功',
		);
	}

	/**
	 * 上傳壓縮檔案
	 *
	 * @param array $file 檔案資料.
	 * @return array 上傳結果.
	 * @throws Exception 當上傳失敗時拋出例外.
	 */
	public function uploadCompressedFile( $file ) {
		$this->validateFileSecurely( $file, 'compressed' );

		$upload_dir = wp_upload_dir();
		$chat_dir   = $upload_dir['basedir'] . '/order-chatz/files/';
		$chat_url   = $upload_dir['baseurl'] . '/order-chatz/files/';

		if ( ! file_exists( $chat_dir ) ) {
			wp_mkdir_p( $chat_dir );
		}

		$filename  = sanitize_file_name( $file['name'] );
		$file_path = $chat_dir . $filename;

		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
			throw new Exception( '壓縮檔保存失敗' );
		}

		$file_url = set_url_scheme( $chat_url . $filename, 'https' );

		return array(
			'file_url'  => $file_url,
			'file_name' => $file['name'],
			'filename'  => $filename,
			'message'   => '壓縮檔上傳成功',
		);
	}

	/**
	 * 上傳影片檔案
	 *
	 * @param array $file 檔案資料.
	 * @return array 上傳結果.
	 * @throws Exception 當上傳失敗時拋出例外.
	 */
	public function uploadVideoFile( $file ) {
		$this->validateFileSecurely( $file, 'video' );

		$upload_dir = wp_upload_dir();
		$chat_dir   = $upload_dir['basedir'] . '/order-chatz/videos/';
		$chat_url   = $upload_dir['baseurl'] . '/order-chatz/videos/';

		if ( ! file_exists( $chat_dir ) ) {
			wp_mkdir_p( $chat_dir );
		}

		$filename  = sanitize_file_name( $file['name'] );
		$file_path = $chat_dir . $filename;

		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
			throw new Exception( '影片檔保存失敗' );
		}

		$file_url = set_url_scheme( $chat_url . $filename, 'https' );

		return array(
			'file_url'  => $file_url,
			'file_name' => $file['name'],
			'filename'  => $filename,
			'message'   => '影片檔上傳成功',
		);
	}

	/**
	 * 取得文件大小
	 *
	 * @param string $file_url 檔案 URL.
	 * @return int 檔案大小（位元組）.
	 */
	public function getFileSize( $file_url ) {
		// 如果是本地文件，直接使用 filesize.
		if ( strpos( $file_url, get_site_url() ) === 0 ) {
			$file_path = str_replace( get_site_url(), ABSPATH, $file_url );
			if ( file_exists( $file_path ) ) {
				return filesize( $file_path );
			}
		}

		// 遠端文件使用 HEAD 請求取得大小.
		$response = wp_remote_head( $file_url );
		if ( ! is_wp_error( $response ) ) {
			$headers = wp_remote_retrieve_headers( $response );
			if ( isset( $headers['content-length'] ) ) {
				return intval( $headers['content-length'] );
			}
		}

		return 0; // 無法取得文件大小.
	}

	/**
	 * 為影片生成預覽圖片 URL
	 *
	 * @param string $video_url 影片 URL.
	 * @return string 預覽圖片 URL.
	 */
	public function generateVideoPreviewUrl( $video_url ) {
		// 簡化實作：使用一個預設的影片預覽圖.
		// 在實際產品中，你可能需要：
		// 1. 從影片第一幀擷取圖片.
		// 2. 生成縮圖.
		// 3. 上傳到 CDN.

		// 暫時返回一個預設的影片圖示 URL.
		$upload_dir  = wp_upload_dir();
		$preview_url = $upload_dir['baseurl'] . '/order-chatz/video-preview.jpg';

		// 如果預設圖不存在，建立一個簡單的佔位符.
		$preview_path = $upload_dir['basedir'] . '/order-chatz/video-preview.jpg';
		if ( ! file_exists( $preview_path ) ) {
			// 使用 WordPress 預設媒體圖示或建立簡單的佔位符.
			$preview_url = admin_url( 'images/media-button-video.gif' );
		}

		return $preview_url;
	}
}

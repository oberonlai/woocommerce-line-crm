<?php
/**
 * Class SampleTest
 *
 * @package OrderChatz
 */

/**
 * Sample test case.
 */
class SampleTest extends WP_UnitTestCase {

	/**
	 * 測試 WordPress 環境是否正常運作
	 */
	public function test_wordpress_is_loaded() {
		$this->assertTrue( function_exists( 'do_action' ) );
		$this->assertTrue( function_exists( 'add_filter' ) );
	}

	/**
	 * 測試外掛是否已載入
	 */
	public function test_plugin_is_loaded() {
		// 檢查外掛主檔案是否存在
		$plugin_file = dirname( dirname( __FILE__ ) ) . '/order-chatz.php';
		$this->assertFileExists( $plugin_file );
	}

	/**
	 * 測試基本的 WordPress 功能
	 */
	public function test_create_post() {
		// 建立測試文章
		$post_id = $this->factory->post->create([
			'post_title'   => 'Test Post',
			'post_content' => 'Test content',
			'post_status'  => 'publish',
		]);

		// 驗證文章已建立
		$this->assertNotEmpty( $post_id );
		$this->assertIsInt( $post_id );

		// 取得文章並驗證內容
		$post = get_post( $post_id );
		$this->assertEquals( 'Test Post', $post->post_title );
		$this->assertEquals( 'Test content', $post->post_content );
		$this->assertEquals( 'publish', $post->post_status );
	}

	/**
	 * 測試建立使用者
	 */
	public function test_create_user() {
		// 建立測試使用者
		$user_id = $this->factory->user->create([
			'user_login' => 'testuser',
			'user_email' => 'test@example.com',
			'role'       => 'subscriber',
		]);

		// 驗證使用者已建立
		$this->assertNotEmpty( $user_id );

		// 取得使用者並驗證資料
		$user = get_user_by( 'id', $user_id );
		$this->assertEquals( 'testuser', $user->user_login );
		$this->assertEquals( 'test@example.com', $user->user_email );
		$this->assertTrue( in_array( 'subscriber', $user->roles, true ) );
	}

	/**
	 * 測試斷言方法範例
	 */
	public function test_assertions() {
		// 相等性測試
		$this->assertEquals( 1, 1 );
		$this->assertNotEquals( 1, 2 );

		// 真假值測試
		$this->assertTrue( true );
		$this->assertFalse( false );

		// 空值測試
		$this->assertEmpty( [] );
		$this->assertNotEmpty( [1, 2, 3] );

		// 包含測試
		$this->assertContains( 'apple', ['apple', 'banana', 'orange'] );

		// 陣列鍵值測試
		$this->assertArrayHasKey( 'name', ['name' => 'John', 'age' => 30] );
	}
}

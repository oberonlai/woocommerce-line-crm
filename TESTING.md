# WordPress 外掛單元測試指南

本專案使用 PHPUnit 和 WordPress 測試框架進行單元測試。

## 📋 前置需求

- PHP >= 8.0
- MySQL/MariaDB
- Composer
- SVN (用於下載 WordPress 測試框架)

## 🚀 快速開始

### 1. 安裝測試環境(只需執行一次)

```bash
composer test:install
```

這個指令會:
- ✅ 建立測試資料庫 `wordpress_test`
- ✅ 下載 WordPress 核心檔案到 `/tmp/wordpress`
- ✅ 下載 WordPress 測試框架到 `/tmp/wordpress-tests-lib`
- ✅ 設定測試環境配置

**注意事項:**
- 預設資料庫名稱:`wordpress_test`
- 預設資料庫使用者:`root`
- 預設資料庫密碼:`root`
- 預設資料庫主機:`127.0.0.1`

如果您的資料庫設定不同,請修改 `composer.json` 中的 `test:install` 指令:

```json
"test:install": "bash bin/install-wp-tests.sh <資料庫名稱> <使用者> <密碼> <主機> latest"
```

### 2. 執行測試

```bash
composer test
```

這會執行所有在 `tests/` 資料夾中的測試檔案。

## 📁 測試檔案結構

```
tests/
├── bootstrap.php          # 測試啟動檔案
└── test-sample.php        # 範例測試檔案
```

## ✍️ 撰寫測試

### 基本測試範例

在 `tests/` 資料夾中建立測試檔案,例如 `test-my-feature.php`:

```php
<?php
/**
 * Class MyFeatureTest
 *
 * @package OrderChatz
 */

class MyFeatureTest extends WP_UnitTestCase {

    /**
     * 測試基本功能
     */
    public function test_basic_functionality() {
        // 準備測試資料
        $expected = 'Hello World';
        
        // 執行要測試的功能
        $actual = my_function();
        
        // 斷言結果
        $this->assertEquals( $expected, $actual );
    }
    
    /**
     * 測試 WordPress 功能整合
     */
    public function test_wordpress_integration() {
        // 建立測試文章
        $post_id = $this->factory->post->create([
            'post_title' => 'Test Post',
            'post_status' => 'publish'
        ]);
        
        // 斷言文章已建立
        $this->assertNotEmpty( $post_id );
        
        // 取得文章
        $post = get_post( $post_id );
        $this->assertEquals( 'Test Post', $post->post_title );
    }
}
```

## 🔧 常用測試方法

### 斷言方法

```php
// 相等性測試
$this->assertEquals( $expected, $actual );
$this->assertNotEquals( $expected, $actual );

// 真假值測試
$this->assertTrue( $condition );
$this->assertFalse( $condition );

// 空值測試
$this->assertEmpty( $value );
$this->assertNotEmpty( $value );

// 包含測試
$this->assertContains( $needle, $haystack );
$this->assertNotContains( $needle, $haystack );

// 陣列鍵值測試
$this->assertArrayHasKey( 'key', $array );
```

### WordPress 測試工廠

```php
// 建立測試文章
$post_id = $this->factory->post->create();

// 建立測試使用者
$user_id = $this->factory->user->create([
    'role' => 'administrator'
]);

// 建立測試分類
$term_id = $this->factory->term->create([
    'taxonomy' => 'category',
    'name' => 'Test Category'
]);
```

## 🎯 測試最佳實踐

### 1. 測試命名規範

- 測試檔案名稱:`test-{feature-name}.php`
- 測試類別名稱:`{FeatureName}Test`
- 測試方法名稱:`test_{what_it_tests}`

### 2. 測試結構 (AAA Pattern)

```php
public function test_example() {
    // Arrange - 準備測試資料
    $input = 'test';
    
    // Act - 執行要測試的功能
    $result = my_function( $input );
    
    // Assert - 驗證結果
    $this->assertEquals( 'expected', $result );
}
```

### 3. 使用 setUp 和 tearDown

```php
class MyTest extends WP_UnitTestCase {
    
    protected $test_user_id;
    
    /**
     * 在每個測試方法執行前執行
     */
    public function setUp(): void {
        parent::setUp();
        $this->test_user_id = $this->factory->user->create();
    }
    
    /**
     * 在每個測試方法執行後執行
     */
    public function tearDown(): void {
        wp_delete_user( $this->test_user_id );
        parent::tearDown();
    }
}
```

## 🔍 執行特定測試

```bash
# 執行特定測試檔案
phpunit tests/test-my-feature.php

# 執行特定測試方法
phpunit --filter test_specific_method

# 顯示詳細輸出
phpunit --verbose
```

## 📊 測試覆蓋率

如果您想查看測試覆蓋率,需要安裝 Xdebug:

```bash
# 執行測試並產生覆蓋率報告
phpunit --coverage-html coverage
```

報告會產生在 `coverage/` 資料夾中。

## 🐛 常見問題

### 問題:找不到 phpunit 指令

**解決方案:**
```bash
# 確保已安裝開發依賴
composer install

# 使用完整路徑執行
./vendor/bin/phpunit
```

### 問題:資料庫連線失敗

**解決方案:**
1. 確認 MySQL 正在運行
2. 檢查資料庫帳號密碼是否正確
3. 重新執行 `composer test:install`

### 問題:測試環境檔案找不到

**解決方案:**
```bash
# 清除舊的測試環境
rm -rf /tmp/wordpress /tmp/wordpress-tests-lib

# 重新安裝
composer test:install
```

## 📚 參考資源

- [WordPress Plugin Unit Tests](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Testing Handbook](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)

## 🎯 CI/CD 整合

本專案已整合 GitHub Actions 自動測試,每次 Pull Request 都會自動執行測試。

查看 `.github/workflows/testing.yml` 了解詳細設定。

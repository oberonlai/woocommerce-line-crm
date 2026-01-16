# OrderChatz Logger Utility

## 使用方式

### 基本用法

```php
use OrderChatz\Util\Logger;

// 錯誤日誌
Logger::error('Something went wrong', ['user_id' => 123], 'UserManager');

// 警告日誌
Logger::warning('Performance issue detected', ['query_time' => 2.5], 'Database');

// 資訊日誌
Logger::info('User login successful', ['user_id' => 123], 'Auth');

// 除錯日誌 (只在 WP_DEBUG=true 時顯示)
Logger::debug('Processing data', ['data' => $data], 'API');
```

### 可用的日誌等級

- `Logger::emergency()` - 系統無法使用
- `Logger::alert()` - 必須立即採取行動
- `Logger::critical()` - 嚴重錯誤
- `Logger::error()` - 執行錯誤
- `Logger::warning()` - 警告訊息
- `Logger::notice()` - 重要事件
- `Logger::info()` - 資訊訊息
- `Logger::debug()` - 除錯訊息

### 參數說明

1. **message** (string): 日誌訊息
2. **context** (array): 額外的上下文資料
3. **component** (string): 組件名稱，用於識別日誌來源

### 自動添加的資訊

Logger 會自動添加以下資訊：
- `source`: 'otz' (固定值)
- `timestamp`: ISO 8601 格式的時間戳
- `request_method`: HTTP 請求方法 (如果有)
- `request_uri`: 請求 URI (如果有)

### 整合

- 優先使用 WooCommerce Logger (如果可用)
- 備援使用 WordPress error_log()
- 支援 WP_DEBUG 模式的除錯日誌
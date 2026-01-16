# OrderChatz 前端元件架構

本目錄包含 OrderChatz 聊天介面的所有前端 JavaScript 元件，採用模組化設計。

## 資料夾結構

```
components/
├── chat-area/                 # 聊天區域相關元件
│   ├── chat-area-core.js     # 聊天區域核心元件
│   ├── chat-area-input.js    # 訊息輸入功能
│   ├── chat-area-messages.js # 訊息顯示功能
│   ├── chat-area-ui.js       # UI 介面管理
│   └── chat-area-utils.js    # 工具函數
│
├── customer-info/             # 客戶資訊相關元件
│   ├── customer-info-core.js      # 客戶資訊核心協調器
│   ├── member-binding-manager.js  # 會員綁定管理
│   ├── order-manager.js           # 訂單管理功能
│   └── tags-notes-manager.js      # 標籤和備註管理
│
├── shared/                    # 共用模組
│   └── ui-helpers.js         # 通用 UI 工具函數
│
├── chat-area.js              # 聊天區域主元件 (包裝器)
├── customer-info.js          # 客戶資訊主元件 (包裝器)
├── friend-list.js            # 好友列表元件
├── responsive-handler.js     # 響應式處理器
└── README.md                 # 本檔案
```

## 元件說明

### 主要元件 (Main Components)

- **chat-area.js**: 聊天區域主元件包裝器，整合所有聊天相關功能
- **customer-info.js**: 客戶資訊主元件包裝器，整合客戶資訊管理功能
- **friend-list.js**: 好友列表管理元件
- **responsive-handler.js**: 處理響應式介面調整

### 聊天區域模組 (Chat Area Modules)

- **chat-area-core.js**: 聊天區域的核心協調器
- **chat-area-input.js**: 處理訊息輸入、發送功能
- **chat-area-messages.js**: 處理訊息顯示、載入歷史訊息
- **chat-area-ui.js**: 管理聊天區域的 UI 介面
- **chat-area-utils.js**: 聊天區域共用的工具函數

### 客戶資訊模組 (Customer Info Modules)

- **customer-info-core.js**: 客戶資訊的核心協調器，管理整體邏輯
- **member-binding-manager.js**: 處理 LINE 好友與 WordPress 會員的綁定
- **order-manager.js**: 訂單搜尋、顯示、詳細資訊、自訂欄位管理
- **tags-notes-manager.js**: 客戶標籤和備註的新增、刪除、修改

### 共用模組 (Shared Modules)

- **ui-helpers.js**: 通用的 UI 工具函數，包含：
  - HTML 轉義處理
  - 載入狀態管理
  - 錯誤訊息顯示
  - 成功訊息顯示
  - 展開收合功能
  - 區塊排序功能

## 依賴關係

### 載入順序 (Loading Order)

1. **jQuery** (WordPress 內建)
2. **shared/ui-helpers.js** (基礎工具，所有模組都依賴)
3. **客戶資訊子模組** (member-binding-manager, tags-notes-manager, order-manager)
4. **customer-info-core.js** (依賴所有客戶資訊子模組)
5. **customer-info.js** (依賴 customer-info-core)
6. **其他主元件** (friend-list, chat-area, responsive-handler)

### 模組間通信

模組間使用 jQuery 自訂事件進行通信：

- `friend:selected` - 好友被選中
- `customer:binding-updated` - 會員綁定更新
- `customer:tags-updated` - 標籤更新
- `customer:notes-updated` - 備註更新
- `customer:reload-required` - 需要重新載入客戶資訊

## 開發指南

### 新增模組

1. 確定模組歸屬（chat-area/, customer-info/, shared/, 或根目錄）
2. 建立模組檔案，遵循現有命名規範
3. 在 Chat.php 中添加 wp_enqueue_script 載入腳本
4. 更新本 README 檔案

### 修改現有模組

1. 遵循模組的單一職責原則
2. 保持向後相容性
3. 更新版本號
4. 測試相關功能

### 最佳實踐

1. **職責單一**: 每個模組專注於特定功能
2. **依賴明確**: 明確聲明模組依賴關係
3. **事件驅動**: 使用事件系統進行模組間通信
4. **向後相容**: 保持 API 穩定性
5. **錯誤處理**: 統一使用 ui-helpers.js 的錯誤處理機制

## 版本歷史

- **1.0.13**: 完成模組化重構，建立資料夾分類結構
- **1.0.12**: 原始單一檔案架構
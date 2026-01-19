# 發布新版本指南

本專案使用 GitHub Actions 自動建立 Release 版本。

## 📋 發布流程

### 1. 更新版本號

在 `order-chatz.php` 檔案中更新版本號:

```php
/**
 * Version: 1.0.1  // 更新這裡
 */
```

### 2. 提交變更

```bash
git add .
git commit -m "chore: bump version to 1.0.1"
git push origin main
```

### 3. 建立並推送 Tag

```bash
# 建立 tag (版本號要與 order-chatz.php 中的一致)
git tag v1.0.1

# 推送 tag 到 GitHub
git push origin v1.0.1
```

### 4. 自動建置

當您推送 tag 後,GitHub Actions 會自動:
1. ✅ 安裝正式環境依賴 (`composer install --no-dev`)
2. ✅ 複製必要的外掛檔案
3. ✅ 建立包含 `firebase/php-jwt` 的 ZIP 檔
4. ✅ 產生 Changelog
5. ✅ 建立 GitHub Release
6. ✅ 上傳 ZIP 檔案到 Release

### 5. 檢查 Release

前往 GitHub Repository 的 Releases 頁面:
```
https://github.com/oberonlai/woocommerce-line-crm/releases
```

您會看到新的 Release,包含:
- 📦 可下載的 ZIP 檔案
- 📝 自動產生的 Changelog
- 📄 安裝說明

## 🔍 查看建置狀態

在 GitHub Repository 的 Actions 頁面可以查看建置進度:
```
https://github.com/oberonlai/woocommerce-line-crm/actions
```

## 🛠️ 本地測試打包

如果想在本地測試打包流程,可以執行:

```bash
./build.sh
```

這會在 `build/` 資料夾中產生 ZIP 檔案。

## 📌 注意事項

1. **版本號格式**: Tag 必須使用 `v` 開頭,例如 `v1.0.1`
2. **版本一致性**: Tag 版本號應與 `order-chatz.php` 中的版本號一致
3. **Vendor 資料夾**: 不會被提交到 Git,但會自動包含在 Release ZIP 中
4. **測試套件**: Release 版本不包含 PHPUnit 等開發依賴

## 🚀 快速發布指令

```bash
# 一次完成所有步驟
VERSION="1.0.1"

# 更新版本號後執行:
git add .
git commit -m "chore: bump version to ${VERSION}"
git push origin main
git tag v${VERSION}
git push origin v${VERSION}
```

## 🔄 刪除錯誤的 Release

如果需要刪除錯誤的 tag:

```bash
# 刪除本地 tag
git tag -d v1.0.1

# 刪除遠端 tag
git push origin :refs/tags/v1.0.1
```

然後在 GitHub 上手動刪除對應的 Release。

#!/bin/bash

# WordPress Plugin Build Script
# 此腳本會建立包含必要套件的發布版本

set -e  # 遇到錯誤立即停止

# 顏色輸出
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}開始建立發布版本...${NC}"
echo -e "${BLUE}========================================${NC}"

# 取得外掛資訊
PLUGIN_SLUG="woocommerce-line-crm"
PLUGIN_NAME="order-chatz"
BUILD_DIR="build"
TEMP_DIR="${BUILD_DIR}/${PLUGIN_NAME}"
VERSION=$(grep "Version:" order-chatz.php | awk '{print $2}' | tr -d '\r')

echo -e "${GREEN}外掛名稱:${NC} ${PLUGIN_NAME}"
echo -e "${GREEN}版本號:${NC} ${VERSION}"
echo ""

# 清理舊的 build 資料夾
echo -e "${BLUE}清理舊的建置檔案...${NC}"
rm -rf "${BUILD_DIR}"
mkdir -p "${TEMP_DIR}"

# 複製必要的檔案
echo -e "${BLUE}複製外掛檔案...${NC}"
rsync -av \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='.gitignore' \
  --exclude='.phpcs.xml.dist' \
  --exclude='node_modules' \
  --exclude='tests' \
  --exclude='bin' \
  --exclude='build' \
  --exclude='vendor' \
  --exclude='composer.json' \
  --exclude='composer.lock' \
  --exclude='phpunit.xml.dist' \
  --exclude='phpunit.xml' \
  --exclude='*.sh' \
  --exclude='CLAUDE.md' \
  --exclude='.claude' \
  --exclude='.kiro' \
  --exclude='.tinkersan' \
  --exclude='.playwright-mcp' \
  . "${TEMP_DIR}/"

# 安裝正式環境依賴
echo -e "${BLUE}安裝正式環境依賴套件...${NC}"
cd "${TEMP_DIR}"
composer install --no-dev --optimize-autoloader --no-interaction

# 移除不必要的 Composer 檔案
echo -e "${BLUE}清理 Composer 檔案...${NC}"
rm -f composer.json composer.lock

# 回到專案根目錄
cd ../..

# 建立 ZIP 檔案
ZIP_FILE="${BUILD_DIR}/${PLUGIN_NAME}-${VERSION}.zip"
echo -e "${BLUE}建立 ZIP 檔案...${NC}"
cd "${BUILD_DIR}"
zip -r "${PLUGIN_NAME}-${VERSION}.zip" "${PLUGIN_NAME}" -q
cd ..

# 清理暫存資料夾
echo -e "${BLUE}清理暫存檔案...${NC}"
rm -rf "${TEMP_DIR}"

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}✓ 建置完成!${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}檔案位置:${NC} ${ZIP_FILE}"
echo -e "${GREEN}檔案大小:${NC} $(du -h "${ZIP_FILE}" | cut -f1)"
echo ""

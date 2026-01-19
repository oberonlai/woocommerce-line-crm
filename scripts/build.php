<?php
/**
 * WordPress Plugin Build Script
 * 此腳本會建立包含必要套件的發布版本
 */

// 顏色輸出
define('GREEN', "\033[0;32m");
define('BLUE', "\033[0;34m");
define('RED', "\033[0;31m");
define('NC', "\033[0m"); // No Color

echo BLUE . "========================================" . NC . PHP_EOL;
echo BLUE . "開始建立發布版本..." . NC . PHP_EOL;
echo BLUE . "========================================" . NC . PHP_EOL;

// 取得外掛資訊
$pluginSlug = 'woocommerce-line-crm';
$pluginName = 'order-chatz';
$buildDir = 'build';
$tempDir = "{$buildDir}/{$pluginName}";

// 從主檔案讀取版本號
$mainFile = 'order-chatz.php';
$content = file_get_contents($mainFile);
preg_match('/Version:\s*(.+)/', $content, $matches);
$version = trim($matches[1] ?? '1.0.0');

echo GREEN . "外掛名稱:" . NC . " {$pluginName}" . PHP_EOL;
echo GREEN . "版本號:" . NC . " {$version}" . PHP_EOL;
echo PHP_EOL;

// 清理舊的 build 資料夾
echo BLUE . "清理舊的建置檔案..." . NC . PHP_EOL;
if (is_dir($buildDir)) {
    exec("rm -rf {$buildDir}");
}
mkdir($buildDir, 0755, true);
mkdir($tempDir, 0755, true);

// 要排除的檔案和資料夾
$excludes = [
    '.git',
    '.github',
    '.gitignore',
    '.phpcs.xml.dist',
    'node_modules',
    'tests',
    'bin',
    'build',
    'vendor',
    'composer.json',
    'composer.lock',
    'phpunit.xml.dist',
    'phpunit.xml',
    '*.sh',
    'CLAUDE.md',
    '.claude',
    '.kiro',
    '.tinkersan',
    '.playwright-mcp',
    'scripts',
];

// 建立 rsync 排除參數
$excludeParams = array_map(function($item) {
    return "--exclude='{$item}'";
}, $excludes);
$excludeString = implode(' ', $excludeParams);

// 複製必要的檔案
echo BLUE . "複製外掛檔案..." . NC . PHP_EOL;
exec("rsync -av {$excludeString} . {$tempDir}/");

// 安裝正式環境依賴
echo BLUE . "安裝正式環境依賴套件..." . NC . PHP_EOL;
exec("cd {$tempDir} && composer install --no-dev --optimize-autoloader --no-interaction");

// 移除不必要的 Composer 檔案
echo BLUE . "清理 Composer 檔案..." . NC . PHP_EOL;
@unlink("{$tempDir}/composer.json");
@unlink("{$tempDir}/composer.lock");

// 建立 ZIP 檔案
$zipFile = "{$buildDir}/{$pluginName}-{$version}.zip";
echo BLUE . "建立 ZIP 檔案..." . NC . PHP_EOL;
exec("cd {$buildDir} && zip -r {$pluginName}-{$version}.zip {$pluginName} -q");

// 清理暫存資料夾
echo BLUE . "清理暫存檔案..." . NC . PHP_EOL;
exec("rm -rf {$tempDir}");

// 取得檔案大小
$fileSize = filesize($zipFile);
$fileSizeHuman = round($fileSize / 1024 / 1024, 2) . 'MB';

echo PHP_EOL;
echo GREEN . "========================================" . NC . PHP_EOL;
echo GREEN . "✓ 建置完成!" . NC . PHP_EOL;
echo GREEN . "========================================" . NC . PHP_EOL;
echo GREEN . "檔案位置:" . NC . " {$zipFile}" . PHP_EOL;
echo GREEN . "檔案大小:" . NC . " {$fileSizeHuman}" . PHP_EOL;
echo PHP_EOL;

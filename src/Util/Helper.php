<?php
/**
 * OrderChatz Helper 工具類別
 *
 * 提供各種實用的靜態方法
 *
 * @package OrderChatz
 * @since 1.0.20
 */

declare(strict_types=1);

namespace OrderChatz\Util;

class Helper {

    /**
     * 將純文字中的連結轉換為可點擊的超連結
     *
     * @param string $text 原始文字內容
     * @return string 轉換後的 HTML
     */
    public static function convert_links_to_html($text) {
        if (empty($text) || !is_string($text)) {
            return '';
        }

        // 先進行 HTML 轉義確保安全性
        $escaped_text = esc_html($text);

        // URL 正則表達式，支援 http、https、www 開頭的連結
        $url_pattern = '/(https?:\/\/[^\s<>"]+)|(www\.[^\s<>"]+)/i';

        return preg_replace_callback($url_pattern, function($matches) {
            $url = $matches[0];
            $full_url = (strpos($url, 'www.') === 0) ? 'https://' . $url : $url;

            return sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" style="color: #0073aa; text-decoration: underline;">%s</a>',
                esc_url($full_url),
                esc_html($url)
            );
        }, $escaped_text);
    }
}
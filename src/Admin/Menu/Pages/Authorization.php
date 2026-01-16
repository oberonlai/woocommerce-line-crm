<?php
/**
 * OrderChatz 授權頁面渲染器
 *
 * 處理外掛授權頁面的內容渲染
 *
 * @package OrderChatz\Admin\Menu
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Menu\Pages;

use OrderChatz\Admin\Menu\PageRenderer;

/**
 * 授權頁面渲染器類別
 *
 * 渲染外掛授權相關功能的管理介面
 */
class Authorization extends PageRenderer {
    /**
     * 建構函式
     */
    public function __construct() {
        parent::__construct(
            __('授權', 'otz'),
            'otz-license',
            true // 授權頁面有頁籤導航
        );
    }

    /**
     * 渲染授權頁面內容
     *
     * @return void
     */
    protected function renderPageContent(): void {
        echo '<div class="orderchatz-authorization-page">';
        
        $this->renderEmptyContent(
            __('外掛授權功能正在開發中，將提供授權碼驗證、授權狀態查詢、續約提醒等功能。', 'otz')
        );
        
        echo '</div>';
    }
}
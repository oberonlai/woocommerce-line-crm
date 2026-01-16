<?php
/**
 * OrderChatz 權限驗證器
 *
 * 負責處理所有選單相關的權限檢查和驗證機制
 *
 * @package OrderChatz\Admin\Menu
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Menu;

use OrderChatz\Util\Logger;

/**
 * 權限驗證器類別
 *
 * 提供完整的權限檢查功能，包含基本權限、頁面特定權限和錯誤處理
 */
class PermissionValidator {
    /**
     * 基本選單存取權限
     *
     * @var string
     */
    private const MENU_CAPABILITY = 'manage_woocommerce';

    /**
     * 頁面特定權限配置
     *
     * @var array<string, string>
     */
    private const PAGE_PERMISSIONS = [
        'webhook' => 'manage_options',
        'export' => 'export_data',
    ];

    /**
     * 檢查使用者是否可以存取選單
     *
     * 驗證使用者是否具備基本的選單存取權限
     *
     * @return bool 是否可以存取選單
     */
    public function canAccessMenu(): bool {
        return current_user_can(self::MENU_CAPABILITY);
    }

    /**
     * 檢查使用者是否可以存取特定頁面
     *
     * 先檢查基本權限，再檢查頁面特定權限
     *
     * @param string $page 頁面 slug
     * @return bool 是否可以存取頁面
     */
    public function canAccessPage(string $page): bool {
        // 基本權限檢查
        if (!$this->canAccessMenu()) {
            return false;
        }

        // 頁面特定權限檢查
        if (isset(self::PAGE_PERMISSIONS[$page])) {
            return current_user_can(self::PAGE_PERMISSIONS[$page]);
        }

        return true;
    }

    /**
     * 驗證使用者權限
     *
     * 檢查當前使用者是否具備基本的管理權限
     *
     * @return bool 權限驗證結果
     */
    public function validateUserPermission(): bool {
        $hasPermission = $this->canAccessMenu();
        
        if (!$hasPermission) {
            Logger::error('權限驗證失敗', [
                'user_id' => get_current_user_id(),
                'user_roles' => wp_get_current_user()->roles ?? [],
                'required_capability' => self::MENU_CAPABILITY
            ]);
        }

        return $hasPermission;
    }

    /**
     * 處理未授權存取
     *
     * 當使用者沒有足夠權限時顯示錯誤訊息並終止執行
     *
     * @return void
     */
    public function handleUnauthorizedAccess(): void {
        Logger::error('未授權存取嘗試', [
            'user_id' => get_current_user_id(),
            'user_roles' => wp_get_current_user()->roles ?? [],
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        wp_die(
            __('您沒有足夠的權限存取此頁面。', 'otz'),
            __('權限不足', 'otz'),
            ['response' => 403]
        );
    }

    /**
     * 取得頁面特定權限需求
     *
     * 回傳指定頁面所需的特定權限，如果沒有特定權限則回傳基本權限
     *
     * @param string $page 頁面 slug
     * @return string 所需權限
     */
    public function getPageRequiredCapability(string $page): string {
        return self::PAGE_PERMISSIONS[$page] ?? self::MENU_CAPABILITY;
    }

    /**
     * 檢查使用者角色
     *
     * 驗證使用者是否具備指定的角色
     *
     * @param array $requiredRoles 需要的角色列表
     * @return bool 是否具備任一指定角色
     */
    public function hasRequiredRole(array $requiredRoles): bool {
        $user = wp_get_current_user();
        $userRoles = $user->roles ?? [];

        return !empty(array_intersect($requiredRoles, $userRoles));
    }

    /**
     * 取得使用者權限摘要
     *
     * 回傳當前使用者的權限資訊，用於除錯和日誌記錄
     *
     * @return array 權限摘要
     */
    public function getUserPermissionSummary(): array {
        $user = wp_get_current_user();
        
        return [
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_roles' => $user->roles ?? [],
            'can_manage_woocommerce' => current_user_can('manage_woocommerce'),
            'can_manage_options' => current_user_can('manage_options'),
            'can_export_data' => current_user_can('export_data'),
            'is_admin' => current_user_can('administrator'),
            'is_shop_manager' => in_array('shop_manager', $user->roles ?? [])
        ];
    }
}
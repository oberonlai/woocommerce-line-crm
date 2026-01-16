<?php
/**
 * 好友編輯表單模板
 *
 * @var object $friend 好友資料物件
 * @package OrderChatz
 */

// 防止直接存取
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo __('Edit Friend', 'otz'); ?></h1>
    
    <form method="post">
        <?php wp_nonce_field('edit_friend'); ?>
        <input type="hidden" name="friend_id" value="<?php echo esc_attr($friend->id); ?>" />
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php echo __('Avatar', 'otz'); ?></th>
                <td>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <?php if (!empty($friend->avatar_url)): ?>
                            <img src="<?php echo esc_url($friend->avatar_url); ?>" alt="" style="width: 60px; height: 60px; border-radius: 50%;" />
                        <?php else: ?>
                            <div style="width: 60px; height: 60px; background: #ddd; border-radius: 50%; display: inline-block;"></div>
                            <span style="color: #999;"><?php echo __('No Avatar', 'otz'); ?></span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="display_name"><?php echo __('LINE Display Name', 'otz'); ?></label></th>
                <td>
                    <input type="text" name="display_name" id="display_name" value="<?php echo esc_attr($friend->display_name); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo __('LINE ID', 'otz'); ?></th>
                <td>
                    <?php echo esc_html($friend->line_user_id); ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo __('Source Type', 'otz'); ?></th>
                <td>
                    <?php 
                    $source_types = [
                        'user' => __('User', 'otz'),
                        'group' => __('Group', 'otz'),
                        'room' => __('Room', 'otz')
                    ];
                    $source_display = $source_types[$friend->source_type] ?? $friend->source_type;
                    echo esc_html($source_display);
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo __('Group/Room ID', 'otz'); ?></th>
                <td>
                    <?php if (!empty($friend->group_id)): ?>
                        <code><?php echo esc_html($friend->group_id); ?></code>
                    <?php else: ?>
                        <span style="color: #999;"><?php echo __('None', 'otz'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo __('Followed At', 'otz'); ?></th>
                <td>
                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($friend->followed_at)); ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo __('Unfollowed At', 'otz'); ?></th>
                <td>
                    <?php if (!empty($friend->unfollowed_at)): ?>
                        <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($friend->unfollowed_at)); ?>
                    <?php else: ?>
                        <span style="color: #999;"><?php echo __('Not Unfollowed', 'otz'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo __('User Status', 'otz'); ?></th>
                <td>
                    <?php 
                    $status_types = [
                        'active' => '<span style="color: #46b450;">' . __('Active', 'otz') . '</span>',
                        'blocked' => '<span style="color: #dc3232;">' . __('Blocked', 'otz') . '</span>',
                        'unfollowed' => '<span style="color: #999;">' . __('Unfollowed', 'otz') . '</span>'
                    ];
                    echo $status_types[$friend->status] ?? esc_html($friend->status);
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo __('Last Active', 'otz'); ?></th>
                <td>
                    <?php if (!empty($friend->last_active)): ?>
                        <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($friend->last_active)); ?>
                    <?php else: ?>
                        <span style="color: #999;"><?php echo __('No Activity Record', 'otz'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wp_user_select"><?php echo __('Website Member', 'otz'); ?></label></th>
                <td>
                    <select id="wp_user_select" name="wp_user_select" style="width: 100%;">
                        <?php 
                        if (!empty($friend->wp_user_id)) {
                            $current_user = get_user_by('ID', $friend->wp_user_id);
                            if ($current_user) {
                                $current_user_display = sprintf('%s (%s)', $current_user->display_name, $current_user->user_email);
                                echo '<option value="' . esc_attr($friend->wp_user_id) . '" selected>' . esc_html($current_user_display) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    
                    <input type="hidden" name="wp_user_id" id="wp_user_id" value="<?php echo esc_attr($friend->wp_user_id); ?>" />
                    
                    <p class="description" style="margin-top: 10px;">
                        <?php echo __('Bind LINE friend to website member account to display customer order information on chat page. Search by username or email.', 'otz'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Update Friend', 'otz')); ?>
    </form>
    
    <p>
        <a href="<?php echo remove_query_arg(['action', 'friend_id']); ?>" class="button button-secondary">
            <?php echo __('Back to Friend List', 'otz'); ?>
        </a>
    </p>
</div>
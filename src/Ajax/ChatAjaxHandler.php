<?php
/**
 * 聊天 AJAX 處理器管理類別
 *
 * 統一初始化各個分類的 AJAX 處理器
 *
 * @package OrderChatz\Ajax
 * @since 1.0.0
 */

namespace OrderChatz\Ajax;

use OrderChatz\Ajax\Chat\FriendHandler;
use OrderChatz\Ajax\Customer\CustomerHandler;
use OrderChatz\Ajax\Customer\Tag;
use OrderChatz\Ajax\Message\MessageHandlerRefactored;
use OrderChatz\Ajax\Message\MessageSearch;
use OrderChatz\Ajax\Order\OrderHandler;
use OrderChatz\Ajax\Product\ProductHandler;
use OrderChatz\Ajax\Statistics\StatisticsHandler;

class ChatAjaxHandler {

	public function __construct() {
		// 初始化各個分類的處理器
		new FriendHandler();
		new MessageHandlerRefactored();
		new MessageSearch();
		new CustomerHandler();
		new Tag();
		new OrderHandler();
		new ProductHandler();
		new StatisticsHandler();
	}
}

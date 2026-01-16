/**
 * OrderChatz 聊天介面假資料管理系統
 *
 * 提供三位好友的完整資料，包含基本資訊、對話記錄、客戶詳細資料
 * 支援動態新增訊息和資料查詢功能
 *
 * @package OrderChatz
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * 聊天假資料管理系統
     */
    window.ChatMockData = {
        // 好友基本資料
        friends: [],

        // 按好友 ID 分組的訊息資料
        messages: {},

        // 按好友 ID 分組的客戶資料
        customers: {},

        // 是否已初始化
        initialized: false,

        /**
         * 初始化假資料系統
         */
        init: function () {
            if (this.initialized) {
                return;
            }

            console.log('ChatMockData: 開始初始化假資料系統');

            try {
                this.generateFriendsData();
                this.generateMessagesData();
                this.generateCustomersData();
                this.initialized = true;

                console.log('ChatMockData: 假資料系統初始化完成');
                console.log('- 好友數量:', this.friends.length);
                console.log('- 訊息群組:', Object.keys(this.messages).length);
                console.log('- 客戶資料:', Object.keys(this.customers).length);
            } catch (error) {
                console.error('ChatMockData: 初始化失敗', error);
            }
        },

        /**
         * 生成好友基本資料
         */
        generateFriendsData: function () {
            this.friends = [
                {
                    id: 'friend_001',
                    name: '小明',
                    email: 'ming@example.com',
                    avatar: this.getAvatarUrl('ming@example.com'),
                    lastMessage: '最近有什麼新產品嗎？',
                    lastMessageTime: '14:25',
                    unreadCount: 2,
                    isOnline: true
                },
                {
                    id: 'friend_002',
                    name: '小黃',
                    email: 'huang@example.com',
                    avatar: this.getAvatarUrl('huang@example.com'),
                    lastMessage: '我的訂單什麼時候會出貨？',
                    lastMessageTime: '昨天',
                    unreadCount: 0,
                    isOnline: false
                },
                {
                    id: 'friend_003',
                    name: '小賴',
                    email: 'lai@example.com',
                    avatar: this.getAvatarUrl('lai@example.com'),
                    lastMessage: '謝謝你們的幫忙！',
                    lastMessageTime: '週三',
                    unreadCount: 1,
                    isOnline: true
                }
            ];
        },

        /**
         * 生成訊息資料
         */
        generateMessagesData: function () {
            // 小明的對話記錄
            this.messages['friend_001'] = [
                {
                    id: 'msg_001_001',
                    content: '你好，我想詢問最近的促銷活動',
                    type: 'customer',
                    timestamp: '2024-08-12 14:20:00',
                    sender: '小明',
                    read: true
                },
                {
                    id: 'msg_001_002',
                    content: '您好！現在有夏季促銷活動，全館商品 8 折優惠',
                    type: 'agent',
                    timestamp: '2024-08-12 14:21:00',
                    sender: '客服小美',
                    read: true
                },
                {
                    id: 'msg_001_003',
                    content: '太好了！有什麼推薦的商品嗎？',
                    type: 'customer',
                    timestamp: '2024-08-12 14:22:00',
                    sender: '小明',
                    read: true
                },
                {
                    id: 'msg_001_004',
                    content: '根據您之前的購買記錄，推薦您這款有機茶葉禮盒，品質很棒且現在有特價',
                    type: 'agent',
                    timestamp: '2024-08-12 14:23:00',
                    sender: '客服小美',
                    read: true
                },
                {
                    id: 'msg_001_005',
                    content: '最近有什麼新產品嗎？',
                    type: 'customer',
                    timestamp: '2024-08-12 14:25:00',
                    sender: '小明',
                    read: false
                }
            ];

            // 小黃的對話記錄
            this.messages['friend_002'] = [
                {
                    id: 'msg_002_001',
                    content: '請問我上個禮拜下的訂單現在處理到哪個階段了？',
                    type: 'customer',
                    timestamp: '2024-08-11 10:15:00',
                    sender: '小黃',
                    read: true
                },
                {
                    id: 'msg_002_002',
                    content: '您好，請提供您的訂單編號，我立即為您查詢',
                    type: 'agent',
                    timestamp: '2024-08-11 10:16:00',
                    sender: '客服小美',
                    read: true
                },
                {
                    id: 'msg_002_003',
                    content: '訂單編號是 #48',
                    type: 'customer',
                    timestamp: '2024-08-11 10:17:00',
                    sender: '小黃',
                    read: true
                },
                {
                    id: 'msg_002_004',
                    content: '查到了！您的訂單已經在包裝階段，預計明天就會出貨，出貨後會立即提供追蹤號碼',
                    type: 'agent',
                    timestamp: '2024-08-11 10:18:00',
                    sender: '客服小美',
                    read: true
                },
                {
                    id: 'msg_002_005',
                    content: '我的訂單什麼時候會出貨？',
                    type: 'customer',
                    timestamp: '2024-08-11 15:30:00',
                    sender: '小黃',
                    read: true
                }
            ];

            // 小賴的對話記錄
            this.messages['friend_003'] = [
                {
                    id: 'msg_003_001',
                    content: '我昨天收到商品了，但是有個小問題想請教',
                    type: 'customer',
                    timestamp: '2024-08-10 16:45:00',
                    sender: '小賴',
                    read: true
                },
                {
                    id: 'msg_003_002',
                    content: '沒問題，請說，我們會盡力協助您解決',
                    type: 'agent',
                    timestamp: '2024-08-10 16:46:00',
                    sender: '客服小美',
                    read: true
                },
                {
                    id: 'msg_003_003',
                    content: '包裝盒有點受損，不過商品本身沒問題，這樣可以換一個新的包裝嗎？',
                    type: 'customer',
                    timestamp: '2024-08-10 16:47:00',
                    sender: '小賴',
                    read: true
                },
                {
                    id: 'msg_003_004',
                    content: '當然可以！我們立即為您安排更換，明天快遞會到府收取並送上新包裝的商品',
                    type: 'agent',
                    timestamp: '2024-08-10 16:48:00',
                    sender: '客服小美',
                    read: true
                },
                {
                    id: 'msg_003_005',
                    content: '謝謝你們的幫忙！',
                    type: 'customer',
                    timestamp: '2024-08-10 16:50:00',
                    sender: '小賴',
                    read: false
                }
            ];
        },

        /**
         * 生成客戶詳細資料
         */
        generateCustomersData: function () {
            // 小明的客戶資料
            this.customers['friend_001'] = {
                basicInfo: {
                    name: '王小明',
                    email: 'ming@example.com',
                    phone: '0912-345-678',
                    level: 'VIP',
                    joinDate: '2023-05-15'
                },
                orders: [
                    {
                        orderNumber: '#150',
                        amount: 1580,
                        status: 'completed',
                        statusText: '已完成',
                        date: '2024-08-10',
                        products: '有機茶葉禮盒 x1'
                    },
                    {
                        orderNumber: '#89',
                        amount: 890,
                        status: 'completed',
                        statusText: '已完成',
                        date: '2024-07-25',
                        products: '蜂蜜柚子茶 x2'
                    },
                    {
                        orderNumber: '#34',
                        amount: 1250,
                        status: 'completed',
                        statusText: '已完成',
                        date: '2024-06-18',
                        products: '養生茶包組合 x1'
                    }
                ],
                notes: [
                    {
                        id: 'note_001',
                        content: '喜歡購買有機產品，對品質要求很高',
                        createdBy: '客服小美',
                        createdAt: '2024-07-20'
                    },
                    {
                        id: 'note_002',
                        content: '經常詢問新產品資訊，是忠實客戶',
                        createdBy: '客服小美',
                        createdAt: '2024-08-01'
                    }
                ],
                tags: ['VIP客戶', '有機產品愛好者', '回購率高']
            };

            // 小黃的客戶資料
            this.customers['friend_002'] = {
                basicInfo: {
                    name: '黃大華',
                    email: 'huang@example.com',
                    phone: '0987-654-321',
                    level: '一般',
                    joinDate: '2024-03-20'
                },
                orders: [
                    {
                        orderNumber: '#48',
                        amount: 750,
                        status: 'processing',
                        statusText: '處理中',
                        date: '2024-08-08',
                        products: '花草茶組合 x1'
                    },
                    {
                        orderNumber: '#98',
                        amount: 420,
                        status: 'completed',
                        statusText: '已完成',
                        date: '2024-06-30',
                        products: '玫瑰花茶 x1'
                    }
                ],
                notes: [
                    {
                        id: 'note_003',
                        content: '喜歡花草茶類產品，偏好清淡口味',
                        createdBy: '客服小美',
                        createdAt: '2024-06-30'
                    }
                ],
                tags: ['新客戶', '花草茶愛好者']
            };

            // 小賴的客戶資料
            this.customers['friend_003'] = {
                basicInfo: {
                    name: '賴志強',
                    email: 'lai@example.com',
                    phone: '0955-123-456',
                    level: '金牌',
                    joinDate: '2023-01-10'
                },
                orders: [
                    {
                        orderNumber: '#45',
                        amount: 2100,
                        status: 'completed',
                        statusText: '已完成',
                        date: '2024-08-09',
                        products: '精裝禮盒組 x1'
                    },
                    {
                        orderNumber: '#12',
                        amount: 1650,
                        status: 'completed',
                        statusText: '已完成',
                        date: '2024-07-15',
                        products: '高級烏龍茶 x2'
                    },
                    {
                        orderNumber: '#67',
                        amount: 980,
                        status: 'completed',
                        statusText: '已完成',
                        date: '2024-05-22',
                        products: '鐵觀音茶葉 x1'
                    }
                ],
                notes: [
                    {
                        id: 'note_004',
                        content: '對茶葉品質很講究，是資深茶葉愛好者',
                        createdBy: '客服小美',
                        createdAt: '2024-05-22'
                    },
                    {
                        id: 'note_005',
                        content: '很願意嘗試新品，給予建議很中肯',
                        createdBy: '客服小美',
                        createdAt: '2024-07-15'
                    }
                ],
                tags: ['金牌客戶', '茶葉專家', '產品試用者', '忠實客戶']
            };
        },

        /**
         * 取得頭像 URL
         * @param {string} email - 郵箱地址
         * @returns {string} 頭像 URL
         */
        getAvatarUrl: function (email) {
            if (typeof otzChatConfig !== 'undefined' && otzChatConfig.avatar_urls) {
                return otzChatConfig.avatar_urls[email] || this.getDefaultAvatar();
            }
            return this.getDefaultAvatar();
        },

        /**
         * 取得預設頭像
         * @returns {string} 預設頭像 URL
         */
        getDefaultAvatar: function () {
            return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNFNUU1RTUiLz4KPHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTEyIDEyQzE0LjIwOTEgMTIgMTYgMTAuMjA5MSAxNiA4QzE2IDUuNzkwODYgMTQuMjA5MSA0IDEyIDRDOS43OTA4NiA0IDggNS43OTA4NiA4IDhDOCAxMC4yMDkxIDkuNzkwODYgMTIgMTIgMTJaIiBmaWxsPSIjOTk5OTk5Ii8+CjxwYXRoIGQ9Ik0xMiAxNEM5LjMzIDMgNyAxNS4zMyA3IDE4VjIwSDEwVjE4QzEwIDE2LjY3IDEwLjY3IDE2IDEyIDE2QzEzLjMzIDE2IDE0IDE2LjY3IDE0IDE4VjIwSDE3VjE4QzE3IDE1LjMzIDE0LjY3IDE0IDEyIDRaIiBmaWxsPSIjOTk5OTk5Ii8+Cjwvc3ZnPgo8L3N2Zz4K';
        },

        /**
         * 取得好友資料
         * @param {string} friendId - 好友 ID
         * @returns {object|null} 好友資料
         */
        getFriend: function (friendId) {
            return this.friends.find(friend => friend.id === friendId) || null;
        },

        /**
         * 取得所有好友列表
         * @returns {array} 好友列表
         */
        getAllFriends: function () {
            return [...this.friends];
        },

        /**
         * 取得訊息列表
         * @param {string} friendId - 好友 ID
         * @returns {array} 訊息列表
         */
        getMessages: function (friendId) {
            return this.messages[friendId] || [];
        },

        /**
         * 取得客戶資料
         * @param {string} friendId - 好友 ID
         * @returns {object|null} 客戶資料
         */
        getCustomerData: function (friendId) {
            return this.customers[friendId] || null;
        },

        /**
         * 新增訊息
         * @param {string} friendId - 好友 ID
         * @param {object} messageData - 訊息資料
         * @returns {boolean} 是否成功
         */
        addMessage: function (friendId, messageData) {
            try {
                if (!this.messages[friendId]) {
                    this.messages[friendId] = [];
                }

                // 確保訊息有必要的欄位
                const message = {
                    id: messageData.id || 'msg_' + Date.now(),
                    content: messageData.content || '',
                    type: messageData.type || 'agent',
                    timestamp: messageData.timestamp || this.getCurrentTimestamp(),
                    sender: messageData.sender || '客服小美',
                    read: messageData.read !== undefined ? messageData.read : true
                };

                this.messages[friendId].push(message);

                // 更新好友的最後訊息
                this.updateFriendLastMessage(friendId, message);

                console.log('ChatMockData: 新增訊息成功', {friendId, message});
                return true;
            } catch (error) {
                console.error('ChatMockData: 新增訊息失敗', error);
                return false;
            }
        },

        /**
         * 更新好友最後訊息
         * @param {string} friendId - 好友 ID
         * @param {object} message - 訊息資料
         */
        updateFriendLastMessage: function (friendId, message) {
            const friend = this.getFriend(friendId);
            if (friend) {
                friend.lastMessage = message.content;
                friend.lastMessageTime = this.formatTime(new Date());

                // 如果是客戶發送的訊息，增加未讀數量
                if (message.type === 'customer' && !message.read) {
                    friend.unreadCount = (friend.unreadCount || 0) + 1;
                }
            }
        },

        /**
         * 標記訊息為已讀
         * @param {string} friendId - 好友 ID
         * @param {string} messageId - 訊息 ID (可選，未提供則標記所有未讀訊息)
         */
        markMessageAsRead: function (friendId, messageId) {
            const messages = this.getMessages(friendId);
            const friend = this.getFriend(friendId);

            if (messageId) {
                // 標記特定訊息為已讀
                const message = messages.find(msg => msg.id === messageId);
                if (message) {
                    message.read = true;
                }
            } else {
                // 標記所有未讀訊息為已讀
                messages.forEach(message => {
                    if (!message.read) {
                        message.read = true;
                    }
                });

                // 清除未讀數量
                if (friend) {
                    friend.unreadCount = 0;
                }
            }
        },

        /**
         * 搜尋好友
         * @param {string} query - 搜尋關鍵字
         * @returns {array} 符合的好友列表
         */
        searchFriends: function (query) {
            if (!query || query.trim() === '') {
                return this.getAllFriends();
            }

            const searchTerm = query.toLowerCase().trim();
            return this.friends.filter(friend =>
                friend.name.toLowerCase().includes(searchTerm) ||
                friend.email.toLowerCase().includes(searchTerm) ||
                friend.lastMessage.toLowerCase().includes(searchTerm)
            );
        },

        /**
         * 取得目前時間戳
         * @returns {string} 時間戳字串
         */
        getCurrentTimestamp: function () {
            const now = new Date();
            return now.getFullYear() + '-' +
                String(now.getMonth() + 1).padStart(2, '0') + '-' +
                String(now.getDate()).padStart(2, '0') + ' ' +
                String(now.getHours()).padStart(2, '0') + ':' +
                String(now.getMinutes()).padStart(2, '0') + ':' +
                String(now.getSeconds()).padStart(2, '0');
        },

        /**
         * 格式化時間顯示
         * @param {Date} date - 日期物件
         * @returns {string} 格式化後的時間字串
         */
        formatTime: function (date) {
            return String(date.getHours()).padStart(2, '0') + ':' +
                String(date.getMinutes()).padStart(2, '0');
        },

        /**
         * 清除所有資料
         */
        clear: function () {
            this.friends = [];
            this.messages = {};
            this.customers = {};
            this.initialized = false;
            console.log('ChatMockData: 所有資料已清除');
        },

        /**
         * 取得統計資訊
         * @returns {object} 統計資訊
         */
        getStatistics: function () {
            const totalMessages = Object.values(this.messages).reduce((total, messages) => total + messages.length, 0);
            const totalUnread = this.friends.reduce((total, friend) => total + (friend.unreadCount || 0), 0);
            const onlineFriends = this.friends.filter(friend => friend.isOnline).length;

            return {
                totalFriends: this.friends.length,
                totalMessages: totalMessages,
                totalUnread: totalUnread,
                onlineFriends: onlineFriends,
                offlineFriends: this.friends.length - onlineFriends
            };
        },

        /**
         * 匯出資料 (用於除錯)
         * @returns {object} 完整的資料物件
         */
        exportData: function () {
            return {
                friends: this.friends,
                messages: this.messages,
                customers: this.customers,
                initialized: this.initialized,
                statistics: this.getStatistics()
            };
        }
    };

    // 在頁面載入完成後自動初始化
    $(document).ready(function () {
        if (typeof ChatMockData !== 'undefined') {
            ChatMockData.init();
        }
    });

})(jQuery);
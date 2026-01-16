/**
 * Mobile Component Integrator
 * OrderChatz Frontend Mobile Chat Interface
 *
 * Integrates existing chat components with mobile navigation
 * Ensures seamless operation of friend-list.js, chat-area.js, customer-info.js
 * and polling-manager.js in mobile environment
 *
 * @version 1.0.1
 */

(function ($) {
    'use strict';

    /**
     * Mobile Component Integrator
     */
    window.otzMobileComponentIntegrator = {

        // Component status tracking
        componentsReady: {
            friendList: false,
            chatArea: false,
            customerInfo: false,
            pollingManager: false
        },

        // Integration flags
        integrationComplete: false,

        /**
         * Initialize component integration
         */
        init: function () {
            this.waitForComponents();
            this.setupComponentOverrides();
            this.setupEventBridging();
            this.setupMobileOptimizations();
        },

        /**
         * Wait for all existing components to be ready
         */
        waitForComponents: function () {
            var self = this;
            var maxAttempts = 50; // 5 seconds max wait
            var attempts = 0;

            var checkComponents = function () {
                attempts++;

                // Check if components are available
                self.componentsReady.friendList = !!(window.otzFriendList || window.friendList);
                self.componentsReady.chatArea = !!(window.otzChatArea || window.chatArea);
                self.componentsReady.customerInfo = !!(window.otzCustomerInfo || window.customerInfo);
                self.componentsReady.pollingManager = !!(window.otzPollingManager || window.pollingManager);

                var allReady = Object.values(self.componentsReady).every(status => status);

                if (allReady || attempts >= maxAttempts) {
                    self.onComponentsReady();
                } else {
                    setTimeout(checkComponents, 100);
                }
            };

            checkComponents();
        },

        /**
         * Called when components are ready (or timeout)
         */
        onComponentsReady: function () {
            this.integrateComponents();
            this.integrationComplete = true;

            // Trigger integration complete event
            $(document).trigger('otz-mobile-integration-complete');
        },

        /**
         * Integrate existing components with mobile interface
         */
        integrateComponents: function () {
            this.integrateFriendList();
            this.integrateChatArea();
            this.integrateCustomerInfo();
            this.integratePollingManager();
        },

        /**
         * Integrate friend list component
         */
        integrateFriendList: function () {
            var friendListComponent = window.otzFriendList || window.friendList;
            if (!friendListComponent) {
                console.warn('Friend list component not found');
                return;
            }

            var self = this;

            // Override friend selection to work with mobile navigation
            var originalSelectFriend = friendListComponent.selectFriend || friendListComponent.onFriendSelected;
            if (originalSelectFriend) {
                friendListComponent.selectFriend = function (friendData, friendElement) {
                    // Call original function
                    if (typeof originalSelectFriend === 'function') {
                        originalSelectFriend.call(this, friendData, friendElement);
                    }

                    // Notify mobile navigation
                    if (window.otzMobileTabNavigation) {
                        window.otzMobileTabNavigation.selectFriend(friendData);
                    }

                    // Trigger mobile-specific event
                    $(document).trigger('friend-selected', [friendData]);
                };
            }

            // Redirect friend list rendering to mobile container
            if (friendListComponent.renderFriendList || friendListComponent.loadFriends) {
                var originalRenderFriendList = friendListComponent.renderFriendList;
                var originalLoadFriends = friendListComponent.loadFriends;
                
                // Override renderFriendList separately
                if (originalRenderFriendList) {
                    friendListComponent.renderFriendList = function (friendsToRender) {
                        // Ensure we're rendering in the mobile friends panel
                        var $mobileContainer = $('#otz-friends-panel #friends-list');
                        if ($mobileContainer.length) {
                            // Temporarily redirect the target container
                            var $originalContainer = $('#friends-list');
                            if ($originalContainer.length && $originalContainer[0] !== $mobileContainer[0]) {
                                $originalContainer.attr('id', 'friends-list-backup');
                                $mobileContainer.attr('id', 'friends-list');
                            }

                            // Call original render function with proper parameter
                            originalRenderFriendList.call(this, friendsToRender);

                            // Restore original container ID
                            if ($('#friends-list-backup').length) {
                                $mobileContainer.attr('id', '');
                                $('#friends-list-backup').attr('id', 'friends-list');
                            }
                        } else {
                            // Fallback to original behavior
                            originalRenderFriendList.call(this, friendsToRender);
                        }
                    };
                }
                
                // Override loadFriends separately
                if (originalLoadFriends) {
                    friendListComponent.loadFriends = function (reset) {
                        // Call original loadFriends with proper parameter
                        return originalLoadFriends.call(this, reset);
                    };
                }
            }
        },

        /**
         * Integrate chat area component
         */
        integrateChatArea: function () {
            var chatAreaComponent = window.otzChatArea || window.chatArea;
            if (!chatAreaComponent) {
                return;
            }

            // Override message loading to work with mobile layout
            if (chatAreaComponent.loadMessages) {
                var originalLoadMessages = chatAreaComponent.loadMessages;
                chatAreaComponent.loadMessages = function (userId, options) {
                    // Ensure we're in the correct mobile panel
                    if (window.otzMobileTabNavigation &&
                        window.otzMobileTabNavigation.getCurrentPanel() !== 'chat') {
                        window.otzMobileTabNavigation.switchToPanel('chat');
                    }

                    // Call original function
                    if (typeof originalLoadMessages === 'function') {
                        return originalLoadMessages.call(this, userId, options);
                    }
                };
            }

            // Redirect message rendering to mobile container
            if (chatAreaComponent.renderMessages || chatAreaComponent.displayMessages) {
                var originalRenderMessages = chatAreaComponent.renderMessages || chatAreaComponent.displayMessages;
                chatAreaComponent.renderMessages = chatAreaComponent.displayMessages = function (messages) {
                    // Ensure we're rendering in the mobile chat panel
                    var $mobileContainer = $('#otz-chat-panel #messages-container');
                    if ($mobileContainer.length) {
                        // Temporarily redirect the target container
                        var $originalContainer = $('#messages-container');
                        if ($originalContainer.length && $originalContainer[0] !== $mobileContainer[0]) {
                            $originalContainer.attr('id', 'messages-container-backup');
                            $mobileContainer.attr('id', 'messages-container');
                        }

                        // Call original render function
                        if (typeof originalRenderMessages === 'function') {
                            originalRenderMessages.call(this, messages);
                        }

                        // Restore original container ID
                        if ($('#messages-container-backup').length) {
                            $mobileContainer.attr('id', '');
                            $('#messages-container-backup').attr('id', 'messages-container');
                        }
                    } else {
                        // Fallback to original behavior
                        if (typeof originalRenderMessages === 'function') {
                            originalRenderMessages.call(this, messages);
                        }
                    }
                };
            }

            // Integrate message sending
            if (chatAreaComponent.sendMessage) {
                var originalSendMessage = chatAreaComponent.sendMessage;
                chatAreaComponent.sendMessage = function (message, userId) {
                    // Disable send button during sending to prevent double-tap
                    $('#send-message-btn').prop('disabled', true);

                    // Call original function
                    var result = originalSendMessage.call(this, message, userId);

                    // Re-enable send button after a delay
                    setTimeout(function () {
                        $('#send-message-btn').prop('disabled', false);
                    }, 1000);

                    return result;
                };
            }
        },

        /**
         * Integrate customer info component
         */
        integrateCustomerInfo: function () {
            var customerInfoComponent = window.otzCustomerInfo || window.customerInfo;
            if (!customerInfoComponent) {
                console.warn('Customer info component not found');
                return;
            }

            // Override customer data loading
            if (customerInfoComponent.loadCustomerData) {
                var originalLoadCustomerData = customerInfoComponent.loadCustomerData;
                customerInfoComponent.loadCustomerData = function (userId, options) {
                    // Ensure we're in the correct mobile panel
                    if (window.otzMobileTabNavigation &&
                        window.otzMobileTabNavigation.getCurrentPanel() !== 'customer') {
                        window.otzMobileTabNavigation.switchToPanel('customer');
                    }

                    // Call original function
                    if (typeof originalLoadCustomerData === 'function') {
                        return originalLoadCustomerData.call(this, userId, options);
                    }
                };
            }

            // Redirect customer info rendering to mobile container
            if (customerInfoComponent.renderCustomerInfo || customerInfoComponent.displayCustomerInfo) {
                var originalRenderCustomer = customerInfoComponent.renderCustomerInfo || customerInfoComponent.displayCustomerInfo;
                customerInfoComponent.renderCustomerInfo = customerInfoComponent.displayCustomerInfo = function (customerData) {
                    // Ensure we're rendering in the mobile customer panel
                    var $mobileContainer = $('#customer-info-panel #customer-info');
                    if ($mobileContainer.length) {
                        // Temporarily redirect the target container
                        var $originalContainer = $('#customer-info');
                        if ($originalContainer.length && $originalContainer[0] !== $mobileContainer[0]) {
                            $originalContainer.attr('id', 'customer-info-backup');
                            $mobileContainer.attr('id', 'customer-info');
                        }

                        // Call original render function
                        if (typeof originalRenderCustomer === 'function') {
                            originalRenderCustomer.call(this, customerData);
                        }

                        // Restore original container ID
                        if ($('#customer-info-backup').length) {
                            $mobileContainer.attr('id', '');
                            $('#customer-info-backup').attr('id', 'customer-info');
                        }
                    } else {
                        // Fallback to original behavior
                        if (typeof originalRenderCustomer === 'function') {
                            originalRenderCustomer.call(this, customerData);
                        }
                    }
                };
            }
        },

        /**
         * Integrate polling manager
         */
        integratePollingManager: function () {
            var pollingManagerComponent = window.otzPollingManager || window.pollingManager;
            if (!pollingManagerComponent) {
                console.warn('Polling manager component not found');
                return;
            }

            var self = this;

            // Override polling callbacks to work with mobile interface
            if (pollingManagerComponent.onNewMessage) {
                var originalOnNewMessage = pollingManagerComponent.onNewMessage;
                pollingManagerComponent.onNewMessage = function (messageData) {
                    // Call original callback
                    if (typeof originalOnNewMessage === 'function') {
                        originalOnNewMessage.call(this, messageData);
                    }

                    // Update mobile UI if needed
                    if (window.otzMobileTabNavigation) {
                        var currentPanel = window.otzMobileTabNavigation.getCurrentPanel();
                        var selectedFriend = window.otzMobileTabNavigation.getSelectedFriend();

                        // If new message is for current selected friend and we're not on chat panel
                        if (selectedFriend && messageData.userId === selectedFriend.userId && currentPanel !== 'chat') {
                            // Could show a notification or badge here
                            console.log('New message for selected friend while on', currentPanel, 'panel');
                        }
                    }
                };
            }

            // Handle network status changes
            if (pollingManagerComponent.onNetworkError || pollingManagerComponent.onConnectionLost) {
                var originalOnNetworkError = pollingManagerComponent.onNetworkError || pollingManagerComponent.onConnectionLost;
                pollingManagerComponent.onNetworkError = pollingManagerComponent.onConnectionLost = function () {
                    // Call original handler
                    if (typeof originalOnNetworkError === 'function') {
                        originalOnNetworkError.call(this);
                    }

                    // Update mobile network status
                    if (window.otzMobileTabNavigation) {
                        window.otzMobileTabNavigation.setNetworkStatus(false);
                    }
                };
            }

            if (pollingManagerComponent.onNetworkRestore || pollingManagerComponent.onConnectionRestored) {
                var originalOnNetworkRestore = pollingManagerComponent.onNetworkRestore || pollingManagerComponent.onConnectionRestored;
                pollingManagerComponent.onNetworkRestore = pollingManagerComponent.onConnectionRestored = function () {
                    // Call original handler
                    if (typeof originalOnNetworkRestore === 'function') {
                        originalOnNetworkRestore.call(this);
                    }

                    // Update mobile network status
                    if (window.otzMobileTabNavigation) {
                        window.otzMobileTabNavigation.setNetworkStatus(true);
                    }
                };
            }
        },

        /**
         * Setup component overrides for mobile compatibility
         */
        setupComponentOverrides: function () {
            // Override any desktop-specific behaviors

            // Prevent desktop panel switching
            if (window.otzChatInterface && window.otzChatInterface.switchPanel) {
                window.otzChatInterface.switchPanel = function (panelName) {
                    if (window.otzMobileTabNavigation) {
                        window.otzMobileTabNavigation.switchToPanel(panelName);
                    }
                };
            }

            // Override responsive handler if it exists
            if (window.otzResponsiveHandler) {
                // Disable desktop responsive behaviors
                window.otzResponsiveHandler.isMobile = function () {
                    return true;
                };
                window.otzResponsiveHandler.handleResize = function () { /* No-op for mobile */
                };
            }

            // Override panel resizer (not needed in mobile)
            if (window.otzPanelResizer) {
                window.otzPanelResizer.init = function () { /* No-op for mobile */
                };
                window.otzPanelResizer.enable = function () { /* No-op for mobile */
                };
            }
        },

        /**
         * Setup event bridging between components and mobile navigation
         */
        setupEventBridging: function () {
            var self = this;

            // Listen for component events and translate them for mobile
            $(document).on('friendListUpdated', function (e, friends) {
                // Friends list was updated, refresh mobile view if needed
                if (window.otzMobileTabNavigation &&
                    window.otzMobileTabNavigation.getCurrentPanel() === 'friends') {
                    // Could trigger a refresh animation here
                }
            });

            $(document).on('chatLoaded', function (e, chatData) {
                // Chat was loaded, ensure mobile navigation is in sync
                if (window.otzMobileTabNavigation) {
                    var currentPanel = window.otzMobileTabNavigation.getCurrentPanel();
                    if (currentPanel !== 'chat' && chatData.userId) {
                        // Could auto-switch to chat panel or show notification
                    }
                }
            });

            $(document).on('customerInfoLoaded', function (e, customerData) {
                // Customer info was loaded, ensure mobile navigation is in sync

            });
        },

        /**
         * Setup mobile-specific optimizations
         */
        setupMobileOptimizations: function () {
            // Optimize scroll behavior
            this.optimizeScrolling();

            // Setup touch optimizations
            this.setupTouchOptimizations();

            // Setup keyboard optimizations
            this.setupKeyboardOptimizations();
        },

        /**
         * Optimize scrolling for mobile
         */
        optimizeScrolling: function () {
            // Add momentum scrolling for iOS
            $('.otz-panel-content, .messages-container, .friends-list, .customer-info').css({
                '-webkit-overflow-scrolling': 'touch',
                'overflow-scrolling': 'touch'
            });

            // Prevent body scroll when panels are scrolling
            $('.otz-panel-content').on('touchstart touchmove', function (e) {
                var $this = $(this);
                var scrollTop = $this.scrollTop();
                var deltaY = e.originalEvent.touches[0].clientY - ($this.data('startY') || 0);

                if (scrollTop === 0 && deltaY > 0) {
                    // At top and scrolling down
                    e.preventDefault();
                }
                // 移除底部滾動阻止邏輯，避免好友列表滾動到底部時跳回頂部

                $this.data('startY', e.originalEvent.touches[0].clientY);
            });
        },

        /**
         * Setup touch optimizations
         */
        setupTouchOptimizations: function () {
            // Add active states for touch
            $('.otz-tab-btn, .friend-item, .order-item').on('touchstart', function () {
                $(this).addClass('touch-active');
            }).on('touchend touchcancel', function () {
                $(this).removeClass('touch-active');
            });

            // Prevent double-tap zoom on buttons
            $('.otz-tab-btn, button, .button').css('touch-action', 'manipulation');
        },

        /**
         * Setup keyboard optimizations
         */
        setupKeyboardOptimizations: function () {
            var self = this;

            // Handle virtual keyboard appearance
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', function () {
                    var keyboardHeight = window.innerHeight - window.visualViewport.height;

                    if (keyboardHeight > 0) {
                        // Keyboard is showing
                        $('.otz-content-container').css('padding-bottom', keyboardHeight + 60 + 'px');
                        $('body').addClass('keyboard-open');
                    } else {
                        // Keyboard is hidden
                        $('.otz-content-container').css('padding-bottom', '60px');
                        $('body').removeClass('keyboard-open');
                    }
                });
            }

            // Auto-scroll to message input when focused
            $('#message-input').on('focus', function () {
                setTimeout(function () {
                    var $input = $('#message-input');
                    if ($input.length) {
                        $input[0].scrollIntoView({behavior: 'smooth', block: 'center'});
                    }
                }, 300);
            });
        },

        /**
         * Check if integration is complete
         */
        isReady: function () {
            return this.integrationComplete;
        },

        /**
         * Get component ready status
         */
        getComponentStatus: function () {
            return this.componentsReady;
        }
    };

    // Initialize when document is ready
    $(document).ready(function () {
        // Wait a bit for other components to initialize first
        setTimeout(function () {
            window.otzMobileComponentIntegrator.init();
        }, 100);

        // Make it globally accessible for debugging
        if (window.otzChatConfig && window.otzChatConfig.debug) {
            window.componentIntegrator = window.otzMobileComponentIntegrator;
        }
    });

})(jQuery);
/**
 * OrderChatz Pull-to-Refresh Manager
 * 
 * Handles pull-to-refresh functionality for the chat interface on mobile devices.
 */

class PullToRefreshManager {
    constructor() {
        this.isEnabled = false;
        this.startY = 0;
        this.currentY = 0;
        this.pullDistance = 0;
        this.maxPullDistance = 80;
        this.isRefreshing = false;
        this.isPulling = false;
        this.pullThreshold = 60;
        
        // UI elements
        this.refreshIndicator = null;
        this.pullContainer = null;
        this.scrollContainer = null;
        
        // Bind methods
        this.handleTouchStart = this.handleTouchStart.bind(this);
        this.handleTouchMove = this.handleTouchMove.bind(this);
        this.handleTouchEnd = this.handleTouchEnd.bind(this);
        this.triggerRefresh = this.triggerRefresh.bind(this);
        
        console.log('[PullToRefresh] Pull-to-refresh manager initialized');
    }

    /**
     * Initialize pull-to-refresh functionality
     */
    init() {
        // Only enable on mobile devices
        if (!this.isMobileDevice()) {
            console.log('[PullToRefresh] Not a mobile device, pull-to-refresh disabled');
            return;
        }

        // Find scroll container
        this.scrollContainer = this.findScrollContainer();
        if (!this.scrollContainer) {
            console.warn('[PullToRefresh] Scroll container not found');
            return;
        }

        // Create UI elements
        this.createRefreshIndicator();
        this.setupEventListeners();
        
        this.isEnabled = true;
        console.log('[PullToRefresh] Pull-to-refresh enabled');
    }

    /**
     * Check if device is mobile
     */
    isMobileDevice() {
        return /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
               window.innerWidth <= 768;
    }

    /**
     * Find the main scroll container
     */
    findScrollContainer() {
        // Try common chat container selectors
        const selectors = [
            '#otz-chat-container',
            '.otz-chat-interface',
            '.chat-container',
            '.chat-messages',
            'main'
        ];

        for (const selector of selectors) {
            const element = document.querySelector(selector);
            if (element) {
                return element;
            }
        }

        // Fallback to body
        return document.body;
    }

    /**
     * Create refresh indicator UI
     */
    createRefreshIndicator() {
        // Create pull container
        this.pullContainer = document.createElement('div');
        this.pullContainer.className = 'otz-pull-container';

        // Create refresh indicator
        this.refreshIndicator = document.createElement('div');
        this.refreshIndicator.className = 'otz-refresh-indicator';
        this.refreshIndicator.innerHTML = `
            <div class="otz-refresh-spinner">
                <div class="otz-spinner-icon">⟲</div>
                <div class="otz-refresh-text">下拉重新整理</div>
            </div>
        `;

        this.pullContainer.appendChild(this.refreshIndicator);

        // Insert at the beginning of scroll container
        this.scrollContainer.insertBefore(this.pullContainer, this.scrollContainer.firstChild);

        // Add CSS styles
        this.addPullToRefreshStyles();
    }

    /**
     * Setup touch event listeners
     */
    setupEventListeners() {
        // Use passive listeners for better performance
        this.scrollContainer.addEventListener('touchstart', this.handleTouchStart, { passive: true });
        this.scrollContainer.addEventListener('touchmove', this.handleTouchMove, { passive: false });
        this.scrollContainer.addEventListener('touchend', this.handleTouchEnd, { passive: true });

        // Also listen for scroll events to disable when not at top
        this.scrollContainer.addEventListener('scroll', this.handleScroll.bind(this), { passive: true });
    }

    /**
     * Handle touch start event
     */
    handleTouchStart(event) {
        if (this.isRefreshing || !this.isAtTop()) {
            return;
        }

        this.startY = event.touches[0].clientY;
        this.currentY = this.startY;
        this.isPulling = false;
    }

    /**
     * Handle touch move event
     */
    handleTouchMove(event) {
        if (this.isRefreshing) {
            event.preventDefault();
            return;
        }

        this.currentY = event.touches[0].clientY;
        this.pullDistance = this.currentY - this.startY;

        // Only handle pull down when at top of container
        if (this.pullDistance > 0 && this.isAtTop()) {
            // Only prevent default if we're actually pulling down significantly
            // This allows normal scrolling for small movements
            if (this.pullDistance > 10) {
                this.isPulling = true;
                event.preventDefault();
                
                // Calculate pull progress
                const progress = Math.min(this.pullDistance / this.maxPullDistance, 1);
                this.updateRefreshIndicator(progress);
            }
        } else {
            this.isPulling = false;
            this.resetRefreshIndicator();
        }
    }

    /**
     * Handle touch end event
     */
    handleTouchEnd(event) {
        if (!this.isPulling || this.isRefreshing) {
            return;
        }

        this.isPulling = false;

        // Trigger refresh if pulled enough
        if (this.pullDistance >= this.pullThreshold) {
            this.triggerRefresh();
        } else {
            this.resetRefreshIndicator();
        }
    }

    /**
     * Handle scroll events
     */
    handleScroll() {
        // Reset pull state if user scrolls away from top
        if (!this.isAtTop() && this.isPulling) {
            this.isPulling = false;
            this.resetRefreshIndicator();
        }
    }

    /**
     * Check if container is at the top
     */
    isAtTop() {
        return this.scrollContainer.scrollTop <= 0;
    }

    /**
     * Update refresh indicator based on pull progress
     */
    updateRefreshIndicator(progress) {
        if (!this.refreshIndicator) return;

        const indicator = this.refreshIndicator;
        const spinnerIcon = indicator.querySelector('.otz-spinner-icon');
        const refreshText = indicator.querySelector('.otz-refresh-text');

        // Show indicator
        indicator.style.opacity = progress.toString();
        indicator.style.transform = `translateY(${progress * 100 - 100}%)`;

        // Rotate spinner based on progress
        if (spinnerIcon) {
            spinnerIcon.style.transform = `rotate(${progress * 360}deg)`;
        }

        // Update text based on progress
        if (refreshText) {
            if (progress >= this.pullThreshold / this.maxPullDistance) {
                refreshText.textContent = '放開以重新整理';
                indicator.classList.add('ready-to-refresh');
            } else {
                refreshText.textContent = '下拉重新整理';
                indicator.classList.remove('ready-to-refresh');
            }
        }
    }

    /**
     * Reset refresh indicator to hidden state
     */
    resetRefreshIndicator() {
        if (!this.refreshIndicator) return;

        const indicator = this.refreshIndicator;
        const spinnerIcon = indicator.querySelector('.otz-spinner-icon');
        const refreshText = indicator.querySelector('.otz-refresh-text');

        // Hide indicator
        indicator.style.opacity = '0';
        indicator.style.transform = 'translateY(-100%)';
        indicator.classList.remove('ready-to-refresh');

        // Reset spinner
        if (spinnerIcon) {
            spinnerIcon.style.transform = 'rotate(0deg)';
        }

        // Reset text
        if (refreshText) {
            refreshText.textContent = '下拉重新整理';
        }

        this.pullDistance = 0;
    }

    /**
     * Trigger refresh action
     */
    async triggerRefresh() {
        if (this.isRefreshing) {
            return;
        }

        console.log('[PullToRefresh] Triggering refresh...');
        this.isRefreshing = true;

        const indicator = this.refreshIndicator;
        const spinnerIcon = indicator?.querySelector('.otz-spinner-icon');
        const refreshText = indicator?.querySelector('.otz-refresh-text');

        // Show loading state
        if (indicator) {
            indicator.style.opacity = '1';
            indicator.style.transform = 'translateY(0)';
            indicator.classList.add('refreshing');
        }

        if (refreshText) {
            refreshText.textContent = '重新整理中...';
        }

        if (spinnerIcon) {
            spinnerIcon.style.animation = 'spin 1s linear infinite';
        }

        try {
            // Perform refresh actions
            await this.performRefresh();
            
            // Show success feedback
            if (refreshText) {
                refreshText.textContent = '重新整理完成';
            }

            // Dispatch refresh event
            this.dispatchRefreshEvent('completed');
            
        } catch (error) {
            console.error('[PullToRefresh] Refresh failed:', error);
            
            // Show error feedback
            if (refreshText) {
                refreshText.textContent = '重新整理失敗';
            }

            this.dispatchRefreshEvent('failed', { error });
            
        } finally {
            // Reset state after a delay
            setTimeout(() => {
                this.isRefreshing = false;
                this.resetRefreshIndicator();
                
                if (indicator) {
                    indicator.classList.remove('refreshing');
                }
                
                if (spinnerIcon) {
                    spinnerIcon.style.animation = '';
                }
            }, 1000);
        }
    }

    /**
     * Perform actual refresh actions
     */
    async performRefresh() {
        const refreshActions = [];

        // Refresh chat messages if polling manager is available
        if (window.pollingManager && typeof window.pollingManager.forceUpdate === 'function') {
            refreshActions.push(window.pollingManager.forceUpdate());
        }

        // Refresh friend list if available
        if (window.friendListManager && typeof window.friendListManager.refreshList === 'function') {
            refreshActions.push(window.friendListManager.refreshList());
        }

        // Refresh customer info if available
        if (window.customerInfoManager && typeof window.customerInfoManager.refresh === 'function') {
            refreshActions.push(window.customerInfoManager.refresh());
        }

        // Update service worker cache
        if ('serviceWorker' in navigator) {
            const registration = await navigator.serviceWorker.ready;
            if (registration.active) {
                registration.active.postMessage({ type: 'UPDATE_CACHE' });
            }
        }

        // If no specific refresh actions, just reload the page content
        if (refreshActions.length === 0) {
            await new Promise(resolve => setTimeout(resolve, 1000));
        } else {
            await Promise.allSettled(refreshActions);
        }
    }

    /**
     * Dispatch custom refresh events
     */
    dispatchRefreshEvent(type, data = {}) {
        const event = new CustomEvent(`pull-to-refresh-${type}`, {
            detail: {
                manager: this,
                ...data
            }
        });
        document.dispatchEvent(event);
    }

    /**
     * Add CSS styles for pull-to-refresh
     */
    addPullToRefreshStyles() {
        if (document.getElementById('otz-pull-to-refresh-styles')) {
            return;
        }

        const styles = document.createElement('style');
        styles.id = 'otz-pull-to-refresh-styles';
        styles.textContent = `
            .otz-pull-container {
                position: relative;
                height: 0;
                overflow: visible;
                z-index: 1000;
            }
            
            .otz-refresh-indicator {
                position: absolute;
                top: -80px;
                left: 50%;
                transform: translateX(-50%) translateY(-100%);
                width: 120px;
                text-align: center;
                opacity: 0;
                transition: opacity 0.2s ease, transform 0.2s ease;
                z-index: 1001;
            }
            
            .otz-refresh-spinner {
                background: rgba(255, 255, 255, 0.95);
                border-radius: 8px;
                padding: 12px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                border: 1px solid #e0e0e0;
            }
            
            .otz-spinner-icon {
                font-size: 24px;
                color: #007cba;
                margin-bottom: 8px;
                transition: transform 0.2s ease, color 0.2s ease;
            }
            
            .otz-refresh-text {
                font-size: 12px;
                color: #666;
                margin: 0;
                white-space: nowrap;
            }
            
            .otz-refresh-indicator.ready-to-refresh .otz-spinner-icon {
                color: #28a745;
            }
            
            .otz-refresh-indicator.ready-to-refresh .otz-refresh-text {
                color: #28a745;
            }
            
            .otz-refresh-indicator.refreshing .otz-spinner-icon {
                color: #007cba;
            }
            
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            
            /* Disable overscroll behavior on supported browsers */
            .otz-chat-interface,
            #otz-chat-container {
                overscroll-behavior-y: none;
            }
            
            /* Mobile-specific adjustments */
            @media (max-width: 768px) {
                .otz-refresh-indicator {
                    width: 100px;
                    top: -70px;
                }
                
                .otz-refresh-spinner {
                    padding: 10px;
                }
                
                .otz-spinner-icon {
                    font-size: 20px;
                }
                
                .otz-refresh-text {
                    font-size: 11px;
                }
            }
        `;
        document.head.appendChild(styles);
    }

    /**
     * Enable pull-to-refresh
     */
    enable() {
        this.isEnabled = true;
        if (this.pullContainer) {
            this.pullContainer.style.display = 'block';
        }
    }

    /**
     * Disable pull-to-refresh
     */
    disable() {
        this.isEnabled = false;
        this.isPulling = false;
        this.isRefreshing = false;
        
        if (this.pullContainer) {
            this.pullContainer.style.display = 'none';
        }
        
        this.resetRefreshIndicator();
    }

    /**
     * Check if pull-to-refresh is enabled
     */
    isEnabled() {
        return this.isEnabled;
    }

    /**
     * Manually trigger refresh
     */
    refresh() {
        if (!this.isRefreshing) {
            this.triggerRefresh();
        }
    }

    /**
     * Clean up event listeners
     */
    destroy() {
        if (this.scrollContainer) {
            this.scrollContainer.removeEventListener('touchstart', this.handleTouchStart);
            this.scrollContainer.removeEventListener('touchmove', this.handleTouchMove);
            this.scrollContainer.removeEventListener('touchend', this.handleTouchEnd);
        }

        if (this.pullContainer && this.pullContainer.parentNode) {
            this.pullContainer.parentNode.removeChild(this.pullContainer);
        }

        console.log('[PullToRefresh] Pull-to-refresh manager destroyed');
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize on chat interface pages and mobile devices
    if ((document.body.classList.contains('otz-chat-interface') || 
        document.querySelector('#otz-chat-container') ||
        window.location.search.includes('otz_frontend_chat')) &&
        /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
        
        window.otzPullToRefreshManager = new PullToRefreshManager();
        window.otzPullToRefreshManager.init();
    }
});

// Export for use in other scripts
window.PullToRefreshManager = PullToRefreshManager;
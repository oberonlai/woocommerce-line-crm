/**
 * Viewport Manager - Mobile Browser Height Fix
 * OrderChatz Frontend Mobile Chat Interface
 * 
 * Handles dynamic viewport height adjustments for mobile browsers,
 * especially iOS Safari and Android Chrome with floating UI bars
 * 
 * @version 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * Viewport Manager
     */
    window.otzViewportManager = {
        
        // Configuration
        updateThreshold: 100, // Minimum change in pixels to trigger update
        debounceDelay: 150,   // Debounce delay for resize events
        
        // State tracking
        lastHeight: 0,
        lastWidth: 0,
        resizeTimeout: null,
        isInitialized: false,
        
        // Browser detection
        isIOS: false,
        isAndroid: false,
        isSafari: false,
        isChrome: false,
        isPWA: false,

        /**
         * Initialize viewport manager
         */
        init: function () {
            if (this.isInitialized) {
                return;
            }

            this.detectBrowser();
            this.detectPWAMode();
            this.setInitialViewport();
            this.bindEvents();
            
            this.isInitialized = true;
            
            if (window.otzDebug) {
                console.log('[ViewportManager] Initialized', {
                    isIOS: this.isIOS,
                    isAndroid: this.isAndroid,
                    isSafari: this.isSafari,
                    isChrome: this.isChrome,
                    isPWA: this.isPWA
                });
            }
        },

        /**
         * Detect browser and platform
         */
        detectBrowser: function () {
            const ua = navigator.userAgent;
            
            this.isIOS = /iPad|iPhone|iPod/.test(ua);
            this.isAndroid = /Android/.test(ua);
            this.isSafari = /Safari/.test(ua) && !/Chrome/.test(ua);
            this.isChrome = /Chrome/.test(ua);
            
            // Add browser classes to body
            const $body = $('body');
            if (this.isIOS) $body.addClass('otz-ios');
            if (this.isAndroid) $body.addClass('otz-android');
            if (this.isSafari) $body.addClass('otz-safari');
            if (this.isChrome) $body.addClass('otz-chrome');
        },

        /**
         * Detect PWA mode
         */
        detectPWAMode: function () {
            this.isPWA = window.matchMedia('(display-mode: standalone)').matches ||
                        window.navigator.standalone ||
                        document.referrer.includes('android-app://');
            
            if (this.isPWA) {
                $('body').addClass('otz-pwa-mode');
                
                if (window.otzDebug) {
                    console.log('[ViewportManager] PWA mode detected');
                }
            }
        },

        /**
         * Set initial viewport properties
         */
        setInitialViewport: function () {
            this.updateViewportProperties();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function () {
            // Window resize with debouncing
            $(window).on('resize.otzViewport', () => {
                clearTimeout(this.resizeTimeout);
                this.resizeTimeout = setTimeout(() => {
                    this.handleResize();
                }, this.debounceDelay);
            });

            // Orientation change
            $(window).on('orientationchange.otzViewport', () => {
                setTimeout(() => {
                    this.updateViewportProperties();
                }, 200);
            });

            // Visual viewport API (if available)
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', () => {
                    this.handleVisualViewportChange();
                });
            }

            // Listen for PWA mode changes
            const mqStandalone = window.matchMedia('(display-mode: standalone)');
            mqStandalone.addEventListener('change', (e) => {
                this.isPWA = e.matches;
                if (this.isPWA) {
                    $('body').addClass('otz-pwa-mode');
                } else {
                    $('body').removeClass('otz-pwa-mode');
                }
                this.updateViewportProperties();
            });
        },

        /**
         * Handle window resize
         */
        handleResize: function () {
            const currentHeight = window.innerHeight;
            const currentWidth = window.innerWidth;
            
            // Only update if significant change
            const heightDiff = Math.abs(currentHeight - this.lastHeight);
            const widthDiff = Math.abs(currentWidth - this.lastWidth);
            
            if (heightDiff > this.updateThreshold || widthDiff > 50) {
                this.updateViewportProperties();
                this.lastHeight = currentHeight;
                this.lastWidth = currentWidth;
            }
        },

        /**
         * Handle visual viewport changes (for virtual keyboard, etc.)
         */
        handleVisualViewportChange: function () {
            if (!window.visualViewport) return;
            
            const viewport = window.visualViewport;
            const heightDiff = window.innerHeight - viewport.height;
            
            // If viewport height significantly reduced, likely keyboard is open
            if (heightDiff > 150) {
                $('body').addClass('otz-keyboard-open');
                document.documentElement.style.setProperty('--keyboard-height', `${heightDiff}px`);
            } else {
                $('body').removeClass('otz-keyboard-open');
                document.documentElement.style.setProperty('--keyboard-height', '0px');
            }
        },

        /**
         * Update CSS custom properties for viewport
         */
        updateViewportProperties: function () {
            const vh = window.innerHeight * 0.01;
            const vw = window.innerWidth * 0.01;
            const screenHeight = window.screen.height;
            const actualHeight = window.innerHeight;
            
            // Set basic viewport properties
            document.documentElement.style.setProperty('--vh', `${vh}px`);
            document.documentElement.style.setProperty('--vw', `${vw}px`);
            document.documentElement.style.setProperty('--actual-height', `${actualHeight}px`);
            document.documentElement.style.setProperty('--screen-height', `${screenHeight}px`);
            
            // Calculate browser UI height
            const browserUIHeight = this.calculateBrowserUIHeight();
            document.documentElement.style.setProperty('--browser-ui-height', `${browserUIHeight}px`);
            
            // Set safe viewport height (excluding browser UI)
            const safeHeight = actualHeight - browserUIHeight;
            document.documentElement.style.setProperty('--safe-vh', `${safeHeight * 0.01}px`);
            
            // Platform-specific adjustments
            if (this.isIOS && this.isSafari && !this.isPWA) {
                this.applyIOSSafariAdjustments(actualHeight, screenHeight);
            } else if (this.isAndroid && this.isChrome && !this.isPWA) {
                this.applyAndroidChromeAdjustments(actualHeight, screenHeight);
            }
            
            if (window.otzDebug) {
                console.log('[ViewportManager] Viewport updated', {
                    innerHeight: actualHeight,
                    screenHeight: screenHeight,
                    browserUIHeight: browserUIHeight,
                    safeHeight: safeHeight,
                    isPWA: this.isPWA
                });
            }
        },

        /**
         * Calculate estimated browser UI height
         */
        calculateBrowserUIHeight: function () {
            const screenHeight = window.screen.height;
            const actualHeight = window.innerHeight;
            const difference = screenHeight - actualHeight;
            
            // In PWA mode, there's usually no browser UI
            if (this.isPWA) {
                return 0;
            }
            
            // Platform-specific calculations
            if (this.isIOS) {
                // iOS Safari typically has 44-50px bottom bar when visible
                return difference > 100 ? Math.min(difference, 100) : 0;
            } else if (this.isAndroid) {
                // Android Chrome typically has 56px bottom bar
                return difference > 50 ? Math.min(difference, 80) : 0;
            }
            
            return difference > 50 ? Math.min(difference, 80) : 0;
        },

        /**
         * Apply iOS Safari specific adjustments
         */
        applyIOSSafariAdjustments: function (actualHeight, screenHeight) {
            const difference = screenHeight - actualHeight;
            
            // iOS Safari bottom bar detection
            const hasBottomBar = difference > 50;
            
            if (hasBottomBar) {
                $('body').addClass('otz-ios-bottom-bar');
                // Use small viewport height to avoid bottom bar overlap
                document.documentElement.style.setProperty('--mobile-vh', '1svh');
            } else {
                $('body').removeClass('otz-ios-bottom-bar');
                document.documentElement.style.setProperty('--mobile-vh', '1vh');
            }
        },

        /**
         * Apply Android Chrome specific adjustments
         */
        applyAndroidChromeAdjustments: function (actualHeight, screenHeight) {
            const difference = screenHeight - actualHeight;
            
            // Android Chrome address bar detection
            const hasAddressBar = difference > 50;
            
            if (hasAddressBar) {
                $('body').addClass('otz-android-address-bar');
            } else {
                $('body').removeClass('otz-android-address-bar');
            }
        },

        /**
         * Force viewport update (for external triggers)
         */
        forceUpdate: function () {
            this.updateViewportProperties();
        },

        /**
         * Destroy viewport manager
         */
        destroy: function () {
            $(window).off('.otzViewport');
            
            if (window.visualViewport) {
                window.visualViewport.removeEventListener('resize', this.handleVisualViewportChange);
            }
            
            // Remove CSS custom properties
            const propsToRemove = [
                '--vh', '--vw', '--actual-height', '--screen-height',
                '--browser-ui-height', '--safe-vh', '--mobile-vh', '--keyboard-height'
            ];
            
            propsToRemove.forEach(prop => {
                document.documentElement.style.removeProperty(prop);
            });
            
            // Remove body classes
            $('body').removeClass('otz-ios otz-android otz-safari otz-chrome otz-pwa-mode otz-ios-bottom-bar otz-android-address-bar otz-keyboard-open');
            
            this.isInitialized = false;
        }
    };

    // Auto-initialize when DOM is ready
    $(document).ready(function () {
        // Initialize with slight delay to ensure other components are ready
        setTimeout(() => {
            window.otzViewportManager.init();
        }, 100);
    });

})(jQuery);
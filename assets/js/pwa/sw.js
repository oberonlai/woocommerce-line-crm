/**
 * OrderChatz Service Worker
 *
 * Handles PWA functionality including caching, push notifications, and offline support.
 */

const CACHE_NAME = 'orderchatz-v1.0.32';
const OFFLINE_URL = '/offline.html';

// Resources to cache on install - only include essential files that definitely exist
const URLS_TO_CACHE = [
    '/wp-content/plugins/order-chatz/assets/img/otz-icon-192.png',
    '/wp-content/plugins/order-chatz/assets/img/otz-icon-512.png'
];

/**
 * Service Worker Install Event
 * Cache essential resources for offline functionality
 */
self.addEventListener('install', (event) => {
    console.log('[SW] Installing service worker...');

    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Caching app shell resources');
                return cache.addAll(URLS_TO_CACHE);
            })
            .then(() => {
                console.log('[SW] Service worker installed successfully');
                // Take control immediately
                return self.skipWaiting();
            })
            .catch((error) => {
                console.error('[SW] Failed to cache resources:', error);
            })
    );
});

/**
 * Service Worker Activate Event
 * Clean up old caches and take control
 */
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating service worker...');

    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => {
                        // Delete old caches
                        if (cacheName !== CACHE_NAME) {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('[SW] Service worker activated');
                // Take control of all clients immediately
                return self.clients.claim();
            })
    );
});

/**
 * Service Worker Fetch Event
 * Implement caching strategy: Network first, fallback to cache
 */
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Skip cross-origin requests
    if (!request.url.startsWith(self.location.origin)) {
        return;
    }

    event.respondWith(
        handleFetchRequest(request)
    );
});

/**
 * Handle fetch requests with caching strategy
 * @param {Request} request - The fetch request
 * @returns {Promise<Response>} - The response
 */
async function handleFetchRequest(request) {
    const url = new URL(request.url);

    try {
        // Network first strategy for API calls and dynamic content
        if (isApiRequest(url) || isDynamicContent(url)) {
            return await networkFirstStrategy(request);
        }

        // Cache first strategy for static assets
        if (isStaticAsset(url)) {
            return await cacheFirstStrategy(request);
        }

        // Default: Network first with cache fallback
        return await networkFirstStrategy(request);

    } catch (error) {
        console.error('[SW] Fetch error:', error);
        return await handleFetchError(request, error);
    }
}

/**
 * Network first caching strategy
 * Try network first, fallback to cache if network fails
 */
async function networkFirstStrategy(request) {
    const cache = await caches.open(CACHE_NAME);

    try {
        // Try network first
        const networkResponse = await fetch(request);

        // Cache successful responses
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }

        return networkResponse;
    } catch (error) {
        // Network failed, try cache
        const cachedResponse = await cache.match(request);
        if (cachedResponse) {
            console.log('[SW] Serving from cache:', request.url);
            return cachedResponse;
        }

        // Both network and cache failed
        throw error;
    }
}

/**
 * Cache first caching strategy
 * Check cache first, fallback to network if not cached
 */
async function cacheFirstStrategy(request) {
    const cache = await caches.open(CACHE_NAME);
    const cachedResponse = await cache.match(request);

    if (cachedResponse) {
        console.log('[SW] Serving from cache:', request.url);
        return cachedResponse;
    }

    // Not in cache, fetch from network
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
        cache.put(request, networkResponse.clone());
    }

    return networkResponse;
}

/**
 * Handle fetch errors with appropriate fallbacks
 */
async function handleFetchError(request, error) {
    const cache = await caches.open(CACHE_NAME);
    const cachedResponse = await cache.match(request);

    if (cachedResponse) {
        return cachedResponse;
    }

    // If it's a navigation request, return offline page
    if (request.mode === 'navigate') {
        const offlineResponse = await cache.match(OFFLINE_URL);
        if (offlineResponse) {
            return offlineResponse;
        }
    }

    // Return a generic offline response
    return new Response(
        JSON.stringify({
            error: 'Offline',
            message: '目前無法連接網路，請稍後再試。'
        }),
        {
            headers: {'Content-Type': 'application/json'},
            status: 503,
            statusText: 'Service Unavailable'
        }
    );
}

/**
 * Check if request is an API call
 */
function isApiRequest(url) {
    return url.pathname.includes('/wp-json/') ||
        url.pathname.includes('/wp-admin/admin-ajax.php') ||
        url.pathname.includes('/api/');
}

/**
 * Check if request is for dynamic content
 */
function isDynamicContent(url) {
    // PHP files and dynamic WordPress content
    return url.pathname.endsWith('.php') ||
        url.pathname.includes('/wp-admin/') ||
        url.searchParams.has('otz_frontend_chat');
}

/**
 * Check if request is for static assets
 */
function isStaticAsset(url) {
    const staticExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.woff', '.woff2'];
    return staticExtensions.some(ext => url.pathname.endsWith(ext));
}

/**
 * Service Worker Push Event
 * Handle incoming push notifications
 */
self.addEventListener('push', (event) => {
    // console.log('[SW] Push event received!', event);
    // console.log('[SW] Push data:', event.data ? event.data.text() : 'No data');

    let notificationData = {
        title: 'OrderChatz 通知',
        body: '您有新的訊息',
        icon: '/wp-content/plugins/order-chatz/assets/img/otz-icon-192.png',
        badge: '/wp-content/plugins/order-chatz/assets/img/otz-badge-72.png',
        data: {
            url: '/',
            timestamp: Date.now()
        }
    };

    // Parse push data if available
    if (event.data) {
        try {
            // Try to get text first
            const text = event.data.text();

            // Try to parse as JSON
            const pushData = JSON.parse(text);
            notificationData = {...notificationData, ...pushData};
        } catch (error) {
            console.warn('[SW] Could not parse as JSON, using default notification');
            // If not JSON, just use the default notification data
        }
    }

    // Check if user is actively using the chat page
    event.waitUntil(
        checkActivePageAndShowNotification(notificationData)
    );
});

/**
 * Check if user is actively using chat page before showing notification
 */
async function checkActivePageAndShowNotification(notificationData) {
    try {
        // Get all open windows/tabs
        const clients = await self.clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        });

        // Check if user has active chat page open
        const activeChatPage = clients.find(client =>
            client.url.includes('order-chatz') &&
            client.visibilityState === 'visible' &&
            client.focused === true
        );

        // If user is actively using chat, don't show notification
        if (activeChatPage) {
            return;
        }

        // User is not actively using chat, show notification
        await self.registration.showNotification(notificationData.title, {
            body: notificationData.body,
            icon: notificationData.icon,
            badge: notificationData.badge,
            data: notificationData.data,
            actions: notificationData.actions || [
                {
                    action: 'open',
                    title: '檢視',
                    icon: notificationData.icon
                }
            ],
            requireInteraction: notificationData.requireInteraction || false,
            silent: notificationData.silent || false,
            tag: notificationData.data?.type || 'orderchatz'
        });

    } catch (error) {
        console.error('[SW] Error checking active page or showing notification:', error);

        // Fallback: show notification anyway if error occurs
        await self.registration.showNotification(notificationData.title, {
            body: notificationData.body,
            icon: notificationData.icon,
            badge: notificationData.badge,
            data: notificationData.data,
            actions: notificationData.actions || [
                {
                    action: 'open',
                    title: '檢視',
                    icon: notificationData.icon
                }
            ],
            requireInteraction: notificationData.requireInteraction || false,
            silent: notificationData.silent || false,
            tag: notificationData.data?.type || 'orderchatz'
        });
    }
}

/**
 * Service Worker Notification Click Event
 * Handle notification interactions
 */
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked:', event.notification);

    const notification = event.notification;
    const action = event.action;
    const data = notification.data || {};

    // Close the notification
    notification.close();

    // Determine target URL
    let targetUrl = data.url || '/';

    // Handle specific actions
    if (action === 'open' || !action) {
        // Default action: open the app
        if (data.type === 'line_message' && data.friend_id) {
            // Open chat with specific friend using friend_id and chat=1 parameter
            const chatUrl = new URL(data.url || self.location.origin + '/order-chatz/');
            chatUrl.searchParams.set('friend', data.friend_id);
            chatUrl.searchParams.set('chat', '1');
            targetUrl = chatUrl.toString();
        }

        event.waitUntil(
            handleNotificationClick(targetUrl)
        );
    }
});

/**
 * Handle notification click by opening or focusing app window
 */
async function handleNotificationClick(targetUrl) {
    try {
        // Get all clients (open windows/tabs)
        const clients = await self.clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        });

        // Check if app is already open
        for (const client of clients) {
            const clientUrl = new URL(client.url);
            const targetUrlObj = new URL(targetUrl, self.location.origin);

            // If same origin and similar path, focus existing window
            if (clientUrl.origin === targetUrlObj.origin) {
                // Navigate to target URL and focus
                await client.navigate(targetUrl);
                return client.focus();
            }
        }

        // No existing window found, open new one
        return self.clients.openWindow(targetUrl);

    } catch (error) {
        console.error('[SW] Error handling notification click:', error);
        // Fallback: just open new window
        return self.clients.openWindow(targetUrl);
    }
}

/**
 * Service Worker Message Event
 * Handle messages from main thread
 */
self.addEventListener('message', (event) => {
    const {type, data} = event.data || {};

    switch (type) {
        case 'SKIP_WAITING':
            console.log('[SW] Received skip waiting message');
            self.skipWaiting();
            break;

        case 'UPDATE_CACHE':
            console.log('[SW] Updating cache with new resources');
            event.waitUntil(updateCache(data?.urls || []));
            break;

        case 'CLEAR_CACHE':
            console.log('[SW] Clearing cache');
            event.waitUntil(clearCache());
            break;

        default:
            console.log('[SW] Unknown message type:', type);
    }
});

/**
 * Update cache with new resources
 */
async function updateCache(urls) {
    try {
        const cache = await caches.open(CACHE_NAME);
        if (urls.length > 0) {
            await cache.addAll(urls);
            console.log('[SW] Cache updated with new resources');
        }
    } catch (error) {
        console.error('[SW] Failed to update cache:', error);
    }
}

/**
 * Clear all caches
 */
async function clearCache() {
    try {
        const cacheNames = await caches.keys();
        await Promise.all(
            cacheNames.map(cacheName => caches.delete(cacheName))
        );
        console.log('[SW] All caches cleared');
    } catch (error) {
        console.error('[SW] Failed to clear cache:', error);
    }
}

/**
 * Service Worker Error Event
 * Handle uncaught errors
 */
self.addEventListener('error', (event) => {
    console.error('[SW] Service Worker error:', event.error);
});

/**
 * Service Worker Unhandled Rejection Event
 * Handle uncaught promise rejections
 */
self.addEventListener('unhandledrejection', (event) => {
    console.error('[SW] Unhandled promise rejection:', event.reason);
    event.preventDefault();
});
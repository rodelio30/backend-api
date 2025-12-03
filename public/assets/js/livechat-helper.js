/**
 * LiveChat Helper - API Integration with Embed Support
 * Version: 2.0.0
 * 
 * This helper provides both popup and embedded iframe options
 */

(function() {
    'use strict';

    window.LiveChatHelper = {
        // Configuration
        config: {
            apiKey: '',
            baseUrl: 'https://livechat.kopisugar.cc',
            mode: 'popup', // 'popup' | 'embed' | 'widget'
            embedConfig: {
                theme: 'modern',
                position: 'fixed',
                animation: {
                    duration: 300,
                    easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
                }
            }
        },

        // Reference to embed instance
        embedInstance: null,
        
        // Fullscreen chat state
        fullscreenState: {
            isMinimized: false,
            container: null,
            iframe: null
        },

        /**
         * Initialize the LiveChat Helper
         * @param {Object} options - Configuration options
         */
        init: function(options) {
            // Merge options with default config
            this.config = Object.assign({}, this.config, options);
            
            // Initialize message listener for iframe communication
            this.initMessageListener();

            // Initialize embed if in embed mode
            if (this.config.mode === 'embed') {
                this.initEmbed();
            }
            
            // Initialize widget if in widget mode
            if (this.config.mode === 'widget') {
                this.initWidget();
            }
        },
        
        /**
         * Initialize message listener for iframe communication
         */
        initMessageListener: function() {
            // Only add listener once
            if (this._messageListenerAdded) return;
            
            window.addEventListener('message', (event) => {
                // Security check - ensure message comes from our domain
                const allowedOrigin = new URL(this.config.baseUrl).origin;
                if (event.origin !== allowedOrigin) {
                    return;
                }
                
                // Handle close message from fullscreen chat iframe
                if (event.data && event.data.type === 'close_fullscreen_chat' && event.data.source === 'livechat_iframe') {
                    this.closeFullscreenChat();
                }
            });
            
            this._messageListenerAdded = true;
        },

        /**
         * Initialize the embed system
         */
        initEmbed: async function() {
            // Dynamically load the embed script if not already loaded
            if (!window.LiveChatEmbed) {
                await this.loadScript(`${this.config.baseUrl}/assets/js/livechat-embed.js`);
            }

            // Create embed instance
            this.embedInstance = new window.LiveChatEmbed({
                apiKey: this.config.apiKey,
                baseUrl: this.config.baseUrl,
                ...this.config.embedConfig,
                callbacks: {
                    onOpen: () => {
                        if (this.config.onOpen) this.config.onOpen();
                    },
                    onClose: () => {
                        if (this.config.onClose) this.config.onClose();
                    },
                    onReady: () => {
                        if (this.config.onReady) this.config.onReady();
                    },
                    onError: (error) => {
                        if (this.config.onError) this.config.onError(error);
                    }
                }
            });

            // Initialize the embed
            await this.embedInstance.init();
        },

        /**
         * Initialize the widget system
         */
        initWidget: function() {
            // Get position from widgetConfig or fallback to direct config
            const position = (this.config.widgetConfig && this.config.widgetConfig.position) 
                           || this.config.position 
                           || 'bottom-right';
            
            // Set global config for widget
            window.LiveChatConfig = {
                baseUrl: this.config.baseUrl,
                apiKey: this.config.apiKey,
                theme: this.config.theme || 'blue',
                position: position,
                widgetConfig: this.config.widgetConfig || { position: position }
            };

            // Load widget script
            this.loadScript(`${this.config.baseUrl}/assets/js/widget.js?v=${Date.now()}`);
        },

        /**
         * Open chat with user information
         * @param {Object} userInfo - User information
         */
        openChat: function(userInfo) {
            switch (this.config.mode) {
                case 'embed':
                    this.openEmbedChat(userInfo);
                    break;
                case 'fullscreen':
                    this.openFullscreenChat(userInfo);
                    break;
                case 'widget':
                    this.openWidgetChat(userInfo);
                    break;
                case 'popup':
                default:
                    this.openPopupChat(userInfo);
                    break;
            }
        },

        /**
         * Open chat in embed mode
         */
        openEmbedChat: async function(userInfo) {
            if (!this.embedInstance) {
                await this.initEmbed();
            }
            
            this.embedInstance.openChat({
                userId: userInfo.userId || userInfo.id,
                name: userInfo.name || userInfo.fullname,
                email: userInfo.email,
                username: userInfo.username
            });
        },

        /**
         * Open chat in widget mode
         */
        openWidgetChat: function(userInfo) {
            if (window.LiveChatWidget) {
                window.LiveChatWidget.updateUser({
                    isLoggedIn: true,
                    username: userInfo.username,
                    fullname: userInfo.name || userInfo.fullname,
                    systemId: userInfo.userId || userInfo.id
                });
                window.LiveChatWidget.open();
            }
        },

        /**
         * Open chat in fullscreen mode (fills entire viewport)
         */
        openFullscreenChat: function(userInfo) {
            this.createFullscreenIframe(userInfo);
        },

        /**
         * Create fullscreen iframe (original working version)
         */
        createFullscreenIframe: function(userInfo) {
            // Remove any existing fullscreen iframe
            const existingContainer = document.getElementById('livechat-fullscreen-container');
            if (existingContainer) {
                existingContainer.remove();
            }

            // Inject fullscreen styles
            this.injectFullscreenStyles();

            // Create fullscreen iframe container
            const container = document.createElement('div');
            container.id = 'livechat-fullscreen-container';
            container.className = 'livechat-fullscreen-container';

            // Create loading indicator
            const loader = document.createElement('div');
            loader.className = 'livechat-fullscreen-loader';
            loader.innerHTML = `
                <div class="livechat-fullscreen-spinner"></div>
                <p>Loading LiveChat...</p>
            `;

            // Create the iframe
            const iframe = document.createElement('iframe');
            iframe.id = 'livechat-fullscreen-frame';
            iframe.className = 'livechat-fullscreen-iframe';
            iframe.src = this.generateFullscreenChatUrl(userInfo);
            iframe.setAttribute('frameborder', '0');
            iframe.setAttribute('allowfullscreen', 'true');
            iframe.setAttribute('sandbox', 'allow-same-origin allow-scripts allow-forms allow-popups allow-top-navigation allow-modals');

            // Handle iframe load
            iframe.addEventListener('load', () => {
                loader.style.display = 'none';
                iframe.style.opacity = '1';
            });

            // Handle iframe error
            iframe.addEventListener('error', () => {
                loader.innerHTML = `
                    <div class="livechat-fullscreen-error">
                        <p>Failed to load LiveChat. Please try again.</p>
                        <button onclick="window.LiveChatHelper.closeFullscreenChat()">Close</button>
                    </div>
                `;
            });

            // Add close functionality with ESC key
            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    container.remove();
                }
            };
            document.addEventListener('keydown', escHandler);

            // Assemble the structure
            container.appendChild(loader);
            container.appendChild(iframe);

            // Add to body
            document.body.appendChild(container);

            // Prevent body scrolling
            document.body.style.overflow = 'hidden';

            // Add cleanup handler when container is removed
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList') {
                        mutation.removedNodes.forEach((node) => {
                            if (node.id === 'livechat-fullscreen-container') {
                                document.body.style.overflow = '';
                                document.removeEventListener('keydown', escHandler);
                                observer.disconnect();
                            }
                        });
                    }
                });
            });
            observer.observe(document.body, { childList: true });

            // Call open callback
            if (this.config.onOpen) {
                this.config.onOpen();
            }
        },
        
        /**
         * Minimize fullscreen chat to a small widget
         */
        minimizeFullscreenChat: function() {
            if (!this.fullscreenState.container) return;
            
            this.fullscreenState.container.classList.add('minimized');
            this.fullscreenState.isMinimized = true;
            document.body.style.overflow = ''; // Restore scrolling
            
            // Update minimize button
            const minimizeBtn = this.fullscreenState.container.querySelector('.livechat-minimize-button');
            if (minimizeBtn) {
                minimizeBtn.innerHTML = '□';
                minimizeBtn.title = 'Maximize Chat';
                minimizeBtn.onclick = () => this.maximizeFullscreenChat();
            }
        },
        
        /**
         * Maximize fullscreen chat from minimized state
         */
        maximizeFullscreenChat: function() {
            if (!this.fullscreenState.container) return;
            
            this.fullscreenState.container.classList.remove('minimized');
            this.fullscreenState.isMinimized = false;
            document.body.style.overflow = 'hidden'; // Prevent scrolling
            
            // Update minimize button
            const minimizeBtn = this.fullscreenState.container.querySelector('.livechat-minimize-button');
            if (minimizeBtn) {
                minimizeBtn.innerHTML = '−';
                minimizeBtn.title = 'Minimize Chat';
                minimizeBtn.onclick = () => this.minimizeFullscreenChat();
            }
        },
        
        /**
         * Close fullscreen chat completely
         */
        closeFullscreenChat: function() {
            const container = document.getElementById('livechat-fullscreen-container');
            if (container) {
                container.remove();
                if (this.config.onClose) this.config.onClose();
            }
        },

        /**
         * Inject CSS styles for fullscreen mode
         */
        injectFullscreenStyles: function() {
            if (document.getElementById('livechat-fullscreen-styles')) return;

            const styles = document.createElement('style');
            styles.id = 'livechat-fullscreen-styles';
            styles.textContent = `
                /* LiveChat Fullscreen Styles */
                .livechat-fullscreen-container {
                    position: fixed !important;
                    top: 0 !important;
                    left: 0 !important;
                    right: 0 !important;
                    bottom: 0 !important;
                    width: 100vw !important;
                    height: 100vh !important;
                    z-index: 2147483647 !important;
                    background: #ffffff !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    border: none !important;
                    box-sizing: border-box !important;
                }

                .livechat-fullscreen-iframe {
                    width: 100% !important;
                    height: 100% !important;
                    border: none !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    display: block !important;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                }

                .livechat-fullscreen-loader {
                    position: absolute !important;
                    top: 50% !important;
                    left: 50% !important;
                    transform: translate(-50%, -50%) !important;
                    text-align: center !important;
                    z-index: 10 !important;
                    color: #333 !important;
                }

                .livechat-fullscreen-spinner {
                    width: 40px !important;
                    height: 40px !important;
                    border: 4px solid #f3f3f3 !important;
                    border-top: 4px solid #667eea !important;
                    border-radius: 50% !important;
                    animation: livechat-fullscreen-spin 1s linear infinite !important;
                    margin: 0 auto 15px !important;
                }

                @keyframes livechat-fullscreen-spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }

                .livechat-fullscreen-loader p {
                    margin: 10px 0 !important;
                    font-size: 16px !important;
                    color: #666 !important;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
                }

                .livechat-fullscreen-error {
                    text-align: center !important;
                    color: #e74c3c !important;
                }

                .livechat-fullscreen-error button {
                    margin-top: 10px !important;
                    padding: 8px 16px !important;
                    background: #e74c3c !important;
                    color: white !important;
                    border: none !important;
                    border-radius: 4px !important;
                    cursor: pointer !important;
                }
            `;

            document.head.appendChild(styles);
        },

        /**
         * Generate URL for fullscreen chat
         */
        generateFullscreenChatUrl: function(userInfo) {
            const baseUrl = this.config.baseUrl;
            const apiKey = this.config.apiKey;
            
            // Build URL with iframe=1 and fullscreen=1 parameters
            let url = `${baseUrl}/chat?api_key=${encodeURIComponent(apiKey)}&iframe=1&fullscreen=1`;
            
            if (userInfo && !userInfo.isAnonymous) {
                if (userInfo.userId || userInfo.id) {
                    url += `&external_system_id=${encodeURIComponent(userInfo.userId || userInfo.id)}`;
                }
                if (userInfo.name || userInfo.fullname) {
                    url += `&external_fullname=${encodeURIComponent(userInfo.name || userInfo.fullname)}`;
                }
                if (userInfo.email) {
                    url += `&external_email=${encodeURIComponent(userInfo.email)}`;
                }
                if (userInfo.username) {
                    url += `&external_username=${encodeURIComponent(userInfo.username)}`;
                }
                if (userInfo.phone) {
                    url += `&customer_phone=${encodeURIComponent(userInfo.phone)}`;
                }
                url += '&user_role=loggedUser';
            } else {
                url += '&user_role=anonymous';
            }
            
            return url;
        },

        /**
         * Open chat in popup mode (original behavior)
         */
        openPopupChat: function(userInfo) {
            const baseUrl = this.config.baseUrl;
            const apiKey = this.config.apiKey;
            
            // Build URL with parameters
            let url = `${baseUrl}/chat?api_key=${encodeURIComponent(apiKey)}`;
            
            if (userInfo) {
                if (userInfo.userId || userInfo.id) {
                    url += `&external_system_id=${encodeURIComponent(userInfo.userId || userInfo.id)}`;
                }
                if (userInfo.name || userInfo.fullname) {
                    url += `&external_fullname=${encodeURIComponent(userInfo.name || userInfo.fullname)}`;
                }
                if (userInfo.email) {
                    url += `&external_email=${encodeURIComponent(userInfo.email)}`;
                }
                if (userInfo.username) {
                    url += `&external_username=${encodeURIComponent(userInfo.username)}`;
                }
                if (userInfo.phone) {
                    url += `&customer_phone=${encodeURIComponent(userInfo.phone)}`;
                }
                url += '&user_role=loggedUser';
            }
            
            // Open in new window
            const width = 400;
            const height = 600;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;
            
            window.open(
                url,
                'LiveChat',
                `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
            );
        },

        /**
         * Open anonymous chat
         */
        openAnonymousChat: function() {
            switch (this.config.mode) {
                case 'embed':
                    this.openAnonymousEmbedChat();
                    break;
                case 'fullscreen':
                    this.openFullscreenChat({ isAnonymous: true });
                    break;
                case 'widget':
                    this.openAnonymousWidgetChat();
                    break;
                case 'popup':
                default:
                    this.openAnonymousPopupChat();
                    break;
            }
        },

        /**
         * Open anonymous chat in embed mode
         */
        openAnonymousEmbedChat: async function() {
            if (!this.embedInstance) {
                await this.initEmbed();
            }
            
            this.embedInstance.openAnonymousChat();
        },

        /**
         * Open anonymous chat in widget mode
         */
        openAnonymousWidgetChat: function() {
            if (window.LiveChatWidget) {
                window.LiveChatWidget.updateUser({
                    isLoggedIn: false
                });
                window.LiveChatWidget.open();
            }
        },

        /**
         * Open anonymous chat in popup mode
         */
        openAnonymousPopupChat: function() {
            const baseUrl = this.config.baseUrl;
            const apiKey = this.config.apiKey;
            
            let url = `${baseUrl}/chat?api_key=${encodeURIComponent(apiKey)}&user_role=anonymous`;
            
            const width = 400;
            const height = 600;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;
            
            window.open(
                url,
                'LiveChat',
                `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
            );
        },

        /**
         * Close the chat (works for embed, fullscreen, and widget modes)
         */
        closeChat: function() {
            if (this.config.mode === 'embed' && this.embedInstance) {
                this.embedInstance.close();
            } else if (this.config.mode === 'fullscreen') {
                const container = document.getElementById('livechat-fullscreen-container');
                if (container) {
                    container.remove();
                    if (this.config.onClose) this.config.onClose();
                }
            } else if (this.config.mode === 'widget' && window.LiveChatWidget) {
                window.LiveChatWidget.close();
            }
        },

        /**
         * Update user information
         */
        updateUser: function(userInfo) {
            if (this.config.mode === 'embed' && this.embedInstance) {
                this.embedInstance.updateUser(userInfo);
            } else if (this.config.mode === 'widget' && window.LiveChatWidget) {
                window.LiveChatWidget.updateUser({
                    isLoggedIn: true,
                    username: userInfo.username,
                    fullname: userInfo.name || userInfo.fullname,
                    systemId: userInfo.userId || userInfo.id
                });
            }
        },

        /**
         * Set configuration mode
         */
        setMode: function(mode) {
            this.config.mode = mode;
            
            // Re-initialize if switching to embed or widget
            if (mode === 'embed') {
                this.initEmbed();
            } else if (mode === 'widget') {
                this.initWidget();
            }
        },

        /**
         * Set widget position (only works in widget mode)
         */
        setWidgetPosition: function(position) {
            if (this.config.mode !== 'widget') {
                return;
            }
            
            // Update config
            if (!this.config.widgetConfig) {
                this.config.widgetConfig = {};
            }
            this.config.widgetConfig.position = position;
            this.config.position = position;
            
            // Update global config if it exists
            if (window.LiveChatConfig) {
                window.LiveChatConfig.position = position;
                if (window.LiveChatConfig.widgetConfig) {
                    window.LiveChatConfig.widgetConfig.position = position;
                }
            }
            
            // Update widget position if it exists
            if (window.LiveChatWidget && typeof window.LiveChatWidget.updatePosition === 'function') {
                window.LiveChatWidget.updatePosition(position);
            }
        },

        /**
         * Load external script
         */
        loadScript: function(src) {
            return new Promise((resolve, reject) => {
                // Check if script already exists
                if (document.querySelector(`script[src="${src}"]`)) {
                    resolve();
                    return;
                }

                const script = document.createElement('script');
                script.src = src;
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        },

        /**
         * Destroy the helper instance
         */
        destroy: function() {
            if (this.embedInstance) {
                this.embedInstance.destroy();
                this.embedInstance = null;
            }
            
            if (window.LiveChatWidget) {
                window.LiveChatWidget.destroy();
            }
        }
    };

})();
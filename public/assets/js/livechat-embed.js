/**
 * LiveChat Embed Integration
 * Full-screen iframe integration for external websites
 * Version: 1.0.0
 */

(function() {
    'use strict';

    // Main LiveChat Embed Class
    class LiveChatEmbed {
        constructor(config = {}) {
            // Default configuration
            this.config = {
                apiKey: '',
                baseUrl: 'https://livechat.kopisugar.cc',
                theme: 'modern',
                position: 'fixed',
                zIndex: 999999,
                animation: {
                    duration: 300,
                    easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
                },
                customStyles: {},
                callbacks: {
                    onOpen: null,
                    onClose: null,
                    onReady: null,
                    onError: null
                },
                ...config
            };

            this.isOpen = false;
            this.isInitialized = false;
            this.container = null;
            this.iframe = null;
            this.overlay = null;
        }

        /**
         * Initialize the embed system
         */
        async init() {
            if (this.isInitialized) {
                console.warn('LiveChatEmbed: Already initialized');
                return;
            }

            // Validate API key first
            const isValid = await this.validateApiKey();
            if (!isValid) {
                this.handleError('Invalid API key or domain not authorized');
                return false;
            }

            // Inject styles
            this.injectStyles();

            // Mark as initialized
            this.isInitialized = true;

            // Call ready callback
            if (this.config.callbacks.onReady) {
                this.config.callbacks.onReady();
            }

            return true;
        }

        /**
         * Validate API key with the server
         */
        async validateApiKey() {
            if (!this.config.apiKey) {
                console.error('LiveChatEmbed: No API key provided');
                return false;
            }

            try {
                const response = await fetch(`${this.config.baseUrl}/api/widget/validate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        api_key: this.config.apiKey,
                        domain: window.location.hostname
                    })
                });

                const result = await response.json();
                if (!result.valid) {
                    console.error(`LiveChatEmbed: ${result.error}`);
                    return false;
                }

                return true;
            } catch (error) {
                console.error('LiveChatEmbed: API validation failed', error);
                return false;
            }
        }

        /**
         * Open chat with user information
         */
        async openChat(userInfo = {}) {
            // Initialize if not already done
            if (!this.isInitialized) {
                const initialized = await this.init();
                if (!initialized) return;
            }

            if (this.isOpen) return;

            // Create the embed container
            this.createEmbedContainer(userInfo);

            // Show with animation
            requestAnimationFrame(() => {
                this.container.classList.add('livechat-embed-active');
                this.isOpen = true;

                // Call open callback
                if (this.config.callbacks.onOpen) {
                    this.config.callbacks.onOpen();
                }
            });
        }

        /**
         * Open chat for anonymous user
         */
        async openAnonymousChat() {
            return this.openChat({
                isAnonymous: true
            });
        }

        /**
         * Close the chat
         */
        close() {
            if (!this.isOpen || !this.container) return;

            // Animate out
            this.container.classList.remove('livechat-embed-active');

            // Remove after animation
            setTimeout(() => {
                if (this.container) {
                    this.container.remove();
                    this.container = null;
                    this.iframe = null;
                    this.overlay = null;
                }
                this.isOpen = false;

                // Call close callback
                if (this.config.callbacks.onClose) {
                    this.config.callbacks.onClose();
                }
            }, this.config.animation.duration);
        }

        /**
         * Create the embed container with iframe
         */
        createEmbedContainer(userInfo) {
            // Remove existing container if any
            if (this.container) {
                this.container.remove();
            }

            // Create main container
            this.container = document.createElement('div');
            this.container.className = 'livechat-embed-container';
            this.container.setAttribute('data-theme', this.config.theme);

            // Create overlay
            this.overlay = document.createElement('div');
            this.overlay.className = 'livechat-embed-overlay';
            this.overlay.addEventListener('click', () => this.close());

            // Create iframe wrapper
            const iframeWrapper = document.createElement('div');
            iframeWrapper.className = 'livechat-embed-wrapper';

            // Create header
            const header = document.createElement('div');
            header.className = 'livechat-embed-header';
            header.innerHTML = `
                <div class="livechat-embed-header-content">
                    <h3 class="livechat-embed-title">
                        <span class="livechat-embed-icon">💬</span>
                        Live Chat Support
                    </h3>
                    <div class="livechat-embed-actions">
                        <button class="livechat-embed-minimize" title="Minimize">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M8 12h8"/>
                            </svg>
                        </button>
                        <button class="livechat-embed-close" title="Close">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 6L6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            `;

            // Create iframe
            this.iframe = document.createElement('iframe');
            this.iframe.className = 'livechat-embed-iframe';
            this.iframe.src = this.generateChatUrl(userInfo);
            this.iframe.setAttribute('frameborder', '0');
            this.iframe.setAttribute('allowfullscreen', 'true');

            // Create loading indicator
            const loader = document.createElement('div');
            loader.className = 'livechat-embed-loader';
            loader.innerHTML = `
                <div class="livechat-embed-spinner"></div>
                <p>Connecting to support...</p>
            `;

            // Assemble the structure
            iframeWrapper.appendChild(header);
            iframeWrapper.appendChild(loader);
            iframeWrapper.appendChild(this.iframe);
            this.container.appendChild(this.overlay);
            this.container.appendChild(iframeWrapper);

            // Add to body
            document.body.appendChild(this.container);

            // Bind events
            this.bindContainerEvents();

            // Handle iframe load
            this.iframe.addEventListener('load', () => {
                loader.style.display = 'none';
            });
        }

        /**
         * Bind events to container elements
         */
        bindContainerEvents() {
            // Close button
            const closeBtn = this.container.querySelector('.livechat-embed-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.close());
            }

            // Minimize button
            const minimizeBtn = this.container.querySelector('.livechat-embed-minimize');
            if (minimizeBtn) {
                minimizeBtn.addEventListener('click', () => this.minimize());
            }

            // ESC key to close
            const escHandler = (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);
        }

        /**
         * Minimize the chat (convert to smaller window)
         */
        minimize() {
            if (!this.container) return;
            
            const wrapper = this.container.querySelector('.livechat-embed-wrapper');
            if (wrapper) {
                wrapper.classList.toggle('minimized');
                
                // Update minimize button icon
                const minimizeBtn = this.container.querySelector('.livechat-embed-minimize');
                if (minimizeBtn) {
                    if (wrapper.classList.contains('minimized')) {
                        minimizeBtn.innerHTML = `
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            </svg>
                        `;
                        minimizeBtn.title = 'Maximize';
                    } else {
                        minimizeBtn.innerHTML = `
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M8 12h8"/>
                            </svg>
                        `;
                        minimizeBtn.title = 'Minimize';
                    }
                }
            }
        }

        /**
         * Generate the chat URL with parameters
         */
        generateChatUrl(userInfo) {
            let url = `${this.config.baseUrl}/chat`;
            
            const params = new URLSearchParams({
                'iframe': '1',
                'api_key': this.config.apiKey,
                'embed_mode': 'fullscreen'
            });

            // Add user information if provided
            if (userInfo && !userInfo.isAnonymous) {
                if (userInfo.userId) params.append('external_system_id', userInfo.userId);
                if (userInfo.name) params.append('external_fullname', userInfo.name);
                if (userInfo.email) params.append('external_email', userInfo.email);
                if (userInfo.username) params.append('external_username', userInfo.username);
                params.append('user_role', 'loggedUser');
            } else {
                params.append('user_role', 'anonymous');
            }

            return url + '?' + params.toString();
        }

        /**
         * Update user information
         */
        updateUser(userInfo) {
            if (this.iframe && this.isOpen) {
                // Reload iframe with new user info
                this.iframe.src = this.generateChatUrl(userInfo);
            }
        }

        /**
         * Handle errors
         */
        handleError(message) {
            console.error(`LiveChatEmbed: ${message}`);
            if (this.config.callbacks.onError) {
                this.config.callbacks.onError(message);
            }
        }

        /**
         * Inject CSS styles
         */
        injectStyles() {
            if (document.getElementById('livechat-embed-styles')) return;

            const styles = document.createElement('style');
            styles.id = 'livechat-embed-styles';
            styles.textContent = `
                /* LiveChat Embed Styles */
                .livechat-embed-container {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    z-index: ${this.config.zIndex};
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    opacity: 0;
                    visibility: hidden;
                    transition: opacity ${this.config.animation.duration}ms ${this.config.animation.easing},
                                visibility ${this.config.animation.duration}ms ${this.config.animation.easing};
                }

                .livechat-embed-container.livechat-embed-active {
                    opacity: 1;
                    visibility: visible;
                }

                .livechat-embed-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.5);
                    backdrop-filter: blur(4px);
                    -webkit-backdrop-filter: blur(4px);
                }

                .livechat-embed-wrapper {
                    position: relative;
                    width: 90%;
                    height: 90%;
                    max-width: 1200px;
                    max-height: 800px;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                    display: flex;
                    flex-direction: column;
                    transform: scale(0.95);
                    transition: transform ${this.config.animation.duration}ms ${this.config.animation.easing};
                }

                .livechat-embed-active .livechat-embed-wrapper {
                    transform: scale(1);
                }

                .livechat-embed-wrapper.minimized {
                    width: 400px;
                    height: 600px;
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    top: auto;
                    left: auto;
                }

                .livechat-embed-header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 16px 20px;
                    border-radius: 12px 12px 0 0;
                    flex-shrink: 0;
                }

                .livechat-embed-header-content {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }

                .livechat-embed-title {
                    margin: 0;
                    font-size: 18px;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }

                .livechat-embed-icon {
                    font-size: 24px;
                }

                .livechat-embed-actions {
                    display: flex;
                    gap: 8px;
                }

                .livechat-embed-actions button {
                    background: rgba(255, 255, 255, 0.2);
                    border: none;
                    color: white;
                    width: 36px;
                    height: 36px;
                    border-radius: 8px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: background 0.2s;
                }

                .livechat-embed-actions button:hover {
                    background: rgba(255, 255, 255, 0.3);
                }

                .livechat-embed-iframe {
                    flex: 1;
                    width: 100%;
                    border: none;
                    border-radius: 0 0 12px 12px;
                }

                .livechat-embed-loader {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    text-align: center;
                    z-index: 10;
                }

                .livechat-embed-spinner {
                    width: 50px;
                    height: 50px;
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #667eea;
                    border-radius: 50%;
                    animation: livechat-spin 1s linear infinite;
                    margin: 0 auto 15px;
                }

                @keyframes livechat-spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }

                .livechat-embed-loader p {
                    color: #666;
                    font-size: 14px;
                    margin: 0;
                }

                /* Theme variations */
                .livechat-embed-container[data-theme="dark"] .livechat-embed-wrapper {
                    background: #1a1a1a;
                    color: white;
                }

                .livechat-embed-container[data-theme="dark"] .livechat-embed-header {
                    background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
                }

                /* Mobile responsive */
                @media (max-width: 768px) {
                    .livechat-embed-wrapper {
                        width: 100%;
                        height: 100%;
                        max-width: 100%;
                        max-height: 100%;
                        border-radius: 0;
                    }

                    .livechat-embed-header {
                        border-radius: 0;
                    }

                    .livechat-embed-iframe {
                        border-radius: 0;
                    }

                    .livechat-embed-wrapper.minimized {
                        width: 100%;
                        height: 70%;
                        bottom: 0;
                        right: 0;
                        border-radius: 12px 12px 0 0;
                    }
                }

                /* Custom scrollbar for iframe content */
                .livechat-embed-iframe::-webkit-scrollbar {
                    width: 8px;
                }

                .livechat-embed-iframe::-webkit-scrollbar-track {
                    background: #f1f1f1;
                }

                .livechat-embed-iframe::-webkit-scrollbar-thumb {
                    background: #888;
                    border-radius: 4px;
                }

                .livechat-embed-iframe::-webkit-scrollbar-thumb:hover {
                    background: #555;
                }
            `;

            // Add any custom styles
            if (this.config.customStyles) {
                for (const [selector, rules] of Object.entries(this.config.customStyles)) {
                    styles.textContent += `\n${selector} { ${rules} }`;
                }
            }

            document.head.appendChild(styles);
        }

        /**
         * Destroy the embed instance
         */
        destroy() {
            this.close();
            
            // Remove styles
            const styles = document.getElementById('livechat-embed-styles');
            if (styles) {
                styles.remove();
            }

            this.isInitialized = false;
        }
    }

    // Expose to global scope
    window.LiveChatEmbed = LiveChatEmbed;

})();
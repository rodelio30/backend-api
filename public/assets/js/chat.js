let ws = null;
let reconnectInterval = null;
let typingTimer = null;
let isTyping = false;
let displayedMessages = new Set(); 
let messageQueue = []; 
let lastMessageTime = 0; 
const MESSAGE_RATE_LIMIT = 1000;
let isInitializing = false;
const widgetBehaviorOptions = window.widgetBehaviorOptions || {};
const SOUND_DISABLED_BY_CLIENT = !!widgetBehaviorOptions.disable_sound_notification;
const TYPING_DISABLED_BY_CLIENT = !!widgetBehaviorOptions.disable_agent_typing_notification;

// Avatar generation functions
function generateInitials(name, isAgent = false) {
    if (!name || name.trim() === '') {
        return 'A';
    }
    
    // Handle Anonymous users
    if (name.toLowerCase() === 'anonymous') {
        return 'A';
    }
    
    // Split name into words and get first letter of each
    const words = name.trim().split(/\s+/);
    
    if (words.length === 1) {
        // Single word - get first letter
        return words[0].charAt(0).toUpperCase();
    } else if (words.length >= 2) {
        // Multiple words - get first letter of first and last word
        return (words[0].charAt(0) + words[words.length - 1].charAt(0)).toUpperCase();
    }
    
    return name.charAt(0).toUpperCase();
}

function createAvatar(name, type = 'customer', size = 'normal') {
    const initials = generateInitials(name, type === 'agent');
    const sizeClass = size === 'small' ? 'small' : '';
    const typeClass = name && name.toLowerCase() === 'anonymous' ? 'anonymous' : type;
    
    return `<div class="avatar ${typeClass} ${sizeClass}">${initials}</div>`;
}

// Function to add avatar to messages
function addAvatarToMessage(messageElement, senderName, senderType) {
    if (!messageElement.querySelector('.avatar')) {
        const avatarHTML = createAvatar(senderName, senderType, 'small');
        
        if (messageElement.classList.contains('system')) {
            return; // Don't add avatars to system messages
        }
        
        messageElement.insertAdjacentHTML('afterbegin', avatarHTML);
        
        // Wrap the existing content in a message-content div for proper flex layout
        const existingContent = messageElement.innerHTML.replace(avatarHTML, '');
        messageElement.innerHTML = avatarHTML + `<div class="message-content">${existingContent}</div>`;
    }
}

// Helper function to safely get DOM elements
function safeGetElement(id) {
    const element = document.getElementById(id);
    if (!element) {
        return null;
    }
    return element;
}

// Helper function to safely get session variables
function getSessionId() {
    // For admin, prioritize currentSessionId
    if (getUserType() === 'agent' && typeof currentSessionId !== 'undefined' && currentSessionId) {
        return currentSessionId;
    }
    return typeof sessionId !== 'undefined' ? sessionId : (typeof currentSessionId !== 'undefined' ? currentSessionId : null);
}

function getUserType() {
    return typeof userType !== 'undefined' ? userType : null;
}

function getUserId() {
    return typeof userId !== 'undefined' ? userId : null;
}

// Admin session refresh function
function refreshAdminSessions() {
    // Try to use JSON API first, fallback to HTML parsing
    fetch('/admin/sessions-data')
        .then(response => {
            if (response.ok) {
                return response.json();
            } else {
                // Fallback to HTML parsing
                return fetch('/admin/chat').then(r => r.text()).then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    const waitingSessions = [];
                    const activeSessions = [];
                    
                    // Parse waiting sessions
                    const waitingElements = doc.querySelectorAll('#waitingSessions .session-item');
                    waitingElements.forEach(el => {
                        const sessionId = el.getAttribute('data-session-id');
                        const name = el.querySelector('strong')?.textContent || '';
                        const time = el.querySelector('small')?.textContent || '';
                        waitingSessions.push({ session_id: sessionId, customer_name: name, created_at: time });
                    });
                    
                    // Parse active sessions
                    const activeElements = doc.querySelectorAll('#activeSessions .session-item');
                    activeElements.forEach(el => {
                        const sessionId = el.getAttribute('data-session-id');
                        const name = el.querySelector('strong')?.textContent || '';
                        const agent = el.querySelector('small')?.textContent || '';
                        activeSessions.push({ session_id: sessionId, customer_name: name, agent_name: agent });
                    });
                    
                    return { waitingSessions, activeSessions };
                });
            }
        })
        .then(data => {
            // Update waiting sessions
            const waitingContainer = document.getElementById('waitingSessions');
            const waitingCount = document.getElementById('waitingCount');
            if (waitingContainer && waitingCount) {
                waitingContainer.innerHTML = '';
                waitingCount.textContent = data.waitingSessions.length;
                
                data.waitingSessions.forEach(session => {
                    const item = document.createElement('div');
                    item.className = 'session-item';
                    item.setAttribute('data-session-id', session.session_id);
                    
                    item.innerHTML = `
                        <div class="session-info">
                            <strong>${escapeHtml(session.customer_name || 'Anonymous')}</strong>
                            <small>Topic: ${escapeHtml(session.chat_topic || 'No topic specified')}</small>
                            <small>${session.created_at}</small>
                        </div>
                        <button class="btn btn-accept" onclick="acceptChat('${session.session_id}')">Accept</button>
                    `;
                    
                    waitingContainer.appendChild(item);
                });
            }
            
            // Update active sessions
            const activeContainer = document.getElementById('activeSessions');
            const activeCount = document.getElementById('activeCount');
            if (activeContainer && activeCount) {
                activeContainer.innerHTML = '';
                activeCount.textContent = data.activeSessions.length;
                
                data.activeSessions.forEach(session => {
                    const item = document.createElement('div');
                    item.className = 'session-item active';
                    item.setAttribute('data-session-id', session.session_id);
                    item.onclick = () => openChat(session.session_id);
                    
                    item.innerHTML = `
                        <div class="session-info">
                            <strong>${escapeHtml(session.customer_name || 'Anonymous')}</strong>
                            <small>Topic: ${escapeHtml(session.chat_topic || 'No topic specified')}</small>
                            <small>Agent: ${escapeHtml(session.agent_name || 'Unassigned')}</small>
                        </div>
                        <span class="unread-badge" style="display: none;">0</span>
                    `;
                    
                    activeContainer.appendChild(item);
                });
            }
        })
        .catch(error => {
            // Error handling without console log
        });
}

// Auto-refresh admin sessions every 5 seconds
function startAdminAutoRefresh() {
    if (getUserType() === 'agent') {
        setInterval(() => {
            refreshAdminSessions();
        }, 5000); // Refresh every 5 seconds
    }
}

// Critical functions that need to be available early
async function checkSessionStatus() {
    const sessionToCheck = getSessionId();
    const userTypeToCheck = getUserType();
    
    if (sessionToCheck && userTypeToCheck === 'customer') {
        try {
            const response = await fetch(`/chat/check-session-status/${sessionToCheck}`);
            const result = await response.json();
            
            if (result.status === 'closed') {
                disableChatInput();
                showChatClosedMessage();
                displaySystemMessage('This chat session has been closed by the support team.');
                
                // Hide Leave Chat button when session is already closed
                const closeBtn = document.getElementById('customerCloseBtn');
                if (closeBtn) {
                    closeBtn.style.display = 'none';
                }
                
                return false;
            }
            return true;
        } catch (error) {
            return true;
        }
    }
    return true;
}

async function loadChatHistory() {
    const currentSession = getSessionId();
    if (!currentSession) {
        return;
    }
    
    try {
        // Check if this is a logged user with chat history enabled
        let messagesUrl = `/chat/messages/${currentSession}`;
        
        // For logged users, use the endpoint that includes historical messages
        if (typeof userRole !== 'undefined' && userRole === 'loggedUser' && 
            (externalUsername || externalFullname)) {
            const params = new URLSearchParams();
            params.append('user_role', userRole);
            if (externalUsername) {
                params.append('external_username', externalUsername);
            }
            if (externalFullname) {
                params.append('external_fullname', externalFullname);
            }
            if (externalSystemId) {
                params.append('external_system_id', externalSystemId);
            }
            
            messagesUrl = `/chat/messages-with-history/${currentSession}?${params.toString()}`;
        }
        
        const response = await fetch(messagesUrl);
        const apiResponse = await response.json();
        
        // Handle different response formats and extract debug info
        let messages = apiResponse;
        let debugInfo = null;
        
        if (apiResponse && typeof apiResponse === 'object') {
            if (apiResponse.messages) {
                messages = apiResponse.messages;
                debugInfo = apiResponse.debug;
            } else if (Array.isArray(apiResponse)) {
                messages = apiResponse;
            } else {
                console.error('❌ Unexpected response format:', apiResponse);
                messages = [];
            }
        } else if (Array.isArray(apiResponse)) {
            messages = apiResponse;
        } else {
            console.error('❌ Unexpected response format:', apiResponse);
            messages = [];
        }
        
        // Debug info available for troubleshooting if needed
        
        
        const container = document.getElementById('messagesContainer');
        if (container) {
            container.innerHTML = '';
            displayedMessages.clear(); // Clear displayed messages for fresh load
            currentDateSeparator = null; // Reset date separator tracker
            isLoadingHistory = true; // Disable automatic date separators during history loading
            
            // Extract customer name from the first customer message for future use
            if (!window.currentCustomerName && messages.length > 0) {
                const firstCustomerMessage = messages.find(msg => msg.sender_type === 'customer');
                if (firstCustomerMessage && (firstCustomerMessage.customer_name || firstCustomerMessage.customer_fullname)) {
                    window.currentCustomerName = firstCustomerMessage.customer_name || firstCustomerMessage.customer_fullname;
                }
            }
            
            // Process messages and add date separators
            let previousDate = null;
            let hasHistoricalMessages = false;
            
            // Group messages by date first to ensure proper ordering
            const messagesByDate = new Map();
            
            messages.forEach((message) => {
                // Ensure each message has proper timestamp
                message = ensureMessageTimestamp(message);
                
                // Skip if this is already a date separator from server
                if (message.type === 'date_separator') {
                    return;
                }

                const messageTimestamp = message.created_at || message.timestamp;
                const messageDate = new Date(messageTimestamp).toDateString();
                const formattedDate = formatChatDate(messageTimestamp);
                
                // Check if this message is from a different session (historical)
                const isHistoricalMessage = message.chat_session_id && message.chat_session_id !== currentSession;
                
                // Track historical messages for notification
                if (isHistoricalMessage && !hasHistoricalMessages) {
                    hasHistoricalMessages = true;
                }
                
                // Add special styling for historical messages
                if (isHistoricalMessage) {
                    message._isHistorical = true;
                }
                
                // Group messages by date
                if (!messagesByDate.has(messageDate)) {
                    messagesByDate.set(messageDate, {
                        formattedDate: formattedDate,
                        messages: []
                    });
                }
                messagesByDate.get(messageDate).messages.push(message);
            });
            
            // Sort date groups chronologically and display them
            const sortedDates = Array.from(messagesByDate.entries()).sort((a, b) => {
                // Sort by the actual date, not the string
                const dateA = new Date(a[0]);
                const dateB = new Date(b[0]);
                return dateA - dateB;
            });
            
            // Now display messages in chronological order
            sortedDates.forEach(([dateKey, dateGroup], groupIndex) => {
                // Add date separator for this group
                currentDateSeparator = displayDateSeparator(dateGroup.formattedDate);
                
                // Add all messages for this date
                dateGroup.messages.forEach((message, msgIndex) => {
                    displayMessage(message);
                });
            });
            
            // Show a notification if historical messages were loaded
            if (hasHistoricalMessages && messages.length > 0) {
                const historicalCount = messages.filter(msg => msg.chat_session_id !== currentSession).length;
                showHistoryLoadedNotification(historicalCount);
            }
            
            // Re-enable automatic date separators after history loading is complete
            isLoadingHistory = false;
        }
    } catch (error) {
        console.error('❌ Error loading chat history:', error.message);
    }
}

async function acceptChat(sessionId) {
    console.log('acceptChat function called with sessionId:', sessionId);
    console.log('Current user type:', getUserType());
    console.log('Current user ID:', getUserId());
    
    try {
        console.log('Making HTTP request to assign agent...');
        // First, assign the agent via HTTP
        const response = await fetch('/chat/assign-agent', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `session_id=${sessionId}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Then notify WebSocket server
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({
                    type: 'assign_agent',
                    session_id: sessionId,
                    agent_id: userId
                }));
            }
            
            // Open the chat after successful assignment
            openChat(sessionId);
        } else {
            alert('Failed to accept chat. Please try again.');
        }
    } catch (error) {
        alert('Failed to accept chat. Please try again.');
    }
}

function openChat(sessionId) {
    currentSessionId = sessionId;
    const chatPanel = document.getElementById('chatPanel');
    if (chatPanel) {
        chatPanel.style.display = 'flex';
    }
    
    displayedMessages.clear();
    
    const sessionItem = document.querySelector(`[data-session-id="${sessionId}"]`);
    if (sessionItem) {
        const customerName = sessionItem.querySelector('strong').textContent;
        const customerNameElement = document.getElementById('chatCustomerName');
        if (customerNameElement) {
            customerNameElement.textContent = customerName;
        }
    }
    
    document.querySelectorAll('.session-item').forEach(item => {
        item.classList.remove('active');
    });
    sessionItem.classList.add('active');
    
    if (ws && ws.readyState === WebSocket.OPEN) {
        const registerData = {
            type: 'register',
            session_id: sessionId,
            user_type: 'agent',
            user_id: userId
        };
        ws.send(JSON.stringify(registerData));
    }
    
    loadChatHistoryForSession(sessionId);
    
    // Start periodic refresh for admin to catch system messages
    if (getUserType() === 'agent') {
        startMessageRefresh(sessionId);
    }
    
    // Re-initialize message form for admin after opening chat
    setTimeout(() => {
        initializeMessageForm();
        if (getUserType() === 'agent') {
            initQuickActions();
        }
    }, 500);
    
    // Ensure chat input is enabled for admin
    const input = document.getElementById('messageInput');
    const button = document.querySelector('.btn-send');
    if (input) {
        input.disabled = false;
        input.placeholder = 'Type your message...';
    }
    if (button) {
        button.disabled = false;
        button.textContent = 'Send';
    }
    
    // Remove any "chat ended" messages for admin
    const closedMessage = document.querySelector('.chat-closed-message');
    if (closedMessage) {
        closedMessage.remove();
    }
    
    // Clear any system messages about chat ending
    const systemMessages = document.querySelectorAll('.message.system');
    systemMessages.forEach(msg => {
        if (msg.textContent.includes('ended') || msg.textContent.includes('closed')) {
            msg.remove();
        }
    });
}

function closeCurrentChat() {
    if (currentSessionId && confirm('Are you sure you want to close this chat?')) {
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({
                type: 'close_session',
                session_id: currentSessionId
            }));
        }
        
        fetch('/chat/close-session', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `session_id=${currentSessionId}`
        });
        
        // Stop message refresh when closing chat
        stopMessageRefresh();
        
        const chatPanel = document.getElementById('chatPanel');
        if (chatPanel) {
            chatPanel.style.display = 'none';
        }
        currentSessionId = null;
    }
}

async function loadChatHistoryForSession(sessionId) {
    if (!sessionId) return;
    
    try {
        // Use backend=1 parameter only for admin/agent users to filter out system messages
        const backendParam = getUserType() === 'agent' ? '?backend=1' : '';
        const response = await fetch(`/chat/messages/${sessionId}${backendParam}`);
        const messages = await response.json();
        
        const container = document.getElementById('messagesContainer');
        if (container) {
            container.innerHTML = '';
            displayedMessages.clear();
            
            // Extract agent name from the first agent message for future use (if not already set)
            if (!window.currentAgentName && messages.length > 0) {
                const firstAgentMessage = messages.find(msg => msg.sender_type === 'agent');
                if (firstAgentMessage && (firstAgentMessage.agent_name || firstAgentMessage.sender_name)) {
                    window.currentAgentName = firstAgentMessage.agent_name || firstAgentMessage.sender_name;
                }
            }
            
            messages.forEach(message => {
                // Ensure each message has proper timestamp
                message = ensureMessageTimestamp(message);
                displayMessage(message);
            });
        }
    } catch (error) {
        // Error handling without console log
    }
}

function displaySystemMessage(message) {
    const container = document.getElementById('messagesContainer');
    if (!container) return;
    
    // Check if we need to add a date separator before this system message
    const existingMessages = container.querySelectorAll('.message:not(.date-separator)');
    const currentDate = new Date();
    const currentDateString = currentDate.toDateString();
    
    if (existingMessages.length === 0) {
        // First message, add date separator
        displayDateSeparator(formatChatDate(currentDate.toISOString()));
    } else {
        // Check if we need a date separator compared to the last message
        const lastMessage = existingMessages[existingMessages.length - 1];
        const lastMessageDate = lastMessage.dataset.messageDate;
        
        if (lastMessageDate && lastMessageDate !== currentDateString) {
            displayDateSeparator(formatChatDate(currentDate.toISOString()));
        }
    }
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message system';
    
    // Add proper date metadata to system messages
    messageDiv.dataset.messageDate = currentDateString;
    messageDiv.dataset.messageId = `system_${Date.now()}`;
    
    const p = document.createElement('p');
    p.textContent = message;
    
    messageDiv.appendChild(p);
    container.appendChild(messageDiv);
    
    container.scrollTop = container.scrollHeight;
}

function updateConnectingMessage(newMessage) {
    const container = document.getElementById('messagesContainer');
    if (!container) return;
    
    // Find the initial "Connecting to support..." message
    const systemMessages = container.querySelectorAll('.message.system p');
    systemMessages.forEach(p => {
        if (p.textContent.includes('Connecting to support')) {
            p.textContent = newMessage;
        }
    });
}

function disableChatInput() {
    const input = document.getElementById('messageInput');
    const button = document.querySelector('.btn-send');
    
    if (input) {
        input.disabled = true;
        input.placeholder = 'Chat session has ended';
    }
    if (button) {
        button.disabled = true;
        button.textContent = 'Chat Ended';
    }
}

function showChatClosedMessage() {
    const chatInterface = document.getElementById('chatInterface');
    if (chatInterface) {
        const closedMessage = document.createElement('div');
        closedMessage.className = 'chat-closed-message';
        closedMessage.innerHTML = `
            <div class="closed-overlay">
                <div class="closed-content">
                    <h3>Chat Session Ended</h3>
                    <p>This chat session has been closed by the support team.</p>
                    <p>Thank you for contacting us!</p>
                    <button class="btn btn-primary start-new-chat-btn-old" onclick="startNewChat()">
                        Start New Chat
                    </button>
                </div>
            </div>
        `;
        chatInterface.appendChild(closedMessage);
    }
}

// Function to show and properly initialize the customer close button
function showCustomerCloseButton() {
    const closeBtn = document.getElementById('customerCloseBtn');
    if (closeBtn) {
        // Reset button state in case it was stuck in "Ending..." state
        closeBtn.disabled = false;
        closeBtn.textContent = 'Leave Chat';
        closeBtn.style.display = 'inline-block';
    }
}

function startNewChat() {
    sessionId = null;
    currentSessionId = null;
    
    const closedMessage = document.querySelector('.chat-closed-message');
    if (closedMessage) {
        closedMessage.remove();
    }
    
    const chatInterface = document.getElementById('chatInterface');
    if (chatInterface) {
        // Check if role information is available (from customer view)
        const currentUserRole = typeof userRole !== 'undefined' ? userRole : 'anonymous';
        const currentExternalUsername = typeof externalUsername !== 'undefined' ? externalUsername : '';
        const currentExternalFullname = typeof externalFullname !== 'undefined' ? externalFullname : '';
        const currentExternalSystemId = typeof externalSystemId !== 'undefined' ? externalSystemId : '';
        const currentApiKey = typeof apiKey !== 'undefined' ? apiKey : '';
        const currentCustomerPhone = typeof customerPhone !== 'undefined' ? customerPhone : '';
        const currentExternalEmail = typeof externalEmail !== 'undefined' ? externalEmail : '';
        
        // For logged users, provide seamless experience
        if (currentUserRole === 'loggedUser' && (currentExternalFullname || currentExternalUsername)) {
            // Show seamless restart interface
            chatInterface.innerHTML = `
                <div class="chat-start-form" style="text-align: center; padding: 30px;">
                    <h4>Chat Session Ended</h4>
                    <p style="color: #666; margin-bottom: 25px;">Thank you for contacting us. Your conversation has ended.</p>
                    <p style="color: #28a745; font-size: 14px; margin-bottom: 20px;">
                        ✓ Logged in as ${currentExternalFullname || currentExternalUsername}
                    </p>
                    <button type="button" class="btn btn-primary start-new-chat-btn-direct" 
                           style="padding: 12px 24px; font-size: 16px;">
                        Start New Chat
                    </button>
                </div>
            `;
            
            // Add event listener directly with closure to capture current variables
            setTimeout(() => {
                const button = chatInterface.querySelector('.start-new-chat-btn-direct');
                if (button) {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        startNewChatForLoggedUser(
                            currentUserRole, 
                            currentExternalUsername, 
                            currentExternalFullname, 
                            currentExternalSystemId, 
                            currentApiKey, 
                            currentCustomerPhone, 
                            currentExternalEmail
                        );
                    });
                }
            }, 100); // Small delay to ensure DOM is updated
        } else {
            // For anonymous users, show the full form
            const nameFieldHtml = `
                <div class="form-group">
                    <label for="customerName">Your Name (Optional)</label>
                    <input type="text" id="customerName" name="customer_name" placeholder="Enter your name (or leave blank for Anonymous)">
                </div>
            `;
            
            const roleFieldsHtml = `
                <input type="hidden" name="user_role" value="${currentUserRole}">
                <input type="hidden" name="external_username" value="${currentExternalUsername}">
                <input type="hidden" name="external_fullname" value="${currentExternalFullname}">
                <input type="hidden" name="external_system_id" value="${currentExternalSystemId}">
                <input type="hidden" name="api_key" value="${currentApiKey}">
                <input type="hidden" name="customer_phone" value="${currentCustomerPhone}">
            `;
            
            chatInterface.innerHTML = `
                <div class="chat-start-form">
                    <h4>Start a New Chat Session</h4>
                    <form id="startChatForm">
                        ${roleFieldsHtml}
                        ${nameFieldHtml}
                        <div class="form-group">
                            <label for="chatTopic">What do you need help with?</label>
                            <input type="text" id="chatTopic" name="chat_topic" required placeholder="Describe your issue or question...">
                        </div>
                        <div class="form-group">
                            <label for="email">Email (Optional)</label>
                            <input type="email" id="email" name="email" value="${currentExternalEmail}">
                        </div>
                        <button type="submit" class="btn btn-primary">Start Chat</button>
                    </form>
                </div>
            `;
        }
    }
}

// Function to start new chat for logged users without form (global version)
function startNewChatForLoggedUser(userRoleParam, externalUsernameParam, externalFullnameParam, externalSystemIdParam, apiKeyParam, customerPhoneParam, externalEmailParam) {
    const chatInterface = document.getElementById('chatInterface');
    
    // Show loading state
    if (chatInterface) {
        chatInterface.innerHTML = `
            <div class="chat-loading" style="text-align: center; padding: 40px;">
                <div class="loading-spinner"></div>
                <h4>Starting new chat session...</h4>
                <p class="loading-message">Please wait while we prepare your chat.</p>
            </div>
        `;
    }
    
    // Create form data with existing user information
    const formData = new FormData();
    formData.append('user_role', userRoleParam);
    formData.append('external_username', externalUsernameParam);
    formData.append('external_fullname', externalFullnameParam);
    formData.append('external_system_id', externalSystemIdParam);
    formData.append('api_key', apiKeyParam);
    formData.append('customer_phone', customerPhoneParam);
    formData.append('customer_name', externalFullnameParam || externalUsernameParam);
    formData.append('chat_topic', 'General Support'); // Default topic for logged users
    formData.append('email', externalEmailParam);
    
    
    // Start new session
    fetch('/chat/start-session', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(result => {
        if (result.success) {
            sessionId = result.session_id;
            currentSessionId = result.session_id;
            
            if (chatInterface) {
                chatInterface.innerHTML = `
                    <div class="chat-window customer-chat" data-session-id="${result.session_id}">
                        <div class="messages-container" id="messagesContainer">
                            <div class="message system">
                                <p>Connecting to support...</p>
                            </div>
                        </div>
                        
                        ${TYPING_DISABLED_BY_CLIENT ? '' : `<div class="typing-indicator" id="typingIndicator" style="display: none;">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>`}
                        
                        <div class="chat-input-area">
                            <!-- Voice Recording UI -->
                            <div id="voiceRecordingUI" class="voice-recording-ui" style="display: none;">
                                <div class="recording-content">
                                    <div class="recording-indicator">
                                        <i class="bi bi-mic-fill recording-icon"></i>
                                        <span class="recording-text">Recording...</span>
                                        <span class="recording-timer" id="recordingTimer">00:00</span>
                                    </div>
                                    <button type="button" class="btn-cancel-recording" onclick="cancelVoiceRecording()" title="Cancel recording">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <form id="messageForm">
                                <div class="input-group">
                                    <input type="file" id="fileInput" class="file-input-hidden" onchange="handleFileSelect(event)" accept="*/*">
                                    <button type="button" class="file-upload-btn" onclick="triggerFileUpload()" title="Attach file">
                                        <i class="bi bi-paperclip"></i>
                                    </button>
                                    <button type="button" class="voice-record-btn" id="voiceRecordBtn" title="Hold to record voice message">
                                        <i class="bi bi-mic-fill"></i>
                                    </button>
                                    <input type="text" id="messageInput" class="form-control" placeholder="Type your message..." autocomplete="off">
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-send">Send</button>
                                    </div>
                                </div>
                            </form>
                            
                            <!-- File Upload Progress -->
                            <div id="fileUploadProgress" class="file-upload-progress" style="display: none;">
                                <div class="progress-bar">
                                    <div class="progress-fill"></div>
                                </div>
                                <span class="progress-text">Uploading file...</span>
                            </div>
                            
                            <!-- File Preview -->
                            <div id="filePreview" class="file-preview" style="display: none;">
                                <div class="preview-content">
                                    <span class="file-info">
                                        <i class="file-icon"></i>
                                        <span class="file-name"></span>
                                        <span class="file-size"></span>
                                    </span>
                                    <button type="button" class="btn-remove-file" onclick="removeFilePreview()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Voice Message Preview (before sending) -->
                            <div id="voicePreview" class="voice-preview" style="display: none;">
                                <div class="preview-content">
                                    <div class="voice-info">
                                        <i class="bi bi-mic-fill" style="color: #667eea;"></i>
                                        <span class="voice-duration" id="voiceDuration">00:00</span>
                                        <span class="voice-label">Voice Message</span>
                                    </div>
                                    <button type="button" class="btn-remove-voice" onclick="removeVoicePreview()">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                    
                    // Initialize WebSocket and chat functionality
                    initWebSocket();
                    
                    // Re-initialize voice recording immediately after creating the UI
                    if (typeof window.reinitializeVoiceRecording === 'function') {
                        window.reinitializeVoiceRecording();
                    } else if (typeof initializeVoiceRecording === 'function') {
                        setTimeout(initializeVoiceRecording, 100);
                    }
                
                // Initialize the message form with a slight delay
                setTimeout(() => {
                    initializeMessageForm();
                    
                    // Re-initialize voice recording after form is cloned (this removes event listeners)
                    if (typeof window.reinitializeVoiceRecording === 'function') {
                        setTimeout(() => {
                            window.reinitializeVoiceRecording();
                        }, 100);
                    }
                    
                    // Load quick actions if available
                    if (typeof fetchQuickActions === 'function') {
                        fetchQuickActions();
                    }
                    
                    // Initialize typing functionality if available
                    if (typeof initializeTypingForCustomer === 'function') {
                        initializeTypingForCustomer();
                    }
                }, 1000);
            }
        } else {
            // Show error and revert to previous state
            alert(result.error || 'Failed to start new chat session');
            startNewChat(); // Fall back to regular start chat
        }
    })
    .catch(error => {
        alert('Failed to connect. Please try again.');
        startNewChat(); // Fall back to regular start chat
    });
}

// Old data attributes event listener removed - now using closure approach above

// Initialize WebSocket connection
let wsUrls = [
    // 'wss://ws.kopisugar.cc:39147'
    'wss://api-taapin.danhar.cc:8443'
];
let currentUrlIndex = 0;

function initWebSocket() {
    if (ws && ws.readyState !== WebSocket.CLOSED) {
        ws.close();
    }
    
    const wsUrl = wsUrls[currentUrlIndex];
    
    ws = new WebSocket(wsUrl);
    
    ws.onopen = function() {
        const connectionStatus = safeGetElement('connectionStatus');
        if (connectionStatus) {
            connectionStatus.textContent = 'Online';
            connectionStatus.classList.add('online');
        }
        
        setTimeout(() => {
            const currentUserType = getUserType();
            if (currentUserType) {
                const currentSession = getSessionId();
                const currentUserId = getUserId();
                const registerData = {
                    type: 'register',
                    session_id: currentSession || null,
                    user_type: currentUserType,
                    user_id: currentUserId
                };
                
                ws.send(JSON.stringify(registerData));
            }
        }, 100);
        
        if (reconnectInterval) {
            clearInterval(reconnectInterval);
            reconnectInterval = null;
        }
        
        if (messageQueue.length > 0) {
            messageQueue.forEach(msg => {
                ws.send(JSON.stringify(msg));
            });
            messageQueue = [];
        }
        
        if (userType === 'agent') {
            setInterval(refreshAdminSessions, 10000);
        } else if (userType === 'customer') {
            // Show Leave Chat button when WebSocket connects and there's an active session
            const currentSession = getSessionId();
            if (currentSession) {
                showCustomerCloseButton();
            }
        }
    };
    
    ws.onmessage = function(event) {
        const data = JSON.parse(event.data);
        handleWebSocketMessage(data);
    };
    
    ws.onclose = function() {
        const connectionStatus = safeGetElement('connectionStatus');
        if (connectionStatus) {
            connectionStatus.textContent = 'Offline';
            connectionStatus.classList.remove('online');
        }
        
        if (!reconnectInterval) {
            reconnectInterval = setInterval(function() {
                initWebSocket();
            }, 5000);
        }
    };
    
    ws.onerror = function(error) {
        const connectionStatus = safeGetElement('connectionStatus');
        if (connectionStatus) {
            connectionStatus.textContent = 'Connection Error';
            connectionStatus.classList.remove('online');
        }
        
        // Try next URL if available
        if (currentUrlIndex < wsUrls.length - 1) {
            currentUrlIndex++;
            setTimeout(() => {
                initWebSocket();
            }, 2000); // Wait 2 seconds before trying next URL
        } else {
            // Reset to first URL for reconnect attempts
            currentUrlIndex = 0;
            displaySystemMessage('Connection error. All connection methods failed. Retrying...');
        }
    };
}

// Handle incoming WebSocket messages
function handleWebSocketMessage(data) {
    switch (data.type) {
        case 'connected':
            const connectedSession = getSessionId();
            const connectedUserType = getUserType();
            
            if (connectedSession) {
                if (connectedUserType === 'customer') {
                    // Update the initial "Connecting to support..." message
                    updateConnectingMessage('Connected to Chat');
                    loadChatHistory();
                    
                    // Show Leave Chat button now that we're successfully connected to the chat
                    const closeBtn = document.getElementById('customerCloseBtn');
                    if (closeBtn) {
                        closeBtn.style.display = 'inline-block';
                    }
                } else if (connectedUserType === 'agent' && currentSessionId) {
                    loadChatHistoryForSession(currentSessionId);
                }
            }
            break;
            
        case 'message':
            // Check for duplicate of our own message BEFORE clearing tracking variables
            if (window.lastSentMessageContent && data.message && 
                data.message.toLowerCase().trim() === window.lastSentMessageContent && 
                data.sender_type === getUserType()) {
                // Clear tracking variables for our own message
                window.lastSentMessageContent = null;
                window.lastMessageTime = null;
                window.justSentMessage = false;
                return; // Exit early, don't display the message
            }
            
            if (data.message_type !== 'system') {
                addDateSeparatorIfNeeded(data);
            }
            
            // Clear the last sent message ID since we received it from server
            if (window.lastSentMessageId) {
                window.lastSentMessageId = null;
            }
            
            // Clear the last sent message content as well
            if (window.lastSentMessageContent) {
                window.lastSentMessageContent = null;
            }
            
            // Clear the last message time as well
            if (window.lastMessageTime) {
                window.lastMessageTime = null;
            }
            
            // Clear tracking variables when we receive our own message
            if (data.sender_type === getUserType()) {
                window.lastSentMessageContent = null;
                window.lastMessageTime = null;
                window.justSentMessage = false;
            }
            
            const messageUserType = getUserType();
            const messageSession = getSessionId();
            
            // Always display system messages that match the current session
            if (data.message_type === 'system') {
                if ((messageUserType === 'agent' && currentSessionId && data.session_id === currentSessionId) ||
                    (messageUserType === 'customer' && messageSession && data.session_id === messageSession)) {
                    displayMessage(data);
                    playNotificationSound();
                }
            }
            // Handle regular messages
            else if (messageUserType === 'agent' && currentSessionId && data.session_id === currentSessionId) {
                displayMessage(data);
                playNotificationSound();
            } else if (messageUserType === 'customer' && messageSession && data.session_id === messageSession) {
                displayMessage(data);
                playNotificationSound();
            } else {
                // Try to display message anyway if session IDs match
                if (data.session_id === (messageSession || currentSessionId)) {
                    displayMessage(data);
                }
            }
            break;
            
        case 'typing':
            handleTypingIndicator(data);
            break;
            
        case 'agent_assigned':
            // Only show "An agent has joined the chat" for the correct session
            const currentUserType = getUserType();
            const currentUserSession = getSessionId();
            
            // For customers, check if this assignment is for their session
            if (currentUserType === 'customer' && currentUserSession && data.session_id === currentUserSession) {
                displaySystemMessage(data.message);
                playNotificationSound();
                
                // Update the "Connecting to support..." message if it exists
                updateConnectingMessage('An agent has joined the chat');
            }
            // For agents, show the message if they're viewing the correct session
            else if (currentUserType === 'agent' && currentSessionId && data.session_id === currentSessionId) {
                displaySystemMessage(data.message);
                playNotificationSound();
            }
            break;
            
        case 'session_closed':
            displaySystemMessage(data.message);
            disableChatInput();
            showChatClosedMessage();
            
            // Hide Leave Chat button when session is closed by admin
            const closeBtn = document.getElementById('customerCloseBtn');
            if (closeBtn) {
                closeBtn.style.display = 'none';
            }
            
            // Clear session variables so new chat can be started
            sessionId = null;
            currentSessionId = null;
            break;
            
        case 'waiting_sessions':
            updateWaitingSessions(data.sessions);
            break;
            
        case 'update_sessions':
            refreshAdminSessions();
            break;
            
            
        case 'system_message':
            displaySystemMessage(data.message);
            break;
    }
}

// Customer chat functions - Use event delegation to handle dynamically created forms
document.addEventListener('submit', async function(e) {
    if (e.target && e.target.id === 'startChatForm') {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : 'Start Chat';
        
        // Disable submit button to prevent double submission
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Starting Chat...';
        }
        
        try {
            const response = await fetch('/chat/start-session', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
                if (result.success) {
                    sessionId = result.session_id;
                    currentSessionId = result.session_id;
                    
                    // Store customer name for immediate message display
                    const formData = new FormData(e.target);
                    const customerName = formData.get('customer_name') || formData.get('external_fullname') || formData.get('external_username') || 'anonymous';
                    window.currentCustomerName = customerName;
                    
                    const chatInterface = document.getElementById('chatInterface');
                    if (chatInterface) {
                        const currentDate = new Date();
                        const currentDateString = currentDate.toDateString();
                        const formattedDate = formatChatDate(currentDate.toISOString());
                        
                        chatInterface.innerHTML = `
                            <div class="chat-window" data-session-id="${result.session_id}">
                                <div class="messages-container" id="messagesContainer">
                                    <div class="date-separator ${getDateType(formattedDate)}" data-separator-id="date_${currentDateString.replace(/[^a-zA-Z0-9]/g, '_')}">
                                        <div class="date-badge">${formattedDate}</div>
                                    </div>
                                    <div class="message system" data-message-date="${currentDateString}" data-message-id="system_${Date.now()}">
                                        <p>Connecting to support...</p>
                                    </div>
                                </div>
                                
                                ${TYPING_DISABLED_BY_CLIENT ? '' : `<div class="typing-indicator" id="typingIndicator" style="display: none;">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>`}
                                
                                <!-- Quick Action Toolbar -->
                                <div class="quick-actions-toolbar" id="quickActionsToolbar">
                                    <div class="quick-actions-buttons" id="quickActionsButtons">
                                        <!-- Quick action buttons will be loaded here -->
                                    </div>
                                </div>
                                
                                <div class="chat-input-area">
                                    <!-- Voice Recording UI -->
                                    <div id="voiceRecordingUI" class="voice-recording-ui" style="display: none;">
                                        <div class="recording-content">
                                            <div class="recording-indicator">
                                                <i class="bi bi-mic-fill recording-icon"></i>
                                                <span class="recording-text">Recording...</span>
                                                <span class="recording-timer" id="recordingTimer">00:00</span>
                                            </div>
                                            <button type="button" class="btn-cancel-recording" onclick="cancelVoiceRecording()" title="Cancel recording">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <form id="messageForm">
                                        <div class="input-group">
                                            <input type="file" id="fileInput" class="file-input-hidden" onchange="handleFileSelect(event)" accept="*/*">
                                            <button type="button" class="file-upload-btn" onclick="triggerFileUpload()" title="Attach file">
                                                <i class="bi bi-paperclip"></i>
                                            </button>
                                            <button type="button" class="voice-record-btn" id="voiceRecordBtn" title="Hold to record voice message">
                                                <i class="bi bi-mic-fill"></i>
                                            </button>
                                            <input type="text" id="messageInput" class="form-control" placeholder="Type your message..." autocomplete="off">
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-send">Send</button>
                                            </div>
                                        </div>
                                    </form>
                                    
                                    <!-- File Upload Progress -->
                                    <div id="fileUploadProgress" class="file-upload-progress" style="display: none;">
                                        <div class="progress-bar">
                                            <div class="progress-fill"></div>
                                        </div>
                                        <span class="progress-text">Uploading file...</span>
                                    </div>
                                    
                                    <!-- File Preview -->
                                    <div id="filePreview" class="file-preview" style="display: none;">
                                        <div class="preview-content">
                                            <span class="file-info">
                                                <i class="file-icon"></i>
                                                <span class="file-name"></span>
                                                <span class="file-size"></span>
                                            </span>
                                            <button type="button" class="btn-remove-file" onclick="removeFilePreview()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Voice Message Preview (before sending) -->
                                    <div id="voicePreview" class="voice-preview" style="display: none;">
                                        <div class="preview-content">
                                            <div class="voice-info">
                                                <i class="bi bi-mic-fill" style="color: #667eea;"></i>
                                                <span class="voice-duration" id="voiceDuration">00:00</span>
                                                <span class="voice-label">Voice Message</span>
                                            </div>
                                            <button type="button" class="btn-remove-voice" onclick="removeVoicePreview()">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        // Re-initialize WebSocket connection with the new session ID
                        initWebSocket();
                        initializeMessageForm();
                        
                        // Re-initialize voice recording after form is cloned (this removes event listeners)
                        setTimeout(() => {
                            if (typeof window.reinitializeVoiceRecording === 'function') {
                                window.reinitializeVoiceRecording();
                            } else if (typeof initializeVoiceRecording === 'function') {
                                initializeVoiceRecording();
                            }
                        }, 100);
                        
                        // IMPORTANT: Re-register with WebSocket using the new session ID
                        // Wait a moment for WebSocket to connect, then register with session
                        setTimeout(() => {
                            if (ws && ws.readyState === WebSocket.OPEN) {
                                console.log('Re-registering customer with session ID:', result.session_id);
                                const registerData = {
                                    type: 'register',
                                    session_id: result.session_id,
                                    user_type: 'customer',
                                    user_id: null
                                };
                                ws.send(JSON.stringify(registerData));
                            }
                        }, 1000);
                    
                    // Note: Leave Chat button will be shown when WebSocket connects successfully
                    // This ensures the user is actually connected before showing the leave option
                    
                    // Load quick actions for the customer
                    setTimeout(() => {
                        if (typeof fetchQuickActions === 'function') {
                            fetchQuickActions();
                        }
                        
                        // Explicitly load chat history for the new session
                        // This ensures history loads even if WebSocket 'connected' event doesn't fire
                        if (typeof loadChatHistory === 'function') {
                            loadChatHistory();
                        }
                    }, 1000);
                }
            } else {
                alert(result.error || 'Failed to start chat');
                // Re-enable submit button on error
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            }
        } catch (error) {
            alert('Failed to connect. Please try again.');
            // Re-enable submit button on error
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    }
});

// Initialize message form handler
function initializeMessageForm() {
    const messageForm = document.getElementById('messageForm');
    
    if (messageForm) {
        const newForm = messageForm.cloneNode(true);
        messageForm.parentNode.replaceChild(newForm, messageForm);
        
        const freshMessageForm = document.getElementById('messageForm');
        
        freshMessageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Check if the customer page has submitMessageOrFile function (for file upload support)
            if (typeof submitMessageOrFile === 'function') {
                submitMessageOrFile(e);
                return;
            }
            
            // Fallback to original text-only message handling
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (messageInput.disabled) {
                return;
            }
            
            const now = Date.now();
            if (now - lastMessageTime < MESSAGE_RATE_LIMIT) {
                return;
            }
            
            // For admin, use currentSessionId if getSessionId() is null
            const sessionToUse = getSessionId() || currentSessionId;
            
            if (message && ws && ws.readyState === WebSocket.OPEN && sessionToUse) {
                const messageData = {
                    type: 'message',
                    session_id: sessionToUse,
                    message: message,
                    sender_type: getUserType(),
                    sender_id: getUserId()
                };
                
                ws.send(JSON.stringify(messageData));
                messageInput.value = '';
                lastMessageTime = now;
                
                // Display message immediately for sender
                const immediateMessage = {
                    type: 'message',
                    session_id: sessionToUse,
                    sender_type: getUserType(),
                    message: message,
                    timestamp: new Date().toISOString(),
                    id: 'temp_' + Date.now() // Temporary ID for immediate display
                };
                displayMessage(immediateMessage);
                
                // Store this message ID and content to prevent duplicate display when received from WebSocket
                window.lastSentMessageId = immediateMessage.id;
                window.lastSentMessageContent = message.toLowerCase().trim();
                window.lastMessageTime = Date.now();
                window.justSentMessage = true; // Flag to indicate we just sent a message
                
                // Clear tracking variables after 3 seconds to prevent permanent blocking
                setTimeout(() => {
                    if (window.lastSentMessageContent === message.toLowerCase().trim()) {
                        window.lastSentMessageContent = null;
                        window.lastMessageTime = null;
                        window.justSentMessage = false;
                    }
                }, 3000);
                
                if (isTyping) {
                    sendTypingIndicator(false);
                }
            } else if (message) {
                messageQueue.push({
                    type: 'message',
                    session_id: sessionToUse,
                    message: message,
                    sender_type: getUserType(),
                    sender_id: getUserId()
                });
                messageInput.value = '';
            }
        });
        
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                if (this.disabled) {
                    return;
                }
                
                if (!isTyping) {
                    sendTypingIndicator(true);
                }
                
                clearTimeout(typingTimer);
                typingTimer = setTimeout(function() {
                    sendTypingIndicator(false);
                }, 1000);
            });
        }
    }
}

// Send typing indicator
function sendTypingIndicator(typing) {
    if (ws && ws.readyState === WebSocket.OPEN) {
        isTyping = typing;
        const currentSession = getSessionId();
        ws.send(JSON.stringify({
            type: 'typing',
            session_id: currentSession,
            user_type: getUserType(),
            is_typing: typing
        }));
    }
}

// Handle typing indicator display
function handleTypingIndicator(data) {
    const indicator = document.getElementById('typingIndicator');
    if (indicator) {
        // Only show typing indicator if:
        // 1. Someone is actually typing (data.is_typing is true)
        // 2. It's not the current user typing (data.user_type !== getUserType())
        // 3. The data contains a valid session_id that matches current session
        const currentSession = getSessionId();
        if (data.is_typing && data.user_type !== getUserType() && data.session_id === currentSession) {
            indicator.style.display = 'flex';
        } else {
            indicator.style.display = 'none';
        }
    }
}

// Display message
function displayMessage(data) {
    const container = document.getElementById('messagesContainer');
    if (!container) {
        return;
    }
    
    // Check if this is a date separator from server
    if (data.type === 'date_separator') {
        displayDateSeparator(data.date, data.id);
        return;
    }
    
    // Ensure message has proper timestamp
    data = ensureMessageTimestamp(data);
    
    // Only add automatic date separators if not loading history
    // During history loading, date separators are managed by loadChatHistory()
    if (!isLoadingHistory) {
        // Check if we need to add a date separator before this message
        const existingMessages = container.querySelectorAll('.message:not(.date-separator)');
        if (existingMessages.length > 0) {
            const lastMessage = existingMessages[existingMessages.length - 1];
            const lastMessageDate = lastMessage.dataset.messageDate;
            const messageTimestamp = data.created_at || data.timestamp || Date.now();
            const currentMessageDate = new Date(messageTimestamp).toDateString();
            
            if (lastMessageDate !== currentMessageDate) {
                currentDateSeparator = displayDateSeparator(formatChatDate(messageTimestamp));
            }
        } else {
            // First message, always show date separator
            currentDateSeparator = displayDateSeparator(formatChatDate(data.created_at || data.timestamp));
        }
    }
    
    // Use the same ID generation logic as refreshMessagesForSession
    const messageContent = data.message ? data.message.toLowerCase().trim() : '';
    const messageId = data.id ? `db_${data.id}` : `${data.sender_type}_${messageContent}_${data.timestamp}`;
    
    if (displayedMessages.has(messageId)) {
        return;
    }
    
    displayedMessages.add(messageId);
    
    const messageDiv = document.createElement('div');
    
    // Set the message date for future date separator checks
    const messageTimestamp = data.created_at || data.timestamp || Date.now();
    messageDiv.dataset.messageId = data.id || messageId;
    messageDiv.dataset.messageDate = new Date(messageTimestamp).toDateString();
    
    // Handle system messages specially - check for message_type = 'system'
    if (data.message_type === 'system') {
        messageDiv.className = 'message system';
        const p = document.createElement('p');
        p.textContent = data.message;
        messageDiv.appendChild(p);
    } else {
        // Regular message handling
        if (userType === 'agent') {
            if (data.sender_type === 'customer') {
                messageDiv.className = 'message customer';
            } else {
                messageDiv.className = 'message agent';
            }
        } else {
            messageDiv.className = `message ${data.sender_type}`;
        }
        
        // Determine sender name for avatar
        let senderName = 'Anonymous';
        if (data.sender_type === 'customer') {
            // For immediate messages (temp IDs), use stored customer name
            if (data.id && data.id.toString().startsWith('temp_') && window.currentCustomerName) {
                senderName = window.currentCustomerName;
            } else {
                senderName = data.customer_name || data.customer_fullname || window.currentCustomerName || 'Anonymous';
            }
        } else if (data.sender_type === 'agent') {
            // For immediate messages (temp IDs), use stored agent name
            if (data.id && data.id.toString().startsWith('temp_') && window.currentAgentName) {
                senderName = window.currentAgentName;
            } else {
                senderName = data.agent_name || data.sender_name || window.currentAgentName || 'Agent';
            }
        }
        
        // Create avatar element
        const avatarDiv = document.createElement('div');
        avatarDiv.className = `avatar ${senderName && senderName.toLowerCase() === 'anonymous' ? 'anonymous' : data.sender_type} small`;
        avatarDiv.textContent = generateInitials(senderName);
        
        // Create message content container
        const messageContent = document.createElement('div');
        messageContent.className = 'message-content';
        
        // Handle file messages differently
        if (data.file_data) {
            const fileContainer = renderFileMessage(data.file_data, data.id);
            messageContent.appendChild(fileContainer);
            
            // Add file message text if present
            if (data.message && data.message.trim() !== `File: ${data.file_data.original_name}`) {
                const textBubble = document.createElement('div');
                textBubble.className = 'message-bubble';
                textBubble.innerHTML = makeLinksClickable(data.message);
                messageContent.appendChild(textBubble);
            }
            
            // For voice messages, don't add a separate timestamp as the voice player has its own time display
            const isVoiceMessage = data.file_data.file_type === 'voice' || 
                data.file_data.file_type === 'audio' || 
                data.message_type === 'voice' ||
                (data.file_data.mime_type && data.file_data.mime_type.startsWith('audio/')) ||
                (data.file_data.original_name && data.file_data.original_name.match(/^voice_message_/)) ||
                // Additional check for voice messages based on file content
                (data.file_data.original_name && data.file_data.original_name.includes('voice'));
            
            if (!isVoiceMessage) {
                // Only add timestamp for non-voice file messages
                const time = document.createElement('div');
                time.className = 'message-time';
                time.textContent = formatTime(data.created_at || data.timestamp);
                messageContent.appendChild(time);
            } else {
                // For voice messages, add a timestamp similar to text messages
                const time = document.createElement('div');
                time.className = 'message-time';
                time.textContent = formatTime(data.created_at || data.timestamp);
                messageContent.appendChild(time);
            }
        } else {
            // Regular text message
            const bubble = document.createElement('div');
            bubble.className = 'message-bubble';
            bubble.innerHTML = makeLinksClickable(data.message);
            messageContent.appendChild(bubble);
            
            // Add timestamp for text messages
            const time = document.createElement('div');
            time.className = 'message-time';
            time.textContent = formatTime(data.created_at || data.timestamp);
            messageContent.appendChild(time);
        }
        
        // Assemble the message
        messageDiv.appendChild(avatarDiv);
        messageDiv.appendChild(messageContent);
    }
    
    // Insert message after the current date separator, not at the end
    if (currentDateSeparator && currentDateSeparator.parentNode === container) {
        // Find the insertion point: after the date separator and any existing messages for this date
        let insertAfter = currentDateSeparator;
        let nextSibling = insertAfter.nextSibling;
        
        // Find the last message that belongs to the same date group
        while (nextSibling && 
               nextSibling.classList && 
               nextSibling.classList.contains('message') && 
               !nextSibling.classList.contains('date-separator')) {
            insertAfter = nextSibling;
            nextSibling = nextSibling.nextSibling;
        }
        
        // Insert after the last message in this date group
        container.insertBefore(messageDiv, insertAfter.nextSibling);
    } else {
        // Fallback to append if no current separator
        container.appendChild(messageDiv);
    }
    
    // Attach click handlers for images after DOM is updated
    if (data.file_data && data.file_data.file_type === 'image') {
        const imageElement = messageDiv.querySelector('.customer-image');
        if (imageElement) {
            imageElement.addEventListener('click', function() {
                openCustomerImageModal(this);
            });
        }
    }
    
    container.scrollTop = container.scrollHeight;
}

// Track the current date separator for proper message insertion
let currentDateSeparator = null;
// Flag to control automatic date separator creation during history loading
let isLoadingHistory = false;

//Date separator functions for customers
function displayDateSeparator(dateString, id = null) {
    const container = safeGetElement('messagesContainer');
    if (!container) return null;

    // Create a unique identifier for this date
    const dateId = id || `date_${dateString.replace(/[^a-zA-Z0-9]/g, '_')}`;
    
    // Check if date separator already exists for this specific date ID only
    // Remove the content-based check as it might interfere with proper ordering
    const existingSeparator = container.querySelector(`[data-separator-id="${dateId}"]`);
    if (existingSeparator) {
        currentDateSeparator = existingSeparator;
        return existingSeparator; // Already exists, don't add another
    }

    // Determine date type for styling
    const dateType = getDateType(dateString);
    
    const separatorDiv = document.createElement('div');
    separatorDiv.className = `date-separator ${dateType}`;
    separatorDiv.dataset.separatorId = dateId;
    
    separatorDiv.innerHTML = `
        <div class="date-badge">${dateString}</div>
    `;

    // Add animation class for new separators
    separatorDiv.classList.add('new');
    
    container.appendChild(separatorDiv);
    
    // Update the current date separator tracker
    currentDateSeparator = separatorDiv;
    
    // Remove animation class after animation completes
    setTimeout(() => {
        separatorDiv.classList.remove('new');
    }, 300);
    
    return separatorDiv;
}

function formatChatDate(timestamp) {
    const date = new Date(timestamp);
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    
    // Helper function to format date as DD-MM-YYYY
    const formatDDMMYYYY = (d) => {
        const day = d.getDate().toString().padStart(2, '0');
        const month = (d.getMonth() + 1).toString().padStart(2, '0');
        const year = d.getFullYear();
        return `${day}-${month}-${year}`;
    };
    
    // Check if it's today
    if (date.toDateString() === today.toDateString()) {
        return `Today, ${formatDDMMYYYY(date)}`;
    } 
    // Check if it's yesterday
    else if (date.toDateString() === yesterday.toDateString()) {
        return `Yesterday, ${formatDDMMYYYY(date)}`;
    } 
    // For other dates, show full date with day name
    else {
        return date.toLocaleDateString('en-US', { 
            weekday: 'long',
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });
    }
}

function getDateType(dateString) {
    if (dateString.includes('Today')) {
        return 'today';
    } else if (dateString.includes('Yesterday')) {
        return 'yesterday';
    }
    return '';
}

// Function to add date separator when receiving new messages via WebSocket
function addDateSeparatorIfNeeded(newMessage) {
    const container = safeGetElement('messagesContainer');
    if (!container) return false;

    const lastMessage = container.querySelector('.message:last-child:not(.date-separator)');
    if (!lastMessage) {
        // First message, always add date separator
        const messageTimestamp = newMessage.created_at || newMessage.timestamp;
        displayDateSeparator(formatChatDate(messageTimestamp));
        return true;
    }

    const lastMessageDate = lastMessage.dataset.messageDate;
    const messageTimestamp = newMessage.created_at || newMessage.timestamp || Date.now();
    const newMessageDate = new Date(messageTimestamp).toDateString();

    if (lastMessageDate !== newMessageDate) {
        displayDateSeparator(formatChatDate(messageTimestamp));
        return true;
    }

    return false;
}

function updateWaitingSessions(sessions) {
    const container = document.getElementById('waitingSessions');
    const count = document.getElementById('waitingCount');
    
    if (container && count) {
        container.innerHTML = '';
        count.textContent = sessions.length;
        
        sessions.forEach(session => {
            const item = document.createElement('div');
            item.className = 'session-item';
            item.setAttribute('data-session-id', session.session_id);
            
            item.innerHTML = `
                <div class="session-info">
                    <strong>${escapeHtml(session.customer_name)}</strong>
                    <small>${formatTime(session.created_at)}</small>
                </div>
                <button class="btn btn-accept" onclick="acceptChat('${session.session_id}')">Accept</button>
            `;
            
            container.appendChild(item);
        });
    }
}

// URL detection utility function
function makeLinksClickable(text) {
    if (!text) return text;
    
    // Enhanced URL regex pattern to catch various URL formats
    const urlPattern = /(https?:\/\/(?:[-\w.])+(?:\.[a-zA-Z]{2,})+(?:[\/#?][-\w._~:/#[\]@!$&'()*+,;=?%]*)?|www\.(?:[-\w.])+(?:\.[a-zA-Z]{2,})+(?:[\/#?][-\w._~:/#[\]@!$&'()*+,;=?%]*)?|(?:(?:[a-zA-Z0-9][-\w]*[a-zA-Z0-9]*\.)+[a-zA-Z]{2,})(?:[\/#?][-\w._~:/#[\]@!$&'()*+,;=?%]*)?)/gi;
    
    return text.replace(urlPattern, function(url) {
        // Add protocol if missing
        let href = url;
        if (!url.match(/^https?:\/\//)) {
            href = 'https://' + url;
        }
        
        // Create clickable link with security attributes
        return `<a href="${href}" target="_blank" rel="noopener noreferrer">${url}</a>`;
    });
}

// Utility functions
function formatTime(timestamp) {
    if (!timestamp) {
        return 'Invalid Date';
    }
    
    try {
        const date = new Date(timestamp);
        
        // Check if date is valid
        if (isNaN(date.getTime())) {
            return 'Invalid Date';
        }
        
        return date.toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit',
            hour12: true,
            timeZone: 'Asia/Kuala_Lumpur'
        });
    } catch (error) {
        return 'Invalid Date';
    }
}

// Helper function to ensure message has proper timestamp
function ensureMessageTimestamp(message) {
    // Prefer created_at from database over timestamp
    if (message.created_at) {
        message.timestamp = message.created_at;
    } else if (!message.timestamp || message.timestamp === 'Invalid Date') {
        // Only use current time if no timestamp at all
        message.timestamp = new Date().toISOString();
    }
    return message;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Notification sound state (default: enabled)
let notificationSoundEnabled = !SOUND_DISABLED_BY_CLIENT;

// Initialize notification preference from localStorage
function initNotificationPreference() {
    if (SOUND_DISABLED_BY_CLIENT) {
        notificationSoundEnabled = false;
        const toggle = document.getElementById('notificationToggle');
        if (toggle) {
            toggle.style.display = 'none';
        }
        updateNotificationIcon();
        return;
    }

    const savedPreference = localStorage.getItem('chatNotificationSound');
    if (savedPreference !== null) {
        notificationSoundEnabled = savedPreference === 'true';
    }
    updateNotificationIcon();
}

// Toggle notification sound on/off
function toggleNotificationSound() {
    if (SOUND_DISABLED_BY_CLIENT) {
        return;
    }
    notificationSoundEnabled = !notificationSoundEnabled;
    localStorage.setItem('chatNotificationSound', notificationSoundEnabled.toString());
    updateNotificationIcon();
}

// Update notification icon based on state
function updateNotificationIcon() {
    const icon = document.getElementById('notificationIcon');
    if (icon) {
        if (notificationSoundEnabled) {
            icon.className = 'bi bi-bell-fill';
        } else {
            icon.className = 'bi bi-bell-slash-fill';
        }
    }
}

// Play notification sound using Web Audio API
function playNotificationSound() {
    if (!notificationSoundEnabled || SOUND_DISABLED_BY_CLIENT) {
        return; // Don't play if notifications are disabled
    }
    
    try {
        // Create audio context
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        
        // Create oscillator for beep sound
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        // Connect nodes
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        // Configure the beep sound
        oscillator.frequency.value = 800; // Frequency in Hz (higher = higher pitch)
        oscillator.type = 'sine'; // Sine wave for smooth sound
        
        // Set volume envelope (fade in/out for smoother sound)
        gainNode.gain.setValueAtTime(0, audioContext.currentTime);
        gainNode.gain.linearRampToValueAtTime(0.3, audioContext.currentTime + 0.01); // Fade in
        gainNode.gain.linearRampToValueAtTime(0, audioContext.currentTime + 0.15); // Fade out
        
        // Play the sound
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.15); // Duration: 150ms
        
        // Clean up
        setTimeout(() => {
            audioContext.close();
        }, 200);
    } catch (error) {
        // Silently fail if Web Audio API is not supported
        console.warn('Web Audio API not supported or failed:', error);
    }
}

// Message refresh for admin to catch system messages in real-time
let messageRefreshInterval = null;

function startMessageRefresh(sessionId) {
    // Clear any existing refresh interval
    if (messageRefreshInterval) {
        clearInterval(messageRefreshInterval);
    }
    
    // Refresh messages every 1 second for admin to catch system messages
    if (getUserType() === 'agent') {
        messageRefreshInterval = setInterval(() => {
            refreshMessagesForSession(sessionId);
        }, 1000);
    }
}

function stopMessageRefresh() {
    if (messageRefreshInterval) {
        clearInterval(messageRefreshInterval);
        messageRefreshInterval = null;
    }
}

async function refreshMessagesForSession(sessionId) {
    if (!sessionId || sessionId !== currentSessionId) {
        return;
    }
    
    try {
        // Use backend=1 parameter only for admin/agent users to filter out system messages
        const backendParam = getUserType() === 'agent' ? '?backend=1' : '';
        const response = await fetch(`/chat/messages/${sessionId}${backendParam}`);
        const messages = await response.json();
        
        const container = document.getElementById('messagesContainer');
        if (container) {
            // Store current scroll position
            const isScrolledToBottom = container.scrollTop === container.scrollHeight - container.clientHeight;
            
            // Create a more robust tracking system using actual message IDs from database
            let newMessagesAdded = false;
            
            messages.forEach(message => {
                message = ensureMessageTimestamp(message);
                
                // Use the actual database message ID if available, otherwise fall back to content-based ID
                const messageId = message.id ? `db_${message.id}` : `${message.sender_type}_${message.message ? message.message.toLowerCase().trim() : ''}_${message.timestamp}`;
                
                if (!displayedMessages.has(messageId)) {
                    displayMessage(message);
                    newMessagesAdded = true;
                }
            });
            
            // Only adjust scroll if new messages were added and user was at bottom
            if (newMessagesAdded && isScrolledToBottom) {
                container.scrollTop = container.scrollHeight;
            }
        }
    } catch (error) {
        // Error handling without console log
    }
}

// Canned responses
function showCannedResponses() {
    if (!currentSessionId) return;
    
    fetch('/api/canned-responses')
        .then(response => response.json())
        .then(responses => {
            displayCannedResponsesModal(responses);
        });
}

function displayCannedResponsesModal(responses) {
    const modal = document.createElement('div');
    modal.className = 'canned-responses-modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>Quick Responses</h3>
                <button class="close-modal" onclick="this.parentElement.parentElement.parentElement.remove()">×</button>
            </div>
            <div class="modal-body">
                ${responses.map(response => `
                    <div class="canned-response-item" onclick="sendCannedResponse('${response.id}')">
                        <strong>${response.title}</strong>
                        <p>${response.content}</p>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

async function sendCannedResponse(responseId) {
    try {
        const response = await fetch(`/admin/canned-responses/get/${responseId}`);
        const responseData = await response.json();
        
        if (responseData.content) {
            if (ws && ws.readyState === WebSocket.OPEN) {
                const currentSession = getSessionId();
                
                ws.send(JSON.stringify({
                    type: 'message',
                    session_id: currentSession,
                    message: responseData.content,
                    sender_type: getUserType(),
                    sender_id: getUserId()
                }));
            }
        }
    } catch (error) {
        // Error handling without console log
    }
}

// Quick actions for common responses
function initQuickActions() {
    const chatInputArea = document.querySelector('.chat-input-area');
    if (!chatInputArea || getUserType() !== 'agent') return;
    
    // Remove existing quick actions to prevent duplicates
    const existingQuickActions = chatInputArea.querySelector('.quick-actions');
    if (existingQuickActions) {
        existingQuickActions.remove();
    }
    
    const quickActions = document.createElement('div');
    quickActions.className = 'quick-actions';
    
    // Load canned responses from database
    fetch('/admin/canned-responses/get-all')
        .then(response => response.json())
        .then(responses => {
            quickActions.innerHTML = responses.map(response => 
                `<button class="quick-action-btn" onclick="sendCannedResponse(${response.id})">${response.title}</button>`
            ).join('');
        })
        .catch(error => {
            // Fallback to default responses
            quickActions.innerHTML = `
                <button class="quick-action-btn" onclick="sendQuickResponse('greeting')">👋 Greeting</button>
                <button class="quick-action-btn" onclick="sendQuickResponse('please_wait')">⏳ Please Wait</button>
                <button class="quick-action-btn" onclick="sendQuickResponse('thank_you')">🙏 Thank You</button>
            `;
        });
    
    chatInputArea.insertBefore(quickActions, chatInputArea.firstChild);
}

async function sendQuickResponse(type) {
    const responses = {
        greeting: "Hello! How can I help you today?",
        please_wait: "Thank you for your patience. Let me look into this for you.",
        thank_you: "Thank you for contacting us. Have a great day!"
    };
    
    const message = responses[type];
    if (message && getSessionId()) {
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({
                type: 'message',
                session_id: getSessionId(),
                message: message,
                sender_type: getUserType(),
                sender_id: getUserId()
            }));
        }
    }
}

function initializeChatContainer() {
    const container = safeGetElement('messagesContainer');
    if (container) {
        // Add appropriate class based on user type
        const userType = getUserType();
        if (userType === 'agent') {
            container.closest('.chat-window')?.classList.add('admin-chat');
            // Also add to the main dashboard
            document.querySelector('.admin-dashboard')?.classList.add('chat-dashboard');
        } else {
            // For customer chat, add customer-chat class
            container.closest('.chat-window')?.classList.add('customer-chat');
            // Also add to container
            container.closest('.chat-container')?.classList.add('customer-chat');
        }
    }
}

// Helper function for chat history notification
function showHistoryLoadedNotification(messageCount) {
    const container = document.getElementById('messagesContainer');
    if (!container) return;
    
    const notificationDiv = document.createElement('div');
    notificationDiv.className = 'history-notification';
    notificationDiv.innerHTML = `
        <p>📜 ${messageCount} messages from your previous conversations are included</p>
    `;
    
    // Insert at the top of the container
    container.insertBefore(notificationDiv, container.firstChild);
    
    // Auto-remove after 4 seconds
    setTimeout(() => {
        if (notificationDiv.parentNode) {
            notificationDiv.remove();
        }
    }, 4000);
}

// Initialize WebSocket on page load
document.addEventListener('DOMContentLoaded', async function() {
    displayedMessages.clear();
    
    // Initialize notification preference
    initNotificationPreference();
    
    // Initialize chat container classes
    initializeChatContainer();
    
    // Initialize agent name if available (for admin interface)
    if (typeof currentUsername !== 'undefined' && currentUsername) {
        window.currentAgentName = currentUsername;
    }
    
    const chatInterface = safeGetElement('chatInterface');
    const messagesContainer = safeGetElement('messagesContainer');
    const adminDashboard = safeGetElement('admin-dashboard');
    
    // Check if we're on a chat page (customer or admin)
    if (chatInterface || messagesContainer || adminDashboard) {
        // For admin interface, always initialize WebSocket
        if (getUserType() === 'agent') {
            initWebSocket();
            
            // Start auto-refresh for admin sessions
            startAdminAutoRefresh();
            
            // Ensure admin interface doesn't show "chat ended" messages
            const closedMessage = document.querySelector('.chat-closed-message');
            if (closedMessage) {
                closedMessage.remove();
            }
            
            setTimeout(() => {
                // Initialize quick actions for agents only
                initQuickActions();
            }, 500);
        } else {
            // For customer interface, check session status
            const sessionActive = await checkSessionStatus();
            if (sessionActive) {
                // Show the Leave Chat button immediately for existing sessions
                // This prevents the button from being stuck in "Ending..." state on page refresh
                const currentSession = getSessionId();
                if (currentSession) {
                    showCustomerCloseButton();
                }
                
                initWebSocket();
                
                // Wait a moment for WebSocket to connect before initializing form
                setTimeout(() => {
                    initializeMessageForm();
                    
                    // Re-initialize voice recording after form is cloned (this removes event listeners)
                    setTimeout(() => {
                        if (typeof window.reinitializeVoiceRecording === 'function') {
                            window.reinitializeVoiceRecording();
                        }
                    }, 100);
                    
                    // CRITICAL FIX: Load chat history directly on page refresh
                    // Don't wait only for WebSocket 'connected' event
                    loadChatHistory();
                }, 500);
            }
        }
    }
});

// Render image messages directly in chat
function renderCustomerImageMessage(fileData, messageId) {
    const imageContainer = document.createElement('div');
    imageContainer.className = 'customer-image-container';
    
    // Create clickable image element
    const img = document.createElement('img');
    img.className = 'customer-image';
    img.alt = fileData.original_name || 'Image';
    img.title = fileData.original_name || 'Click to view full size';
    
    // Set image source using local thumbnail endpoint
    const thumbnailBaseUrl = typeof baseUrl !== 'undefined' ? baseUrl : (typeof window.location !== 'undefined' ? window.location.origin + '/' : '/');
    img.src = `${thumbnailBaseUrl}chat/thumbnail/${messageId}`;
    
    // Store metadata for modal
    img.setAttribute('data-message-id', messageId);
    img.setAttribute('data-file-name', fileData.original_name || 'Unknown File');
    img.setAttribute('data-file-size', fileData.compressed_size || fileData.file_size);
    img.setAttribute('data-original-url', fileData.file_url || `${thumbnailBaseUrl}chat/download-file/${messageId}`);
    
    // Add error handling
    img.onerror = function() {
        console.warn('Image failed to load:', img.src);
        // Replace with fallback
        const fallbackContainer = document.createElement('div');
        fallbackContainer.className = 'customer-image-fallback';
        fallbackContainer.innerHTML = `
            <i class="fas fa-image text-muted" style="font-size: 48px;"></i>
            <div class="text-muted mt-2">Image failed to load</div>
        `;
        imageContainer.innerHTML = '';
        imageContainer.appendChild(fallbackContainer);
    };
    
    imageContainer.appendChild(img);
    return imageContainer; // Return DOM element, not HTML string
}

// Fallback voice message player (in case voice-recording.js isn't loaded)
function createFallbackVoicePlayer(fileData, messageId) {
    
    const player = document.createElement('div');
    player.className = 'voice-message-player';
    player.dataset.messageId = messageId;
    
    // Construct the correct file URL
    let audioUrl = '';
    if (fileData.file_url) {
        audioUrl = fileData.file_url;
    } else if (fileData.file_path) {
        audioUrl = 'https://api-taapin.danhar.cc/livechat/default/chat/' + fileData.file_path;
    } else {
        const currentBaseUrl = typeof baseUrl !== 'undefined' ? baseUrl : (typeof window.location !== 'undefined' ? window.location.origin + '/' : '/');
        audioUrl = currentBaseUrl + 'chat/download-file/' + messageId;
    }
    
    player.innerHTML = `
        <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8f9fa; border-radius: 8px; max-width: 300px;">
            <button class="voice-play-btn" onclick="playFallbackVoice('${audioUrl}', '${messageId}')" style="background: #667eea; border: none; color: white; cursor: pointer; padding: 10px; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-play-fill"></i>
            </button>
            <div style="flex: 1; display: flex; flex-direction: column; gap: 6px;">
                <div style="width: 100%; height: 6px; background: #dee2e6; border-radius: 3px; position: relative; overflow: hidden; cursor: pointer;" onclick="seekFallbackVoice(event, '${messageId}')">
                    <div id="voice-progress-${messageId}" style="height: 100%; background: #667eea; border-radius: 3px; transition: width 0.1s linear; width: 0%;"></div>
                </div>
                <div style="display: flex; justify-content: flex-start; font-size: 11px; color: #6c757d; font-family: 'Courier New', monospace;">
                    <span id="voice-time-${messageId}">00:00</span>
                </div>
            </div>
        </div>
    `;
    
    // Pre-load the audio to get duration immediately
    const preloadAudio = new Audio(audioUrl);
    preloadAudio.addEventListener('loadedmetadata', () => {
        const timeEl = document.getElementById(`voice-time-${messageId}`);
        if (timeEl && preloadAudio.duration && !isNaN(preloadAudio.duration)) {
            timeEl.textContent = formatFallbackTime(preloadAudio.duration);
        }
    });
    preloadAudio.load();
    
    return player;
}

// Fallback audio players storage
const fallbackAudioPlayers = {};

function playFallbackVoice(audioUrl, messageId) {
    
    // Stop other playing audio
    Object.keys(fallbackAudioPlayers).forEach(id => {
        if (id !== messageId && fallbackAudioPlayers[id]) {
            fallbackAudioPlayers[id].pause();
            fallbackAudioPlayers[id].currentTime = 0;
            updateFallbackPlayButton(id, false);
        }
    });
    
    // Create audio element if it doesn't exist
    if (!fallbackAudioPlayers[messageId]) {
        const audio = new Audio(audioUrl);
        fallbackAudioPlayers[messageId] = audio;
        
        // Update progress during playback
        audio.addEventListener('timeupdate', () => {
            updateFallbackVoiceProgress(messageId);
        });
        
        // Handle playback end
        audio.addEventListener('ended', () => {
            updateFallbackPlayButton(messageId, false);
            
            // Reset to total duration when playback ends
            const timeEl = document.getElementById(`voice-time-${messageId}`);
            if (timeEl && audio._totalDuration) {
                timeEl.textContent = formatFallbackTime(audio._totalDuration);
            }
            
            // Reset progress bar
            const progressFill = document.getElementById(`voice-progress-${messageId}`);
            if (progressFill) {
                progressFill.style.width = '0%';
            }
            
            // Reset audio position AFTER updating the display
            audio.currentTime = 0;
        });
        
        // Load metadata to get duration
        audio.addEventListener('loadedmetadata', () => {
            const timeEl = document.getElementById(`voice-time-${messageId}`);
            if (timeEl && audio.duration && !isNaN(audio.duration)) {
                // Store the total duration for later use
                audio._totalDuration = audio.duration;
                // Show total duration initially
                timeEl.textContent = formatFallbackTime(audio.duration);
            }
        });
        
        // Fallback: Try to get duration after a short delay if metadata didn't load
        setTimeout(() => {
            const timeEl = document.getElementById(`voice-time-${messageId}`);
            if (timeEl && timeEl.textContent === '00:00' && audio.duration && !isNaN(audio.duration)) {
                audio._totalDuration = audio.duration;
                timeEl.textContent = formatFallbackTime(audio.duration);
            }
        }, 1000);
        
        // Add error handling
        audio.addEventListener('error', (e) => {
            updateFallbackPlayButton(messageId, false);
            alert('Failed to load voice message. Please try again.');
        });
    }
    
    const audio = fallbackAudioPlayers[messageId];
    
    // Toggle play/pause
    if (audio.paused) {
        // Reset to start of playback when starting
        audio.currentTime = 0;
        const timeEl = document.getElementById(`voice-time-${messageId}`);
        if (timeEl) {
            timeEl.textContent = formatFallbackTime(0);
        }
        const progressFill = document.getElementById(`voice-progress-${messageId}`);
        if (progressFill) {
            progressFill.style.width = '0%';
        }
        
        audio.play();
        updateFallbackPlayButton(messageId, true);
    } else {
        audio.pause();
        updateFallbackPlayButton(messageId, false);
    }
}

function updateFallbackPlayButton(messageId, isPlaying) {
    const playBtn = document.querySelector(`[data-message-id="${messageId}"] .voice-play-btn`);
    if (playBtn) {
        if (isPlaying) {
            playBtn.innerHTML = '<i class="bi bi-pause-fill"></i>';
            playBtn.style.background = '#dc3545';
        } else {
            playBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
            playBtn.style.background = '#667eea';
        }
    }
}

function updateFallbackVoiceProgress(messageId) {
    const audio = fallbackAudioPlayers[messageId];
    if (!audio) return;
    
    const progressFill = document.getElementById(`voice-progress-${messageId}`);
    const timeEl = document.getElementById(`voice-time-${messageId}`);
    
    if (progressFill) {
        const progress = (audio.currentTime / audio.duration) * 100;
        progressFill.style.width = `${progress}%`;
    }
    
    if (timeEl && !audio.paused) {
        // Only show current time during active playback
        timeEl.textContent = formatFallbackTime(audio.currentTime);
    }
}

function seekFallbackVoice(event, messageId) {
    const audio = fallbackAudioPlayers[messageId];
    if (!audio) return;
    
    const progressBar = event.currentTarget;
    const rect = progressBar.getBoundingClientRect();
    const clickX = event.clientX - rect.left;
    const percentage = clickX / rect.width;
    
    audio.currentTime = percentage * audio.duration;
    updateFallbackVoiceProgress(messageId);
}

function formatFallbackTime(seconds) {
    if (isNaN(seconds)) return '00:00';
    
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

// File message rendering function
function renderFileMessage(fileData, messageId) {
    // Ensure messageId is a string
    if (messageId && typeof messageId === 'object' && messageId.toString) {
        messageId = messageId.toString();
    } else {
        messageId = String(messageId);
    }
    
    // For voice messages, display voice player
    if (fileData.file_type === 'voice' || fileData.file_type === 'audio' || 
        (fileData.mime_type && fileData.mime_type.startsWith('audio/')) ||
        fileData.original_name.match(/^voice_message_/)) {
        
        // Use the createVoiceMessagePlayer function from voice-recording.js
        if (typeof createVoiceMessagePlayer === 'function') {
            return createVoiceMessagePlayer(fileData, messageId);
        } else {
            // Create a simple fallback voice message player
            return createFallbackVoicePlayer(fileData, messageId);
        }
    }
    
    // For images, display them directly in chat
    if (fileData.file_type === 'image') {
        return renderCustomerImageMessage(fileData, messageId);
    }
    
    // For non-image files, use the existing card format
    const fileContainer = document.createElement('div');
    fileContainer.className = `message-file file-type-${fileData.file_type || 'other'}`;
    
    // File icon
    const fileIcon = document.createElement('i');
    fileIcon.className = getFileIconClass(fileData.file_type, fileData.mime_type);
    
    // File details container
    const fileDetails = document.createElement('div');
    fileDetails.className = 'file-details';
    
    // File name
    const fileName = document.createElement('div');
    fileName.className = 'file-name';
    fileName.textContent = fileData.original_name || 'Unknown File';
    fileName.title = fileData.original_name || 'Unknown File';
    
    // File metadata
    const fileMeta = document.createElement('div');
    fileMeta.className = 'file-meta';
    
    const fileSize = document.createElement('span');
    fileSize.textContent = formatFileSize(fileData.compressed_size || fileData.file_size);
    fileMeta.appendChild(fileSize);
    
    // Show compression info if file was compressed
    if (fileData.compression_status === 'compressed' && fileData.file_size !== fileData.compressed_size) {
        const compressionInfo = document.createElement('span');
        compressionInfo.className = 'compression-info compression-saved';
        const savedSize = fileData.file_size - fileData.compressed_size;
        const compressionRatio = ((savedSize / fileData.file_size) * 100).toFixed(1);
        compressionInfo.innerHTML = `<i class="fas fa-compress-arrows-alt"></i> Compressed ${compressionRatio}%`;
        fileMeta.appendChild(compressionInfo);
    }
    
    fileDetails.appendChild(fileName);
    fileDetails.appendChild(fileMeta);
    
    // File actions
    const fileActions = document.createElement('div');
    fileActions.className = 'file-actions';
    
    // Download button - handle iframe restrictions
    const downloadBtn = document.createElement('a');
    downloadBtn.className = 'file-download-btn';
    const downloadBaseUrl = typeof baseUrl !== 'undefined' ? baseUrl : (typeof window.location !== 'undefined' ? window.location.origin + '/' : '/');
    downloadBtn.href = `${downloadBaseUrl}chat/download-file/${messageId}`;
    downloadBtn.download = fileData.original_name;
    downloadBtn.innerHTML = '<i class="fas fa-download"></i> Download';
    downloadBtn.target = '_blank';
    
    // Handle iframe download restrictions
    downloadBtn.onclick = function(e) {
        // If we're in an iframe, try to open in parent window
        if (window !== window.parent) {
            e.preventDefault();
            try {
                // Try to open in parent window
                window.parent.open(this.href, '_blank');
            } catch (error) {
                // Fallback: try direct navigation
                window.location.href = this.href;
            }
        }
        // If not in iframe, let default behavior work
    };
    
    fileActions.appendChild(downloadBtn);
    
    // Assemble the file message for non-image files
    fileContainer.appendChild(fileIcon);
    fileContainer.appendChild(fileDetails);
    fileContainer.appendChild(fileActions);
    
    return fileContainer;
}

// Helper function to get file icon class
function getFileIconClass(fileType, mimeType) {
    switch (fileType) {
        case 'image':
            return 'fas fa-image text-primary';
        case 'video':
            return 'fas fa-video text-danger';
        case 'document':
            if (mimeType && mimeType.includes('pdf')) {
                return 'fas fa-file-pdf text-danger';
            }
            return 'fas fa-file-alt text-info';
        case 'archive':
            return 'fas fa-file-archive text-warning';
        case 'other':
            if (mimeType) {
                if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) {
                    return 'fas fa-file-excel text-success';
                }
                if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) {
                    return 'fas fa-file-powerpoint text-warning';
                }
            }
            return 'fas fa-file text-secondary';
        default:
            return 'fas fa-file text-secondary';
    }
}

// Helper function to handle iframe download restrictions
function handleIframeDownload(event, element) {
    // If we're in an iframe, try to open in parent window
    if (window !== window.parent) {
        event.preventDefault();
        try {
            // Try to open in parent window
            window.parent.open(element.href, '_blank');
        } catch (error) {
            // Fallback: try direct navigation
            window.location.href = element.href;
        }
    }
    // If not in iframe, let default behavior work
}

// Helper function to format file size
function formatFileSize(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    
    return parseFloat((bytes / Math.pow(1024, i)).toFixed(2)) + ' ' + sizes[i];
}

// Open customer image modal for full-size preview
function openCustomerImageModal(imgElement) {
    const messageId = imgElement.getAttribute('data-message-id');
    const fileName = imgElement.getAttribute('data-file-name');
    const fileSize = imgElement.getAttribute('data-file-size');
    const originalUrl = imgElement.getAttribute('data-original-url');

    // Create modal HTML
    const modalHTML = `
        <div class="customer-image-preview-modal" id="customerImageModal">
            <div class="customer-modal-dialog">
                <div class="customer-modal-header">
                    <h5 class="customer-modal-title">
                        <i class="fas fa-image"></i>${fileName}
                    </h5>
                    <button type="button" class="customer-close-btn" onclick="closeCustomerImageModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="customer-modal-body">
                    <img src="${originalUrl}" class="customer-modal-image" alt="${fileName}">
                    <div class="customer-image-info">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            ${formatFileSize(fileSize)} • Click outside to close
                        </small>
                    </div>
                </div>
                <div class="customer-modal-footer">
                    <a href="${originalUrl}" download="${fileName}" class="customer-download-btn" onclick="handleIframeDownload(event, this)">
                        <i class="fas fa-download"></i>Download Image
                    </a>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    const existingModal = document.querySelector('.customer-image-preview-modal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Add click outside to close
    const modal = document.querySelector('.customer-image-preview-modal');
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeCustomerImageModal();
        }
    });

    // Close modal on escape key
    const closeOnEscape = (e) => {
        if (e.key === 'Escape') {
            closeCustomerImageModal();
        }
    };
    document.addEventListener('keydown', closeOnEscape);

    // Store the escape handler so we can remove it later
    modal.setAttribute('data-escape-handler', 'true');
}

// Close customer image modal
function closeCustomerImageModal() {
    const modal = document.querySelector('.customer-image-preview-modal');
    if (modal) {
        modal.remove();
    }
    // Remove escape key listener
    document.removeEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeCustomerImageModal();
        }
    });
}

// Legacy function for backward compatibility (if called from old code)
function showImagePreview(fileData, messageId) {
    // Create a temporary image element with the required data attributes
    const tempImg = document.createElement('img');
    tempImg.setAttribute('data-message-id', messageId);
    tempImg.setAttribute('data-file-name', fileData.original_name || 'Unknown File');
    tempImg.setAttribute('data-file-size', fileData.compressed_size || fileData.file_size);
    tempImg.setAttribute('data-original-url', fileData.file_url || `${typeof baseUrl !== 'undefined' ? baseUrl : (typeof window.location !== 'undefined' ? window.location.origin + '/' : '/')}chat/download-file/${messageId}`);
    
    // Use the new modal function
    openCustomerImageModal(tempImg);
}

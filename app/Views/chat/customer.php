<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/date.css?v=' . time()) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/file-upload.css?v=' . time()) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/image-display.css?v=' . time()) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/voice-recording.css?v=' . time()) ?>">

<?php if (isset($is_fullscreen) && $is_fullscreen): ?>
<!-- Fullscreen Mode CSS -->
<link rel="stylesheet" href="<?= base_url('assets/css/chat-fullscreen.css?v=' . time()) ?>">
<?php endif; ?>

<?php
// Set widget color CSS variable if available
$primaryColor = $widget_color ?? '#667eea';
// Create a darker shade for gradient
function darkenColor($color, $percent = 20) {
    $color = ltrim($color, '#');
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));
    $r = max(0, min(255, $r - ($r * $percent / 100)));
    $g = max(0, min(255, $g - ($g * $percent / 100)));
    $b = max(0, min(255, $b - ($b * $percent / 100)));
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}
$primaryColorDark = darkenColor($primaryColor, 15);
$behaviorDefaults = [
    'disable_sound_notification' => false,
    'disable_agent_typing_notification' => false,
    'hide_widget_on_mobile' => false,
    'maximize_on_click' => false,
];
$behaviorOptions = array_merge($behaviorDefaults, is_array($behavior_options ?? null) ? $behavior_options : []);
?>
<style>
    :root {
        --chat-primary-color: <?= esc($primaryColor) ?>;
        --chat-primary-color-dark: <?= esc($primaryColorDark) ?>;
    }
</style>

<?php if (isset($is_iframe) && $is_iframe): ?>
<script>
    // Add iframe-mode class for full-screen styling
    document.addEventListener('DOMContentLoaded', function() {
        document.body.classList.add('iframe-mode');
        document.documentElement.classList.add('iframe-mode');
    });
</script>
<?php endif; ?>

<div class="chat-container customer-chat">
    <div class="chat-header">
        <h3><?= esc($widget_name ?? 'Customer Support') ?></h3>
        <div class="header-actions">
            <span class="status-indicator" id="connectionStatus">Offline</span>
            <?php if (!$behaviorOptions['disable_sound_notification']): ?>
            <button class="btn btn-notification-toggle" id="notificationToggle" onclick="toggleNotificationSound()" title="Toggle notification sound">
                <i class="bi bi-bell-fill" id="notificationIcon"></i>
            </button>
            <?php endif; ?>
            <button class="btn btn-close-chat" id="customerCloseBtn" onclick="closeCustomerChat()" style="display: none;">Leave Chat</button>
            <?php if (isset($is_fullscreen) && $is_fullscreen): ?>
            <button class="btn btn-fullscreen-close" id="fullscreenCloseBtn" onclick="closeFullscreen()" title="Close Chat">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="chatInterface">
        <?php if (isset($auto_session_error) && $auto_session_error): ?>
        <!-- Show error for failed auto-session creation -->
        <div class="chat-error">
            <h4>Chat Unavailable</h4>
            <div class="error-message">
                <p style="color: #dc3545; margin-bottom: 15px;">
                    <strong>Error:</strong> <?= htmlspecialchars($auto_session_error) ?>
                </p>
                <p>We're sorry for the inconvenience. Please try refreshing the page or contact support directly.</p>
                <button onclick="location.reload()" class="btn btn-primary">Try Again</button>
            </div>
        </div>
        <?php elseif (!$session_id): ?>
        <div class="chat-start-form">
            <h4>Start a Conversation</h4>
                    <form id="startChatForm">
                        <!-- Hidden fields for role-based information -->
                        <input type="hidden" name="user_role" value="<?= $user_role ?? 'anonymous' ?>">
                        <input type="hidden" name="external_username" value="<?= $external_username ?? '' ?>">
                        <input type="hidden" name="external_fullname" value="<?= $external_fullname ?? '' ?>">
                        <input type="hidden" name="external_system_id" value="<?= $external_system_id ?? '' ?>">
                        <input type="hidden" name="api_key" value="<?= $api_key ?? '' ?>">
                        <input type="hidden" name="customer_phone" value="<?= $customer_phone ?? '' ?>">
                        
                        <?php if ($user_role === 'loggedUser' && ($external_fullname || $external_username)): ?>
                            <!-- For logged users, show the name as read-only -->
                            <div class="form-group">
                                <label for="customerName">Your Name</label>
                                <input type="text" id="customerName" name="customer_name" value="<?= $external_fullname ?: $external_username ?>" readonly style="background-color: #f0f0f0;">
                                <small style="color: #666;">This information was provided by your system login.</small>
                            </div>
                        <?php else: ?>
                            <!-- For anonymous users, allow name input -->
                            <div class="form-group">
                                <label for="customerName">Your Name (Optional)</label>
                                <input type="text" id="customerName" name="customer_name" placeholder="Enter your name (or leave blank for Anonymous)">
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="customerProblem">What do you need help with? *</label>
                            <input type="text" id="customerProblem" name="chat_topic" required placeholder="Describe your issue or question...">
                        </div>
                        <div class="form-group">
                            <label for="customerEmail">Email (Optional)</label>
                            <?php if ($user_role === 'loggedUser' && !empty($external_email)): ?>
                            <input type="email" id="customerEmail" name="email" value="<?= esc($external_email) ?>" readonly style="background-color: #f0f0f0;">
                            <small style="color: #666;">This email was provided by your system login.</small>
                            <?php else: ?>
                            <input type="email" id="customerEmail" name="email" value="<?= esc($external_email ?? '') ?>">
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($user_role === 'loggedUser'): ?>
                            <p style="color: #28a745; font-size: 14px; margin-bottom: 15px;">
                                ✓ You are logged in as a verified user
                            </p>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-primary">Start Chat</button>
                    </form>
        </div>
        <?php else: ?>
        <div class="chat-window customer-chat" data-session-id="<?= $session_id ?>">
            <div class="messages-container" id="messagesContainer">
                <div class="message system">
                    <p>Connecting to support...</p>
                </div>
            </div>
            
            <?php if (!$behaviorOptions['disable_agent_typing_notification']): ?>
            <div class="typing-indicator" id="typingIndicator" style="display: none;">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <?php endif; ?>
            
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
                            <span class="recording-text">Recording...Move away from button to cancel</span>
                            <span class="recording-timer" id="recordingTimer">00:00</span>
                        </div>
                        <button type="button" class="btn-cancel-recording" onclick="cancelVoiceRecording()" title="Cancel recording">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Plus Button Menu -->
                <div id="plusButtonMenu" class="plus-button-menu" style="display: none;">
                    <button type="button" class="menu-item" onclick="triggerFileUpload()">
                        <i class="bi bi-file-earmark-plus"></i>
                        <span>Send a file</span>
                    </button>
                    <button type="button" class="menu-item" id="voiceRecordMenuBtn">
                        <i class="bi bi-mic-fill"></i>
                        <span>Voice recording</span>
                    </button>
                </div>
                
                <!-- Emoji Picker -->
                <div id="emojiPicker" class="emoji-picker" style="display: none;">
                    <div class="emoji-category active" data-category="smileys">
                        <div class="emoji-grid" id="emojiGrid">
                            <!-- Emojis will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <form id="messageForm">
                    <div class="unified-input-container">
                        <input type="file" id="fileInput" class="file-input-hidden" onchange="handleFileSelect(event)" accept="*/*">
                        
                        <!-- File Upload Button (Plus) -->
                        <button type="button" class="input-btn btn-plus" id="plusButton" title="Upload file">
                            <i class="bi bi-paperclip"></i>
                        </button>
                        
                        <!-- Voice Recording Button -->
                        <button type="button" class="input-btn btn-voice" id="voiceRecordBtn" title="Voice recording">
                            <i class="bi bi-mic-fill"></i>
                        </button>
                        
                        <!-- Message Input -->
                        <input type="text" id="messageInput" class="unified-message-input" placeholder="Write a message..." autocomplete="off">
                        
                        <!-- Quick Actions Button -->
                        <button type="button" class="input-btn btn-quick-actions" id="quickActionsToggle" title="Quick actions" style="display: none;">
                            <i class="bi bi-lightning-fill"></i>
                        </button>
                        
                        <!-- Emoji Button -->
                        <button type="button" class="input-btn btn-emoji" id="emojiButton" title="Emoji">
                            <i class="bi bi-emoji-smile"></i>
                        </button>
                        
                        <!-- Send Button -->
                        <button type="submit" class="input-btn btn-send" id="sendButton" title="Send">
                            <i class="bi bi-send send-icon"></i>
                            <i class="bi bi-upload upload-icon" style="display: none;"></i>
                        </button>
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
        <?php endif; ?>
    </div>
</div>

    <script>
    let userType = 'customer';
    let sessionId = '<?= $session_id ?? '' ?>';
    let currentSessionId = null;
    let baseUrl = '<?= base_url() ?>';
    
    // Role information for iframe integration
    let userRole = '<?= $user_role ?? 'anonymous' ?>';
    let externalUsername = '<?= $external_username ?? '' ?>';
    let externalFullname = '<?= $external_fullname ?? '' ?>';
    let externalSystemId = '<?= $external_system_id ?? '' ?>';
    let apiKey = '<?= $api_key ?? '' ?>';
    let customerPhone = '<?= $customer_phone ?? '' ?>';
    let externalEmail = '<?= $external_email ?? '' ?>';
    let isIframe = <?= $is_iframe ? 'true' : 'false' ?>;
    window.widgetBehaviorOptions = Object.freeze(<?= json_encode($behaviorOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
    
    
    // Function to check if agent has joined by examining messages
    function checkIfAgentJoined() {
        const messagesContainer = document.getElementById('messagesContainer');
        if (messagesContainer) {
            const systemMessages = messagesContainer.querySelectorAll('.message.system');
            systemMessages.forEach(msg => {
                const text = msg.textContent || msg.innerText;
                if (text && (text.includes('agent has joined') || text.includes('Agent has joined'))) {
                    hideQuickActionsToolbar();
                }
            });
        }
    }
    
    // Load quick actions when page loads
    document.addEventListener('DOMContentLoaded', function() {
        if (sessionId) {
            fetchQuickActions();
            // Initialize typing functionality for existing sessions
            initializeTypingForCustomer();
            
            // Check if agent has already joined
            setTimeout(() => {
                checkIfAgentJoined();
            }, 1000);
        }
    });

    function fetchQuickActions() {
        // Build URL with API key parameter if available
        let url = baseUrl + 'chat/quick-actions';
        if (apiKey) {
            url += '?api_key=' + encodeURIComponent(apiKey);
        }
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                const quickActionsButtons = document.getElementById('quickActionsButtons');
                if (!quickActionsButtons) return;
                
                quickActionsButtons.innerHTML = '';

                data.forEach(action => {
                    const btn = document.createElement('button');
                    btn.classList.add('quick-action-btn');
                    btn.textContent = action.display_name;
                    btn.onclick = () => sendQuickMessage(action.keyword);
                    quickActionsButtons.appendChild(btn);
                });
            })
            .catch(error => {
                // Fallback to hide the toolbar if quick actions fail to load
                const toolbar = document.getElementById('quickActionsToolbar');
                if (toolbar) {
                    toolbar.style.display = 'none';
                }
            });
    }

    function sendQuickMessage(keyword) {
        const messageInput = document.getElementById('messageInput');
        messageInput.value = keyword;
        
        // Trigger the form submission to send the message
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.dispatchEvent(new Event('submit'));
        }
    }
    
    // File upload functionality
    let selectedFile = null;
    
    // Drag and drop support
    document.addEventListener('DOMContentLoaded', function() {
        const chatInputArea = document.querySelector('.chat-input-area');
        if (chatInputArea) {
            chatInputArea.addEventListener('dragover', handleDragOver);
            chatInputArea.addEventListener('drop', handleFileDrop);
            chatInputArea.addEventListener('dragleave', handleDragLeave);
        }
    });
    
    function handleFileSelect(event) {
        const file = event.target.files[0];
        if (file) {
            selectedFile = file;
            // Ensure DOM is ready before showing preview
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => showFilePreview(file));
            } else {
                showFilePreview(file);
            }
        }
    }
    
    function handleDragOver(event) {
        event.preventDefault();
        event.stopPropagation();
        event.currentTarget.classList.add('drag-over');
    }
    
    function handleDragLeave(event) {
        event.preventDefault();
        event.stopPropagation();
        event.currentTarget.classList.remove('drag-over');
    }
    
    function handleFileDrop(event) {
        event.preventDefault();
        event.stopPropagation();
        event.currentTarget.classList.remove('drag-over');
        
        const files = event.dataTransfer.files;
        if (files.length > 0) {
            selectedFile = files[0];
            // Ensure DOM is ready before showing preview
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => showFilePreview(files[0]));
            } else {
                showFilePreview(files[0]);
            }
        }
    }
    
    function showFilePreview(file) {
        const preview = document.getElementById('filePreview');
        if (!preview) return;
        
        const fileName = preview.querySelector('.file-name');
        const fileSize = preview.querySelector('.file-size');
        const fileIcon = preview.querySelector('.file-icon');
        
        if (!fileName || !fileSize || !fileIcon) {
            console.error('File preview elements not found');
            return;
        }
        
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        
        // Set appropriate icon based on file type
        const extension = file.name.split('.').pop().toLowerCase();
        fileIcon.className = getFileIcon(extension);
        
        preview.style.display = 'block';
    }
    
    function removeFilePreview() {
        selectedFile = null;
        document.getElementById('filePreview').style.display = 'none';
        document.getElementById('fileInput').value = '';
    }
    
    // Send regular text message via WebSocket
    function sendTextMessage(message) {
        if (ws && ws.readyState === WebSocket.OPEN && sessionId) {
            const messageData = {
                type: 'message',
                session_id: sessionId,
                message: message,
                sender_type: 'customer',
                sender_id: null
            };
            
            ws.send(JSON.stringify(messageData));
            
            // Clear the message input
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.value = '';
            }
        }
    }
    
    // Trigger file upload dialog (similar to bo-livechat)
    function triggerFileUpload() {
        document.getElementById('fileInput').click();
    }
    
    // Handle form submission with file or text message
    function submitMessageOrFile(event) {
        if (event) event.preventDefault();
        
        if (selectedFile) {
            // Upload file instead of sending text message
            return uploadFile();
        } else {
            // Send regular text message
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            if (message) {
                sendTextMessage(message);
            }
        }
        
        return false;
    }
    
    function uploadFile() {
        if (!selectedFile || !sessionId) {
            return;
        }
        
        const formData = new FormData();
        formData.append('file', selectedFile);
        formData.append('session_id', sessionId);
        
        // Show progress
        showUploadProgress();
        
        fetch(baseUrl + 'chat/upload-file', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideUploadProgress();
            
            if (data.success) {
                // File uploaded successfully, remove preview
                removeFilePreview();
                
                // Send WebSocket notification for real-time updates
                if (ws && ws.readyState === WebSocket.OPEN && data.file_data) {
                    const fileMessage = {
                        type: 'file_message',
                        id: data.message_id,
                        session_id: sessionId,
                        sender_type: 'customer',
                        sender_id: null,
                        sender_name: data.file_data.customer_name || 'Customer',
                        message: '', // No text message, just show the file
                        message_type: data.file_data.file_type || 'file',
                        file_data: data.file_data,
                        timestamp: new Date().toISOString(),
                        created_at: new Date().toISOString()
                    };
                    
                    ws.send(JSON.stringify(fileMessage));
                }
                
                console.log('File uploaded successfully:', data.file_data);
            } else {
                alert('File upload failed: ' + data.error);
            }
        })
        .catch(error => {
            hideUploadProgress();
            console.error('File upload error:', error);
            alert('File upload failed. Please try again.');
        });
    }
    
    function showUploadProgress() {
        document.getElementById('fileUploadProgress').style.display = 'block';
        // Animate progress bar
        const progressFill = document.querySelector('.progress-fill');
        progressFill.style.width = '0%';
        
        // Simulate progress (in a real implementation, you'd track actual upload progress)
        let progress = 0;
        const interval = setInterval(() => {
            progress += Math.random() * 30;
            if (progress > 90) progress = 90;
            progressFill.style.width = progress + '%';
            
            if (progress >= 90) {
                clearInterval(interval);
            }
        }, 200);
    }
    
    function hideUploadProgress() {
        const progressElement = document.getElementById('fileUploadProgress');
        const progressFill = document.querySelector('.progress-fill');
        
        // Complete the progress
        progressFill.style.width = '100%';
        
        setTimeout(() => {
            progressElement.style.display = 'none';
            progressFill.style.width = '0%';
        }, 500);
    }
    
    function formatFileSize(bytes) {
        if (bytes >= 1073741824) {
            return (bytes / 1073741824).toFixed(2) + ' GB';
        } else if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(2) + ' MB';
        } else if (bytes >= 1024) {
            return (bytes / 1024).toFixed(2) + ' KB';
        } else {
            return bytes + ' B';
        }
    }
    
    function getFileIcon(extension) {
        const iconMap = {
            // Images
            'jpg': 'fas fa-image text-primary',
            'jpeg': 'fas fa-image text-primary',
            'png': 'fas fa-image text-primary',
            'gif': 'fas fa-image text-primary',
            'webp': 'fas fa-image text-primary',
            'bmp': 'fas fa-image text-primary',
            
            // Videos
            'mp4': 'fas fa-video text-danger',
            'avi': 'fas fa-video text-danger',
            'mov': 'fas fa-video text-danger',
            'wmv': 'fas fa-video text-danger',
            'flv': 'fas fa-video text-danger',
            'webm': 'fas fa-video text-danger',
            
            // Documents
            'pdf': 'fas fa-file-pdf text-danger',
            'doc': 'fas fa-file-word text-info',
            'docx': 'fas fa-file-word text-info',
            'txt': 'fas fa-file-alt text-info',
            'rtf': 'fas fa-file-alt text-info',
            
            // Archives
            'zip': 'fas fa-file-archive text-warning',
            'rar': 'fas fa-file-archive text-warning',
            '7z': 'fas fa-file-archive text-warning',
            'tar': 'fas fa-file-archive text-warning',
            'gz': 'fas fa-file-archive text-warning',
            
            // Spreadsheets
            'xls': 'fas fa-file-excel text-success',
            'xlsx': 'fas fa-file-excel text-success',
            'csv': 'fas fa-file-csv text-success',
            
            // Presentations
            'ppt': 'fas fa-file-powerpoint text-warning',
            'pptx': 'fas fa-file-powerpoint text-warning'
        };
        
        return iconMap[extension] || 'fas fa-file text-secondary';
    }
    
    // Function to handle customer leaving the chat (session closes for both customer and admin)
    function closeCustomerChat() {
        if (sessionId && confirm('Are you sure you want to leave this chat? This will close the chat session for both you and the agent.')) {
            // Get references to UI elements
            const closeBtn = document.getElementById('customerCloseBtn');
            const messageInput = document.getElementById('messageInput');
            const sendBtn = document.querySelector('.btn-send');
            
            // Function to reset UI state in case of error
            const resetUIState = () => {
                if (closeBtn) {
                    closeBtn.disabled = false;
                    closeBtn.textContent = 'Leave Chat';
                }
                if (messageInput) messageInput.disabled = false;
                if (sendBtn) sendBtn.disabled = false;
            };
            
            // Function to complete successful leave process
            const completeLeaveProcess = (message) => {
                // Show message that customer has left
                displaySystemMessage(message || 'You have left the chat. Thank you for contacting us!');
                
                // Clear the session ID so it can't be reused
                sessionId = null;
                currentSessionId = null;
                
                // Hide the close button
                if (closeBtn) {
                    closeBtn.style.display = 'none';
                }
                
                // Show start new chat interface after a delay
                setTimeout(() => {
                    showStartNewChatInterface();
                }, 2000);
            };
            
            // Disable the close button to prevent multiple clicks
            if (closeBtn) {
                closeBtn.disabled = true;
                closeBtn.textContent = 'Ending...';
            }
            
            // Disable message input
            if (messageInput) messageInput.disabled = true;
            if (sendBtn) sendBtn.disabled = true;
            
            // Set a timeout as a fallback in case the request hangs
            const timeoutId = setTimeout(() => {
                resetUIState();
                alert('The request timed out. Please try again.');
            }, 10000); // 10 second timeout
            
            // Additional timeout to force UI reset if everything else fails
            const forceResetTimeoutId = setTimeout(() => {
                resetUIState();
            }, 12000); // 12 second force reset
            
            // End the session completely (the HTTP request will handle the system message)
            fetch('/chat/end-customer-session', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `session_id=${sessionId}`
            })
            .then(response => {
                // Clear the timeout since we got a response
                clearTimeout(timeoutId);
                clearTimeout(forceResetTimeoutId);
                
                // Handle both success and error status codes
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                return response.json();
            })
            .then(result => {
                
                if (result && result.success) {
                    completeLeaveProcess(result.message);
                } else {
                    // Handle server-side errors
                    resetUIState();
                    alert(result?.error || 'Failed to end chat session. Please try again.');
                }
            })
            .catch(error => {
                // Clear the timeout since we caught an error
                clearTimeout(timeoutId);
                clearTimeout(forceResetTimeoutId);
                
                // Always reset UI state on any error
                resetUIState();
                
                // Show appropriate error message
                let errorMessage = 'Failed to end chat session. Please try again.';
                if (error.message && error.message.includes('Failed to fetch')) {
                    errorMessage = 'Network error. Please check your connection and try again.';
                } else if (error.message) {
                    errorMessage = `Error: ${error.message}`;
                }
                
                alert(errorMessage);
            });
        }
    }
    
    function displaySystemMessage(message) {
        const container = document.getElementById('messagesContainer');
        if (!container) return;
        
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message system';
        
        const p = document.createElement('p');
        p.textContent = message;
        
        messageDiv.appendChild(p);
        container.appendChild(messageDiv);
        
        container.scrollTop = container.scrollHeight;
    }
    
    // Function to show start new chat interface
    function showStartNewChatInterface() {
        const chatInterface = document.getElementById('chatInterface');
        if (chatInterface) {
            // For logged users, show simplified interface
            if (userRole === 'loggedUser' && (externalFullname || externalUsername)) {
                // Seamless experience for logged users - no form needed
                chatInterface.innerHTML = `
                    <div class="chat-start-form" style="text-align: center; padding: 30px;">
                        <h4>Chat Session Ended</h4>
                        <p style="color: #666; margin-bottom: 25px;">Thank you for contacting us. Your conversation has ended.</p>
                        <p style="color: #28a745; font-size: 14px; margin-bottom: 20px;">
                            ✓ Logged in as ${externalFullname || externalUsername}
                        </p>
                        <button type="button" class="btn btn-primary start-new-chat-btn-local" style="padding: 12px 24px; font-size: 16px;">
                            Start New Chat
                        </button>
                    </div>
                `;
            } else {
                // For anonymous users, show the full form
                const nameFieldHtml = `
                    <div class="form-group">
                        <label for="customerName">Your Name (Optional)</label>
                        <input type="text" id="customerName" name="customer_name" placeholder="Enter your name (or leave blank for Anonymous)">
                    </div>
                `;
                
                const roleFieldsHtml = `
                    <input type="hidden" name="user_role" value="${userRole}">
                    <input type="hidden" name="external_username" value="${externalUsername}">
                    <input type="hidden" name="external_fullname" value="${externalFullname}">
                    <input type="hidden" name="external_system_id" value="${externalSystemId}">
                    <input type="hidden" name="api_key" value="${apiKey}">
                    <input type="hidden" name="customer_phone" value="${customerPhone}">
                `;
                
                chatInterface.innerHTML = `
                    <div class="chat-start-form">
                        <h4>Start a New Conversation</h4>
                        <p style="color: #666; margin-bottom: 20px;">Your previous chat has ended. You can start a new conversation below:</p>
                        <form id="startChatForm">
                            ${roleFieldsHtml}
                            ${nameFieldHtml}
                            <div class="form-group">
                                <label for="customerProblem">What do you need help with? *</label>
                                <input type="text" id="customerProblem" name="chat_topic" required placeholder="Describe your issue or question...">
                            </div>
                            <div class="form-group">
                                <label for="customerEmail">Email (Optional)</label>
                                <input type="email" id="customerEmail" name="email" value="${externalEmail}">
                            </div>
                            <button type="submit" class="btn btn-primary">Start New Chat</button>
                        </form>
                    </div>
                `;
            }
        }
    }
    
    // Function to start new chat for logged users without form (local version)
    function startNewChatForLoggedUserLocal() {
        // Show loading state
        const chatInterface = document.getElementById('chatInterface');
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
        formData.append('user_role', userRole);
        formData.append('external_username', externalUsername);
        formData.append('external_fullname', externalFullname);
        formData.append('external_system_id', externalSystemId);
        formData.append('api_key', apiKey);
        formData.append('customer_phone', customerPhone);
        formData.append('customer_name', externalFullname || externalUsername);
        formData.append('chat_topic', 'General Support'); // Default topic for logged users
        formData.append('email', externalEmail);
        
        
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
                            
                            <div class="typing-indicator" id="typingIndicator" style="display: none;">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                            
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
                                            <span class="recording-text">Recording...Move away from button to cancel</span>
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
                    initializeMessageForm();
                    
                    // Initialize unified input after chat interface is created
                    setTimeout(() => {
                        unifiedInputInitialized = false; // Reset flag for new interface
                        tryInitializeUnifiedInput();
                    }, 100);
                    
                    // Load quick actions and initialize features
                    setTimeout(() => {
                        fetchQuickActions();
                        // Initialize typing functionality
                        initializeTypingForCustomer();
                        
                        // Explicitly load chat history for the new session
                        // This ensures history loads even if WebSocket 'connected' event doesn't fire
                        if (typeof loadChatHistory === 'function') {
                            loadChatHistory();
                        }
                        
                        // Check if agent has already joined after history loads
                        setTimeout(() => {
                            checkIfAgentJoined();
                        }, 1000);
                    }, 300);
                    
                    // Re-initialize voice recording immediately
                    setTimeout(() => {
                        if (typeof initializeVoiceRecording === 'function') {
                            initializeVoiceRecording();
                        }
                    }, 100);
                }
            } else {
                // Show error and revert to previous state
                alert(result.error || 'Failed to start new chat session');
                showStartNewChatInterface();
            }
        })
        .catch(error => {
            alert('Failed to connect. Please try again.');
            showStartNewChatInterface();
        });
    }
    
    // Add event listener for Start New Chat button (local version)
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('start-new-chat-btn-local')) {
            e.preventDefault();
            startNewChatForLoggedUserLocal();
        }
    });
    
    // Function to hide quick actions toolbar when agent joins
    function hideQuickActionsToolbar() {
        const toolbar = document.getElementById('quickActionsToolbar');
        if (toolbar) {
            toolbar.style.display = 'none';
        }
    }
    
    // Override the handleWebSocketMessage function to include our typing indicator logic
    if (typeof handleWebSocketMessage !== 'undefined') {
        const originalHandleWebSocketMessage = handleWebSocketMessage;
        
        window.handleWebSocketMessage = function(data) {
            // Call the original handler
            originalHandleWebSocketMessage(data);
            
            // Handle typing indicator specifically for customer interface
            if (data.type === 'typing') {
                const indicator = document.getElementById('typingIndicator');
                if (indicator && data.session_id === sessionId) {
                    // Show typing indicator only if agent is typing and it's not the customer
                    if (data.is_typing && data.user_type !== 'customer') {
                        indicator.style.display = 'flex';
                    } else {
                        indicator.style.display = 'none';
                    }
                }
            }
            
            // Hide quick actions when agent joins the chat
            if (data.type === 'agent_assigned' && data.session_id === sessionId) {
                hideQuickActionsToolbar();
            }
        };
    }
    
    // Add typing event listeners to message input (integrate with chat.js)
    function initializeTypingForCustomer() {
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            // Use the chat.js typing functionality if available
            if (typeof sendTypingIndicator === 'function') {
                messageInput.addEventListener('input', function() {
                    if (typeof isTyping === 'undefined' || !isTyping) {
                        sendTypingIndicator(true);
                    }
                    
                    if (typeof typingTimer !== 'undefined') {
                        clearTimeout(typingTimer);
                    }
                    
                    typingTimer = setTimeout(() => {
                        sendTypingIndicator(false);
                    }, 1000);
                });
                
                messageInput.addEventListener('blur', function() {
                    if (typeof isTyping !== 'undefined' && isTyping) {
                        sendTypingIndicator(false);
                    }
                });
            } else {
                // Fallback if chat.js typing functions are not available
                let localIsTyping = false;
                let localTypingTimer = null;
                
                messageInput.addEventListener('input', function() {
                    if (ws && ws.readyState === WebSocket.OPEN && sessionId) {
                        if (!localIsTyping) {
                            localIsTyping = true;
                            ws.send(JSON.stringify({
                                type: 'typing',
                                session_id: sessionId,
                                user_type: 'customer',
                                is_typing: true
                            }));
                        }
                        
                        clearTimeout(localTypingTimer);
                        localTypingTimer = setTimeout(() => {
                            localIsTyping = false;
                            ws.send(JSON.stringify({
                                type: 'typing',
                                session_id: sessionId,
                                user_type: 'customer',
                                is_typing: false
                            }));
                        }, 1000);
                    }
                });
                
                messageInput.addEventListener('blur', function() {
                    if (ws && ws.readyState === WebSocket.OPEN && sessionId && localIsTyping) {
                        localIsTyping = false;
                        ws.send(JSON.stringify({
                            type: 'typing',
                            session_id: sessionId,
                            user_type: 'customer',
                            is_typing: false
                        }));
                    }
                });
            }
        }
    }
    
    // Function to close the fullscreen chat iframe only
    function closeFullscreen() {
        // If we're in an iframe (which we should be for fullscreen chat),
        // send a message to the parent window to close the iframe
        if (window.parent !== window) {
            try {
                window.parent.postMessage({
                    type: 'close_fullscreen_chat',
                    source: 'livechat_iframe',
                    sessionId: sessionId
                }, '*');
            } catch (e) {
                console.error('Failed to send close message to parent:', e);
            }
        } else {
            // If we're not in an iframe, we might be in a popup or standalone window
            // In this case, we can try to close the window
            try {
                window.close();
            } catch (e) {
                // If we can't close, try to go back in history
                try {
                    window.history.back();
                } catch (historyError) {
                    // Last resort - show message
                    alert('Unable to close the chat. Please use your browser\'s back button or close this tab.');
                }
            }
        }
    }
    
    // Track if unified input is initialized to prevent duplicate listeners
    let unifiedInputInitialized = false;
    
    // Initialize Unified Input Box Functionality
    function initializeUnifiedInput() {
        // Prevent duplicate initialization
        if (unifiedInputInitialized) {
            return;
        }
        
        const plusButton = document.getElementById('plusButton');
        const emojiButton = document.getElementById('emojiButton');
        const emojiPicker = document.getElementById('emojiPicker');
        const quickActionsToggle = document.getElementById('quickActionsToggle');
        const quickActionsToolbar = document.getElementById('quickActionsToolbar');
        const sendButton = document.getElementById('sendButton');
        const fileInput = document.getElementById('fileInput');
        
        // Check if all required elements exist
        if (!plusButton || !emojiButton || !emojiPicker) {
            return;
        }
        
        unifiedInputInitialized = true;
        
        // Plus Button - Direct File Upload
        if (plusButton) {
            // Use event delegation on the document to catch clicks even if button is replaced
            function handlePlusButtonClick(e) {
                // Check if the click is on the plus button or its children
                const clickedElement = e.target.closest('#plusButton');
                if (!clickedElement) return;
                
                e.stopPropagation();
                e.preventDefault();
                
                // Trigger file upload directly
                if (typeof triggerFileUpload === 'function') {
                    triggerFileUpload();
                } else {
                    const fileInput = document.getElementById('fileInput');
                    if (fileInput) {
                        fileInput.click();
                    }
                }
            }
            
            // Add both direct listener and delegation
            plusButton.addEventListener('click', handlePlusButtonClick);
            document.addEventListener('click', handlePlusButtonClick, true);
        }
        
        // Voice Recording Button
        const voiceRecordBtn = document.getElementById('voiceRecordBtn');
        if (voiceRecordBtn) {
            // Prevent click events from interfering with hold-to-record
            voiceRecordBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }, true);
            
            // Initialize hold-to-record listeners if the function exists
            if (typeof addHoldToRecordListeners === 'function') {
                addHoldToRecordListeners(voiceRecordBtn);
            } else if (typeof window.addHoldToRecordListeners === 'function') {
                window.addHoldToRecordListeners(voiceRecordBtn);
            } else {
                // Fallback: try to initialize voice recording
                if (typeof initializeVoiceRecording === 'function') {
                    initializeVoiceRecording();
                } else if (typeof window.initializeVoiceRecording === 'function') {
                    window.initializeVoiceRecording();
                }
            }
        }
        
        // Emoji Picker Toggle
        if (emojiButton && emojiPicker) {
            initializeEmojiPicker();
            
            // Use event delegation on the document to catch clicks even if button is replaced
            function handleEmojiButtonClick(e) {
                // Check if the click is on the emoji button or its children
                const clickedElement = e.target.closest('#emojiButton');
                if (!clickedElement) return;
                
                e.stopPropagation();
                e.preventDefault();
                
                // Get the picker element fresh each time
                const picker = document.getElementById('emojiPicker');
                const menu = document.getElementById('plusButtonMenu');
                
                if (!picker) return;
                
                // Simple check: if display is 'none' or empty, show it; otherwise hide it
                const currentDisplay = picker.style.display;
                const computedDisplay = window.getComputedStyle(picker).display;
                
                if (currentDisplay === 'none' || currentDisplay === '' || computedDisplay === 'none') {
                    picker.style.display = 'block';
                    picker.style.visibility = 'visible';
                    picker.style.opacity = '1';
                    // Close plus menu when opening emoji picker
                    if (menu) {
                        menu.style.display = 'none';
                    }
                } else {
                    picker.style.display = 'none';
                }
            }
            
            // Add both direct listener and delegation
            emojiButton.addEventListener('click', handleEmojiButtonClick);
            document.addEventListener('click', handleEmojiButtonClick, true);
            
            // Close emoji picker when clicking outside (use capture phase to avoid conflicts)
            document.addEventListener('click', function closeEmojiHandler(e) {
                const picker = document.getElementById('emojiPicker');
                const button = document.getElementById('emojiButton');
                if (!picker || !button) return;
                
                const style = window.getComputedStyle(picker);
                if (style.display !== 'none' && style.visibility !== 'hidden') {
                    // Don't close if clicking on the button or picker itself
                    if (!picker.contains(e.target) && !button.contains(e.target) && e.target !== button) {
                        picker.style.display = 'none';
                    }
                }
            }, true);
        }
        
        // Quick Actions Toggle
        if (quickActionsToggle && quickActionsToolbar) {
            const quickActionsButtons = quickActionsToolbar.querySelector('.quick-actions-buttons');
            let quickActionsVisible = false;
            
            quickActionsToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                quickActionsVisible = !quickActionsVisible;
                if (quickActionsButtons) {
                    quickActionsToolbar.style.display = quickActionsVisible ? 'block' : 'none';
                }
            });
            
            // Show quick actions button if there are quick actions available
            if (quickActionsButtons && quickActionsButtons.children.length > 0) {
                quickActionsToggle.style.display = 'flex';
            }
        }
        
        // Send Button Icon Switching
        function updateSendButtonIcon() {
            if (!sendButton) return;
            
            const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
            const sendIcon = sendButton.querySelector('.send-icon');
            const uploadIcon = sendButton.querySelector('.upload-icon');
            
            if (hasFile && uploadIcon && sendIcon) {
                sendIcon.style.display = 'none';
                uploadIcon.style.display = 'block';
            } else if (sendIcon && uploadIcon) {
                sendIcon.style.display = 'block';
                uploadIcon.style.display = 'none';
            }
        }
        
        // Monitor file input changes
        if (fileInput) {
            fileInput.addEventListener('change', updateSendButtonIcon);
        }
        
        // Also check on form initialization
        updateSendButtonIcon();
        
        // Monitor file preview removal
        const removeFileBtn = document.querySelector('.btn-remove-file');
        if (removeFileBtn) {
            removeFileBtn.addEventListener('click', function() {
                setTimeout(updateSendButtonIcon, 100);
            });
        }
        
        // Override removeFilePreview to update icon
        const originalRemoveFilePreview = window.removeFilePreview;
        if (typeof originalRemoveFilePreview === 'function') {
            window.removeFilePreview = function() {
                originalRemoveFilePreview();
                setTimeout(updateSendButtonIcon, 100);
            };
        }
    }
    
    // Initialize Emoji Picker with Daily-Use Emojis
    window.initializeEmojiPicker = function() {
        const emojiGrid = document.getElementById('emojiGrid');
        if (!emojiGrid) return;
        
        // Daily-use emojis - similar to screenshot design
        const dailyEmojis = [
            '😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣',
            '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰',
            '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜',
            '🤪', '🤨', '🧐', '🤓', '😎', '🤩', '🥳', '😏',
            '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣',
            '😖', '😫', '😩', '🥺', '😢', '😭', '😤', '😠',
            '😡', '🤬', '🤯', '😳', '🥵', '🥶', '😱', '😨',
            '😰', '😥', '😓', '🤗', '🤔', '🤭', '🤫', '🤥',
            '😶', '😐', '😑', '😬', '🙄', '😯', '😦', '😧',
            '😮', '😲', '🥱', '😴', '🤤', '😪', '😵', '🤐',
            '👍', '👎', '👌', '✌️', '🤞', '🤟', '🤘', '🤙',
            '👏', '🙌', '👐', '🤲', '🤝', '🙏', '✍️', '💪',
            '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍',
            '🤎'
        ];
        
        emojiGrid.innerHTML = '';
        dailyEmojis.forEach(emoji => {
            const emojiItem = document.createElement('div');
            emojiItem.className = 'emoji-item';
            emojiItem.textContent = emoji;
            emojiItem.addEventListener('click', function() {
                insertEmoji(emoji);
            });
            emojiGrid.appendChild(emojiItem);
        });
    };
    
    // Helper function to check if element is visible - make it global
    window.isElementVisible = function(element) {
        if (!element) return false;
        const style = window.getComputedStyle(element);
        return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
    };
    
    // Toggle functions for inline onclick handlers (fallback) - make global
    window.togglePlusMenu = function(event) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }
        const plusMenu = document.getElementById('plusButtonMenu');
        const emojiPicker = document.getElementById('emojiPicker');
        if (plusMenu) {
            const isVisible = window.isElementVisible ? window.isElementVisible(plusMenu) : (window.getComputedStyle(plusMenu).display !== 'none');
            plusMenu.style.display = isVisible ? 'none' : 'block';
            // Close emoji picker when opening plus menu
            if (!isVisible && emojiPicker) {
                emojiPicker.style.display = 'none';
            }
        }
    };
    
    window.toggleEmojiPicker = function(event) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }
        const emojiPicker = document.getElementById('emojiPicker');
        const plusMenu = document.getElementById('plusButtonMenu');
        if (emojiPicker) {
            // Initialize emoji picker if not already initialized
            const emojiGrid = emojiPicker.querySelector('#emojiGrid');
            if (emojiGrid && emojiGrid.children.length === 0) {
                initializeEmojiPicker();
            }
            
            const isVisible = window.isElementVisible ? window.isElementVisible(emojiPicker) : (window.getComputedStyle(emojiPicker).display !== 'none');
            emojiPicker.style.display = isVisible ? 'none' : 'block';
            // Close plus menu when opening emoji picker
            if (!isVisible && plusMenu) {
                plusMenu.style.display = 'none';
            }
        }
    };
    
    // Insert emoji into message input
    window.insertEmoji = function(emoji) {
        const messageInput = document.getElementById('messageInput') || document.querySelector('.unified-message-input');
        if (messageInput) {
            const cursorPos = messageInput.selectionStart || messageInput.value.length;
            const textBefore = messageInput.value.substring(0, cursorPos);
            const textAfter = messageInput.value.substring(messageInput.selectionEnd || cursorPos);
            messageInput.value = textBefore + emoji + textAfter;
            messageInput.focus();
            messageInput.setSelectionRange(cursorPos + emoji.length, cursorPos + emoji.length);
            
            // Trigger input event for any listeners
            messageInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
        
        // Close emoji picker
        const emojiPicker = document.getElementById('emojiPicker');
        if (emojiPicker) {
            emojiPicker.style.display = 'none';
        }
    };
    
    // Initialize on DOM ready and retry if elements don't exist yet
    window.tryInitializeUnifiedInput = function() {
        // Reset initialization flag if elements were removed and re-added
        const plusButton = document.getElementById('plusButton');
        const emojiButton = document.getElementById('emojiButton');
        const emojiPicker = document.getElementById('emojiPicker');
        
        if (!plusButton || !emojiButton || !emojiPicker) {
            unifiedInputInitialized = false;
            return false;
        }
        
        if (!unifiedInputInitialized) {
            initializeUnifiedInput();
            return true;
        }
        return true;
    };
    
    // Try immediately
    if (!tryInitializeUnifiedInput()) {
        // If elements don't exist, wait for DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            if (!tryInitializeUnifiedInput()) {
                // If still not found, retry after a short delay (for dynamically loaded content)
                setTimeout(function() {
                    tryInitializeUnifiedInput();
                }, 500);
            }
        });
    }
    
    // Also try when chat interface is dynamically created
    const originalShowFilePreview = window.showFilePreview;
    if (typeof originalShowFilePreview === 'function') {
        window.showFilePreview = function(file) {
            originalShowFilePreview(file);
            setTimeout(tryInitializeUnifiedInput, 100);
        };
    }
    
    // Monitor for dynamically added chat interface
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                const hasChatInput = Array.from(mutation.addedNodes).some(node => {
                    return node.nodeType === 1 && (
                        node.id === 'plusButton' || 
                        node.querySelector && node.querySelector('#plusButton')
                    );
                });
                if (hasChatInput) {
                    setTimeout(tryInitializeUnifiedInput, 100);
                }
            }
        });
    });
    
    // Start observing when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            observer.observe(document.body, { childList: true, subtree: true });
        });
    } else {
        observer.observe(document.body, { childList: true, subtree: true });
    }

</script>
<script src="<?= base_url('assets/js/voice-recording.js?v=' . time()) ?>"></script>
<?= $this->endSection() ?>
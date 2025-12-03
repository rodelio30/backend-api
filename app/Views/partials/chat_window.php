<?php
/**
 * app/Views/partials/chat_window.php
 * Reusable chat window component
 */
?>
<div class="chat-window" data-session-id="<?= $session_id ?? '' ?>">
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
    
    <div class="chat-input-area">
        <form id="messageForm">
            <input type="text" id="messageInput" placeholder="Type your message..." autocomplete="off" required>
            <button type="submit" class="btn btn-send">Send</button>
        </form>
    </div>
</div>
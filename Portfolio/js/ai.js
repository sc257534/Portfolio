// --- Enhanced Santosh AI Bot (Secure Backend Version) ---
(function() {
    // 1. Create and inject the HTML for the chatbot UI.
   const chatWidgetHTML = `
         <div class="ai-bot-float" title="Chat with Santosh AI">
            <svg width="32" height="32" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#0077b6"/><text x="12" y="17" text-anchor="middle" font-size="13" fill="#fff" font-family="Arial" font-weight="bold">AI</text></svg>
        </div>
        <div class="ai-bot-chat">
            <div class="ai-bot-header">
                <span>Santosh AI</span>
                <button class="ai-bot-close" title="Close">&times;</button>
            </div>
            <div class="ai-bot-messages"></div>
            <form class="ai-bot-form">
                <input type="text" placeholder="Ask about skills, experience..." required autocomplete="off" />
                <button type="submit">Send</button>
            </form>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', chatWidgetHTML);

    // 2. Get references to the UI elements.
    const botBtn = document.querySelector('.ai-bot-float');
    const chatWindow = document.querySelector('.ai-bot-chat');
    const closeBtn = document.querySelector('.ai-bot-close');
    const chatMessages = document.querySelector('.ai-bot-messages');
    const chatForm = document.querySelector('.ai-bot-form');
    const chatInput = chatForm.querySelector('input');

    // 3. Logic to open and close the chat window.
    const toggleChat = () => {
        chatWindow.classList.toggle('open');
        if (chatWindow.classList.contains('open') && chatMessages.children.length === 0) {
            addMsg('bot', 'Hi! 👋 I\'m Santosh AI. Ask me anything about this portfolio.');
        }
    };
    botBtn.addEventListener('click', toggleChat);
    closeBtn.addEventListener('click', toggleChat);

    // 4. Function to add a new message to the chat display.
    function addMsg(sender, text) {
        const div = document.createElement('div');
        div.className = `ai-bot-msg ai-bot-msg-${sender}`;
        div.innerHTML = text;
        chatMessages.appendChild(div);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        return div;
    }

    // 5. Handle the form submission when the user sends a message.
    chatForm.onsubmit = async (e) => {
        e.preventDefault();
        const userMessage = chatInput.value.trim();
        if (!userMessage) return;

        addMsg('user', userMessage);
        chatInput.value = '';
        const thinkingMsg = addMsg('bot', 'Thinking...');

        try {
            // **FIX:** Using a relative path to ensure it works in subdirectories.
            const response = await fetch('api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ question: userMessage })
            });

            if (!response.ok) {
                // Provide a more specific error if the file is not found (404) or if there's a server error (500).
                throw new Error(`Connection failed. Server responded with status: ${response.status}`);
            }

            const data = await response.json();
            thinkingMsg.innerHTML = data.answer || "Sorry, I received an empty response.";

        } catch (error) {
            console.error("AI Chatbot Error:", error);
            thinkingMsg.innerHTML = `Sorry, I can't connect to my brain right now. <br><small><strong>Error:</strong> ${error.message}</small>`;
        } finally {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    };
})();
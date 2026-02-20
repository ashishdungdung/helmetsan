<?php
/**
 * Interactive Helmet Selection Tool (AI Chatbot Scaffold)
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

$settings = get_option('helmetsan_features', []);
$isAiEnabled = !empty($settings['ai_chatbot_enabled']);

if (!$isAiEnabled) {
    return;
}
?>

<div id="hs-ai-tool" class="hs-ai-tool">
    <button class="hs-ai-tool__toggle hs-btn hs-btn--primary" aria-label="Open AI Helmet Selector" aria-expanded="false" data-ai-toggle>
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hs-icon hs-icon--mr"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
        AI Helmet Finder
    </button>

    <div class="hs-ai-tool__window hs-panel" aria-hidden="true" data-ai-window>
        <div class="hs-ai-tool__header">
            <h3><strong>AI Helmet Expert</strong></h3>
            <button class="hs-ai-tool__close" aria-label="Close AI tool" data-ai-close>&times;</button>
        </div>
        
        <div class="hs-ai-tool__body" data-ai-messages>
            <div class="hs-ai-tool__message hs-ai-tool__message--bot">
                <div class="hs-avatar">🤖</div>
                <div class="hs-bubble">Hi there! Looking for the perfect helmet? Tell me what kind of riding you do, what bike you ride, or your budget.</div>
            </div>
            <!-- Scaffold for user messages 
            <div class="hs-ai-tool__message hs-ai-tool__message--user">
                <div class="hs-bubble">I ride a ninja 400 and need a quiet helmet under $400</div>
            </div>
            -->
            <!-- Scaffold for loading indicator
            <div class="hs-ai-tool__message hs-ai-tool__message--bot hs-ai-tool__message--typing">
                <div class="hs-avatar">🤖</div>
                <div class="hs-bubble hs-bubble--typing"><span>.</span><span>.</span><span>.</span></div>
            </div>
            -->
        </div>

        <form class="hs-ai-tool__form" data-ai-form>
            <input type="text" placeholder="Type your request here..." class="hs-ai-tool__input" required aria-label="Chat input" />
            <button type="submit" class="hs-ai-tool__send hs-btn hs-btn--primary" aria-label="Send message">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            </button>
        </form>
    </div>
</div>

<style>
/* Scoped AI Tool Scaffold Styles */
.hs-ai-tool {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
}
.hs-ai-tool__toggle {
    border-radius: 50px;
    box-shadow: var(--hs-shadow-md);
    display: flex;
    align-items: center;
    padding: 12px 24px;
    font-weight: 600;
}
.hs-ai-tool__window {
    position: absolute;
    bottom: calc(100% + 16px);
    right: 0;
    width: 350px;
    max-width: calc(100vw - 48px);
    height: 500px;
    max-height: calc(100vh - 100px);
    display: flex;
    flex-direction: column;
    box-shadow: var(--hs-shadow-lg);
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px);
    transition: all 0.2s ease-out;
}
.hs-ai-tool__window.is-open {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}
.hs-ai-tool__header {
    padding: 16px;
    border-bottom: 1px solid var(--c-surface-hover);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--c-surface);
    border-radius: var(--hs-radius-lg) var(--hs-radius-lg) 0 0;
}
.hs-ai-tool__header h3 {
    margin: 0;
    font-size: var(--fs-base);
    color: var(--c-text);
}
.hs-ai-tool__close {
    background: transparent;
    border: none;
    font-size: 24px;
    line-height: 1;
    color: var(--c-text-muted);
    cursor: pointer;
    padding: 0;
}
.hs-ai-tool__body {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    background: var(--c-surface-hover);
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.hs-ai-tool__message {
    display: flex;
    gap: 12px;
    align-items: flex-end;
}
.hs-ai-tool__message--user {
    flex-direction: row-reverse;
}
.hs-avatar {
    width: 32px;
    height: 32px;
    background: var(--c-surface);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    box-shadow: var(--hs-shadow-sm);
    flex-shrink: 0;
}
.hs-bubble {
    background: var(--c-surface);
    padding: 12px 16px;
    border-radius: 16px 16px 16px 4px;
    color: var(--c-text);
    font-size: 0.95rem;
    box-shadow: var(--hs-shadow-sm);
    max-width: 85%;
    line-height: 1.4;
}
.hs-ai-tool__message--user .hs-bubble {
    background: var(--c-primary);
    color: white;
    border-radius: 16px 16px 4px 16px;
}
.hs-bubble--typing {
    display: flex;
    gap: 4px;
    padding: 16px;
}
.hs-bubble--typing span {
    width: 6px;
    height: 6px;
    background: var(--c-text-muted);
    border-radius: 50%;
    animation: hsTyping 1.4s infinite forwards;
}
.hs-bubble--typing span:nth-child(1) { animation-delay: 0s; }
.hs-bubble--typing span:nth-child(2) { animation-delay: 0.2s; }
.hs-bubble--typing span:nth-child(3) { animation-delay: 0.4s; }
@keyframes hsTyping {
    0%, 100% { transform: translateY(0); opacity: 0.5; }
    50% { transform: translateY(-4px); opacity: 1; }
}
.hs-ai-tool__form {
    display: flex;
    padding: 12px;
    gap: 8px;
    background: var(--c-surface);
    border-top: 1px solid var(--c-surface-hover);
    border-radius: 0 0 var(--hs-radius-lg) var(--hs-radius-lg);
}
.hs-ai-tool__input {
    flex: 1;
    border: 1px solid var(--c-border);
    border-radius: 24px;
    padding: 10px 16px;
    outline: none;
    background: var(--c-surface-hover);
    color: var(--c-text);
}
.hs-ai-tool__input:focus {
    border-color: var(--c-primary);
    background: var(--c-surface);
}
.hs-ai-tool__send {
    border-radius: 50%;
    width: 44px;
    height: 44px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-ai-toggle]');
    const closeBtn = document.querySelector('[data-ai-close]');
    const windowEl = document.querySelector('[data-ai-window]');
    const form = document.querySelector('[data-ai-form]');
    const input = document.querySelector('.hs-ai-tool__input');
    const msgs = document.querySelector('[data-ai-messages]');

    if (!toggle || !windowEl) return;

    toggle.addEventListener('click', () => {
        const isOpen = windowEl.classList.contains('is-open');
        if (isOpen) {
            windowEl.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        } else {
            windowEl.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
            if (input) input.focus();
        }
    });

    closeBtn.addEventListener('click', () => {
        windowEl.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
    });

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const val = input.value.trim();
            if (!val) return;

            // Add user message
            const id = Date.now();
            msgs.insertAdjacentHTML('beforeend', `
                <div class="hs-ai-tool__message hs-ai-tool__message--user" style="opacity:0; transform:translateY(10px); transition:all 0.2s">
                    <div class="hs-bubble">${val.replace(/</g, "&lt;")}</div>
                </div>
            `);
            const newMessage = msgs.lastElementChild;
            requestAnimationFrame(() => {
                newMessage.style.opacity = '1';
                newMessage.style.transform = 'translateY(0)';
            });
            input.value = '';
            msgs.scrollTop = msgs.scrollHeight;

            // Artificial delay for scaffold bot
            setTimeout(() => {
                // Add typing indicator
                msgs.insertAdjacentHTML('beforeend', `
                    <div class="hs-ai-tool__message hs-ai-tool__message--bot hs-ai-tool__message--typing" id="typing-${id}">
                        <div class="hs-avatar">🤖</div>
                        <div class="hs-bubble hs-bubble--typing"><span>.</span><span>.</span><span>.</span></div>
                    </div>
                `);
                msgs.scrollTop = msgs.scrollHeight;

                setTimeout(() => {
                    const typing = document.getElementById(`typing-${id}`);
                    if (typing) typing.remove();

                    msgs.insertAdjacentHTML('beforeend', `
                        <div class="hs-ai-tool__message hs-ai-tool__message--bot">
                            <div class="hs-avatar">🤖</div>
                            <div class="hs-bubble">I'm still learning about helmets! This feature is coming soon to help you find the absolute perfect fit.</div>
                        </div>
                    `);
                    msgs.scrollTop = msgs.scrollHeight;
                }, 1500);

            }, 500);
        });
    }
});
</script>

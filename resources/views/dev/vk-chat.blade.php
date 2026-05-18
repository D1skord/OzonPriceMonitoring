<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VK Bot Dev Chat</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; margin: 0; background: #e5e9ef; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; padding: 24px 16px; }

        .chat-window { background: #fff; border-radius: 12px; width: 100%; max-width: 480px; display: flex; flex-direction: column; height: calc(100vh - 48px); box-shadow: 0 4px 24px rgba(0,0,0,.12); overflow: hidden; }

        .chat-header { background: #0077ff; color: #fff; padding: 14px 18px; display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .chat-header__title { font-size: 16px; font-weight: 600; flex: 1; }
        .chat-header__badge { background: rgba(255,255,255,.25); font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 10px; letter-spacing: .5px; }

        .chat-messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 8px; }

        .msg { max-width: 78%; display: flex; flex-direction: column; }
        .msg--user { align-self: flex-end; align-items: flex-end; }
        .msg--bot { align-self: flex-start; align-items: flex-start; }

        .msg__bubble { padding: 9px 12px; border-radius: 16px; font-size: 14px; line-height: 1.45; white-space: pre-wrap; word-break: break-word; }
        .msg--user .msg__bubble { background: #0077ff; color: #fff; border-bottom-right-radius: 4px; }
        .msg--bot .msg__bubble { background: #f0f0f0; color: #111; border-bottom-left-radius: 4px; }

        .msg__time { font-size: 11px; color: #aaa; margin-top: 3px; padding: 0 2px; }

        .chat-keyboard { padding: 8px 12px 4px; display: flex; gap: 8px; border-top: 1px solid #e5e7eb; flex-shrink: 0; }
        .kb-btn { flex: 1; padding: 9px 6px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; transition: opacity .15s; border: none; }
        .kb-btn:hover { opacity: .85; }
        .kb-btn--primary { background: #0077ff; color: #fff; }
        .kb-btn--secondary { background: #e5e9ef; color: #333; }

        .chat-input-row { display: flex; gap: 8px; padding: 10px 12px 14px; border-top: 1px solid #e5e7eb; flex-shrink: 0; }
        .chat-input { flex: 1; border: 1px solid #d1d5db; border-radius: 20px; padding: 9px 14px; font-size: 14px; outline: none; resize: none; font-family: inherit; line-height: 1.4; max-height: 100px; overflow-y: auto; }
        .chat-input:focus { border-color: #0077ff; }
        .send-btn { background: #0077ff; color: #fff; border: none; border-radius: 50%; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; align-self: flex-end; transition: opacity .15s; }
        .send-btn:hover { opacity: .85; }
        .send-btn:disabled { opacity: .4; cursor: default; }

        .typing { align-self: flex-start; padding: 8px 12px; background: #f0f0f0; border-radius: 16px; border-bottom-left-radius: 4px; }
        .typing span { display: inline-block; width: 6px; height: 6px; background: #999; border-radius: 50%; animation: blink 1.2s infinite; margin: 0 1px; }
        .typing span:nth-child(2) { animation-delay: .2s; }
        .typing span:nth-child(3) { animation-delay: .4s; }
        @keyframes blink { 0%,80%,100%{opacity:.2} 40%{opacity:1} }
    </style>
</head>
<body>
<div class="chat-window">
    <div class="chat-header">
        <div class="chat-header__title">VK Bot Dev Chat</div>
        <div class="chat-header__badge">DEV</div>
    </div>

    <div class="chat-messages" id="messages"></div>

    <div class="chat-keyboard">
        <button class="kb-btn kb-btn--primary" data-text="Мои товары">Мои товары</button>
        <button class="kb-btn kb-btn--secondary" data-text="Помощь">Помощь</button>
    </div>

    <div class="chat-input-row">
        <textarea class="chat-input" id="input" rows="1" placeholder="Написать сообщение..."></textarea>
        <button class="send-btn" id="sendBtn" disabled>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
        </button>
    </div>
</div>

<script>
const messagesEl = document.getElementById('messages');
const inputEl = document.getElementById('input');
const sendBtn = document.getElementById('sendBtn');
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

function now() {
    return new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

function addMessage(text, role) {
    const wrapper = document.createElement('div');
    wrapper.className = `msg msg--${role}`;
    wrapper.innerHTML = `<div class="msg__bubble"></div><div class="msg__time">${now()}</div>`;
    wrapper.querySelector('.msg__bubble').textContent = text;
    messagesEl.appendChild(wrapper);
    messagesEl.scrollTop = messagesEl.scrollHeight;
    return wrapper;
}

function addTyping() {
    const el = document.createElement('div');
    el.className = 'typing';
    el.innerHTML = '<span></span><span></span><span></span>';
    messagesEl.appendChild(el);
    messagesEl.scrollTop = messagesEl.scrollHeight;
    return el;
}

async function send(text) {
    if (!text.trim()) return;
    addMessage(text, 'user');
    inputEl.value = '';
    inputEl.style.height = 'auto';
    sendBtn.disabled = true;

    const typing = addTyping();

    try {
        const res = await fetch('/dev/vk-chat/send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ message: text }),
        });
        const data = await res.json();
        typing.remove();
        if (data.reply) {
            addMessage(data.reply, 'bot');
        }
    } catch (e) {
        typing.remove();
        addMessage('Ошибка соединения', 'bot');
    }
}

sendBtn.addEventListener('click', () => send(inputEl.value));

inputEl.addEventListener('input', () => {
    sendBtn.disabled = !inputEl.value.trim();
    inputEl.style.height = 'auto';
    inputEl.style.height = Math.min(inputEl.scrollHeight, 100) + 'px';
});

inputEl.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (!sendBtn.disabled) send(inputEl.value);
    }
});

document.querySelectorAll('.kb-btn').forEach(btn => {
    btn.addEventListener('click', () => send(btn.dataset.text));
});

addMessage('Привет! Отправь ссылку на вишлист Ozon или используй команды /start, /help, /items.', 'bot');
inputEl.focus();
</script>
</body>
</html>

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('chat-form');
    const thread = document.getElementById('chat-thread');

    if (!form || !thread) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const query = document.getElementById('chat-query').value.trim();
        const topK = document.getElementById('chat-topk').value || '5';

        if (!query) {
            return;
        }

        appendUserMessage(query);
        const status = appendStatusMessage();
        document.getElementById('chat-query').value = '';

        try {
            const data = await App.api('/api/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ q: query, top_k: Number(topK) }),
            });

            status.remove();
            appendAssistantMessage(data.answer);

            if (data.sources && data.sources.length > 0) {
                appendSources(data.sources);
            }
        } catch (err) {
            status.remove();
            appendErrorMessage(err.message);
        }
    });

    function appendUserMessage(text) {
        const el = document.createElement('div');
        el.className = 'chat-message chat-user';
        el.innerHTML = `<div class="chat-bubble">${App.escapeHtml(text)}</div>`;
        thread.appendChild(el);
        thread.scrollTop = thread.scrollHeight;
    }

    function appendStatusMessage() {
        const el = document.createElement('div');
        el.className = 'chat-message chat-assistant';
        el.innerHTML = '<div class="chat-bubble chat-status">Thinking...</div>';
        thread.appendChild(el);
        thread.scrollTop = thread.scrollHeight;
        return el;
    }

    function appendAssistantMessage(text) {
        const el = document.createElement('div');
        el.className = 'chat-message chat-assistant';
        el.innerHTML = `<div class="chat-bubble chat-answer">${renderAnswer(text)}</div>`;
        thread.appendChild(el);
        thread.scrollTop = thread.scrollHeight;
    }

    function appendSources(sources) {
        const el = document.createElement('div');
        el.className = 'chat-sources';
        el.innerHTML = `
            <h4 class="chat-sources-title">Sources (${sources.length})</h4>
            ${sources.map((item) => `
                <article class="chat-source">
                    <div class="chat-source-head">
                        <span class="search-result-relevance">${App.formatPercent(item.relevance)}</span>
                        <h5 class="chat-source-name">${App.escapeHtml(item.source)}</h5>
                    </div>
                    <p class="chat-source-heading">${App.escapeHtml(item.heading || '(no heading)')}</p>
                </article>
            `).join('')}
        `;
        thread.appendChild(el);
        thread.scrollTop = thread.scrollHeight;
    }

    function appendErrorMessage(message) {
        const el = document.createElement('div');
        el.className = 'chat-message chat-assistant';
        el.innerHTML = `<div class="chat-bubble chat-error">${App.escapeHtml(message)}</div>`;
        thread.appendChild(el);
        thread.scrollTop = thread.scrollHeight;
    }

    function renderAnswer(text) {
        const paragraphs = String(text ?? '')
            .split(/\n{2,}/)
            .map((part) => `<p>${App.escapeHtml(part).replace(/\n/g, '<br>')}</p>`)
            .join('');
        return paragraphs || '<p></p>';
    }
});

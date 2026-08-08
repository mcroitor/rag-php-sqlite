'use strict';

document.addEventListener('DOMContentLoaded', async () => {
    const grid = document.getElementById('stats-grid');

    if (!grid) {
        return;
    }

    try {
        const data = await App.api('/api/stats');

        const cards = [
            card('Documents', data.documents),
            card('Chunks', data.chunks),
            card('Embeddings', data.embeddings),
            card('Database size', data.db_size_human),
        ];

        const ollama = renderOllama(data.ollama);
        const embedding = renderEmbedding(data.embedding);

        grid.innerHTML = `
            <div class="row">${cards.join('')}</div>
            <div class="row">${embedding}${ollama}</div>
        `;
    } catch (err) {
        grid.innerHTML = `<p class="search-error">${App.escapeHtml(err.message)}</p>`;
    }

    function card(label, value) {
        return `
            <article class="stats-card three columns">
                <div class="stats-card-value">${App.escapeHtml(value)}</div>
                <div class="stats-card-label">${App.escapeHtml(label)}</div>
            </article>
        `;
    }

    function renderOllama(ollama) {
        const statusLabel = { ok: 'Online', offline: 'Offline', model_missing: 'Model missing' }[ollama.status] || ollama.status;
        const statusClass = 'status-' + (ollama.status === 'ok' ? 'ok' : 'warn');
        const error = ollama.error ? `<p class="stats-error">${App.escapeHtml(ollama.error)}</p>` : '';

        return `
            <article class="stats-panel six columns">
                <h4 class="stats-panel-title">Ollama</h4>
                <p>Status: <span class="status-badge ${statusClass}">${App.escapeHtml(statusLabel)}</span></p>
                <p>Embedding model: <code>${App.escapeHtml(ollama.model)}</code></p>
                <p>Base URL: <code>${App.escapeHtml(ollama.base_url)}</code></p>
                ${error}
            </article>
        `;
    }

    function renderEmbedding(embedding) {
        const models = embedding.models.length
            ? embedding.models.map((m) => `<li><code>${App.escapeHtml(m)}</code></li>`).join('')
            : '<li>none indexed</li>';

        return `
            <article class="stats-panel six columns">
                <h4 class="stats-panel-title">Embedding</h4>
                <p>Configured model: <code>${App.escapeHtml(embedding.model)}</code></p>
                <p>Vector dimension: <code>${App.escapeHtml(embedding.dimension || 'n/a')}</code></p>
                <p>Indexed models:</p>
                <ul class="stats-list">${models}</ul>
            </article>
        `;
    }
});

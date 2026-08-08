'use strict';

function fileNameFromPath(path) {
    return String(path).split(/[\\/]/).pop() || path;
}

document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('documents-list');
    const form = document.getElementById('documents-search-form');

    if (!list || !form) {
        return;
    }

    let currentSearch = '';

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        currentSearch = document.getElementById('documents-search').value.trim();
        loadDocuments(currentSearch);
    });

    list.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-remove-id]');
        if (!button) {
            return;
        }

        const id = button.dataset.removeId;
        const path = button.dataset.removePath || '';
        const confirmed = window.confirm(`Remove document #${id}?\n\n${path}\n\nIts chunks and embeddings will be deleted.`);

        if (!confirmed) {
            return;
        }

        button.disabled = true;
        button.textContent = 'Removing...';

        try {
            await App.api(`/api/documents/${encodeURIComponent(id)}`, { method: 'DELETE' });
            loadDocuments(currentSearch);
        } catch (err) {
            button.disabled = false;
            button.textContent = 'Remove';
            alert(err.message);
        }
    });

    loadDocuments('');

    async function loadDocuments(search) {
        const query = new URLSearchParams({ search });
        list.innerHTML = '<p class="search-status">Loading...</p>';

        try {
            const data = await App.api(`/api/documents?${query.toString()}`);
            renderDocuments(data);
        } catch (err) {
            list.innerHTML = `<p class="search-error">${App.escapeHtml(err.message)}</p>`;
        }
    }

    function renderDocuments(data) {
        if (data.count === 0) {
            list.innerHTML = '<p class="search-status">No documents found.</p>';
            return;
        }

        const rows = data.documents.map((doc) => `
            <article class="doc-card">
                <div class="doc-meta">
                    <span class="doc-id">#${App.escapeHtml(doc.id)}</span>
                    <span class="doc-chunks">chunks: ${App.escapeHtml(doc.chunks)}</span>
                    <span class="doc-date">${App.escapeHtml(doc.created_at)}</span>
                </div>
                <div class="doc-path" title="${App.escapeHtml(doc.path)}">
                    <a class="doc-download" href="/api/documents/${doc.id}/download" download>${App.escapeHtml(fileNameFromPath(doc.path))}</a>
                </div>
                <button class="doc-remove" data-remove-id="${App.escapeHtml(doc.id)}" data-remove-path="${App.escapeHtml(doc.path)}">Remove</button>
            </article>
        `).join('');

        list.innerHTML = `
            <p class="search-status">${App.escapeHtml(data.count)} of ${App.escapeHtml(data.total)} documents</p>
            ${rows}
        `;
    }
});
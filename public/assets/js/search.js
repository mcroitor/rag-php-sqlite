'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('search-form');
    const results = document.getElementById('search-results');

    if (!form || !results) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const query = document.getElementById('search-query').value.trim();
        const topK = document.getElementById('search-topk').value || '5';

        if (!query) {
            return;
        }

        results.innerHTML = '<p class="search-status">Searching...</p>';

        try {
            const data = await App.api(`/api/search?q=${encodeURIComponent(query)}&top_k=${encodeURIComponent(topK)}`);

            if (!data.results || data.results.length === 0) {
                results.innerHTML = '<p class="search-empty">No results found.</p>';
                return;
            }

            results.innerHTML = data.results.map((item) => `
                <article class="search-result">
                    <header class="search-result-head">
                        <span class="search-result-relevance">${App.formatPercent(item.relevance)}</span>
                        <h3 class="search-result-source">${App.escapeHtml(item.source)}</h3>
                    </header>
                    <p class="search-result-heading">${App.escapeHtml(item.heading || '(no heading)')}</p>
                    <pre class="search-result-text">${App.escapeHtml(item.text)}</pre>
                    <footer class="search-result-meta">
                        ${item.token_count} tokens
                    </footer>
                </article>
            `).join('');
        } catch (err) {
            results.innerHTML = `<p class="search-error">${App.escapeHtml(err.message)}</p>`;
        }
    });
});

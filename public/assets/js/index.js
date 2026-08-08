'use strict';

const IndexUI = (() => {
    const POLL_INTERVAL_MS = 2000;

    let currentJobId = null;
    let pollTimer = null;
    let jobsTimer = null;

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('index-form');
        const status = document.getElementById('index-status');
        const fileInput = document.getElementById('index-files');

        if (!form || !status || !fileInput) {
            return;
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const files = fileInput.files;
            if (!files || files.length === 0) {
                return;
            }

            setFormEnabled(false);
            status.innerHTML = '<p class="search-status">Uploading and starting indexing job...</p>';

            const formData = new FormData();
            for (const file of files) {
                formData.append('files[]', file);
            }

            try {
                const data = await App.api('/api/index', { method: 'POST', body: formData });
                currentJobId = data.job_id;
                pollJob(currentJobId);
            } catch (err) {
                setFormEnabled(true);
                status.innerHTML = `<p class="search-error">${App.escapeHtml(err.message)}</p>`;
            }
        });

        loadJobs();
        jobsTimer = setInterval(loadJobs, POLL_INTERVAL_MS);
    });

    async function pollJob(jobId) {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }

        pollTimer = setInterval(async () => {
            try {
                const data = await App.api(`/api/jobs/${encodeURIComponent(jobId)}`);
                renderJob(data, true);

                if (data.state === 'done' || data.state === 'error') {
                    clearInterval(pollTimer);
                    pollTimer = null;
                    setFormEnabled(true);
                    loadJobs();
                }
            } catch (err) {
                clearInterval(pollTimer);
                pollTimer = null;
                setFormEnabled(true);
                const status = document.getElementById('index-status');
                if (status) {
                    status.innerHTML = `<p class="search-error">${App.escapeHtml(err.message)}</p>`;
                }
            }
        }, POLL_INTERVAL_MS);
    }

    function renderJob(job, active) {
        const status = document.getElementById('index-status');
        if (!status || !active) {
            return;
        }

        const progress = job.progress !== null && job.progress !== undefined
            ? `<div class="progress"><div class="progress-bar" style="width:${App.escapeHtml(job.progress)}%"></div></div>
               <p class="progress-label">${App.escapeHtml(job.progress)}%</p>`
            : '';

        const stateLabel = { running: 'Running', done: 'Done', error: 'Error' }[job.state] || job.state;
        const stateClass = 'status-' + (job.state === 'done' ? 'ok' : (job.state === 'error' ? 'warn' : 'run'));
        const stats = job.stats
            ? `<p class="job-stats">Processed: ${job.stats.processed}, skipped: ${job.stats.skipped}, failed: ${job.stats.failed}, chunks: ${job.stats.chunks}</p>`
            : '';
        const error = job.error ? `<p class="search-error">${App.escapeHtml(job.error)}</p>` : '';
        const log = (job.log || []).slice(-15).map((line) => `<div>${App.escapeHtml(line)}</div>`).join('');

        status.innerHTML = `
            <article class="job-card">
                <h4>Job ${App.escapeHtml(job.job_id)} <span class="status-badge ${stateClass}">${App.escapeHtml(stateLabel)}</span></h4>
                ${progress}
                ${stats}
                ${error}
                <pre class="job-log">${log}</pre>
            </article>
        `;
    }

    async function loadJobs() {
        const list = document.getElementById('jobs-list');
        if (!list) {
            return;
        }

        try {
            const data = await App.api('/api/jobs');
            if (!data.jobs || data.jobs.length === 0) {
                list.innerHTML = '<p class="search-status">No jobs yet.</p>';
                return;
            }

            list.innerHTML = data.jobs.map((job) => {
                const stateLabel = { running: 'Running', done: 'Done', error: 'Error' }[job.state] || job.state;
                const stateClass = 'status-' + (job.state === 'done' ? 'ok' : (job.state === 'error' ? 'warn' : 'run'));
                const progress = job.progress !== null && job.progress !== undefined ? `${job.progress}%` : '';
                return `
                    <article class="job-card job-card-small">
                        <span class="job-id">${App.escapeHtml(job.job_id)}</span>
                        <span class="status-badge ${stateClass}">${App.escapeHtml(stateLabel)}</span>
                        <span class="job-progress">${App.escapeHtml(progress)}</span>
                    </article>
                `;
            }).join('');
        } catch (err) {
            // Keep the current content on transient poll failures.
        }
    }

    function setFormEnabled(enabled) {
        const inputs = document.querySelectorAll('#index-form input, #index-form button');
        inputs.forEach((input) => {
            input.disabled = !enabled;
        });
    }

    return {};
})();
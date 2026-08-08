# RAG-PHP-SQLite

Local Retrieval-Augmented Generation (RAG) engine using PHP, SQLite and Ollama.

## Requirements

- PHP 8.1+
- SQLite 3.35+
- Ollama (running locally, v0.3.0+)

## Installation

```bash
git clone https://github.com/mcroitor/rag-php-sqlite.git
cd rag-php-sqlite
php composer.phar install
php bin/setup.php
```

## Ollama Models

```bash
# Recommended embedding models (size vs quality trade-off):
ollama pull qwen3-embedding:0.6b   # Fast, low resource (default)
ollama pull qwen3-embedding:4b     # Higher quality, needs more RAM/VRAM
ollama pull bge-m3                 # Multilingual alternative

# Recommended LLM models:
ollama pull qwen3.5:4b             # Good quality balanced (default)
ollama pull qwen3.5:2b             # Faster, less accurate
ollama pull llama3.2:3b            # Alternative, English-focused
```

Make sure Ollama is running locally on `http://localhost:11434`.

## Usage

### Index documents (CLI)

```bash
php bin/index.php --dir=./docs --recursive
```

### Incremental re-index

```bash
php bin/index.php --dir=./docs --incremental
```

### Search

```bash
php bin/query.php -q "architecture"
php bin/query.php -q "architecture" --top-k=10 --format=json
```

### Ask (RAG + LLM)

```bash
php bin/ask.php -q "What is RAG?"
php bin/ask.php -q "What is std::vector?" --top-k=8
```

### Utilities

```bash
php bin/stats.php
php bin/clear.php --confirm
php bin/list.php
php bin/remove.php --id=42 --confirm
```

### Migrate existing project to runtime/

If you have an existing `rag.sqlite` in the project root or documents in `documents/`, run the migration:

```bash
php bin/migrate.php
```

This moves the legacy database to `runtime/dbs/rag.sqlite` and relocates source files to `runtime/documents/rag/{uuid}/{filename}`.

## Web UI

Start the development server:

```bash
php bin/serve.php
```

Then open `http://127.0.0.1:8000` in your browser.

### Features

- **Search** – vector search with relevance scores and source preview
- **Chat** – RAG-powered Q&A with LLM, shows cited sources
- **Index** – upload Markdown files (`.md`) for indexing; each file is stored under `runtime/documents/{base}/{uuid}/`
- **Documents** – list all indexed documents with download links; delete documents with confirmation
- **Stats** – database size, document/chunk/embedding counts, Ollama status
- **Database selector** – switch between multiple RAG databases; create new databases from the UI

### Indexing via Web UI

On the **Index** page, use the file upload form to select one or more `.md` files. Each uploaded file is stored in `runtime/documents/{base}/{uuid}/` and a background indexing job is started automatically (recursive + incremental). Job progress is shown in real time.

### Managing Documents

The **Documents** page lists all indexed files with their chunk count and creation date. Each row shows a **Download** link (serves the original Markdown file) and a **Remove** button (deletes the document, its chunks, and embeddings).

### Multiple Databases

Create and switch between independent RAG databases:

- Click **New Base** in the navbar, enter a name (e.g., `myproject`), and a fresh database is created under `runtime/dbs/{name}.sqlite` with the full schema
- Use the dropdown to switch the active database — the change persists across requests
- Each database has its own documents, chunks, embeddings, and cache

### REST API

| Endpoint                       | Method | Description                                  |
| ------------------------------ | ------ | -------------------------------------------- |
| `/api/health`                  | GET    | Health check                                 |
| `/api/search`                  | GET    | Vector search (`q`, `top_k`)                 |
| `/api/chat`                    | POST   | RAG chat (`q`, `top_k`)                      |
| `/api/stats`                   | GET    | Database statistics                          |
| `/api/index`                   | POST   | Upload & index (multipart `files[]`)         |
| `/api/jobs`                    | GET    | List background jobs                         |
| `/api/jobs/{id}`               | GET    | Job status & logs                            |
| `/api/bases`                   | GET    | List databases + active                      |
| `/api/bases`                   | POST   | Switch active database (`base`)              |
| `/api/bases/create`            | POST   | Create new database (`base`)                 |
| `/api/documents`               | GET    | List documents (`limit`, `offset`, `search`) |
| `/api/documents/{id}`          | DELETE | Remove document by ID                        |
| `/api/documents/{id}/download` | GET    | Download original Markdown file              |

Example — switch database:

```bash
curl -X POST http://127.0.0.1:8000/api/bases -H "Content-Type: application/json" -d '{"base": "test"}'
```

Example — create database:

```bash
curl -X POST http://127.0.0.1:8000/api/bases/create -H "Content-Type: application/json" -d '{"base": "myproject"}'
```

Example — upload files for indexing:

```bash
curl -X POST http://127.0.0.1:8000/api/index \
  -F "files[]=@doc1.md" -F "files[]=@doc2.md"
```

## Configuration

Edit `config/config.yaml`:

```yaml
ollama:
  base_url: http://localhost:11434

embedding:
  model: qwen3-embedding:0.6b
  dimension: 1024
  max_tokens: 350
  safety_margin: 50
  overlap: 50
  retry: 3

llm:
  model: qwen3.5:4b
  temperature: 0.7
  num_predict: 10240
  context_window: 20480

retrieval:
  top_k: 10
  threshold: 0.40
```

## Storage Layout

```text
runtime/
├── meta.sqlite           # Active base + job history
├── dbs/
│   ├── rag.sqlite        # Default database
│   ├── test.sqlite
│   └── {name}.sqlite     # User-created databases
├── documents/
│   ├── rag/
│   │   ├── {uuid}/       # One per document
│   │   │   └── file.md
│   │   └── ...
│   ├── test/
│   └── {name}/
└── jobs/
    ├── {job-id}.log
    ├── {job-id}.done
    └── {job-id}.error
```

All user data lives under `runtime/` (git-ignored). The legacy `rag.sqlite` at the project root is no longer used.

## Tuning Guide

### Embedding — `max_tokens` / `safety_margin`

Chunk size = `max_tokens - safety_margin` (default: `350 - 50 = 300` tokens).

- **Smaller chunks** (100–200): More precise retrieval, higher embedding cost, better for factual Q&A
- **Larger chunks** (400–600): Better context for summarization, less precise
- **Safety margin** must leave room for heading/path overhead (~20 tokens per chunk). If chunks fail validation, increase `safety_margin`
- Max chars sent to Ollama is capped at 24000 internally (avoids tokenizer mismatch)

### Embedding — `overlap`

Token overlap between adjacent chunks (default: 50, ~25 words). Helps preserve context across boundaries. Increase (100–150) for documents with gradual topic transitions. Disable by setting to 0.

### Embedding — model selection

| Model                  | Size | Quality | Speed  | RAM   |
| ---------------------- | ---- | ------- | ------ | ----- |
| `qwen3-embedding:0.6b` | 0.6B | Good    | Fast   | ~1 GB |
| `qwen3-embedding:4b`   | 4B   | Better  | Slower | ~3 GB |
| `bge-m3`               | 567M | Good    | Fast   | ~1 GB |

When switching embedding models, re-index all documents (not incremental). Update `dimension` to match the model's output dimension. Common dimensions: qwen3-embedding:0.6b = 2560, qwen3-embending:4b = 1024, bge-m3 = 1024, nomic-embed-text = 768.

**Each embedding model produces similarity scores in different ranges.** For example, `nomic-embed-text` typically scores top matches at 0.65–0.85, while `qwen3-embedding:0.6b` maxes out around 0.40–0.55. After switching models, this means:

1. Re-index with the new model (required — vectors are incomparable across models)
2. Adjust `threshold` accordingly: start low (0.30) and raise until precision aligns with your needs
3. Run `php bin/query.php -q "test query"` and gradually increase threshold until the right balance is found

### Retrieval — `threshold` vs `top_k`

- **`threshold`** (0.0–1.0): Minimum cosine similarity score. Lower = more results (higher recall), lower precision
- **`top_k`**: Max candidates to return. Hard limit regardless of threshold

Common presets:

- **Precision mode**: `threshold: 0.60`, `top_k: 5` — few, highly relevant results
- **Recall mode**: `threshold: 0.25`, `top_k: 15` — more results, may include noise
- **Default** (config): `threshold: 0.40`, `top_k: 10` — balanced

If ask.php returns "No results found.", lower `threshold` to 0.25–0.35.

### LLM — `context_window` vs `num_predict`

- **`context_window`** = max input tokens sent as `num_ctx` to Ollama. The model's actual limit is auto-detected from `/api/show` but **capped** at this value. If the cap is too low, long prompts are silently truncated; if too high, Ollama allocates a large KV cache which is very slow on CPU
- **`num_predict`** = output token limit. Larger = longer answers but slower
- Set `context_window` to a value your hardware can handle comfortably. Start at **8192** on CPU, **32768** on GPU with enough VRAM. The configured value also limits `PromptBuilder`'s context fitting
- Models like qwen3.5 support up to 262K tokens, but using the full context on CPU will cause timeouts

### LLM — `temperature`

- **0.0**: Deterministic, factual answers
- **0.5–0.7** (default): Balanced creativity
- **0.8–1.0**: Creative, may hallucinate more

### Indexing — incremental mode

`php bin/index.php --dir=./docs --incremental` only processes documents whose content hash changed (detected via `md5`). Combine with `--recursive`:

```text
php bin/index.php --dir=./docs --recursive --incremental
```

### Performance notes

- First embedding request is slow (model loading). Subsequent requests use cached model in Ollama
- Embedding is cached in SQLite (`embedding_cache` table). Re-indexing unchanged docs is a no-op
- On CPU, prefer smaller models (`qwen3-embedding:0.6b`) and smaller `max_tokens` (200–300)
- On GPU, larger models and chunks are fine
- `retry: 3` with exponential backoff handles transient Ollama failures. Increase `timeout` on slow hardware

## Testing

Run the current test suite:

```bash
php tests/run.php
```

The current project test stack uses `Mc\Unit`, not PHPUnit.

## Architecture

```text
Markdown Files → Loader → Parser → Chunker → Validator → Embedding → SQLite
                                                                          ↓
Query → Embedding → Vector Search → Retriever → Prompt Builder → LLM → Answer
```

## License

GPLv3
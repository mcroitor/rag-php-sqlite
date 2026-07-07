# RAG-PHP-SQLite

Local Retrieval-Augmented Generation (RAG) engine using PHP, SQLite and Ollama.

## Requirements

- PHP 8.1+
- SQLite 3.35+
- Ollama (running locally)

## Installation

```bash
git clone https://github.com/mcroitor/rag-php-sqlite.git
cd rag-php-sqlite
php composer.phar install
php bin/setup.php
```

## Ollama Models

```bash
ollama pull nomic-embed-text
ollama pull qwen3.5:2b
```

Make sure Ollama is running locally on `http://localhost:11434`.

## Usage

### Index documents

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
```

## Configuration

Edit `config/config.yaml`:

```yaml
ollama:
  base_url: http://localhost:11434

embedding:
  model: qwen3-embedding:4b
  max_tokens: 2000
  safety_margin: 300
  retry: 3

llm:
  model: qwen3.5:2b
  temperature: 0.7

retrieval:
  top_k: 5
  threshold: 0.75
```

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

## Known Gaps Against SRS

- `EmbeddingCache` is not implemented beyond a placeholder
- no standalone `ContextWindow` class yet
- no standalone `QueryService` yet
- no progress bar for long indexing operations
- code block preservation / fallback char split / validator safety-margin checks are not complete
- end-to-end CLI flows require a live local Ollama instance and a prepared SQLite database

## License

GPLv3

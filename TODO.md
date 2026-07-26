# TODO: RAG-PHP-SQLite Implementation

## Legend

- `[ ]` Not started
- `[~]` In progress / partially done
- `[X]` Completed

---

## Phase 1: Project Setup & Infrastructure

- `[X]` Create directory structure (`bin/`, `src/Loader/`, `src/Parser/`, `src/Chunker/`, etc.)
- `[X]` Create configuration loader (`src/Utils/Config.php`) — reads `config/config.yaml` via `ext-yaml`
- `[X]` Implement logging wrapper (`src/Utils/AppLogger.php`) using `Mc\Logger`
- `[X]` Create domain entities: `Document`, `Chunk`, `RetrievalResult`
- `[X]` Create core interfaces: `EmbeddingProvider`, `LLMProvider`, `StorageInterface`, `RetrieverInterface`, `ChunkerInterface`
- `[X]` Create exception classes: `RAGException`, `EmbeddingException`, `StorageException`, `ConfigurationException`, `ValidationException`
- `[X]` Create `bin/setup.php` — SQLite database initialization with tables and indexes

## Phase 2: Document Processing Pipeline

- `[X]` Implement `src/Loader/FileScanner.php` — scan `.md` files, recursive, ignore patterns, path traversal protection
- `[X]` Implement `src/Loader/MarkdownLoader.php` — load Markdown into `Document` entity
- `[X]` Implement `src/Parser/HeadingTree.php` — build heading tree
- `[X]` Implement `src/Parser/MarkdownParser.php` — parse Markdown sections with code block preservation
- `[X]` Implement `src/Chunker/TokenCounter.php` — token estimation
- `[X]` Implement `src/Chunker/SemanticChunker.php` — header/paragraph/sentence/char split strategy
- `[X]` Implement `src/Validator/ChunkValidator.php` — token count, size limit, safety margin, encoding validity

## Phase 3: Embedding & Storage

- `[X]` Implement `src/Embedding/OllamaEmbedding.php` — Ollama `/api/embeddings` with retry/backoff
- `[X]` Implement batch embedding support in `OllamaEmbedding.embedBatch()` and wire into IndexingService
- `[X]` Implement `src/Embedding/EmbeddingCache.php` — SQLite-backed persistence
- `[X]` Implement `src/Storage/SQLiteStorage.php` — full CRUD with transactions, indexes
- `[X]` Implement `src/Storage/VectorSearch.php` — cosine similarity, top-K, threshold filtering

## Phase 4: Retrieval & Generation

- `[X]` Implement `src/Retrieval/VectorRetriever.php` — query → embed → search → rank → return
- `[X]` Implement `src/Prompt/PromptBuilder.php` — SOURCE/TEXT format, dedup, score ordering
- `[X]` Implement `src/Prompt/ContextWindow.php` — fit chunks within max token budget
- `[X]` Implement `src/Embedding/OllamaLLM.php` — Ollama `/api/generate` with retry/backoff

## Phase 5: CLI Interface

- `[X]` Implement `bin/index.php` — `--dir`, `--recursive`, `--incremental`, `--help`
- `[X]` Implement `bin/query.php` — query (positional), `--top-k`, `--format` (text/json), `--ask`, `--help`
- `[X]` Implement `bin/stats.php` — document/chunk counts, DB size
- `[X]` Implement `bin/clear.php` — clear all data with `--confirm`

## Phase 6: Services

- `[X]` Implement `src/Services/IndexingService.php` — full pipeline with incremental mode, error isolation, transactions
- `[X]` Implement `src/Services/RAGService.php` — full RAG pipeline (search → build → generate)
- `[X]` Implement `src/Services/QueryService.php` — search-only pipeline (embed → search → return)

## Phase 7: Testing & Quality Assurance

- `[X]` Create `tests/run.php` — 7 unit tests using `Mc\Unit`
- `[X]` PHPStan level 6 — 0 errors
- `[X]` Expand test suite to cover: embedding failures, CLI edge cases, incremental indexing
- `[ ]` Add coverage reporting

## Phase 8: Error Handling

- `[X]` Embedding failure: retry with exponential backoff (3 attempts), skip on final failure
- `[X]` SQLite errors: transaction rollback, isolate document failure
- `[X]` File corruption / parse error: catch, skip file, log, continue batch
- `[X]` All error paths log via `AppLogger`

## Phase 9: Security

- `[X]` FileScanner path traversal protection (validate against project root)
- `[X]` CLI argument validation via `mcroitor/arguments`
- `[X]` Local-only execution (Ollama on localhost only)
- `[ ]` Markdown sanitization (future, deferred)

## Phase 10: Performance

- `[X]` Embedding cache — SQLite persistence implemented
- `[X]` Batch embeddings — wired into IndexingService with cache check
- `[X]` Batch SQLite inserts — wrapped in transactions
- `[ ]` Optimize for 100k chunks < 2s retrieval target

## Phase 11: Known Gaps

- `[X]` EmbeddingCache — SQLite backed persistence implemented
- `[X]` ContextWindow — implemented
- `[X]` QueryService — implemented
- `[X]` Progress bar for long indexing operations
- `[~]` Code block preservation — implemented in parser/chunker but needs edge-case hardening
- `[ ]` Fallback char split — implemented but untested with edge cases

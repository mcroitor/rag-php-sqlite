<?php

namespace App\Utils;

/**
 * Centralized magic constants for the RAG application.
 */
class Constant
{
    // ------------------------------------------------------------------ Ollama
    public const DEFAULT_OLLAMA_BASE_URL    = 'http://localhost:11434';
    public const OLLAMA_EMBED_MODEL         = 'qwen3-embedding:0.6b';
    public const OLLAMA_LLM_MODEL           = 'qwen3.5:4b';

    // ----------------------------------------------------------------- Embedding
    public const DEFAULT_EMBED_DIMENSION    = 2560;
    public const DEFAULT_EMBED_MAX_TOKENS   = 350;
    public const DEFAULT_EMBED_SAFETY_MARGIN = 50;
    public const DEFAULT_EMBED_OVERLAP      = 50;
    public const DEFAULT_EMBED_RETRY        = 3;
    public const DEFAULT_EMBED_MAX_CHARS    = 24000;

    // ----------------------------------------------------------------------- LLM
    public const DEFAULT_LLM_TEMPERATURE    = 0.7;
    public const DEFAULT_LLM_NUM_PREDICT    = 10240;
    public const DEFAULT_LLM_CONTEXT_WINDOW = 20480;

    // -------------------------------------------------------------- Retrieval / Search
    public const DEFAULT_TOP_K              = 5;
    public const DEFAULT_THRESHOLD          = 0.40;

    // ------------------------------------------------------------- Vector search
    public const VECTOR_ZERO_THRESHOLD      = 1e-10;

    // ------------------------------------------------------------ Prompt Builder
    public const CONTEXT_OVERHEAD_PER_CHUNK = 20;
    public const DEFAULT_MAX_CONTEXT_TOKENS = 4096;

    // ----------------------------------------------------------- Text Chunking
    public const AVG_CHAR_PER_TOKEN         = 4;

    // --------------------------------------------------------------- Retry / Delay
    public const HTTP_CONNECT_TIMEOUT       = 10;

    // ------------------------------------------------------- Safety limits
    public const MAX_CHUNKS_PER_DOCUMENT    = 5000;

    // -------------------------------------------------------------------- Paths
    public const DEFAULT_DB_FILENAME        = 'rag.sqlite';

    // ------------------------------------------------------------- Timeouts (seconds)
    public const DEFAULT_EMBED_TIMEOUT      = 30;
    public const DEFAULT_LLM_TIMEOUT        = 120;
}

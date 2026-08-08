<?php

declare(strict_types=1);

namespace App\Web\Controllers;

use App\Engine\Core\Exceptions\ValidationException;
use App\Engine\Storage\MetaStorage;
use App\Engine\Utils\DbFactory;
use App\Web\Core\Response;
use App\Web\Services\ChatService;
use App\Web\Services\DocumentService;
use App\Web\Services\JobManager;
use App\Web\Services\SearchService;
use App\Web\Services\StatsService;
use App\Web\Services\UploadService;
use Mc\Router;

/**
 * JSON API endpoints.
 */
class ApiController
{
    private SearchService $search;
    private ChatService $chat;
    private StatsService $stats;
    private JobManager $jobs;
    private DocumentService $documents;
    private MetaStorage $meta;

    /** @var callable(): string */
    private $baseResolver;

    /**
     * @param callable(): string $baseResolver
     */
    public function __construct(
        SearchService $search,
        ChatService $chat,
        StatsService $stats,
        JobManager $jobs,
        callable $baseResolver,
        MetaStorage $meta,
        DocumentService $documents,
    ) {
        $this->search = $search;
        $this->chat = $chat;
        $this->stats = $stats;
        $this->jobs = $jobs;
        $this->baseResolver = $baseResolver;
        $this->meta = $meta;
        $this->documents = $documents;
    }

    public function health(): string
    {
        return Response::json([
            'status' => 'ok',
            'service' => 'rag-php-sqlite',
        ]);
    }

    public function search(): string
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $topK = max(1, (int) ($_GET['top_k'] ?? 5));

        if ($query === '') {
            return Response::error('bad_request', 'Missing required query parameter: q', 400);
        }

        try {
            return Response::json($this->search->search($query, $topK));
        } catch (\Throwable $e) {
            return Response::errorFromThrowable($e);
        }
    }

    public function chat(): string
    {
        $body = Router::getBody();
        $query = trim((string) ($body['q'] ?? ''));
        $topK = max(1, (int) ($body['top_k'] ?? 5));

        if ($query === '') {
            return Response::error('bad_request', 'Missing required body parameter: q', 400);
        }

        try {
            return Response::json($this->chat->ask($query, $topK));
        } catch (\Throwable $e) {
            return Response::errorFromThrowable($e);
        }
    }

    public function stats(): string
    {
        try {
            return Response::json($this->stats->stats());
        } catch (\Throwable $e) {
            return Response::errorFromThrowable($e);
        }
    }

    public function index(): string
    {
        $files = $_FILES['files'] ?? null;

        if (!is_array($files)) {
            return Response::error('bad_request', 'No files uploaded', 400);
        }

        $base = ($this->baseResolver)();
        $upload = new UploadService($this->meta->root(), $base);

        try {
            $paths = $upload->store($files);
        } catch (ValidationException $e) {
            return Response::error('validation_error', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            return Response::errorFromThrowable($e);
        }

        if ($paths === []) {
            return Response::error('bad_request', 'No valid files uploaded', 400);
        }

        try {
            $job = $this->jobs->create($upload->dir(), true, true, $base);
            return Response::json([
                'job_id' => $job['job_id'],
                'dir' => $upload->dir(),
                'uploaded' => count($paths),
            ], 202);
        } catch (\Throwable $e) {
            return Response::errorFromThrowable($e);
        }
    }

    public function downloadDocument(string $id): string
    {
        if ($id === '' || preg_match('/^[0-9]+$/', $id) !== 1) {
            return Response::error('bad_request', 'Invalid document id', 400);
        }

        try {
            $path = $this->documents->pathById((int) $id);
        } catch (ValidationException $e) {
            return Response::error('not_found', $e->getMessage(), 404);
        }

        if ($path === '' || !is_file($path)) {
            return Response::error('not_found', 'Document file not found on disk', 404);
        }

        $name = basename($path);
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . (string) filesize($path));

        return (string) file_get_contents($path);
    }

    public function job(string $jobId): string
    {
        if ($jobId === '' || preg_match('/^[A-Za-z0-9_\-]+$/', $jobId) !== 1) {
            return Response::error('bad_request', 'Invalid job id', 400);
        }

        try {
            return Response::json($this->jobs->status($jobId));
        } catch (\Throwable $e) {
            return Response::errorFromThrowable($e);
        }
    }

    public function jobs(): string
    {
        try {
            return Response::json(['jobs' => $this->jobs->list()]);
        } catch (\Throwable $e) {
            return Response::errorFromThrowable($e);
        }
    }

    public function bases(): string
    {
        try {
            return Response::json([
                'active' => ($this->baseResolver)(),
                'bases' => $this->meta->listBases(),
            ]);
        } catch (\Throwable $e) {
            return Response::errorFromThrowable($e);
        }
    }

    public function switchBase(): string
    {
        $body = Router::getBody();
        $base = trim((string) ($body['base'] ?? ''));

        try {
            $base = DbFactory::normalize($base);
        } catch (\Throwable $e) {
            return Response::error('bad_request', 'Invalid database name', 400);
        }

        if (!DbFactory::exists($this->meta->root(), $base)) {
            return Response::error('not_found', "Database '$base' does not exist", 404);
        }

        $this->meta->setActiveBase($base);

        return Response::json([
            'active' => $base,
            'bases' => $this->meta->listBases(),
        ]);
    }

    public function createBase(): string
    {
        $body = Router::getBody();
        $base = trim((string) ($body['base'] ?? ''));

        try {
            $base = DbFactory::normalize($base);
        } catch (\Throwable $e) {
            return Response::error('bad_request', 'Invalid database name', 400);
        }

        if ($base === '') {
            return Response::error('bad_request', 'Database name is required', 400);
        }

        if (DbFactory::exists($this->meta->root(), $base)) {
            return Response::error('conflict', "Database '$base' already exists", 409);
        }

        try {
            // Create the database schema (same as bin/setup.php)
            $db = DbFactory::pdo($this->meta->root(), $base);
            $db->exec('PRAGMA journal_mode = WAL');
            $db->exec('PRAGMA foreign_keys = ON');

            $db->exec("
                CREATE TABLE IF NOT EXISTS documents (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    path TEXT NOT NULL UNIQUE,
                    hash TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT (datetime('now'))
                )
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS chunks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    document_id INTEGER NOT NULL,
                    heading_path TEXT NOT NULL DEFAULT '',
                    text TEXT NOT NULL,
                    token_count INTEGER NOT NULL DEFAULT 0,
                    hash TEXT NOT NULL,
                    language TEXT NOT NULL DEFAULT '',
                    embedding_model TEXT NOT NULL DEFAULT '',
                    document_hash TEXT NOT NULL DEFAULT '',
                    created_at TEXT NOT NULL DEFAULT (datetime('now')),
                    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
                )
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS embeddings (
                    chunk_id INTEGER PRIMARY KEY,
                    vector BLOB NOT NULL,
                    embedding_model TEXT NOT NULL DEFAULT '',
                    embedding_dimension INTEGER NOT NULL DEFAULT 0,
                    embedding_version TEXT NOT NULL DEFAULT '',
                    created_at TEXT NOT NULL DEFAULT (datetime('now')),
                    FOREIGN KEY (chunk_id) REFERENCES chunks(id) ON DELETE CASCADE
                )
            ");

            $db->exec('CREATE INDEX IF NOT EXISTS idx_documents_path ON documents(path)');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_documents_hash ON documents(hash)');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_chunks_document_id ON chunks(document_id)');
            $db->exec("CREATE INDEX IF NOT EXISTS idx_embeddings_vector ON embeddings(vector)");

            $db->exec("
                CREATE TABLE IF NOT EXISTS embedding_cache (
                    hash TEXT PRIMARY KEY,
                    vector BLOB NOT NULL,
                    model TEXT NOT NULL,
                    dimension INTEGER NOT NULL,
                    document_id INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT NOT NULL DEFAULT (datetime('now'))
                )
            ");

            $db->exec('CREATE INDEX IF NOT EXISTS idx_cache_model ON embedding_cache(model)');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_cache_document_id ON embedding_cache(document_id)');

            // Set as active base
            $this->meta->setActiveBase($base);

            return Response::json([
                'created' => $base,
                'active' => $base,
                'bases' => $this->meta->listBases(),
            ], 201);
        } catch (\Throwable $e) {
            return Response::errorFromThrowable($e);
        }
    }

    public function documents(): string
    {
        $query = Router::getQueryParams();
        $limit = max(1, min(500, (int) ($query['limit'] ?? 50)));
        $offset = max(0, (int) ($query['offset'] ?? 0));
        $search = trim((string) ($query['search'] ?? ''));

        try {
            return Response::json($this->documents->list($limit, $offset, $search));
        } catch (\Throwable $e) {
            return Response::errorFromThrowable($e);
        }
    }

    public function removeDocument(string $id): string
    {
        if ($id === '' || preg_match('/^[0-9]+$/', $id) !== 1) {
            return Response::error('bad_request', 'Invalid document id', 400);
        }

        try {
            $result = $this->documents->removeById((int) $id);

            if ($result['removed'] === false) {
                return Response::error('not_found', "Document with id=$id not found", 404);
            }

            return Response::json($result);
        } catch (\App\Engine\Core\Exceptions\ValidationException $e) {
            return Response::error('not_found', $e->getMessage(), 404);
        } catch (\Throwable $e) {
            return Response::errorFromThrowable($e);
        }
    }
}

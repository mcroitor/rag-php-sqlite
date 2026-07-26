<?php

namespace App\Services;

use App\Chunker\SemanticChunker;
use App\Core\Entities\Document;
use App\Core\Exceptions\ValidationException;
use App\Core\Interfaces\EmbeddingProvider;
use App\Core\Interfaces\StorageInterface;
use App\Loader\FileScanner;
use App\Loader\MarkdownLoader;
use App\Parser\HeadingTree;
use App\Parser\MarkdownParser;
use App\Utils\AppLogger;
use App\Utils\Constant;
use App\Validator\ChunkValidator;

class IndexingService
{
    public function __construct(
        private FileScanner $scanner,
        private MarkdownLoader $loader,
        private MarkdownParser $parser,
        private SemanticChunker $chunker,
        private ChunkValidator $validator,
        private EmbeddingProvider $embedding,
        private StorageInterface $storage,
        private string $embeddingVersion = '1.0',
        private ?\App\Embedding\EmbeddingCache $cache = null,
    ) {
    }

    /** @return array{processed: int, skipped: int, failed: int, chunks: int} */
    public function indexDirectory(string $directory, bool $recursive, bool $incremental): array
    {
        $log = AppLogger::instance();
        $files = $this->scanner->scan($directory, $recursive);

        $log->info('Found ' . count($files) . ' Markdown files');

        $stats = [
            'processed' => 0,
            'skipped' => 0,
            'failed' => 0,
            'chunks' => 0,
        ];

        foreach ($files as $index => $filePath) {
            try {
                $progress = round((($index + 1) / count($files)) * 100);
                $log->info("[$progress%] Processing: $filePath");

                if ($incremental) {
                    $existingHash = $this->storage->getDocumentHash($filePath);
                    $currentHash = md5_file($filePath);

                    if ($existingHash !== null && $existingHash === $currentHash) {
                        $log->info("[$progress%] Skipped (unchanged): $filePath");
                        $stats['skipped']++;
                        continue;
                    }
                }

                $this->processFile($filePath, $stats);
                $stats['processed']++;

            } catch (\Throwable $e) {
                $progress = isset($progress) ? $progress : '??';
                $log->error("[$progress%] Failed to process $filePath: " . $e->getMessage());
                $stats['failed']++;
            }
        }

        $log->pass("Indexing complete: {$stats['processed']} processed, {$stats['skipped']} skipped, {$stats['failed']} failed, {$stats['chunks']} chunks");

        return $stats;
    }

    /** @param array{processed: int, skipped: int, failed: int, chunks: int} $stats */
    private function processFile(string $filePath, array &$stats): void
    {
        $log = AppLogger::instance();
        // Removed local info log to avoid duplication with progress in indexDirectory

        $document = $this->loader->load($filePath);

        $this->storage->beginTransaction();

        try {
            $docId = $this->storage->storeDocument($document);
            $document->setId($docId);

            $sections = $this->parser->parse($document);

            // Build hierarchical heading paths
            $tree = new HeadingTree();
            foreach ($sections as $section) {
                $tree->addHeading($section['level'], $section['heading'], $section['content']);
            }
            $hierarchicalSections = $tree->flatten();

            $chunks = [];

            foreach ($hierarchicalSections as $section) {
                $sectionDoc = new Document();
                $sectionDoc->setId($docId);
                $sectionDoc->setPath($filePath);
                $sectionDoc->setHash($document->getHash());

                $chunkText = $section['content'];

                if (trim($chunkText) === '') {
                    continue;
                }

                $chunksFromSection = $this->chunker->chunkText($chunkText, $sectionDoc);

                foreach ($chunksFromSection as $chunk) {
                    $chunk->setHeadingPath($section['heading_path']);
                    $chunk->setDocumentHash($document->getHash());

                    try {
                        $this->validator->validate($chunk);
                    } catch (ValidationException $e) {
                        $log->warn("Chunk validation failed, re-splitting: " . $e->getMessage());
                        $subChunks = $this->chunker->chunkText($chunk->getText(), $sectionDoc);
                        $chunks = array_merge($chunks, $subChunks);
                        continue;
                    }

                    $chunks[] = $chunk;
                }
            }

            if (count($chunks) > Constant::MAX_CHUNKS_PER_DOCUMENT) {
                $log->error("Too many chunks from $filePath: " . count($chunks) . " (limit " . Constant::MAX_CHUNKS_PER_DOCUMENT . ")");
                $this->storage->rollback();
                return;
            }

            $chunksToEmbed = [];
            foreach ($chunks as $chunk) {
                $chunkId = $this->storage->storeChunk($chunk);
                $headingContext = $chunk->getHeadingPath() !== ''
                    ? "Раздел: {$chunk->getHeadingPath()}\n\n"
                    : '';
                $embeddingText = $headingContext . $chunk->getText();
                $chunksToEmbed[] = [
                    'id' => $chunkId,
                    'text' => $embeddingText,
                    'hash' => md5($embeddingText),
                    'document_id' => $docId,
                ];
            }

            if (!empty($chunksToEmbed)) {
                $this->embedAndStoreChunks($chunksToEmbed);
                $stats['chunks'] += count($chunksToEmbed);
            }

            $this->storage->commit();
            $log->pass("Stored " . count($chunks) . " chunks from: $filePath");

        } catch (\Throwable $e) {
            $this->storage->rollback();
            throw $e;
        }
    }

    /** @param list<array{id: int, text: string, hash: string, document_id: int}> $chunkData */
    private function embedAndStoreChunks(array $chunkData): void
    {
        $toEmbedTexts = [];
        $mapping = [];

        foreach ($chunkData as $data) {
            $documentId = $data['document_id'];
            $cacheDimension = $this->embedding->getDimension();
            if ($this->cache !== null && $this->cache->has($data['hash'], $this->embedding->getModel(), $documentId, $cacheDimension)) {
                $vector = $this->cache->get($data['hash'], $this->embedding->getModel(), $documentId, $cacheDimension);
                $this->storage->storeEmbedding(
                    $data['id'],
                    $vector,
                    $this->embedding->getModel(),
                    $this->embedding->getDimension(),
                    $this->embeddingVersion,
                );
            } else {
                $mapping[] = $data;
                $toEmbedTexts[] = $data['text'];
            }
        }

        if (!empty($toEmbedTexts)) {
            $embeddings = $this->embedding->embedBatch($toEmbedTexts);
            foreach ($mapping as $idx => $data) {
                $vector = $embeddings[$idx];
                $documentId = $data['document_id'];
                if ($this->cache !== null) {
                    $this->cache->set(
                        $data['hash'],
                        $this->embedding->getModel(),
                        $this->embedding->getDimension(),
                        $vector,
                        $documentId
                    );
                }
                $this->storage->storeEmbedding(
                    $data['id'],
                    $vector,
                    $this->embedding->getModel(),
                    $this->embedding->getDimension(),
                    $this->embeddingVersion,
                );
            }
        }
    }
}

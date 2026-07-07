<?php

namespace App\Services;

use App\Chunker\SemanticChunker;
use App\Core\Entities\Document;
use App\Core\Exceptions\ValidationException;
use App\Core\Interfaces\EmbeddingProvider;
use App\Core\Interfaces\StorageInterface;
use App\Loader\FileScanner;
use App\Loader\MarkdownLoader;
use App\Parser\MarkdownParser;
use App\Utils\AppLogger;
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

        foreach ($files as $filePath) {
            try {
                if ($incremental) {
                    $existingHash = $this->storage->getDocumentHash($filePath);
                    $currentHash = md5_file($filePath);

                    if ($existingHash !== null && $existingHash === $currentHash) {
                        $log->info("Skipped (unchanged): $filePath");
                        $stats['skipped']++;
                        continue;
                    }
                }

                $this->processFile($filePath, $stats);
                $stats['processed']++;

            } catch (\Throwable $e) {
                $log->error("Failed to process $filePath: " . $e->getMessage());
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
        $log->info("Processing: $filePath");

        $document = $this->loader->load($filePath);

        $this->storage->beginTransaction();

        try {
            $docId = $this->storage->storeDocument($document);
            $document->setId($docId);

            $sections = $this->parser->parse($document);

            $chunks = [];

            foreach ($sections as $section) {
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
                    $chunk->setHeadingPath($section['heading'] ?? '');
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

            foreach ($chunks as $chunk) {
                $chunkId = $this->storage->storeChunk($chunk);

                $vector = $this->embedding->embed($chunk->getText());
                $this->storage->storeEmbedding(
                    $chunkId,
                    $vector,
                    $this->embedding->getModel(),
                    $this->embedding->getDimension(),
                    $this->embeddingVersion,
                );

                $stats['chunks']++;
            }

            $this->storage->commit();
            $log->pass("Stored " . count($chunks) . " chunks from: $filePath");

        } catch (\Throwable $e) {
            $this->storage->rollback();
            throw $e;
        }
    }
}

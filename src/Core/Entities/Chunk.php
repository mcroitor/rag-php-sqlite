<?php

namespace App\Core\Entities;

class Chunk
{
    public function __construct(
        private ?int $id = null,
        private ?int $documentId = null,
        private string $headingPath = '',
        private string $text = '',
        private int $tokenCount = 0,
        private string $hash = '',
        private string $language = '',
        private string $embeddingModel = '',
        private string $documentHash = '',
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getDocumentId(): ?int
    {
        return $this->documentId;
    }

    public function setDocumentId(int $documentId): void
    {
        $this->documentId = $documentId;
    }

    public function getHeadingPath(): string
    {
        return $this->headingPath;
    }

    public function setHeadingPath(string $headingPath): void
    {
        $this->headingPath = $headingPath;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function getTokenCount(): int
    {
        return $this->tokenCount;
    }

    public function setTokenCount(int $tokenCount): void
    {
        $this->tokenCount = $tokenCount;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    public function getEmbeddingModel(): string
    {
        return $this->embeddingModel;
    }

    public function setEmbeddingModel(string $embeddingModel): void
    {
        $this->embeddingModel = $embeddingModel;
    }

    public function getDocumentHash(): string
    {
        return $this->documentHash;
    }

    public function setDocumentHash(string $documentHash): void
    {
        $this->documentHash = $documentHash;
    }
}

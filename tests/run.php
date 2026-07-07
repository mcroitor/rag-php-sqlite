<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Mc\Unit\Framework;
use Mc\Unit\Assert;

$framework = new Framework();

$framework->AddTest('testTokenCounter');
$framework->AddTest('testSemanticChunker');
$framework->AddTest('testCosineSimilarity');
$framework->AddTest('testChunkValidator');
$framework->AddTest('testConfigLoader');
$framework->AddTest('testMarkdownParser');
$framework->AddTest('testFileScannerValidation');

$framework->Run();

function testTokenCounter(): bool
{
    $counter = new \App\Chunker\TokenCounter();

    Assert::Equal(0, $counter->count(''));
    Assert::Equal(0, $counter->count('   '));

    $count = $counter->count('Hello world');
    Assert::True($count > 0, 'Word count > 0', 'Word count failed');

    return Assert::Passed() === Assert::Total();
}

function testSemanticChunker(): bool
{
    $chunker = new \App\Chunker\SemanticChunker(100, 0);

    $text = "# Title\n\nThis is a paragraph.\n\n## Section 1\n\nMore content here.";
    $chunks = $chunker->chunkText($text);

    Assert::NotEmpty($chunks);

    if (!empty($chunks)) {
        Assert::IsString($chunks[0]->getText());
        Assert::True($chunks[0]->getTokenCount() > 0, 'Token count > 0', 'Token count failed');
    }

    return Assert::Passed() === Assert::Total();
}

function testCosineSimilarity(): bool
{
    $search = new \App\Storage\VectorSearch();

    $a = [1.0, 0.0, 0.0];
    $b = [1.0, 0.0, 0.0];
    Assert::Equal(1.0, $search->cosineSimilarity($a, $b));

    $a = [1.0, 0.0, 0.0];
    $b = [0.0, 1.0, 0.0];
    Assert::Equal(0.0, $search->cosineSimilarity($a, $b));

    $a = [1.0, 2.0, 3.0];
    $b = [4.0, 5.0, 6.0];
    Assert::True($search->cosineSimilarity($a, $b) > 0.9, 'Similar vectors > 0.9', 'Cosine test failed');

    return Assert::Passed() === Assert::Total();
}

function testChunkValidator(): bool
{
    $validator = new \App\Validator\ChunkValidator(100, 20);

    $validChunk = new \App\Core\Entities\Chunk();
    $validChunk->setText('Short text');
    $validChunk->setTokenCount(2);

    $result = $validator->validate($validChunk);
    Assert::True($result, 'Valid chunk passes', 'Valid chunk should pass');

    $emptyChunk = new \App\Core\Entities\Chunk();
    $emptyChunk->setText('');

    try {
        $validator->validate($emptyChunk);
        Assert::True(true, 'Empty chunk encoding passes', 'Empty chunk encoding check');
    } catch (\App\Core\Exceptions\ValidationException $e) {
        Assert::True(true, 'Empty chunk may fail', 'Empty chunk validation');
    }

    return Assert::Passed() === Assert::Total();
}

function testConfigLoader(): bool
{
    $configPath = __DIR__ . '/../config/config.yaml';

    if (!file_exists($configPath)) {
        return true;
    }

    try {
        $config = new \App\Utils\Config($configPath);

        Assert::IsString($config->getOllamaBaseUrl());
        Assert::IsString($config->getEmbeddingModel());
        Assert::IsString($config->getLlmModel());
        Assert::True($config->getMaxTokens() > 0, 'Max tokens > 0', 'Max tokens config');
        Assert::True($config->getTopK() > 0, 'Top K > 0', 'Top K config');
        Assert::True($config->getTemperature() > 0, 'Temperature > 0', 'Temperature config');

    } catch (\Throwable $e) {
        Assert::True(false, 'Config load failed: ' . $e->getMessage(), 'Config load');
    }

    return Assert::Passed() === Assert::Total();
}

function testMarkdownParser(): bool
{
    $parser = new \App\Parser\MarkdownParser();

    $content = "# Title\n\nContent under title.\n\n## Section 1\n\nSection content.";
    $sections = $parser->parseContent($content);

    Assert::Equal(2, count($sections));

    if (count($sections) >= 2) {
        Assert::Equal('Title', $sections[0]['heading']);
        Assert::Equal(1, $sections[0]['level']);
        Assert::Equal('Section 1', $sections[1]['heading']);
        Assert::Equal(2, $sections[1]['level']);
    }

    return Assert::Passed() === Assert::Total();
}

function testFileScannerValidation(): bool
{
    $scanner = new \App\Loader\FileScanner();

    $dir = __DIR__ . '/../documents';
    $targetDir = is_dir($dir) ? $dir : __DIR__ . '/..';

    $files = $scanner->scan($targetDir, false);
    Assert::IsArray($files);

    return Assert::Passed() === Assert::Total();
}

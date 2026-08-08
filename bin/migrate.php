<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Engine\Storage\SQLiteStorage;
use App\Engine\Utils\AppLogger;
use App\Engine\Utils\DbFactory;

/**
 * One-time migration of the RAG project into the runtime directory:
 *   - moves the legacy root rag.sqlite into runtime/dbs/rag.sqlite
 *   - moves indexed source files into runtime/documents/{base}/{uuid}/{filename}
 *     and updates their paths in the database.
 *
 * Usage:
 *   php bin/migrate.php                # migrate all existing bases
 *   php bin/migrate.php --rag=rag      # migrate a single base
 *   php bin/migrate.php --dry-run      # preview without changing anything
 *   php bin/migrate.php --keep-source  # copy files, do not delete originals
 */

$log = AppLogger::instance();

\Mc\Arguments::Set([
    'rag' => [
        'long' => 'rag',
        'description' => 'RAG database name to migrate (default: all bases)',
        'required' => false,
        'default' => '',
    ],
    'dry-run' => [
        'long' => 'dry-run',
        'description' => 'Preview changes without applying them',
        'required' => false,
    ],
    'keep-source' => [
        'long' => 'keep-source',
        'description' => 'Copy source files instead of moving them',
        'required' => false,
    ],
    'help' => [
        'short' => 'h',
        'long' => 'help',
        'description' => 'Show help',
        'required' => false,
    ],
]);

$argvList = $_SERVER['argv'] ?? [];
if (in_array('-h', $argvList, true) || in_array('--help', $argvList, true)) {
    echo "Migrate the RAG project into the runtime directory.\n\n";
    echo "Examples:\n";
    echo "  php bin/migrate.php\n";
    echo "  php bin/migrate.php --rag=rag\n";
    echo "  php bin/migrate.php --dry-run\n\n";
    echo \Mc\Arguments::Help();
    exit(0);
}

try {
    \Mc\Arguments::Parse();
} catch (\InvalidArgumentException $e) {
    echo $e->getMessage() . "\n";
    echo \Mc\Arguments::Help();
    exit(1);
}

$root = rtrim(dirname(__DIR__), '/\\');
$dryRun = (bool) \Mc\Arguments::GetValue('dry-run');
$keepSource = (bool) \Mc\Arguments::GetValue('keep-source');
$onlyBase = (string) (\Mc\Arguments::GetValue('rag') ?: '');

// --------------------------------------------------------------------- Step 1
// Move the legacy root database file into runtime/dbs.
$legacyDb = $root . DIRECTORY_SEPARATOR . 'rag.sqlite';
$targetDb = DbFactory::path($root, DbFactory::DEFAULT_BASE);

if ($legacyDb !== $targetDb && file_exists($legacyDb)) {
    $dbsDir = dirname($targetDb);
    if (!is_dir($dbsDir)) {
        if ($dryRun) {
            $log->info("[dry-run] would create $dbsDir");
        } elseif (!mkdir($dbsDir, 0777, true) && !is_dir($dbsDir)) {
            $log->error("Failed to create database directory: $dbsDir");
            exit(1);
        }
    }

    if (file_exists($targetDb)) {
        $log->warn("Target database already exists: $targetDb (legacy left untouched)");
    } else {
        $sizeMb = round(filesize($legacyDb) / 1048576, 1);
        if ($dryRun) {
            $log->info("[dry-run] would move legacy $legacyDb ({$sizeMb}MB) to $targetDb");
        } else {
            if (!rename($legacyDb, $targetDb)) {
                $log->error("Failed to move legacy database: $legacyDb");
                exit(1);
            }
            $log->pass("Moved legacy database to $targetDb");
        }
    }
}

// --------------------------------------------------------------------- Step 2
// Migrate source files of each base into runtime/documents.
$bases = [];

// Always check the default 'rag' base first (legacy location).
$defaultBase = DbFactory::DEFAULT_BASE;
$defaultDbPath = DbFactory::path($root, $defaultBase);
$legacyDbPath = $root . DIRECTORY_SEPARATOR . 'rag.sqlite';

// The rag base is present if either the new location exists or the legacy file exists.
$hasRagBase = file_exists($defaultDbPath) || file_exists($legacyDbPath);

if ($hasRagBase) {
    $bases[] = ['name' => $defaultBase];
}

// Add other bases from DbFactory::list()
if ($onlyBase === '') {
    foreach (DbFactory::list($root) as $baseInfo) {
        if ($baseInfo['name'] === $defaultBase) {
            continue; // already added
        }
        $bases[] = $baseInfo;
    }
} elseif ($onlyBase !== '') {
    $requestedBase = DbFactory::normalize($onlyBase);
    if ($requestedBase !== $defaultBase) {
        $bases[] = ['name' => $requestedBase];
    }
}

foreach ($bases as $baseInfo) {
    $base = $baseInfo['name'];
    $dbPath = DbFactory::path($root, $base);

    // For the default 'rag' base, always prefer the legacy database if it exists,
    // since that's the one with actual data (the new location might be empty).
    $legacyDbPath = $root . DIRECTORY_SEPARATOR . 'rag.sqlite';
    if ($base === $defaultBase && file_exists($legacyDbPath)) {
        $dbPath = $legacyDbPath;
    }

    if (!file_exists($dbPath)) {
        $log->warn("Database '$base' not found at $dbPath, skipping.");
        continue;
    }

    // Open the database directly using the determined path.
    $pdo = new \PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $storage = new SQLiteStorage($pdo);
    $documents = $storage->listDocuments(null, null);

    if ($documents === []) {
        $log->info("Base '$base': no documents to migrate.");
        continue;
    }

    $log->info("Base '$base': " . count($documents) . ' document(s) to check.');

    $moved = 0;
    $updated = 0;

    foreach ($documents as $doc) {
        $oldPath = $doc['path'];

        if ($oldPath === '' || $oldPath === $dbPath) {
            continue;
        }

        // Already inside runtime/documents/{base} — nothing to do.
        $documentsBaseDir = $root . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $base;
        if (str_starts_with($oldPath, $documentsBaseDir)) {
            continue;
        }

        if (!is_file($oldPath)) {
            $log->warn("  missing source, skipping: $oldPath");
            continue;
        }

        $uuid = stableUuid($oldPath);
        $destDir = $documentsBaseDir . DIRECTORY_SEPARATOR . $uuid;
        $destFile = $destDir . DIRECTORY_SEPARATOR . basename($oldPath);

        $log->info("  #{$doc['id']} $oldPath");
        $log->info("      -> $destFile");

        if ($dryRun) {
            $moved++;
            continue;
        }

        if (!is_dir($destDir) && !mkdir($destDir, 0777, true) && !is_dir($destDir)) {
            $log->error("  failed to create directory: $destDir");
            continue;
        }

        if (file_exists($destFile)) {
            $log->warn("  destination already exists, skipping copy: $destFile");
        } else {
            if ($keepSource) {
                if (!copy($oldPath, $destFile)) {
                    $log->error("  failed to copy: $oldPath");
                    continue;
                }
            } elseif (!rename($oldPath, $destFile)) {
                $log->error("  failed to move: $oldPath");
                continue;
            }
            $moved++;
        }

        if ($storage->updateDocumentPathById((int) $doc['id'], $destFile)) {
            $updated++;
            $log->pass("  updated path for #{$doc['id']}");
        } else {
            $log->warn("  path unchanged for #{$doc['id']}");
        }
    }

    $log->info(sprintf(
        'Base %s: %d file(s) %s, %d path(s) updated.',
        $base,
        $moved,
        $dryRun ? 'to be moved' : 'moved',
        $updated,
    ));
}

$log->pass('Migration finished.');

/**
 * Deterministic UUID (v4-shaped) derived from a file path, so re-runs
 * always target the same runtime directory for the same source file.
 */
function stableUuid(string $path): string
{
    $hash = hash('sha256', $path);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hash, 0, 8),
        substr($hash, 8, 4),
        substr($hash, 12, 4),
        substr($hash, 16, 4),
        substr($hash, 20, 12),
    );
}

# Wingman — Explorer

A filesystem abstraction and I/O library for PHP applications, providing a unified interface for reading, writing, and transforming files across local and cloud storage — with a pluggable adapter model, a rich resource hierarchy, a chainable Scanner, atomic filesystem transactions, and a format-agnostic import/export pipeline.

---

## Requirements

- PHP **8.1** or later
- **Wingman/Locator** — required for URI resolution and path handling
- **Wingman/Cortex** *(optional)* — enables `#[Configurable]` attribute-driven configuration hydration for `Stream` and `Scanner`
- **Wingman/Corvus** *(optional)* — enables signal emission on filesystem events (scan lifecycle, import/export, transaction outcomes)
- **Wingman/Synapse** *(optional)* — enables DI container integration via `Provider`
- **Wingman/Console** *(optional)* — enables the `explorer:*` family of CLI commands
- **psr/http-message** *(optional)* — required by `Psr7StreamAdapter`
- **symfony/yaml** *(optional)* — enables YAML import and export when the `yaml` extension is unavailable
- **aws/aws-sdk-php** *(optional)* — required by `S3Adapter`
- **microsoft/azure-storage-blob** *(optional)* — required by `AzureAdapter`
- **google/cloud-storage** *(optional)* — required by `GCSAdapter`
- **ext-ftp** *(optional)* — required by `FTPAdapter`
- **ext-curl** *(optional)* — required by `HTTPAdapter`
- **ext-ssh2** *(optional)* — required by `SFTPAdapter`
- **ext-zip** *(optional)* — required by `ZipImporter` and `ZipExporter`
- **ext-zlib** *(optional)* — required by `GZipImporter` and `GZipExporter`
- **ext-yaml** *(optional)* — enables YAML import and export when `symfony/yaml` is unavailable

---

## Installation

Install the package via Composer:

```bash
composer require wingman/explorer
```

In addition, you can download the package directly and use its autoloader (`autoload.php`), which registers a PSR-style class map for the `Wingman\Explorer` namespace and loads any mandatory dependencies declared in `manifest.json`.

---

## Quick Start

```php
use Wingman\Explorer\Resources\LocalFile;
use Wingman\Explorer\Resources\LocalDirectory;
use Wingman\Explorer\Scanner;

// ─── Local File I/O ───────────────────────────────────────────────────────────

$file = new LocalFile("/var/app/config.json");

if ($file->exists()) {
    $json = $file->getContent();
}

$file->write('{"debug":false}')->save();

// ─── Local Directory ──────────────────────────────────────────────────────────

$dir = new LocalDirectory("/var/app/storage");

foreach ($dir->getFiles() as $f) {
    echo $f->getBaseName(), PHP_EOL;
}

// ─── Scanner ──────────────────────────────────────────────────────────────────

$scanner = Scanner::create();
$results = $scanner
    ->filterBy(ScanFilterType::EXTENSION, "php")
    ->scan("/var/app/src");
```

---

## Adapters

Explorer ships seven storage adapters. Each adapter provides a different transport behind the same interface contracts.

| Adapter | Transport | Interface tiers |
| --- | --- | --- |
| `LocalAdapter` | Local filesystem | Readable, Writable, Directory, Movable, Permission, Symlink |
| `S3Adapter` | Amazon S3 (SDK) | Readable, Writable, Directory, Movable, Multipart, Presigned URL |
| `AzureAdapter` | Azure Blob Storage (SDK) | Readable, Writable, Directory, Movable, Cloud |
| `GCSAdapter` | Google Cloud Storage (SDK) | Readable, Writable, Directory, Movable, Cloud |
| `FTPAdapter` | FTP via `ext-ftp` | Readable, Writable, Directory |
| `HTTPAdapter` | HTTP(S) via `ext-curl` | Readable |
| `SFTPAdapter` | SFTP via `ext-ssh2` | Readable, Writable, Directory |

See [docs/Adapters.md](docs/Adapters.md) for constructor parameters, cloud SDK requirements, and capability tables.

---

## Resource Types

Explorer provides a layered class hierarchy for representing files and directories across all backends.

| Class | Description |
| --- | --- |
| `LocalFile` | File on the local filesystem |
| `LocalDirectory` | Directory on the local filesystem |
| `RemoteFile` | File on a remote adapter |
| `RemoteDirectory` | Directory on a remote adapter |
| `VirtualFile` | In-memory file (abstract base) |
| `VirtualDirectory` | In-memory directory tree node |
| `GeneratedFile` | File whose content is produced by a callable |
| `InlineFile` | File backed by an inline string literal |
| `ProxyFile` | Transparent proxy/decorator around another file |
| `TempFile` | Temporary file cleaned up on destruction |

See [docs/Resources.md](docs/Resources.md) for detailed method references and usage patterns.

---

## Import / Export

Explorer ships twelve built-in importers and eleven built-in exporters, orchestrated by `ImportManager`, `ExportManager`, and the static `IOManager` facade.

```php
use Wingman\Explorer\IO\IOManager;

// Initialise with all defaults registered.
IOManager::init();

// Import a JSON file.
$data = IOManager::getImportManager()->import("config/app.json");

// Export a PHP array to YAML.
IOManager::getExportManager()->export($data, "config/app.yaml");
```

**Supported importer classes:** CSV · GZip · INI · JSON · JSONL · PHP · Text · TAR · XML · YAML · ZIP, plus `PipelineImporter`.

**Supported exporter classes:** CSV · GZip · INI · JSON · JSONL · Text · TAR · XML · YAML · ZIP, plus `PipelineExporter`.

See [docs/IO.md](docs/IO.md) for importer/exporter contracts, pipeline composition, and negotiation strategies.

---

## Scanner

`Scanner` is a fluent directory scanner. It supports depth control, target filtering, metadata filters, single-key sorting, event callbacks, options, and pluggable adapters.

```php
use Wingman\Explorer\Scanner;
use Wingman\Explorer\Enums\ScanTarget;
use Wingman\Explorer\Enums\ScanFilterType;
use Wingman\Explorer\Enums\ScanSortOption;
use Wingman\Explorer\Enums\ScanOrder;

$results = Scanner::create()
    ->setTarget(ScanTarget::FILE)
    ->filterBy(ScanFilterType::EXTENSION, "php")
    ->sortBy(ScanSortOption::NAME, ScanOrder::ASCENDING)
    ->scan("/var/app/src");
```

By default, scanner results are metadata arrays; enabling `ScanOption::PATHS_ONLY` returns plain path strings.

See [docs/Scanner.md](docs/Scanner.md) for the full filter/sort/option/event API.

---

## Filesystem Transactions

`FilesystemTransaction` queues a sequence of operations and applies them atomically, rolling back all completed steps if any operation fails.

```php
use Wingman\Explorer\FilesystemTransaction;
use Wingman\Explorer\Adapters\LocalAdapter;

$tx = new FilesystemTransaction(new LocalAdapter());

$tx->writeFile("config/production.json", $json)
   ->copyFile("config/production.json", "config/production.bak")
   ->deleteFile("config/staging.json")
   ->commit();
```

If `commit()` throws, the transaction automatically rolls back in reverse order. See [docs/Transactions.md](docs/Transactions.md).

---

## IO Streams

Explorer provides a full-featured `Stream` class for in-process I/O. Streams are seekable (when the backend supports it), support chunked reading and writing, maintain separate read and write offsets, and auto-clean temp files on destruction.

```php
use Wingman\Explorer\IO\Stream;
use Wingman\Explorer\Enums\StreamMode;

$stream = Stream::from("php://temp", StreamMode::WRITE_READ_BINARY);
$stream->write("hello world");
$stream->rewindReader();
echo $stream->readAll(); // "hello world"
```

When the optional `psr/http-message` package is installed, wrap any `Stream` in `Psr7StreamAdapter` for PSR-7 compatibility:

```php
use Wingman\Explorer\Bridge\Psr\Psr7StreamAdapter;

$psrStream = new Psr7StreamAdapter($stream);
```

---

## Virtual Trees

`VirtualDirectory` and its resource children provide a fully in-memory tree that can be populated programmatically, traversed, searched, and compiled from a JSON descriptor via `VirtualTreeCompiler`.

```php
use Wingman\Explorer\VirtualTreeCompiler;

$root = VirtualTreeCompiler::compile([
    "name" => "project",
    "children" => [
        ["name" => "src",  "children" => [["name" => "index.php", "content" => "<?php"]]],
        ["name" => "docs", "children" => []],
    ]
]);

$root->search("*.php");
```

See [docs/VirtualTree.md](docs/VirtualTree.md).

---

## Signals

When Wingman Corvus is installed, Explorer emits typed signals at key lifecycle points so that listeners can attach logging, metrics, or alerting without modifying Explorer's internals.

| Signal | Event |
| --- | --- |
| `SCAN_STARTED` | Directory scan begins |
| `SCAN_COMPLETED` | Scan finishes successfully |
| `SCAN_ERROR` | Unhandled exception during scan |
| `FILE_FOUND` / `DIRECTORY_FOUND` | Item passes all filters |
| `TRANSACTION_COMMITTED` | All queued operations applied |
| `TRANSACTION_ROLLED_BACK` | Transaction rolled back |
| `ROLLBACK_STEP_FAILED` | One rollback step threw |
| `ROLLBACK_RESTORE_IMPOSSIBLE` | Deleted file could not be restored |
| `IMPORT_COMPLETED` / `IMPORT_FALLBACK` | Import lifecycle |
| `EXPORT_COMPLETED` / `EXPORT_FALLBACK` | Export lifecycle |

See [docs/Signals.md](docs/Signals.md) for signal identifiers, payloads, and listener registration.

---

## Further Reading

| Topic | File |
| --- | --- |
| Storage Adapters | [docs/Adapters.md](docs/Adapters.md) |
| Resource Types | [docs/Resources.md](docs/Resources.md) |
| Import / Export | [docs/IO.md](docs/IO.md) |
| Scanner | [docs/Scanner.md](docs/Scanner.md) |
| Filesystem Transactions | [docs/Transactions.md](docs/Transactions.md) |
| Virtual Tree | [docs/VirtualTree.md](docs/VirtualTree.md) |
| Signals | [docs/Signals.md](docs/Signals.md) |
| Exceptions | [docs/Exceptions.md](docs/Exceptions.md) |
| Bridges | [docs/Bridges.md](docs/Bridges.md) |
| Console Commands | [docs/Commands.md](docs/Commands.md) |

---

## Licence

This project is licensed under the **Mozilla Public License 2.0 (MPL 2.0)**.

Wingman Explorer is part of the **Wingman Framework**, Copyright (c) 2022–2026 Angel Politis.

For the full licence text, please see the [LICENSE](LICENSE) file.

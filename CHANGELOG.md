# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] — 2026-03-22

### Added

- **`Scanner`** — Fluent, value-object-style directory scanner. Supports recursive depth control via `ScanDepth`, target filtering (`ScanTarget::FILES`, `DIRECTORIES`, `ALL`), inclusion and exclusion filters by extension, glob pattern, regex, name, size, and modification date, multi-field sorting (`ScanSortOption`, `ScanOrder`), event callbacks (`ScanEvent::MATCH`, `ENTER`, `SKIP`), and pluggable `DirectoryFilesystemAdapterInterface` backends. Emits `SCAN_STARTED`, `SCAN_COMPLETED`, `FILE_FOUND`, `DIRECTORY_FOUND`, and `SCAN_ERROR` signals via the Corvus bridge.

- **Seven storage adapters**, all backed by a capability-based interface tier system:
  - `LocalAdapter` — Local filesystem adapter; implements `ReadableFilesystemAdapterInterface`, `WritableFilesystemAdapterInterface`, `DirectoryFilesystemAdapterInterface`, `MovableFilesystemAdapterInterface`, `PermissionFilesystemAdapterInterface`, `SymlinkFilesystemAdapterInterface`.
  - `S3Adapter` — Amazon S3 via the AWS SDK (`aws/aws-sdk-php`); implements `ReadableFilesystemAdapterInterface`, `WritableFilesystemAdapterInterface`, `DirectoryFilesystemAdapterInterface`, `CloudAdapterInterface`, `PresignedUrlAdapterInterface`. Wraps SDK exceptions in `FilesystemException`.
  - `AzureAdapter` — Azure Blob Storage via the SDK (`microsoft/azure-storage-blob`); same capability tier as `S3Adapter`.
  - `GCSAdapter` — Google Cloud Storage via the SDK (`google/cloud-storage`); same capability tier as `S3Adapter`.
  - `FTPAdapter` — FTP/FTPS via `ext-ftp`; implements `ReadableFilesystemAdapterInterface`, `WritableFilesystemAdapterInterface`, `DirectoryFilesystemAdapterInterface`.
  - `HTTPAdapter` — Read-only HTTP/HTTPS via `ext-curl`; implements `ReadableFilesystemAdapterInterface`.
  - `SFTPAdapter` — SFTP via `ext-ssh2`; implements all three core tiers (readable, writable, directory).

- **Eleven resource classes** across four transport tiers:
  - Local: `LocalFile`, `LocalDirectory`
  - Remote: `RemoteFile`, `RemoteDirectory`
  - Virtual: `VirtualFile` (abstract), `VirtualDirectory`
  - Specialised: `GeneratedFile` (callable-produced content), `InlineFile` (string-backed), `ProxyFile` (transparent decorator), `TempFile` (auto-cleaning temporary file)
  - Abstract bases: `File`, `Directory`

- **`VirtualTreeCompiler`** — Builds a `VirtualDirectory` tree from a nested JSON descriptor array. Single static entry point: `VirtualTreeCompiler::compile(array $json) : VirtualDirectory`.

- **`FilesystemTransaction`** — Queues filesystem operations against a `FilesystemAdapterInterface` and commits or rolls them back atomically. Operations: `writeFile`, `createFile`, `deleteFile`, `copyFile`, `moveFile`, `createDirectory`. Rollback is automatic on `commit()` failure; the rollback emits `ROLLBACK_STEP_FAILED` and `ROLLBACK_RESTORE_IMPOSSIBLE` signals for individual step errors rather than silently discarding them.

- **`Stream`** — Low-level stream wrapper around PHP file resources. Features: separate read/write offset tracking, configurable chunk size, in-memory cache, LRU eviction, `deleteRange()`, `append()`, `copyTo()`, `truncate()`, `lock()`/`unlock()`, optional text mode, seekable state detection, and auto-cleanup of temp-directory streams on destruction. Factory methods: `Stream::create()`, `Stream::from()`, `Stream::for()`.

- **Twenty-four importers and exporters** via the `IO` subsystem:
  - **Importers:** `CsvImporter`, `GZipImporter`, `IniImporter`, `JsonImporter`, `JsonLinesImporter`, `PhpImporter`, `PipelineImporter`, `TarImporter`, `TextImporter`, `XmlImporter`, `YamlImporter`, `ZipImporter`.
  - **Exporters:** `CsvExporter`, `GZipExporter`, `IniExporter`, `JsonExporter`, `JsonLinesExporter`, `PipelineExporter`, `TarExporter`, `TextExporter`, `XmlExporter`, `YamlExporter`, `ZipExporter`.
  - `PipelineImporter` — Chains multiple importers in sequence; each step receives the previous step's output written to a temp file, allowing the full `import(string $path)` contract to be satisfied without requiring per-step file creation.
  - `PhpImporter` — Imports a PHP file via `include`; returns the value produced by the file. **Security notice:** only use with trusted files, never with user-controlled input.

- **`IOManager`** — Static facade that co-owns an `ImportManager` and an `ExportManager`. Manages format-to-importer and format-to-exporter bindings, round-trip twin lookup (`getTwin()`), and default registration of all built-in formats via `IOManager::init()`. Supports custom `ImporterNegotiationStrategyInterface` and `ExporterNegotiationStrategyInterface` for advanced content-negotiation.

- **`FileUtils`** — Static utility collection: `computeHash()`, `atomicReplace()`, `countLines()`, `detectMimeType()`, `normaliseNewlines()`, `sanitiseFilename()`, `toHumanReadableSize()`.

- **`IgnorePatternBuilder`** — Parses `.gitignore`-style pattern files into `ScanFilterScope`-aware filter sets usable with `Scanner::filterBy()`.

- **`DirectoryDiff`** — Computes the structural difference between two `DirectoryResource` instances: added, modified, removed, and unchanged files.

- **`FileDiff`** — Line-level diff between two text files using a longest-common-subsequence algorithm: added, removed, and context lines; configurable context window.

- **`UploadHandler`** and **`UploadValidator`** — Handle `$_FILES` single and multi-file uploads with configurable size limits, MIME type allow-lists, extension allow-lists, and optional storage path mapping.

- **`PermissionsMode`** — Value object wrapping a Unix file permission bitmask; parses from octal int, rwx-string, or numeric string. Provides symbolic string rendering and bitwise capability checks.

- **`Signal` enum** — Thirteen typed signal cases for Corvus listener registration. All are `string`-backed with dot-notation identifiers under the `explorer.*` namespace.

- **Eleven adapter interfaces** in `Interfaces/Adapters/`: `FilesystemAdapterInterface`, `ReadableFilesystemAdapterInterface`, `WritableFilesystemAdapterInterface`, `DirectoryFilesystemAdapterInterface`, `MovableFilesystemAdapterInterface`, `PermissionFilesystemAdapterInterface`, `SymlinkFilesystemAdapterInterface`, `CloudAdapterInterface`, `PresignedUrlAdapterInterface`, `MultipartUploadAdapterInterface`, `WatchableFilesystemInterface`.

- **Seventeen resource interfaces** in `Interfaces/Resources/`: `Resource`, `FileResource`, `DirectoryResource`, `LocalResource`, `RemoteResource`, `VirtualResource`, `LocalFileResource`, `LocalDirectoryResource`, `RemoteFileResource`, `RemoteDirectoryResource`, `VirtualFileResource`, `VirtualDirectoryResource`, `CreatableResource`, `EditableFileResource`, `HashableResource`, `WritableResource`.

- **Thirty-one domain-specific exception classes**, all implementing the `ExplorerException` marker interface.

- **Four optional integration bridges:** Corvus signal dispatch, Cortex configuration hydration, PSR-7 stream adapter, and Synapse DI provider.

- **Full test suite** covering local adapters, local resources, cloud adapters (S3, Azure, GCS), remote resources, PSR-7 stream adapter, `Scanner`, `FilesystemTransaction`, `DirectoryDiff`, `FileDiff`, `FileUtils`, `IgnorePatternBuilder`, `Stream`, `VirtualDirectory`, `VirtualFile`, `VirtualTreeCompiler`, `IOManager`, upload validation, and exception instantiation.

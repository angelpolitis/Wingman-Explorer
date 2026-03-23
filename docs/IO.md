# Explorer — Import / Export System

Explorer provides negotiated importing and exporting through three classes:

- `ImportManager`
- `ExportManager`
- `IOManager` (static coordinator)

---

## `IOManager`

`IOManager` manages one shared `ImportManager`, one shared `ExportManager`, and importer/exporter twin bindings.

### Core API

```php
use Wingman\Explorer\IO\IOManager;
use Wingman\Explorer\IO\Importers\JsonImporter;
use Wingman\Explorer\IO\Exporters\JsonExporter;

IOManager::init();

IOManager::bind(JsonImporter::class, JsonExporter::class);

$importManager = IOManager::getImportManager();
$exportManager = IOManager::getExportManager();

$twinExporter = IOManager::getTwinExporter(JsonImporter::class);
$twinImporter = IOManager::getTwinImporter(JsonExporter::class);
$twin = IOManager::getTwin(JsonImporter::class);
```

### Method signatures

- `init(bool $registerDefaults = true, array $importerConfig = [], array $exporterConfig = []): void`
- `registerDefaults(): void`
- `bind(string|ImporterInterface $importer, string|ExporterInterface $exporter): void`
- `unbind(string|ImporterInterface|null $importer = null, string|ExporterInterface|null $exporter = null): void`
- `getImportManager(): ImportManager`
- `getExportManager(): ExportManager`
- `getTwin(string|ImporterInterface|ExporterInterface $io): ImporterInterface|ExporterInterface|null`
- `getTwinExporter(string|ImporterInterface $importer): ?ExporterInterface`
- `getTwinImporter(string|ExporterInterface $exporter): ?ImporterInterface`

`getTwin*()` resolve by importer/exporter class mapping, not by file path.

---

## Defaults Registered by `IOManager::registerDefaults()`

### Importers

- `JsonImporter`
- `JsonLinesImporter`
- `IniImporter`
- `CsvImporter`
- `PhpImporter`
- fallback: `TextImporter`

### Exporters

- `JsonExporter`
- `JsonLinesExporter`
- `IniExporter`
- `CsvExporter`
- fallback: `TextExporter`

### Default twin bindings

- `JsonImporter` <-> `JsonExporter`
- `JsonLinesImporter` <-> `JsonLinesExporter`
- `IniImporter` <-> `IniExporter`
- `CsvImporter` <-> `CsvExporter`
- `TextImporter` <-> `TextExporter`

---

## `ImportManager`

Handles importer registration, negotiation, and execution.

### Public API

- `get(string $class): ?ImporterInterface`
- `getAll(): array`
- `getBestMatch(string $path, ?string $extension = null, ?string $mime = null): ?ImporterInterface`
- `getByType(string $extension): ?ImporterInterface`
- `getByMime(string $mime): ?ImporterInterface`
- `getFallback(): ?ImporterInterface`
- `has(string $class): bool`
- `import(string $path, array $options = []): mixed`
- `register(ImporterInterface $importer): static`
- `setFallback(ImporterInterface $importer): static`
- `setNegotiationStrategy(ImporterNegotiationStrategyInterface|callable $strategy): static`
- `unregister(string $class): static`

### Failure behaviour

If no importer matches and no fallback is set, `import()` throws `UnsupportedImportTypeException`.

---

## `ExportManager`

Handles exporter registration, negotiation, and execution.

### Public API

- `export(mixed $data, string $path, array $options = []): mixed`
- `get(string $class): ?ExporterInterface`
- `getAll(): array`
- `getBestMatch(mixed $data, ?string $extension = null, ?string $mime = null): ?ExporterInterface`
- `getByType(string $extension): ?ExporterInterface`
- `getByMime(string $mime): ?ExporterInterface`
- `getFallback(): ?ExporterInterface`
- `has(string $class): bool`
- `register(ExporterInterface $exporter): static`
- `setFallback(ExporterInterface $exporter): static`
- `setNegotiationStrategy(ExporterNegotiationStrategyInterface|callable $strategy): static`
- `unregister(string $class): static`

### Failure behaviour

If no exporter matches and no fallback is set, `export()` throws `UnsupportedExportTypeException`.

---

## Built-in Importers

- `CsvImporter`
- `GZipImporter`
- `IniImporter`
- `JsonImporter`
- `JsonLinesImporter`
- `PhpImporter`
- `PipelineImporter`
- `TarImporter`
- `TextImporter`
- `XmlImporter`
- `YamlImporter`
- `ZipImporter`

## Built-in Exporters

- `CsvExporter`
- `GZipExporter`
- `IniExporter`
- `JsonExporter`
- `JsonLinesExporter`
- `PipelineExporter`
- `TarExporter`
- `TextExporter`
- `XmlExporter`
- `YamlExporter`
- `ZipExporter`

---

## Pipeline Types

### `PipelineImporter`

`PipelineImporter` is a concrete `ImporterInterface` that chains multiple importers.

### `PipelineExporter`

`PipelineExporter` is a concrete `ExporterInterface` that chains multiple exporters.

---

## `PhpImporter`

`PhpImporter` executes target files via PHP include semantics and therefore must only be used on trusted files.

---

## Negotiation Strategy Interfaces

### `ImporterNegotiationStrategyInterface`

```php
public function select (
    array $importers,
    string $path,
    ?string $mime,
    ?string $extension,
    ?string $sample = null
) : ?ImporterInterface;
```

### `ExporterNegotiationStrategyInterface`

```php
public function select (
    array $exporters,
    string $path,
    ?string $mime,
    ?string $extension
) : ?ExporterInterface;
```

Both managers also accept callables via `setNegotiationStrategy(...)`.

---

## Signals Emitted

- `Signal::IMPORT_COMPLETED` (`path`, `importer`)
- `Signal::IMPORT_FALLBACK` (`path`)
- `Signal::EXPORT_COMPLETED` (`path`, `exporter`)
- `Signal::EXPORT_FALLBACK` (`path`)

See [Signals.md](Signals.md) for payload details.

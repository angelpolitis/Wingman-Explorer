# Explorer — Scanner

`Scanner` traverses local or remote directory trees and returns metadata arrays (or plain paths when `ScanOption::PATHS_ONLY` is enabled). It is the primary tool for filtering, sorting, and walking filesystem hierarchies with full control over depth, target type, and sorting behaviour.

---

## Construction

```php
use Wingman\Explorer\Scanner;

$scanner = Scanner::create();

// With a remote adapter
$scanner = Scanner::withAdapter($s3Adapter);
```

When no adapter is supplied, a `LocalAdapter` is instantiated automatically.

---

## Quick Start

```php
$results = Scanner::create()
    ->setTarget(ScanTarget::FILE)
    ->setDepth(ScanDepth::DEEP)
    ->sortBy(ScanSortOption::NAME, ScanOrder::ASCENDING)
    ->filterBy(ScanFilterType::GLOB, "*.jpg")
    ->scan("/var/app/uploads");
```

`scan()` returns an `array` of metadata arrays by default. When `ScanOption::PATHS_ONLY` is enabled, it returns an `array` of path strings.

---

## Depth Control

```php
use Wingman\Explorer\Enums\ScanDepth;

$scanner->setDepth(ScanDepth::SHALLOW);    // root directory only (no recursion)
$scanner->setDepth(ScanDepth::DEFAULT);    // root + immediate subdirectories (default)
$scanner->setDepth(ScanDepth::DEEP);       // full subtree
```

`ScanDepth::DEFAULT` is the default when no depth is set.

---

## Target Types

```php
use Wingman\Explorer\Enums\ScanTarget;

$scanner->setTarget(ScanTarget::FILE);  // only files
$scanner->setTarget(ScanTarget::DIR);   // only directories
$scanner->setTarget(ScanTarget::ANY);   // both (default)
```

---

## Filtering

Filters can be stacked. Each filter specifies a type and a value. All active filters must match for an entry to be included in results.

```php
use Wingman\Explorer\Enums\ScanFilterType;

$scanner
    ->filterBy(ScanFilterType::EXTENSION, "php")
    ->filterBy(ScanFilterType::GLOB, "App*")
    ->filterBy(ScanFilterType::REGEX, "/^[^.]/");
```

Filters whose type scope is `ScanFilterScope::FILES` (e.g. `EXTENSION`, `SIZE_GREATER`, `SIZE_LESS`) are automatically skipped for directory entries.

`clearFilters()` removes all previously added filters.

---

## Sorting

```php
use Wingman\Explorer\Enums\ScanSortOption;
use Wingman\Explorer\Enums\ScanOrder;

$scanner->sortBy(ScanSortOption::NAME, ScanOrder::ASCENDING);
$scanner->sortBy(ScanSortOption::SIZE, ScanOrder::DESCENDING);
$scanner->sortBy(ScanSortOption::LAST_MODIFIED, ScanOrder::ASCENDING);
```

`ScanSortOption::NONE` (default) preserves the order returned by the adapter.

---

## Event Callbacks

Register callbacks for scan events:

```php
use Wingman\Explorer\Enums\ScanEvent;

$scanner->setEvent(ScanEvent::FILE_FOUND, function (array $info) {
    echo "Found: " . $info["path"] . "\n";
});

$scanner->setEvent(ScanEvent::DIRECTORY_FOUND, function (array $info) {
    echo "Entering: " . $info["path"] . "\n";
});

$scanner->setEvent(ScanEvent::SCAN_ERROR, function (\Throwable $e) {
    error_log("Scan error: " . $e->getMessage());
});
```

| `ScanEvent` | Callback signature |
| --- | --- |
| `ScanEvent::SCAN_STARTED` | `fn(string $path): void` |
| `ScanEvent::SCAN_COMPLETED` | `fn(array $results): void` |
| `ScanEvent::SCAN_ERROR` | `fn(\Throwable $error): void` |
| `ScanEvent::FOUND` | `fn(array $info): void` |
| `ScanEvent::FILE_FOUND` | `fn(array $info): void` |
| `ScanEvent::DIRECTORY_FOUND` | `fn(array $info): void` |
| `ScanEvent::SKIPPED` | Defined in enum, not triggered by current scanner implementation |
| `ScanEvent::FILE_SKIPPED` | `fn(array $info): void` |
| `ScanEvent::DIRECTORY_SKIPPED` | `fn(array $info): void` |

Event callbacks run alongside, not instead of, Corvus signal dispatch. Both mechanisms fire for the same lifecycle points.

---

## Pluggable Adapter

```php
$scanner->setAdapter($sftpAdapter);
```

Any `DirectoryFilesystemAdapterInterface` can serve as the scan backend. This allows the same filtering/sorting logic to apply to FTP, SFTP, S3, GCS, or custom remote stores.

---

## Ignore Patterns

`IgnorePatternBuilder` compiles `.ignore` file rules into a single regex that can be applied as a `REGEX` filter.

```php
use Wingman\Explorer\IgnorePatternBuilder;

$pattern = (new IgnorePatternBuilder())->build(["/var/app/.ignore"]);
$scanner->filterBy(ScanFilterType::REGEX, $pattern);
```

---

## Signals Emitted

| Signal | When |
| --- | --- |
| `Signal::SCAN_STARTED` | Before traversal begins |
| `Signal::SCAN_COMPLETED` | After all entries have been yielded |
| `Signal::SCAN_ERROR` | On a traversal error |
| `Signal::FILE_FOUND` | When a file entry is encountered |
| `Signal::DIRECTORY_FOUND` | When a directory entry is encountered |

See [Signals.md](Signals.md) for full payload documentation.

---

## Public API Summary

| Method | Returns | Description |
| --- | --- | --- |
| `setDepth(ScanDepth)` | `static` | Maximum traversal depth |
| `setTarget(ScanTarget)` | `static` | File, directory, or both |
| `setAdapter(DirectoryFilesystemAdapterInterface)` | `static` | Swap backend adapter |
| `addOption(ScanOption)` | `static` | Enable one scan option |
| `setOptions(ScanOption...)` | `static` | Replace scan options |
| `removeOption(ScanOption)` | `static` | Disable one scan option |
| `hasOption(ScanOption)` | `bool` | Check if an option is enabled |
| `filterBy(ScanFilterType, mixed)` | `static` | Append a filter rule |
| `clearFilters()` | `static` | Remove all filter rules |
| `sortBy(ScanSortOption, ScanOrder)` | `static` | Sort result set |
| `setEvent(ScanEvent, callable)` | `static` | Register a lifecycle callback |
| `getDepth()` | `ScanDepth` | Get scan depth |
| `getTarget()` | `ScanTarget` | Get scan target |
| `getFilters()` | `array` | Get configured filters |
| `getSortBy()` | `?ScanSortOption` | Get sort option |
| `getSortOrder()` | `ScanOrder` | Get sort order |
| `getOptions()` | `ScanOption[]` | Get current options |
| `scan(string $path)` | `array` | Execute and return results |

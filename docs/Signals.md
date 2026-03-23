# Explorer — Signals

Explorer emits lifecycle signals through the Corvus bridge at `Wingman\Explorer\Bridge\Corvus\Emitter`.

When Corvus is installed, this class is an alias of `Wingman\Corvus\Emitter`.
When Corvus is not installed, Explorer provides a no-op stub and all emissions are silently ignored.

---

## Registering Listeners

Corvus listeners are registered through `Listener`, not through static methods on `Emitter`.

```php
use Wingman\Corvus\Listener;
use Wingman\Explorer\Enums\Signal;

Listener::create()
    ->when(Signal::FILE_FOUND)
    ->do(function ($execution) {
        $path = $execution->payload[0] ?? null;
        $name = $execution->payload[1] ?? null;
        echo "File found: {$name} at {$path}\n";
    });
```

Listener callbacks receive a Corvus `HandlerExecution` object (`Wingman\Corvus\Objects\HandlerExecution`).

---

## Important Payload Semantics

Explorer emits using named arguments for readability, for example:

```php
$emitter->with(path: $info["path"], name: $info["name"])->emit(...);
```

Corvus `Emitter::with()` stores payload values positionally (`array_values(...)`), so handler payload is an indexed array, not an associative map.

That means handlers must read by index (`$execution->payload[0]`, `$execution->payload[1]`, ...), not by key (`$payload["path"]`).

---

## Signal Reference

| Signal | Value | Payload (positional) | Emitted When |
| --- | --- | --- | --- |
| `Signal::SCAN_STARTED` | `"explorer.scan.started"` | `[0] => root` | A `Scanner` begins traversal |
| `Signal::SCAN_COMPLETED` | `"explorer.scan.completed"` | `[0] => root, [1] => count` | A `Scanner` finishes traversal |
| `Signal::SCAN_ERROR` | `"explorer.scan.error"` | `[0] => root, [1] => errorMessage` | A scan error is caught |
| `Signal::FILE_FOUND` | `"explorer.scan.file.found"` | `[0] => path, [1] => name` | A file entry passes target and filters |
| `Signal::DIRECTORY_FOUND` | `"explorer.scan.directory.found"` | `[0] => path, [1] => name` | A directory entry passes target and filters |
| `Signal::TRANSACTION_COMMITTED` | `"explorer.transaction.committed"` | `[0] => operations` | A `FilesystemTransaction` commits successfully |
| `Signal::TRANSACTION_ROLLED_BACK` | `"explorer.transaction.rolled_back"` | `[0] => operations` | A `FilesystemTransaction` completes rollback |
| `Signal::ROLLBACK_STEP_FAILED` | `"explorer.transaction.rollback.step.failed"` | `[0] => step, [1] => errorMessage` | A rollback callback throws |
| `Signal::ROLLBACK_RESTORE_IMPOSSIBLE` | `"explorer.transaction.rollback.restore.impossible"` | `[0] => path` | A deleted file cannot be restored |
| `Signal::IMPORT_COMPLETED` | `"explorer.import.completed"` | `[0] => path, [1] => importerClass` | An import succeeds |
| `Signal::IMPORT_FALLBACK` | `"explorer.import.fallback"` | `[0] => path` | The fallback importer is used |
| `Signal::EXPORT_COMPLETED` | `"explorer.export.completed"` | `[0] => path, [1] => exporterClass` | An export succeeds |
| `Signal::EXPORT_FALLBACK` | `"explorer.export.fallback"` | `[0] => path` | The fallback exporter is used |

---

## Payload Details

### `Signal::SCAN_STARTED`

| Index | Type | Meaning |
| --- | --- | --- |
| `0` | `string` | Root path passed to `Scanner::scan()` |

### `Signal::SCAN_COMPLETED`

| Index | Type | Meaning |
| --- | --- | --- |
| `0` | `string` | Root path |
| `1` | `int` | Number of results returned by `scan()` |

### `Signal::SCAN_ERROR`

| Index | Type | Meaning |
| --- | --- | --- |
| `0` | `string` | Root path |
| `1` | `string` | Exception message |

### `Signal::FILE_FOUND` / `Signal::DIRECTORY_FOUND`

| Index | Type | Meaning |
| --- | --- | --- |
| `0` | `string` | Full entry path |
| `1` | `string` | Entry base name |

### `Signal::TRANSACTION_COMMITTED` / `Signal::TRANSACTION_ROLLED_BACK`

| Index | Type | Meaning |
| --- | --- | --- |
| `0` | `int` | Operation count |

### `Signal::ROLLBACK_STEP_FAILED`

| Index | Type | Meaning |
| --- | --- | --- |
| `0` | `int` | Reverse-order rollback step index |
| `1` | `string` | Exception message |

### `Signal::ROLLBACK_RESTORE_IMPOSSIBLE`

| Index | Type | Meaning |
| --- | --- | --- |
| `0` | `string` | Path that could not be restored |

### `Signal::IMPORT_COMPLETED` / `Signal::EXPORT_COMPLETED`

| Index | Type | Meaning |
| --- | --- | --- |
| `0` | `string` | Path being imported/exported |
| `1` | `string` | Fully qualified importer/exporter class name |

### `Signal::IMPORT_FALLBACK` / `Signal::EXPORT_FALLBACK`

| Index | Type | Meaning |
| --- | --- | --- |
| `0` | `string` | Path for which fallback was used |

---

## Emitting Custom Signals via the Bridge

Use fluent bridge emission:

```php
use Wingman\Explorer\Bridge\Corvus\Emitter;

Emitter::create()
    ->with("value")
    ->emit("my.custom.event");
```

When Corvus is absent, this call is a no-op.

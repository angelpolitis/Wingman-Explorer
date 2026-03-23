# Explorer — Filesystem Transactions

`FilesystemTransaction` groups multiple filesystem mutations into a single atomic unit. If any operation fails during `commit()`, the transaction rolls back all previously completed steps by reversing each change — restoring original content where applicable. Rollback progress is reported via Corvus signals even when partial failures occur.

---

## Construction

```php
use Wingman\Explorer\FilesystemTransaction;
use Wingman\Explorer\Adapters\LocalAdapter;

$transaction = new FilesystemTransaction(new LocalAdapter());
```

Any `FilesystemAdapterInterface` can be used. For cloud or remote adapters that also implement `WritableFilesystemAdapterInterface`, all operations are proxied through the adapter's write methods.

---

## Available Operations

All operation methods are chainable and return `static`.

```php
$transaction
    ->writeFile("/var/app/config.json",  $newJson)
    ->createFile("/var/app/cache/.lock", "")
    ->copyFile("/var/app/data.db", "/var/app/data.db.bak")
    ->moveFile("/var/app/tmp/upload", "/var/app/media/photo.jpg")
    ->deleteFile("/var/app/old.log")
    ->createDirectory("/var/app/exports");
```

| Method | Signature | Description |
| --- | --- | --- |
| `writeFile` | `(string $path, string $content)` | Write or overwrite a file |
| `createFile` | `(string $path, string $content = "")` | Create a file with initial content |
| `deleteFile` | `(string $path)` | Delete a file |
| `copyFile` | `(string $source, string $dest)` | Copy a file |
| `moveFile` | `(string $source, string $dest)` | Move or rename a file |
| `createDirectory` | `(string $path, bool $recursive = false, int $permissions = 0775)` | Create a directory |

Operations are recorded and not executed until `commit()` is called.

---

## Committing

```php
$transaction->commit();
```

Operations are executed in the order they were added. If any operation throws, the transaction emits `Signal::TRANSACTION_ROLLED_BACK` and begins rollback immediately, processing completed steps in reverse order.

On success, `Signal::TRANSACTION_COMMITTED` is emitted with the list of completed operations.

---

## Rolling Back Manually

```php
$transaction->rollback();       // roll back all recorded operations
$transaction->rollback(3);      // roll back only the last 3 operations
```

Manual rollback does not throw if individual steps fail — each failed step emits `Signal::ROLLBACK_STEP_FAILED`. If a backup cannot be restored (e.g., because the original content was a new file), `Signal::ROLLBACK_RESTORE_IMPOSSIBLE` is emitted for that path.

---

## Signals Emitted During a Transaction

| Signal | When |
| --- | --- |
| `Signal::TRANSACTION_COMMITTED` | All operations completed successfully |
| `Signal::TRANSACTION_ROLLED_BACK` | Rollback was triggered (manual or after failure) |
| `Signal::ROLLBACK_STEP_FAILED` | A single undo step threw an exception |
| `Signal::ROLLBACK_RESTORE_IMPOSSIBLE` | A file backup could not be restored |

See [Signals.md](Signals.md) for payload descriptions.

---

## Full Example

```php
use Wingman\Explorer\FilesystemTransaction;
use Wingman\Explorer\Adapters\LocalAdapter;
use Wingman\Corvus\Listener;
use Wingman\Explorer\Enums\Signal;

Listener::create()
    ->when(Signal::TRANSACTION_COMMITTED)
    ->do(function ($execution) {
        echo "Committed " . $execution->payload[0] . " operation(s).\n";
    });

Listener::create()
    ->when(Signal::ROLLBACK_STEP_FAILED)
    ->do(function ($execution) {
        error_log("Rollback step failed: " . $execution->payload[1]);
    });

$transaction = new FilesystemTransaction(new LocalAdapter());

$transaction->writeFile("/var/app/data/export.csv", $csv)
    ->createDirectory("/var/app/archive")
    ->moveFile("/var/app/data/export.csv", "/var/app/archive/export.csv")
    ->commit();
```

---

## Notes

- `writeFile` takes a backup of pre-existing content before overwriting, so the original can be restored on rollback.
- `createFile` records a compensating delete operation for rollback.
- `deleteFile` takes a backup before deletion; rollback restores the original content.
- `createDirectory` rollback removes the directory only if it was created empty by the transaction.
- `moveFile` and `copyFile` are reversed by deleting the destination and, in the case of `moveFile`, restoring the source from its backup.

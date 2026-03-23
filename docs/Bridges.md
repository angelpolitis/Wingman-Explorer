# Explorer — Bridges

Explorer includes optional bridge classes for Corvus, Cortex, PSR-7, and Synapse.

These bridges are designed to keep Explorer usable even when optional packages are missing.

---

## Corvus Bridge

### Location

- `Wingman\Explorer\Bridge\Corvus\Emitter`

### Behaviour

- If `Wingman\Corvus\Emitter` exists, Explorer aliases to the real Corvus emitter.
- If Corvus is missing, Explorer provides a no-op stub with the same fluent API.

No-op stub methods include:

- `create()`
- `for(object ...$targets)`
- `emit(...)`
- `with(...)`
- `withOnly(...)`
- `if(...)`, `ifAll(...)`, `ifAny(...)`
- `useBus(...)`
- `getPayload()`
- `hasPredicates()`

This means internal signal calls are safe regardless of Corvus installation.

---

## Cortex Bridge

### Locations

- `Wingman\Explorer\Bridge\Cortex\Configuration`
- `Wingman\Explorer\Bridge\Cortex\Attributes\Configurable`

### Behaviour

`Configuration` is an alias-or-stub bridge:

- If `Wingman\Cortex\Configuration` exists, Explorer aliases to it.
- If Cortex is missing, Explorer provides a compatible fallback class.

Supported fallback API:

- `find()`
- `exists()`
- `getAll()`
- `getAllNames()`
- `fromIterable()`
- `hydrate(object $target, array|self $source = [], array $map = [], bool $strict = false)`
- `getName()`
- `captureObject()`
- `restoreObject()`

The fallback `hydrate()` supports Explorer's `#[Configurable]` attribute key mapping for scalar property hydration from flat dot-notation arrays.

---

## PSR-7 Bridge

### Location

- `Wingman\Explorer\Bridge\Psr\Psr7StreamAdapter`

### Purpose

Adapts Explorer `Stream` to `Psr\Http\Message\StreamInterface`.

### Constructor

```php
new Psr7StreamAdapter(Stream $stream)
```

### Implemented interface methods

- `__toString()`
- `close()`
- `detach()`
- `getSize()`
- `tell()`
- `eof()`
- `isSeekable()`
- `seek()`
- `rewind()`
- `isWritable()`
- `write()`
- `isReadable()`
- `read()`
- `getContents()`
- `getMetadata()`

This class is a strict adapter around Explorer `Stream` behaviour and exception semantics.

---

## Synapse Bridge

### Location

- `Wingman\Explorer\Bridge\Synapse\Provider`

### Purpose

Registers Explorer services into Synapse's container.

### Registration

`Provider::register()` binds:

- `ImportManager` as singleton + alias `"importer"`
- `ExportManager` as singleton + alias `"exporter"`
- `Scanner` as transient + alias `"scanner"`

When the container has `DirectoryFilesystemAdapterInterface`, each resolved `Scanner` receives it via `setAdapter(...)`.

### Notes

- `FilesystemTransaction` is intentionally not registered by this provider.
- `Provider` extends `Wingman\Synapse\Provider`.

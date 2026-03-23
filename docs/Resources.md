# Explorer — Resource Types

Explorer models filesystem entries as resource objects. Core abstract bases are `File` and `Directory`, with concrete implementations for local, remote, and virtual resources.

---

## Class Hierarchy

```
File (abstract, implements FileResource)
├── LocalFile
├── RemoteFile
└── VirtualFile (abstract)
    ├── InlineFile
    └── GeneratedFile

Directory (abstract, implements DirectoryResource)
├── LocalDirectory
├── RemoteDirectory
└── VirtualDirectory
```

Additional concrete resource helpers:

- `ProxyFile`
- `TempFile`

---

## `LocalFile`

Concrete local filesystem file resource.

### Constructor

```php
new LocalFile(string $path, ?DirectoryResource $parent = null)
```

### Public Methods

- `append(string $content): static`
- `create(bool $recursive = true, int $permissions = 0755): static`
- `delete(): bool`
- `discard(): static`
- `exists(): bool`
- `getContent(): string`
- `getContentStream(): Stream`
- `getLastModified(): DateTimeImmutable`
- `getMD5(bool $binary = false): string`
- `getMetadata(): array`
- `getParent(): ?LocalDirectory`
- `getSHA1(bool $binary = false): string`
- `getSize(): int`
- `prepend(string $content): static`
- `save(): static`
- `write(string $content): static`
- `writeStream(Stream $stream): static`

Inherited from `File`:

- `at(string $path): static`
- `getBaseName(): string`
- `getExtension(): ?string`
- `getName(): ?string`
- `getParentDirectory(): ?string`
- `getPath(): string`
- `render(): string`

---

## `LocalDirectory`

Concrete local filesystem directory resource.

### Constructor

```php
new LocalDirectory(string $path, bool $reactive = true, int $pollInterval = 2)
```

### Public Methods

- `add(Resource $item, ?string $newName = null, bool $move = false): LocalResource`
- `copy(string $destination, bool $recursive = true): static`
- `create(bool $recursive = true, int $permissions = 0755): static`
- `createFile(string $name): LocalFile`
- `delete(): bool`
- `deleteRecursive(): bool`
- `exists(): bool`
- `flatten(): array`
- `getContents(): array`
- `getDirectories(): array`
- `getDirectory(int|string $indexOrBaseName): ?static`
- `getFile(int|string $key): ?FileResource`
- `getFiles(): array`
- `getLastModified(): DateTimeImmutable`
- `getMetadata(): array`
- `getParent(): ?DirectoryResource`
- `getSize(): int`
- `isEmpty(): bool`
- `move(string $destination): static`
- `refresh(): static`
- `remove(FileResource|int|string $item): static`
- `search(string $pattern, bool $recursive = true): array`

Inherited from `Directory`:

- `at(string $path): static`
- `getBaseName(): string`
- `getName(): string`
- `getParentDirectory(): ?string`
- `getPath(): string`

---

## `RemoteFile`

File backed by a readable adapter.

### Constructor

```php
new RemoteFile(
    string $url,
    ReadableFilesystemAdapterInterface $adapter,
    ?DirectoryResource $parent = null
)
```

### Public Methods

- `exists(): bool`
- `getContent(): string`
- `getContentStream(): Stream`
- `getLastModified(): DateTimeImmutable`
- `getMetadata(): array`
- `getSize(): int`
- `getUri(): URI`

Plus inherited `File` methods (`getPath()`, `getBaseName()`, etc.).

---

## `RemoteDirectory`

Directory backed by a directory-capable adapter.

### Constructor

```php
new RemoteDirectory(
    string $path,
    DirectoryFilesystemAdapterInterface $adapter,
    ?DirectoryResource $parent = null
)
```

### Public Methods

- `add(Resource $resource, ?string $newName = null, bool $move = false): Resource`
- `exists(): bool`
- `getContents(): array`
- `getDirectories(): array`
- `getDirectory(int|string $indexOrBaseName): ?static`
- `getFiles(): array`
- `getLastModified(): DateTimeImmutable`
- `getMetadata(): array`
- `getParent(): ?DirectoryResource`
- `getSize(): int`
- `getUri(): URI`
- `remove(FileResource|int|string $resource): static`
- `search(string $pattern, bool $recursive = true): array`

Plus inherited `Directory` methods (`getPath()`, `getBaseName()`, etc.).

---

## `VirtualFile` (abstract)

Base class for in-memory file resources.

### Public Methods

- `exists(): bool`
- `getContentStream(): Stream`
- `getLastModified(): DateTimeImmutable`
- `getMetadata(): array`
- `getSize(): int` (abstract)
- `write(string $content): static`

Concrete implementations provide `getContent()` and `getSize()`.

---

## `InlineFile`

### Constructor

```php
new InlineFile(string $content, array $metadata = [])
```

### Public Methods

- `getContent(): string`
- `getSize(): int`
- `jsonSerialize(): mixed`
- `__serialize(): array`
- `__unserialize(array $data): void`

---

## `GeneratedFile`

### Constructor

```php
new GeneratedFile(callable $generator, array $metadata = [])
```

### Public Methods

- `getContent(): string`
- `getSize(): int`
- `jsonSerialize(): mixed`
- `__serialize(): array`
- `__unserialize(array $data): void`

---

## `VirtualDirectory`

In-memory directory tree node.

### Constructor

```php
new VirtualDirectory(string $name, array $contents = [], ?DirectoryResource $parent = null)
```

### Public Methods

- `add(Resource $resource, ?string $newName = null, bool $move = false): Resource`
- `adoptFile(FileResource $file, ?string $newName = null, bool $move = false): FileResource`
- `exists(): bool`
- `getBaseName(): string`
- `getContents(): array`
- `getDirectory(int|string $indexOrBaseName): ?static`
- `getLastModified(): DateTimeImmutable`
- `getMetadata(): array`
- `getName(): string`
- `getParent(): ?DirectoryResource`
- `getPath(): string`
- `getSize(): int`
- `jsonSerialize(): mixed`
- `remove(FileResource|int|string $resource): static`
- `search(string $pattern, bool $recursive = true): array`
- `__serialize(): array`
- `__unserialize(array $data): void`

---

## Additional Resource Helpers

### `ProxyFile`

- Constructor: `__construct(string $source)`
- Public methods: `getContent()`, `getMetadata()`, `getSize()`, `getSource()`, `getSourceFile()`

### `TempFile`

- Static constructor: `create(string $targetName, string $type, mixed $content): static`
- Public methods: `delete()`, `jsonSerialize()`, `moveTo(string $targetDirectory): LocalFile`, `toArray()`

---

## Notes

- There are no classes named `TextFile` or `BinaryFile` in Explorer.
- Remote resources expose `getUri()` using `Wingman\Locator\URI`.

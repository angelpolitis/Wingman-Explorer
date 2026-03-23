# Explorer — Virtual Trees

Explorer supports in-memory file trees using virtual resources and a compiler that converts array descriptors into resource graphs.

---

## Node Types

| Class | Description |
| --- | --- |
| `VirtualDirectory` | In-memory directory node |
| `VirtualFile` | Abstract in-memory file base |
| `InlineFile` | Virtual file backed by inline string content |
| `GeneratedFile` | Virtual file backed by a callable generator |
| `ProxyFile` | Virtual-like file that proxies a source path |

---

## `VirtualTreeCompiler`

`VirtualTreeCompiler` exposes one public method:

```php
use Wingman\Explorer\VirtualTreeCompiler;
use Wingman\Explorer\Resources\VirtualDirectory;

$root = VirtualTreeCompiler::compile($definition);
```

Signature:

```php
public static function compile(array $json): VirtualDirectory
```

The compiler recursively validates the descriptor and throws `VirtualTreeException` on invalid structure.

---

## Descriptor Format Used by `VirtualTreeCompiler`

The compiler expects a typed descriptor.

- Directories: `{"type": "directory", "content": {...}}`
- Files: `{"type": "file", "content": "..."}` or `{"type": "file", "source": "..."}`

### Directory shape

```json
{
    "type": "directory",
    "content": {
        "childName": { "type": "file", "content": "..." },
        "subdir": {
            "type": "directory",
            "content": {}
        }
    }
}
```

### File shape

```json
{
    "type": "file",
    "content": "inline text"
}
```

or

```json
{
    "type": "file",
    "source": "/path/to/source.file"
}
```

### Validation rules

- A directory node must have `type = "directory"`.
- A directory `content` must be an object/associative array when present.
- A file node must have `type = "file"`.
- A file cannot have both `source` and `content`.
- A file must have either `source` or `content`.
- Unknown `type` values throw `VirtualTreeException`.

---

## Building Virtual Trees in Code

```php
use Wingman\Explorer\Resources\VirtualDirectory;
use Wingman\Explorer\Resources\InlineFile;
use Wingman\Explorer\Resources\GeneratedFile;

$root = new VirtualDirectory("dist");

$root->add(new InlineFile("<h1>Hello</h1>"), "index.html");

$root->add(new GeneratedFile(
    fn () => json_encode(["version" => "1.0.0"], JSON_PRETTY_PRINT)
), "manifest.json");
```

---

## `VirtualDirectory` API

Key public methods:

- `add(Resource $resource, ?string $newName = null, bool $move = false): Resource`
- `adoptFile(FileResource $file, ?string $newName = null, bool $move = false): FileResource`
- `remove(FileResource|int|string $resource): static`
- `getContents(): array`
- `getDirectory(int|string $indexOrBaseName): ?static`
- `search(string $pattern, bool $recursive = true): array`
- `jsonSerialize(): mixed`
- `getPath(): string`
- `getSize(): int`

Also supports `__serialize()` / `__unserialize()`.

---

## `VirtualFile` API

Shared virtual file behaviour includes:

- `exists(): bool`
- `getContentStream(): Stream`
- `getLastModified(): DateTimeImmutable`
- `getMetadata(): array`
- `write(string $content): static`

Concrete virtual files (`InlineFile`, `GeneratedFile`) provide `getContent()` and `getSize()`.

---

## Serialisation

`VirtualDirectory` implements `JsonSerializable` and can be JSON-encoded.

```php
$json = json_encode($root, JSON_PRETTY_PRINT);
```

You can decode and recompile only if the decoded structure matches the compiler's typed descriptor format.

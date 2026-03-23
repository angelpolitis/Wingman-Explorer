# Explorer — Console Commands

This document describes the Console bridge exposed by Explorer.

All commands use the `explorer:` prefix and map Explorer APIs onto a scriptable CLI surface. Commands are grouped by functional domain and documented in terms of their public behaviour.

## Shared Conventions

### Command Shape

Explorer Console commands are implemented as `Wingman\Console\Command` subclasses decorated with `#[Command(name: "…", description: "…")]`.

Arguments map to `#[Argument(index: n)]`, named values to `#[Option(name: "…")]`, and boolean switches to `#[Flag(name: "…")]`.

### Adapters

Commands that operate on filesystem resources accept `--adapter=local` and default to `local` when omitted.

Remote adapter support is not exposed through the CLI.

### Exit Codes

| Code | Meaning |
| ------ | --------- |
| `0` | Successful execution |
| `1` | Command failure, missing resource, or operation failure |
| `2` | Validation failure for user-supplied input |

### Output

- Commands that emit structured data support `json` output where documented.
- Line-oriented commands emit plain text by default.
- Mutating commands support `--dry-run` or `--quiet` where a preview or silent success path is meaningful.

---

## 1. File Inspection

### `explorer:read`

Read file content through Explorer's full-content, line-based, byte-range, or stream APIs.

| Input | Name | Type | Description |
| ------- | ------ | ------ | ------------- |
| Argument 0 | `path` | string | File to read |
| Option | `--adapter` | string | Filesystem adapter; `local` |
| Option | `--line` | int | Read one one-based line |
| Option | `--lines` | string | Read an inclusive one-based line range in `from:to` form |
| Option | `--range` | string | Read a zero-based byte range in `start:end` form |
| Flag | `--stream` | bool | Stream the full file to stdout |

Notes:

- Defaults to the full file when no explicit mode is selected.
- `--line`, `--lines`, `--range`, and `--stream` are mutually exclusive.
- `--range` follows Explorer's byte semantics: start inclusive, end exclusive.

**Explorer API:** `LocalFile::getContent()`, `LocalFile::getContentStream()`, line-reading traits, range-reading traits

---

### `explorer:search`

Search within a file using either plain-string or regex matching.

| Input | Name | Type | Description |
| ------- | ------ | ------ | ------------- |
| Argument 0 | `path` | string | File to search |
| Argument 1 | `needle` | string | Search term or regex pattern |
| Option | `--adapter` | string | Filesystem adapter; `local` |
| Flag | `--regex` | bool | Interpret the needle as a PCRE pattern |
| Flag | `--all` | bool | Return all matches instead of the first match |
| Flag | `--line-numbers` | bool | Return one-based line numbers |
| Flag | `--offsets` | bool | Return zero-based byte offsets |
| Flag | `--json` | bool | Emit JSON output |

Notes:

- Defaults to first-match behaviour.
- Plain mode prints matched content, line numbers, offsets, or combined line/offset values depending on flags.
- JSON mode emits a structured payload with the effective output mode and result list.

**Explorer API:** string search methods and pattern search methods on `LocalFile`

---

### `explorer:stat`

Display structured metadata for a file or directory resource.

| Input | Name | Type | Description |
| ------- | ------ | ------ | ------------- |
| Argument 0 | `path` | string | File or directory to inspect |
| Option | `--adapter` | string | Filesystem adapter; `local` |
| Flag | `--hashes` | bool | Include MD5 and SHA1 hashes for files |
| Option | `--format` / `-F` | string | Output format: `table` or `json` |

Notes:

- Detects whether the target is a file or directory.
- `--hashes` is valid for files only and returns a validation error for directories.

**Explorer API:** `LocalFile::getMetadata()`, `LocalDirectory::getMetadata()`, `LocalFile::getMD5()`, `LocalFile::getSHA1()`

---

### `explorer:diff`

Compare two files using Explorer's native file diff implementation.

| Input | Name | Type | Description |
| ------- | ------ | ------ | ------------- |
| Argument 0 | `file-a` | string | Base file |
| Argument 1 | `file-b` | string | Comparison file |
| Option | `--adapter` | string | Filesystem adapter; `local` |
| Option | `--max-lines` | int | Maximum in-memory diff line count |
| Option | `--format` / `-F` | string | Output format: `unified` or `json` |

Notes:

- `unified` emits a human-readable hunk rendering.
- `json` emits the raw hunk structure returned by Explorer.

**Explorer API:** `FileDiff::compare()`

---

## 2. Directory Inspection

### `explorer:scan`

Expose Explorer's fluent `Scanner` for filtered, sorted directory inspection.

| Input | Name | Type | Description |
| ------- | ------ | ------ | ------------- |
| Argument 0 | `path` | string | Root directory to scan |
| Option | `--adapter` | string | Filesystem adapter; `local` |
| Option | `--depth` | string | `shallow`, `default`, or `deep` |
| Option | `--target` | string | `any`, `file`, `dir`, `hidden`, `hidden-file`, or `hidden-dir` |
| Option | `--filter-extension` | string | Comma-separated file extensions |
| Option | `--filter-regex` | string | PCRE pattern applied to item names |
| Option | `--filter-name` | string | Exact item name match |
| Option | `--filter-min-size` | int | Minimum file size in bytes |
| Option | `--filter-max-size` | int | Maximum file size in bytes |
| Option | `--sort` | string | `name`, `size`, `modified`, `accessed`, `type`, `owner`, `group`, or `created` |
| Option | `--order` | string | `asc` or `desc` |
| Option | `--format` / `-F` | string | `table`, `paths`, or `json` |
| Flag | `--skip-errors` | bool | Continue when unreadable entries are encountered |
| Flag | `--paths-only` | bool | Return paths rather than full metadata rows |

Notes:

- `--paths-only` and `--format=paths` converge on the same plain path output.
- This is the full-featured scanner surface; use `explorer:find` for simpler name matching.

**Explorer API:** `Scanner`, `ScanDepth`, `ScanTarget`, `ScanFilterType`, `ScanSortOption`, `ScanOrder`

---

### `explorer:find`

Search a directory by pattern without exposing the full scanner surface.

| Input | Name | Type | Description |
| ------- | ------ | ------ | ------------- |
| Argument 0 | `path` | string | Root directory to search |
| Argument 1 | `pattern` | string | Search pattern |
| Option | `--adapter` | string | Filesystem adapter; `local` |
| Flag | `--recursive` | bool | Recurse into subdirectories; enabled by default |
| Flag | `--dirs` | bool | Include directories in the result set |
| Option | `--format` / `-F` | string | `paths` or `json` |

Notes:

- Defaults to recursive search.
- Returns files only unless `--dirs` is supplied.

**Explorer API:** `LocalDirectory::search()`

---

### `explorer:list`

List directory contents through Explorer resources rather than direct shell calls.

| Input | Name | Type | Description |
| ------- | ------ | ------ | ------------- |
| Argument 0 | `path` | string | Directory to inspect; defaults to the current working directory |
| Option | `--adapter` | string | Filesystem adapter; `local` |
| Flag | `--files-only` | bool | Show only files |
| Flag | `--dirs-only` | bool | Show only directories |
| Flag | `--flatten` | bool | Recursively flatten descendant files |
| Option | `--format` / `-F` | string | `table` or `json` |

Notes:

- Defaults to immediate children.
- `--files-only` and `--dirs-only` cannot be combined.
- Flattened output contains files only because that matches Explorer's `flatten()` behaviour.

**Explorer API:** `LocalDirectory::getContents()`, `LocalDirectory::flatten()`

---

### `explorer:dir-diff`

Compare two directories and report added, removed, and modified resources.

| Input | Name | Type | Description |
| ------- | ------ | ------ | ------------- |
| Argument 0 | `dir-a` | string | Base directory |
| Argument 1 | `dir-b` | string | Comparison directory |
| Option | `--adapter` | string | Filesystem adapter; `local` |
| Flag | `--recursive` | bool | Recurse into matching subdirectories; enabled by default |
| Option | `--format` / `-F` | string | `table` or `json` |

Notes:

- Human output groups resources into `Added`, `Removed`, and `Modified` sections.
- JSON output emits grouped arrays with normalised resource metadata.

**Explorer API:** `DirectoryDiff::compare()`

---

## 3. File Mutation

### `explorer:replace`

Perform safe content replacement within a file using string or regex matching.

| Input | Name | Type | Description |
| ------- | ------ | ------ | ------------- |
| Argument 0 | `path` | string | File to update |
| Argument 1 | `search` | string | Search term or regex pattern |
| Argument 2 | `replacement` | string | Replacement value |
| Option | `--adapter` | string | Filesystem adapter; `local` |
| Flag | `--regex` | bool | Use regex replacement |
| Flag | `--first` | bool | Replace only the first match |
| Flag | `--last` | bool | Replace only the last match |
| Flag | `--all` | bool | Replace all matches |
| Flag | `--dry-run` | bool | Show what would change without persisting |
| Flag | `--quiet` | bool | Suppress success output |

Notes:

- `--all` is the default when no scope flag is supplied.
- Exactly one of `--first`, `--last`, or `--all` may be active.
- Dry-run prints a summary without saving the file.

**Explorer API:** string and pattern replacement methods on `LocalFile`, plus `LocalFile::save()`

---

### `explorer:line`

Perform one line-based edit within a file.

| Input | Name | Type | Description |
| ------- | ------ | ------ | ------------- |
| Argument 0 | `path` | string | File to update |
| Option | `--adapter` | string | Filesystem adapter; `local` |
| Option | `--insert` | string | Insert text before a line using `line:text` |
| Option | `--replace` | string | Replace a line using `line:text` |
| Option | `--delete` | string | Delete a line or inclusive line range |
| Option | `--append` | string | Append a new line |
| Option | `--prepend` | string | Prepend a new line |
| Flag | `--dry-run` | bool | Show a preview without saving |
| Flag | `--quiet` | bool | Suppress success output |

Notes:

- Exactly one mutation mode may be used per invocation.
- Line numbers are one-based.
- `--delete` accepts either `n` or `from:to`.

**Explorer API:** line mutation methods on `LocalFile`, plus `LocalFile::save()`

---

## 4. Import / Export

### `explorer:import`

Import a supported file format and emit the imported value for inspection or piping.

| Input | Name | Type | Description |
| ------- | ------ | ------ | ------------- |
| Argument 0 | `path` | string | Source file |
| Option | `--format` | string | Force a specific importer type |
| Flag | `--pretty` | bool | Pretty-print structured output |
| Flag | `--json` | bool | Emit the imported value as JSON |

Notes:

- Initialises `IOManager` on demand.
- Auto-detects the importer when `--format` is omitted.
- `text` and `txt` resolve to Explorer's fallback text importer.

**Explorer API:** `IOManager::init()`, `ImportManager::import()`

---

### `explorer:export`

Serialise structured input to a destination file using Explorer exporters.

| Input | Name | Type | Description |
| ------- | ------ | ------ | ------------- |
| Argument 0 | `destination` | string | Output file path |
| Argument 1 | `data` | string | Inline serialised input, treated as JSON |
| Option | `--format` | string | Force a specific exporter type |
| Flag | `--stdin` | bool | Read the input payload from stdin |
| Flag | `--quiet` | bool | Suppress success output |

Notes:

- Accepts either inline data or stdin, not both.
- Inline and stdin payloads are treated as JSON.
- `text` and `txt` resolve to Explorer's fallback text exporter.

**Explorer API:** `IOManager::init()`, `ExportManager::export()`

---

### `explorer:convert`

Convert one supported file format into another using Explorer's import/export pipeline.

| Input | Name | Type | Description |
| ------- | ------ | ------ | ------------- |
| Argument 0 | `source` | string | Input file |
| Argument 1 | `destination` | string | Output file |
| Option | `--from` | string | Override source format detection |
| Option | `--to` | string | Override destination format inference |
| Flag | `--quiet` | bool | Suppress success output |

Notes:

- Imports from the source, then exports to the destination.
- `--from` resolves through the importer registry plus `text` / `txt` aliases.
- `--to` resolves through the exporter registry plus `text` / `txt` aliases.

**Explorer API:** `IOManager::init()`, `ImportManager::import()`, `ExportManager::export()`

---

## 5. Transactions

### `explorer:transaction`

Execute a transaction plan atomically through `FilesystemTransaction`.

| Input | Name | Type | Description |
| ------- | ------ | ------ | ------------- |
| Argument 0 | `plan-file` | string | JSON transaction plan |
| Flag | `--dry-run` | bool | Validate and print the plan without executing |
| Flag | `--verbose` | bool | Print each step as it executes |
| Option | `--format` | string | `text` or `json` |

Notes:

- Transaction plans use JSON.
- Accepts either a top-level step array or an object containing `steps`.
- Each step must declare `operation` or `op`.
- Supported operations are `createFile`, `createDirectory`, `writeFile`, `copyFile`, `moveFile`, and `deleteFile`.
- `createDirectory` accepts optional `recursive` and `permissions` fields.
- JSON output emits a machine-readable payload with `success`, `dryRun`, `operations`, and `steps`.

**Explorer API:** `FilesystemTransaction`

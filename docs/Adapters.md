# Explorer — Storage Adapters

Explorer decouples filesystem operations from their transport layer through a capability-based interface tier system. Each adapter declares the interfaces it implements, and callers can use `instanceof` to check for optional capabilities at runtime.

---

## Interface Tiers

| Interface | Methods | Notes |
| --- | --- | --- |
| `FilesystemAdapterInterface` | `exists()`, `getMetadata()` | Required by all adapters |
| `ReadableFilesystemAdapterInterface` | + `read()` | Read file content |
| `WritableFilesystemAdapterInterface` | + `create()`, `write()`, `delete()` | Write/delete files |
| `DirectoryFilesystemAdapterInterface` | + `list()`, `createDirectory()` | Enumerate and create directories |
| `MovableFilesystemAdapterInterface` | + `copy()`, `move()`, `rename()` | Copy, move, and rename files |
| `PermissionFilesystemAdapterInterface` | + `chmod()`, `chown()`, `chgrp()` | Unix permission bits |
| `SymlinkFilesystemAdapterInterface` | + `symlink()`, `readlink()`, `isSymlink()` | Symbolic links |
| `CloudAdapterInterface` | + `getProvider()` | Cloud storage contract |
| `PresignedUrlAdapterInterface` | + `getPresignedUrl()` | Time-limited direct URLs |
| `MultipartUploadAdapterInterface` | + `initiateMultipartUpload()`, etc. | Large file chunked uploads |
| `WatchableFilesystemInterface` | + `watch()` | Filesystem event watching |

---

## `getMetadata()` Return Shape

Every adapter returns an array from `getMetadata(string $path, ?array $properties = null)`:

| Key | Type | Description |
| --- | --- | --- |
| `path` | `string` | Full canonical path |
| `name` | `string` | Base name (file name or directory name) |
| `type` | `string` | `"file"` or `"dir"` |
| `size` | `int` | Size in bytes |
| `modified` | `int` | Unix timestamp of last modification |

When `$properties` is provided, only the listed keys are included in the returned array (useful for performance-sensitive metadata requests over the network).

---

## LocalAdapter

No external dependencies. Works on the local filesystem using standard PHP I/O.

```php
use Wingman\Explorer\Adapters\LocalAdapter;

$adapter = new LocalAdapter();
$content = $adapter->read("/var/app/config.json");
$adapter->write("/var/app/config.json", $newContent);
```

**Implements:** `FilesystemAdapterInterface`, `ReadableFilesystemAdapterInterface`, `WritableFilesystemAdapterInterface`, `DirectoryFilesystemAdapterInterface`, `MovableFilesystemAdapterInterface`, `PermissionFilesystemAdapterInterface`, `SymlinkFilesystemAdapterInterface`.

---

## S3Adapter

Requires `aws/aws-sdk-php`. The constructor accepts a configuration array (passed directly to `S3Client`) and a bucket name.

```php
use Wingman\Explorer\Adapters\S3Adapter;

$adapter = new S3Adapter(
    config: [
        "region" => "eu-west-1",
        "version" => "latest",
        "credentials" => ["key" => "...", "secret" => "..."],
    ],
    bucket: "my-bucket"
);
```

**Implements:** `FilesystemAdapterInterface`, `ReadableFilesystemAdapterInterface`, `WritableFilesystemAdapterInterface`, `DirectoryFilesystemAdapterInterface`, `MovableFilesystemAdapterInterface`, `PresignedUrlAdapterInterface`, `MultipartUploadAdapterInterface`.

All SDK-level exceptions are caught and rethrown as `FilesystemException`.

---

## AzureAdapter

Requires `microsoft/azure-storage-blob`. The constructor accepts a connection string and a container name; it calls `BlobRestProxy::createBlobService()` internally.

```php
use Wingman\Explorer\Adapters\AzureAdapter;

$adapter = new AzureAdapter(
    connectionString: "DefaultEndpointsProtocol=https;AccountName=...;AccountKey=...;",
    container: "assets"
);
```

**Provider string:** `"Microsoft Azure"` (returned by `getProvider()`).

**Implements:** `FilesystemAdapterInterface`, `ReadableFilesystemAdapterInterface`, `WritableFilesystemAdapterInterface`, `DirectoryFilesystemAdapterInterface`, `MovableFilesystemAdapterInterface`, `CloudAdapterInterface`.

---

## GCSAdapter

Requires `google/cloud-storage`. The constructor accepts a configuration array (passed directly to `StorageClient`) and a bucket name.

```php
use Wingman\Explorer\Adapters\GCSAdapter;

$adapter = new GCSAdapter(
    config: ["keyFilePath" => "/secrets/gcs-key.json"],
    bucket: "my-gcs-bucket"
);
```

**Provider string:** `"Google Cloud Storage"` (returned by `getProvider()`).

**Implements:** `FilesystemAdapterInterface`, `ReadableFilesystemAdapterInterface`, `WritableFilesystemAdapterInterface`, `DirectoryFilesystemAdapterInterface`, `MovableFilesystemAdapterInterface`, `CloudAdapterInterface`.

**Note on delete:** if the target object does not exist, `delete()` is a no-op and returns `true` — consistent with the interface contract.

---

## FTPAdapter

Requires `ext-ftp`. The constructor accepts a hostname, username, password, port, and optional passive mode flag.

```php
use Wingman\Explorer\Adapters\FTPAdapter;

$adapter = new FTPAdapter(
    host: "ftp.example.com",
    username: "alice",
    password: "secret",
    port: 21,
    passive: true
);
```

**Implements:** `FilesystemAdapterInterface`, `ReadableFilesystemAdapterInterface`, `WritableFilesystemAdapterInterface`, `DirectoryFilesystemAdapterInterface`.

---

## HTTPAdapter

Requires `ext-curl`. Read-only. The constructor accepts an optional timeout in seconds (default: `30`).

```php
use Wingman\Explorer\Adapters\HTTPAdapter;

$adapter = new HTTPAdapter(timeout: 30);
$content = $adapter->read("https://cdn.example.com/assets/logo.png");
```

**Implements:** `FilesystemAdapterInterface`, `ReadableFilesystemAdapterInterface`.

---

## SFTPAdapter

Requires `ext-ssh2`. The constructor accepts a hostname, username, and either a password or a path pair for key-based authentication.

```php
use Wingman\Explorer\Adapters\SFTPAdapter;

$adapter = new SFTPAdapter(
    host: "sftp.example.com",
    username: "deploy",
    password: "secret"
);
```

**Implements:** `FilesystemAdapterInterface`, `ReadableFilesystemAdapterInterface`, `WritableFilesystemAdapterInterface`, `DirectoryFilesystemAdapterInterface`.

---

## Using Adapters with RemoteFile / RemoteDirectory

Any adapter implementing `ReadableFilesystemAdapterInterface` can back a `RemoteFile`. Any adapter implementing `DirectoryFilesystemAdapterInterface` can back a `RemoteDirectory`. Writable operations (add/remove) additionally require `WritableFilesystemAdapterInterface`.

```php
use Wingman\Explorer\Resources\RemoteFile;
use Wingman\Explorer\Resources\RemoteDirectory;

$file = new RemoteFile("assets/header.jpg", $s3Adapter);
$dir  = new RemoteDirectory("data/exports/", $gcsAdapter);

$file->getContent();          // reads from S3
$dir->getFiles();             // lists GCS objects, returns RemoteFile[]
```

---

## Custom Adapters

Implement one or more of the adapter interfaces directly. There is no base class requirement — the adapters communicate entirely through interface contracts.

```php
use Wingman\Explorer\Interfaces\Adapters\ReadableFilesystemAdapterInterface;

class EncryptedDiskAdapter implements ReadableFilesystemAdapterInterface {
    public function exists (string $path) : bool { ... }
    public function getMetadata (string $path, ?array $properties = null) : array { ... }
    public function read (string $path) : string { ... }
}
```

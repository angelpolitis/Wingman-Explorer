# Explorer — Exception Reference

Explorer defines a marker interface `ExplorerException` and 31 concrete exception classes.

Every package-specific exception implements `ExplorerException`, so you can catch all Explorer-level failures via one catch block.

---

## Catching All Explorer Exceptions

```php
use Wingman\Explorer\Exceptions\ExplorerException;

try {
    $file->getContent();
} catch (ExplorerException $e) {
    // any Explorer exception
}
```

---

## Marker Interface

- `ExplorerException` (interface)

---

## Concrete Exceptions

### Directly implementing `ExplorerException`

- `AtomicReplaceException` (extends `RuntimeException`)
- `DecodingException` (extends `RuntimeException`)
- `EncodingException` (extends `RuntimeException`)
- `ExportException` (extends `RuntimeException`)
- `FileDiffException` (extends `RuntimeException`)
- `FilesystemException` (extends `RuntimeException`)
- `HashComputationException` (extends `RuntimeException`)
- `ImportException` (extends `RuntimeException`)
- `InvalidContentTypeException` (extends `InvalidArgumentException`)
- `InvalidDecimalPlacesException` (extends `InvalidArgumentException`)
- `InvalidFileSizeException` (extends `InvalidArgumentException`)
- `InvalidVirtualFolderException` (extends `InvalidArgumentException`)
- `MissingDependencyException` (extends `RuntimeException`)
- `NonexistentLineException` (extends `OutOfBoundsException`)
- `ResourceNotMemberException` (extends `LogicException`)
- `ScannerConfigurationException` (extends `LogicException`)
- `StreamException` (extends `RuntimeException`)
- `TempFileException` (extends `RuntimeException`)
- `UndefinedMapDirectoryException` (extends `RuntimeException`)
- `UnsupportedAdapterOperationException` (extends `RuntimeException`)
- `UnsupportedExportTypeException` (extends `RuntimeException`)
- `UnsupportedImportTypeException` (extends `RuntimeException`)
- `UnsupportedResourceTypeException` (extends `LogicException`)
- `UploadException` (extends `RuntimeException`)
- `VirtualTreeException` (extends `RuntimeException`)

### Derived exceptions

- `ExtensionRejectedUploadException` (extends `UploadException`)
- `FileSizeLimitExceededException` (extends `UploadException`)
- `MimeTypeRejectedUploadException` (extends `UploadException`)
- `NotAStreamException` (extends `StreamException`)
- `StreamNotReadableException` (extends `StreamException`)
- `StreamNotWritableException` (extends `StreamException`)
- `UnseekableStreamException` (extends `StreamException`)

---

## Practical Groupings

### Stream-related

- `StreamException`
- `StreamNotReadableException`
- `StreamNotWritableException`
- `UnseekableStreamException`
- `NotAStreamException`

### Upload-related

- `UploadException`
- `ExtensionRejectedUploadException`
- `MimeTypeRejectedUploadException`
- `FileSizeLimitExceededException`

### IO import/export related

- `ImportException`
- `ExportException`
- `UnsupportedImportTypeException`
- `UnsupportedExportTypeException`
- `DecodingException`
- `EncodingException`

### Validation / argument related

- `InvalidContentTypeException`
- `InvalidDecimalPlacesException`
- `InvalidFileSizeException`
- `InvalidVirtualFolderException`

---

## Notes

- There are no classes named `AdapterException`, `IOException`, `ScanException`, `TransactionException`, or `ConfigurationException` in Explorer.
- There are no classes named `FileNotFoundException`, `DirectoryNotFoundException`, `PermissionDeniedException`, `TransactionCommitException`, or `TransactionRollbackException` in Explorer.
- Transaction failures and rollbacks are represented by `FilesystemException` and by emitted `Signal` payloads.

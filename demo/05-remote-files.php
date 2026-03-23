<?php
    /**
     * Project Name:    Wingman Explorer - Remote Files Demo
     * Created by:      Angel Politis
     * Creation Date:   Mar 22 2026
     * Last Modified:   Mar 22 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Import the following classes to the current scope.
    use Wingman\Explorer\Adapters\HTTPAdapter;
    use Wingman\Explorer\Adapters\LocalAdapter;
    use Wingman\Explorer\Facades\Importer;
    use Wingman\Explorer\Resources\RemoteFile;

    require_once __DIR__ . "/../autoload.php";

    # --------------------------------------------------------------------------------------
    # REMOTE FILE OVERVIEW
    #
    # RemoteFile is an read-only abstraction over any remote (or adapter-backed) resource.
    # All I/O is delegated to a pluggable ReadableFilesystemAdapterInterface, keeping the
    # concrete transport layer completely transparent to the consumer.
    #
    # Built-in adapters:
    #   HTTPAdapter  — reads over HTTP / HTTPS
    #   S3Adapter    — AWS S3 (requires aws/aws-sdk-php)
    #   AzureAdapter — Azure Blob Storage (requires microsoft/azure-storage-blob)
    #   GCSAdapter   — Google Cloud Storage (requires google/cloud-storage)
    #   FTPAdapter   — FTP/FTPS (requires league/flysystem-ftp)
    #   SFTPAdapter  — SFTP (requires league/flysystem-sftp-v3)
    #   LocalAdapter — local filesystem (useful for testing)
    #
    # API:
    #   new RemoteFile(string $url, ReadableFilesystemAdapterInterface $adapter)
    #   ->exists()            — HEAD / stat check via the adapter
    #   ->getContent()        — full content as a string
    #   ->getContentStream()  — content as a readable in-memory Stream
    #   ->getMetadata()       — size, last modified, content-type, etc.
    #   ->getLastModified()   — DateTimeImmutable
    #   ->getSize()           — bytes
    #   ->getUri()            — Wingman\Locator\Objects\URI value object
    # --------------------------------------------------------------------------------------

    $tmpDir = sys_get_temp_dir() . "/wm_explorer_remote_demo_" . uniqid();
    mkdir($tmpDir, 0775, true);

    echo "=== REMOTE FILE VIA LOCAL ADAPTER ===\n\n";

    # --------------------------------------------------------------------------
    # LocalAdapter implements ReadableFilesystemAdapterInterface, so it can be
    # used as the backing adapter for a RemoteFile.  This pattern is useful for
    # testing remote-file code paths without a real network connection.
    # --------------------------------------------------------------------------
    $localAdapter = new LocalAdapter();

    $samplePath = "$tmpDir/data.json";
    file_put_contents($samplePath, json_encode(["service" => "Explorer", "version" => "1.0"], JSON_PRETTY_PRINT));

    $remoteFile = new RemoteFile($samplePath, $localAdapter);

    echo "exists():       " . ($remoteFile->exists() ? "yes" : "no") . "\n";
    echo "getSize():      " . $remoteFile->getSize() . " bytes\n";
    echo "getLastModified(): " . $remoteFile->getLastModified()->format("D d M Y H:i:s") . "\n\n";

    echo "Content via getContent():\n" . $remoteFile->getContent() . "\n\n";

    # --------------------------------------------------------------------------
    # getContentStream() returns an in-memory Stream (php://temp) wrapping the
    # adapter's read() result.  It is seekable and positioned at byte 0.
    # --------------------------------------------------------------------------
    $stream = $remoteFile->getContentStream();
    echo "Content via getContentStream() readAll():\n" . $stream->readAll() . "\n\n";

    echo "=== PIPE REMOTE CONTENT INTO AN IMPORTER ===\n\n";

    # --------------------------------------------------------------------------
    # Because getContentStream() returns a standard Stream, you can pass it
    # directly to any StreamableImporterInterface implementer (e.g. JsonImporter,
    # CsvImporter) without writing the content to a temporary file first.
    # --------------------------------------------------------------------------
    $csvPath = "$tmpDir/users.csv";
    file_put_contents($csvPath, "name,role,active\nAlice,admin,1\nBob,editor,1\nCarol,viewer,0\n");

    $remoteCsv = new RemoteFile($csvPath, $localAdapter);
    $csvStream = $remoteCsv->getContentStream();

    $csvImporter = new \Wingman\Explorer\IO\Importers\CsvImporter();
    $rows = $csvImporter->importStream($csvStream);

    echo "Parsed CSV from remote stream:\n";
    print_r($rows);

    echo "\n=== REMOTE FILE VIA HTTP ADAPTER ===\n\n";

    # --------------------------------------------------------------------------
    # HTTPAdapter performs actual HTTP requests.  Here we target the JSONPlaceholder
    # public test API — skip this block if you have no network access.
    #
    # The adapter only allows http:// and https:// schemes; passing any other
    # scheme throws FilesystemException immediately.
    # --------------------------------------------------------------------------
    if (filter_var(gethostbyname("jsonplaceholder.typicode.com"), FILTER_VALIDATE_IP)) {
        $httpAdapter = new HTTPAdapter(timeout: 10);
        $apiFile = new RemoteFile("https://jsonplaceholder.typicode.com/todos/1", $httpAdapter);

        if ($apiFile->exists()) {
            $content = $apiFile->getContent();
            echo "HTTP response:\n$content\n\n";

            # Import the streamed JSON directly — no temp file written.
            $parsed = Importer::forType("json")->import("https://jsonplaceholder.typicode.com/todos/1");
            echo "Parsed via Importer::forType('json'):\n";
            print_r($parsed);
        }
        else {
            echo "Remote resource not reachable\n\n";
        }
    }
    else {
        echo "(No network access — HTTP demo skipped)\n\n";
    }

    echo "\n=== ADAPTER REFERENCE ===\n\n";

    # --------------------------------------------------------------------------
    # Below are the constructor signatures for the other built-in adapters.
    # None of these are instantiated here as they require external dependencies
    # or infrastructure to be available.
    #
    # S3Adapter:
    #   new S3Adapter(S3Client $client, string $bucket, string $prefix = "")
    #
    # AzureAdapter:
    #   new AzureAdapter(BlobServiceClient $client, string $container, string $prefix = "")
    #
    # GCSAdapter:
    #   new GCSAdapter(StorageClient $client, string $bucket, string $prefix = "")
    #
    # FTPAdapter:
    #   new FTPAdapter(array $config)  — config keys: host, port, username, password, ssl
    #
    # SFTPAdapter:
    #   new SFTPAdapter(array $config) — config keys: host, port, username, password|privateKey
    # --------------------------------------------------------------------------
    echo "See the inline comments above for other adapter constructors.\n";

    # --------------------------------------------------------------------------
    # Cleanup
    # --------------------------------------------------------------------------
    array_map("unlink", glob("$tmpDir/*") ?: []);
    rmdir($tmpDir);
?>
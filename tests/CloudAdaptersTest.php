<?php
    /**
     * Project Name:    Wingman Explorer - Cloud Adapters Tests
     * Created by:      Angel Politis
     * Creation Date:   Mar 22 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Tests namespace.
    namespace Wingman\Explorer\Tests;

    # Import the following classes to the current scope.
    use DateTimeImmutable;
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Attributes\Requires;
    use Wingman\Argus\Test;
    use Wingman\Explorer\Adapters\AzureAdapter;
    use Wingman\Explorer\Adapters\GCSAdapter;
    use Wingman\Explorer\Adapters\S3Adapter;
    use Wingman\Explorer\Exceptions\FilesystemException;

    /**
     * A testable subclass of S3Adapter that accepts a pre-built mock client
     * and bypasses the SDK availability check in the parent constructor.
     */
    class TestableS3Adapter extends S3Adapter {
        /**
         * Creates a testable S3 adapter with an injected mock client.
         * @param object $mockClient The mock S3 client.
         * @param string $bucket The bucket name.
         */
        public function __construct (object $mockClient, string $bucket = "test-bucket") {
            $this->client = $mockClient;
            $this->bucket = $bucket;
        }
    }

    /**
     * A testable subclass of AzureAdapter that accepts a pre-built mock client
     * and bypasses the SDK availability check and static factory call.
     */
    class TestableAzureAdapter extends AzureAdapter {
        /**
         * Creates a testable Azure adapter with an injected mock client.
         * @param object $mockClient The mock BlobRestProxy client.
         * @param string $container The container name.
         */
        public function __construct (object $mockClient, string $container = "test-container") {
            $this->client = $mockClient;
            $this->container = $container;
        }
    }

    /**
     * A testable subclass of GCSAdapter that accepts a pre-built mock bucket object
     * and bypasses the SDK availability check and StorageClient instantiation.
     */
    class TestableGCSAdapter extends GCSAdapter {
        /**
         * Creates a testable GCS adapter with an injected mock bucket.
         * @param object $mockBucket The mock Google Cloud Storage Bucket.
         */
        public function __construct (object $mockBucket) {
            $this->bucket = $mockBucket;
        }
    }

    /**
     * Tests for the S3Adapter, AzureAdapter, and GCSAdapter cloud adapters.
     *
     * All tests use injected mock SDK clients to avoid requiring real cloud
     * credentials. The mocks verify that the adapter delegates calls correctly
     * to the underlying SDK and that return values are mapped as expected.
     *
     * Error-path tests that depend on SDK-specific exception types (AwsException,
     * ServiceException, GoogleException) require the corresponding SDK to be
     * installed and are outside the scope of these unit tests.
     */
    class CloudAdaptersTest extends Test {
        // ─── S3 Adapter ───────────────────────────────────────────────────────

        #[Group("S3Adapter")]
        #[Define(name: "Provider — Returns 'Amazon S3'", description: "getProvider() returns the 'Amazon S3' string.")]
        public function testS3GetProvider () : void {
            $adapter = new TestableS3Adapter(new class {});
            $this->assertEquals("Amazon S3", $adapter->getProvider());
        }

        #[Group("S3Adapter")]
        #[Define(name: "Write — Delegates to putObject", description: "write() calls putObject on the S3 client with the correct bucket, key, and body.")]
        public function testS3WriteDelegatesToPutObject () : void {
            $calls = [];
            $mock = new class($calls) {
                public function __construct (private array &$calls) {}
                public function putObject (array $args) : void {
                    $this->calls[] = $args;
                }
            };

            $adapter = new TestableS3Adapter($mock, "my-bucket");
            $adapter->write("photos/cat.jpg", "binary-content");

            $this->assertCount(1, $calls);
            $this->assertEquals("my-bucket", $calls[0]["Bucket"]);
            $this->assertEquals("photos/cat.jpg", $calls[0]["Key"]);
            $this->assertEquals("binary-content", $calls[0]["Body"]);
        }

        #[Group("S3Adapter")]
        #[Define(name: "Write — Strips Leading Slash from Key", description: "write() removes a leading slash from the path before passing it to putObject.")]
        public function testS3WriteStripsLeadingSlash () : void {
            $calls = [];
            $mock = new class($calls) {
                public function __construct (private array &$calls) {}
                public function putObject (array $args) : void { $this->calls[] = $args; }
            };

            (new TestableS3Adapter($mock))->write("/leading/slash.txt", "data");

            $this->assertEquals("leading/slash.txt", $calls[0]["Key"]);
        }

        #[Group("S3Adapter")]
        #[Define(name: "Read — Returns Object Body as String", description: "read() calls getObject and returns the Body field cast to string.")]
        public function testS3ReadReturnsObjectBody () : void {
            $mock = new class {
                public function getObject (array $args) : array {
                    return ["Body" => "hello world"];
                }
            };

            $result = (new TestableS3Adapter($mock))->read("docs/readme.txt");
            $this->assertEquals("hello world", $result);
        }

        #[Group("S3Adapter")]
        #[Define(name: "Exists — Returns True When Object Found", description: "exists() returns true when doesObjectExist returns true.")]
        public function testS3ExistsReturnsTrueWhenObjectFound () : void {
            $mock = new class {
                public function doesObjectExist (string $bucket, string $key) : bool {
                    return true;
                }
            };

            $this->assertTrue((new TestableS3Adapter($mock))->exists("file.txt"));
        }

        #[Group("S3Adapter")]
        #[Define(name: "Exists — Returns False When Object Missing", description: "exists() returns false when doesObjectExist returns false.")]
        public function testS3ExistsReturnsFalseWhenObjectMissing () : void {
            $mock = new class {
                public function doesObjectExist (string $bucket, string $key) : bool {
                    return false;
                }
            };

            $this->assertFalse((new TestableS3Adapter($mock))->exists("missing.txt"));
        }

        #[Group("S3Adapter")]
        #[Define(name: "Delete — Delegates to deleteObject", description: "delete() calls deleteObject with the correct bucket and key.")]
        public function testS3DeleteDelegatesToDeleteObject () : void {
            $calls = [];
            $mock = new class($calls) {
                public function __construct (private array &$calls) {}
                public function deleteObject (array $args) : void { $this->calls[] = $args; }
            };

            (new TestableS3Adapter($mock, "bucket-x"))->delete("archive/old.zip");

            $this->assertEquals("bucket-x", $calls[0]["Bucket"]);
            $this->assertEquals("archive/old.zip", $calls[0]["Key"]);
        }

        #[Group("S3Adapter")]
        #[Define(name: "Copy — Delegates to copyObject", description: "copy() calls copyObject with the correct CopySource and Key.")]
        public function testS3CopyDelegatesToCopyObject () : void {
            $calls = [];
            $mock = new class($calls) {
                public function __construct (private array &$calls) {}
                public function copyObject (array $args) : void { $this->calls[] = $args; }
            };

            (new TestableS3Adapter($mock, "bkt"))->copy("src/a.txt", "dst/b.txt");

            $this->assertStringContains("src/a.txt", $calls[0]["CopySource"]);
            $this->assertEquals("dst/b.txt", $calls[0]["Key"]);
        }

        #[Group("S3Adapter")]
        #[Define(name: "List — Yields Object Keys from Contents", description: "list() yields each object key from the Contents page.")]
        public function testS3ListYieldsObjectKeys () : void {
            $mock = new class {
                private int $calls = 0;
                public function listObjectsV2 (array $params) : array {
                    $this->calls++;
                    return [
                        "Contents" => [
                            ["Key" => "dir/a.txt"],
                            ["Key" => "dir/b.txt"],
                        ],
                        "CommonPrefixes" => [],
                        "IsTruncated" => false,
                    ];
                }
            };

            $results = iterator_to_array((new TestableS3Adapter($mock))->list("dir/"));
            $this->assertContains("dir/a.txt", $results);
            $this->assertContains("dir/b.txt", $results);
        }

        #[Group("S3Adapter")]
        #[Define(name: "List — Skips Prefix Placeholder Entry", description: "list() does not yield the prefix entry itself (directory placeholder key).")]
        public function testS3ListSkipsPrefixPlaceholder () : void {
            $mock = new class {
                public function listObjectsV2 (array $params) : array {
                    return [
                        "Contents" => [["Key" => "dir/"], ["Key" => "dir/file.txt"]],
                        "CommonPrefixes" => [],
                        "IsTruncated" => false,
                    ];
                }
            };

            $results = iterator_to_array((new TestableS3Adapter($mock))->list("dir/"));
            $this->assertNotContains("dir/", $results);
            $this->assertContains("dir/file.txt", $results);
        }

        #[Group("S3Adapter")]
        #[Define(name: "GetMetadata — Returns Expected Keys", description: "getMetadata() returns path, name, type, size, and modified keys.")]
        public function testS3GetMetadataReturnsExpectedKeys () : void {
            $modified = new DateTimeImmutable("2025-12-01 10:00:00");
            $mock = new class($modified) {
                public function __construct (private DateTimeImmutable $modified) {}
                public function headObject (array $args) : array {
                    return ["ContentLength" => 512, "LastModified" => $this->modified];
                }
            };

            $meta = (new TestableS3Adapter($mock))->getMetadata("docs/readme.txt");

            $this->assertEquals("docs/readme.txt", $meta["path"]);
            $this->assertEquals("readme.txt", $meta["name"]);
            $this->assertEquals("file", $meta["type"]);
            $this->assertEquals(512, $meta["size"]);
            $this->assertEquals($modified->getTimestamp(), $meta["modified"]);
        }

        // ─── Azure Adapter ─────────────────────────────────────────────────────

        #[Group("AzureAdapter")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\BlobRestProxy", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\Models\\ListBlobsOptions", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\Models\\CreateBlockBlobOptions", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Define(name: "Provider — Returns 'Microsoft Azure'", description: "getProvider() returns the 'Microsoft Azure' string.")]
        public function testAzureGetProvider () : void {
            $adapter = new TestableAzureAdapter(new class {});
            $this->assertEquals("Microsoft Azure", $adapter->getProvider());
        }

        #[Group("AzureAdapter")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\BlobRestProxy", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\Models\\ListBlobsOptions", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\Models\\CreateBlockBlobOptions", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Define(name: "Write — Delegates to createBlockBlob", description: "write() calls createBlockBlob on the Azure client with the correct container, key, and body.")]
        public function testAzureWriteDelegatesToCreateBlockBlob () : void {
            $calls = [];
            $mock = new class($calls) {
                public function __construct (private array &$calls) {}
                public function createBlockBlob (string $container, string $blob, string $content, mixed $options = null) : void {
                    $this->calls[] = compact("container", "blob", "content");
                }
            };

            (new TestableAzureAdapter($mock, "my-container"))->write("uploads/image.png", "img-data");

            $this->assertCount(1, $calls);
            $this->assertEquals("my-container", $calls[0]["container"]);
            $this->assertEquals("uploads/image.png", $calls[0]["blob"]);
            $this->assertEquals("img-data", $calls[0]["content"]);
        }

        #[Group("AzureAdapter")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\BlobRestProxy", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\Models\\ListBlobsOptions", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\Models\\CreateBlockBlobOptions", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Define(name: "Read — Returns Stream Content", description: "read() calls getBlob and returns the content from the blob's stream.")]
        public function testAzureReadReturnsStreamContent () : void {
            $mock = new class {
                public function getBlob (string $container, string $blob) : object {
                    $stream = fopen("php://memory", "r+");
                    fwrite($stream, "azure file content");
                    rewind($stream);

                    return new class($stream) {
                        public function __construct (private mixed $stream) {}
                        public function getContentStream () : mixed { return $this->stream; }
                    };
                }
            };

            $result = (new TestableAzureAdapter($mock))->read("files/data.txt");
            $this->assertEquals("azure file content", $result);
        }

        #[Group("AzureAdapter")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\BlobRestProxy", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\Models\\ListBlobsOptions", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\Models\\CreateBlockBlobOptions", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Define(name: "Exists — Returns True When Blob Found", description: "exists() returns true when getBlobProperties succeeds.")]
        public function testAzureExistsReturnsTrueWhenBlobFound () : void {
            $mock = new class {
                public function getBlobProperties (string $container, string $blob) : object {
                    return new \stdClass();
                }
            };

            $this->assertTrue((new TestableAzureAdapter($mock))->exists("file.bin"));
        }

        #[Group("AzureAdapter")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\BlobRestProxy", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\Models\\ListBlobsOptions", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\Models\\CreateBlockBlobOptions", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Define(name: "Delete — Delegates to deleteBlob", description: "delete() calls deleteBlob on the Azure client.")]
        public function testAzureDeleteDelegatesToDeleteBlob () : void {
            $calls = [];
            $mock = new class($calls) {
                public function __construct (private array &$calls) {}
                public function deleteBlob (string $container, string $blob) : void {
                    $this->calls[] = compact("container", "blob");
                }
            };

            (new TestableAzureAdapter($mock, "ctr"))->delete("path/file.csv");

            $this->assertEquals("ctr", $calls[0]["container"]);
            $this->assertEquals("path/file.csv", $calls[0]["blob"]);
        }

        #[Group("AzureAdapter")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\BlobRestProxy", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\Models\\ListBlobsOptions", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\Models\\CreateBlockBlobOptions", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Define(name: "Copy — Gets Blob URL Then Copies", description: "copy() calls getBlobUrl to get the source URL, then copyBlob.")]
        public function testAzureCopyGetsUrlThenCopies () : void {
            $calls = [];
            $mock = new class($calls) {
                public function __construct (private array &$calls) {}
                public function getBlobUrl (string $container, string $blob) : string {
                    return "https://storage.test/{$container}/{$blob}";
                }
                public function copyBlob (string $container, string $dst, string $url) : void {
                    $this->calls[] = compact("container", "dst", "url");
                }
            };

            (new TestableAzureAdapter($mock, "ctr"))->copy("src.txt", "dst.txt");

            $this->assertEquals("ctr", $calls[0]["container"]);
            $this->assertEquals("dst.txt", $calls[0]["dst"]);
            $this->assertStringContains("src.txt", $calls[0]["url"]);
        }

        #[Group("AzureAdapter")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\BlobRestProxy", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\Models\\ListBlobsOptions", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\Models\\CreateBlockBlobOptions", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Define(name: "List — Yields Blob Names", description: "list() yields each blob name from a single-page result.")]
        public function testAzureListYieldsBlobNames () : void {
            $mock = new class {
                public function listBlobs (string $container, object $options) : object {
                    $blobs = [
                        new class { public function getName () : string { return "prefix/a.json"; } },
                        new class { public function getName () : string { return "prefix/b.json"; } },
                    ];

                    return new class($blobs) {
                        public function __construct (private array $blobs) {}
                        public function getBlobs () : array { return $this->blobs; }
                        public function getBlobPrefixes () : array { return []; }
                        public function getNextMarker () : ?string { return null; }
                    };
                }
            };

            $results = iterator_to_array((new TestableAzureAdapter($mock))->list("prefix/"));
            $this->assertContains("prefix/a.json", $results);
            $this->assertContains("prefix/b.json", $results);
        }

        #[Group("AzureAdapter")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\BlobRestProxy", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\Models\\ListBlobsOptions", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Requires(type: "class", value: "MicrosoftAzure\\Storage\\Blob\\Models\\CreateBlockBlobOptions", message: "Azure SDK for PHP is required for Azure adapter tests.")]
        #[Define(name: "GetMetadata — Returns Expected Keys", description: "getMetadata() returns path, name, type, size, and modified keys from blob properties.")]
        public function testAzureGetMetadataReturnsExpectedKeys () : void {
            $modified = new DateTimeImmutable("2025-06-15 08:30:00");
            $mock = new class($modified) {
                public function __construct (private DateTimeImmutable $modified) {}
                public function getBlobProperties (string $container, string $blob) : object {
                    $instance = $this;
                    return new class($instance->modified) {
                        public function __construct (private DateTimeImmutable $modified) {}
                        public function getContentLength () : int { return 1024; }
                        public function getLastModified () : DateTimeImmutable { return $this->modified; }
                    };
                }
            };

            $meta = (new TestableAzureAdapter($mock))->getMetadata("uploads/photo.jpg");

            $this->assertEquals("uploads/photo.jpg", $meta["path"]);
            $this->assertEquals("photo.jpg", $meta["name"]);
            $this->assertEquals("file", $meta["type"]);
            $this->assertEquals(1024, $meta["size"]);
            $this->assertEquals($modified->getTimestamp(), $meta["modified"]);
        }

        // ─── GCS Adapter ───────────────────────────────────────────────────────

        #[Group("GCSAdapter")]
        #[Define(name: "Provider — Returns 'Google Cloud Storage'", description: "getProvider() returns the 'Google Cloud Storage' string.")]
        public function testGCSGetProvider () : void {
            $adapter = new TestableGCSAdapter(new class {});
            $this->assertEquals("Google Cloud Storage", $adapter->getProvider());
        }

        #[Group("GCSAdapter")]
        #[Define(name: "Write — Delegates to bucket upload", description: "write() calls bucket->upload() with the content and the correct object name.")]
        public function testGCSWriteDelegatesToBucketUpload () : void {
            $calls = [];
            $mockBucket = new class($calls) {
                public function __construct (private array &$calls) {}
                public function upload (string $content, array $options = []) : void {
                    $this->calls[] = compact("content", "options");
                }
            };

            (new TestableGCSAdapter($mockBucket))->write("media/logo.png", "png-data");

            $this->assertCount(1, $calls);
            $this->assertEquals("png-data", $calls[0]["content"]);
            $this->assertEquals("media/logo.png", $calls[0]["options"]["name"]);
        }

        #[Group("GCSAdapter")]
        #[Define(name: "Read — Returns downloadAsString Result", description: "read() calls bucket->object()->downloadAsString() and returns the result.")]
        public function testGCSReadReturnsBucketObjectContent () : void {
            $mockBucket = new class {
                public function object (string $name) : object {
                    return new class {
                        public function exists () : bool { return true; }
                        public function downloadAsString () : string { return "gcs file content"; }
                    };
                }
            };

            $result = (new TestableGCSAdapter($mockBucket))->read("data/report.txt");
            $this->assertEquals("gcs file content", $result);
        }

        #[Group("GCSAdapter")]
        #[Define(name: "Read — Throws FilesystemException When Object Missing", description: "read() throws FilesystemException when the object does not exist.")]
        public function testGCSReadThrowsWhenObjectMissing () : void {
            $mockBucket = new class {
                public function object (string $name) : object {
                    return new class {
                        public function exists () : bool { return false; }
                    };
                }
            };

            $this->assertThrows(FilesystemException::class, function () use ($mockBucket) {
                (new TestableGCSAdapter($mockBucket))->read("missing.txt");
            });
        }

        #[Group("GCSAdapter")]
        #[Define(name: "Exists — Returns True When Object Found", description: "exists() returns true when bucket->object()->exists() returns true.")]
        public function testGCSExistsReturnsTrueWhenObjectFound () : void {
            $mockBucket = new class {
                public function object (string $name) : object {
                    return new class { public function exists () : bool { return true; } };
                }
            };

            $this->assertTrue((new TestableGCSAdapter($mockBucket))->exists("file.txt"));
        }

        #[Group("GCSAdapter")]
        #[Define(name: "Exists — Returns False When Object Missing", description: "exists() returns false when bucket->object()->exists() returns false.")]
        public function testGCSExistsReturnsFalseWhenObjectMissing () : void {
            $mockBucket = new class {
                public function object (string $name) : object {
                    return new class { public function exists () : bool { return false; } };
                }
            };

            $this->assertFalse((new TestableGCSAdapter($mockBucket))->exists("missing.txt"));
        }

        #[Group("GCSAdapter")]
        #[Define(name: "Delete — Returns True When Object Deleted", description: "delete() calls object->delete() and returns true when the object existed.")]
        public function testGCSDeleteReturnsTrueWhenObjectDeleted () : void {
            $deleted = false;
            $mockBucket = new class($deleted) {
                public function __construct (private bool &$deleted) {}
                public function object (string $name) : object {
                    return new class($this->deleted) {
                        public function __construct (private bool &$deleted) {}
                        public function exists () : bool { return true; }
                        public function delete () : void { $this->deleted = true; }
                    };
                }
            };

            $result = (new TestableGCSAdapter($mockBucket))->delete("old.log");

            $this->assertTrue($result);
            $this->assertTrue($deleted);
        }

        #[Group("GCSAdapter")]
        #[Define(name: "Delete — Returns True When Object Already Missing", description: "delete() returns true without error when the object does not exist.")]
        public function testGCSDeleteReturnsTrueWhenObjectAlreadyMissing () : void {
            $mockBucket = new class {
                public function object (string $name) : object {
                    return new class { public function exists () : bool { return false; } };
                }
            };

            $this->assertTrue((new TestableGCSAdapter($mockBucket))->delete("phantom.txt"));
        }

        #[Group("GCSAdapter")]
        #[Define(name: "Copy — Calls Object copy()", description: "copy() calls object->copy() on the source object with the correct destination.")]
        public function testGCSCopyCallsObjectCopy () : void {
            $copyArgs = [];
            $mockBucket = new class($copyArgs) {
                public function __construct (private array &$copyArgs) {}
                public function object (string $name) : object {
                    return new class($this->copyArgs, $this) {
                        public function __construct (private array &$copyArgs, private object $bucket) {}
                        public function exists () : bool { return true; }
                        public function copy (object $bucket, array $args) : void {
                            $this->copyArgs[] = $args;
                        }
                    };
                }
            };

            (new TestableGCSAdapter($mockBucket))->copy("source.txt", "destination.txt");

            $this->assertCount(1, $copyArgs);
            $this->assertEquals("destination.txt", $copyArgs[0]["name"]);
        }

        #[Group("GCSAdapter")]
        #[Define(name: "List — Yields Object Names from Flat Listing", description: "list() yields each non-prefix object name from bucket objects().")]
        public function testGCSListYieldsObjectNames () : void {
            $mockObjects = [
                new class { public function name () : string { return "dir/a.csv"; } },
                new class { public function name () : string { return "dir/b.csv"; } },
            ];

            $mockBucket = new class($mockObjects) {
                public function __construct (private array $objects) {}
                public function objects (array $params = []) : array { return $this->objects; }
            };

            $results = iterator_to_array((new TestableGCSAdapter($mockBucket))->list("dir/"));
            $this->assertContains("dir/a.csv", $results);
            $this->assertContains("dir/b.csv", $results);
        }

        #[Group("GCSAdapter")]
        #[Define(name: "GetMetadata — Returns Expected Keys", description: "getMetadata() returns path, name, type, size, and modified keys from object info.")]
        public function testGCSGetMetadataReturnsExpectedKeys () : void {
            $mockBucket = new class {
                public function object (string $name) : object {
                    return new class {
                        public function exists () : bool { return true; }
                        public function info () : array {
                            return ["size" => 2048, "updated" => "2025-09-01T12:00:00Z"];
                        }
                    };
                }
            };

            $meta = (new TestableGCSAdapter($mockBucket))->getMetadata("reports/q3.pdf");

            $this->assertEquals("reports/q3.pdf", $meta["path"]);
            $this->assertEquals("q3.pdf", $meta["name"]);
            $this->assertEquals("file", $meta["type"]);
            $this->assertEquals(2048, $meta["size"]);
            $this->assertType("integer", $meta["modified"]);
        }
    }
?>
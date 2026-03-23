<?php
    /**
     * Project Name:    Wingman Explorer - Remote Resources Tests
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
    use Wingman\Argus\Test;
    use Wingman\Explorer\Exceptions\FilesystemException;
    use Wingman\Explorer\IO\Stream;
    use Wingman\Explorer\Interfaces\Adapters\DirectoryFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\ReadableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\WritableFilesystemAdapterInterface;
    use Wingman\Explorer\Resources\RemoteDirectory;
    use Wingman\Explorer\Resources\RemoteFile;
    use Wingman\Locator\Objects\URI;

    /**
     * An in-memory mock adapter for testing remote resource objects.
     *
     * Implements all four interface tiers so that tests can exercise both
     * read-only and writable code paths. Files are stored in an array keyed
     * by path. Directories are stored as an array of path strings with a
     * trailing slash. The <code>getMetadata</code> method infers the type
     * from the trailing slash: paths ending in <code>/</code> are typed
     * as <code>dir</code>, all others as <code>file</code>.
     */
    class InMemoryRemoteAdapter implements DirectoryFilesystemAdapterInterface, ReadableFilesystemAdapterInterface, WritableFilesystemAdapterInterface {
        /**
         * The in-memory file storage, keyed by path and containing the file content.
         * @var array<string, string>
         */
        private array $files = [];

        /**
         * The in-memory directory paths (each path has a trailing slash).
         * @var string[]
         */
        private array $directories = [];

        /**
         * Records of all delete() calls made on the adapter.
         * @var string[]
         */
        public array $deletedPaths = [];

        /**
         * Records of all write() calls made on the adapter.
         * @var array<array{path: string, content: string}>
         */
        public array $writtenFiles = [];

        /**
         * Populates the adapter with a file.
         * @param string $path The file path.
         * @param string $content The file content.
         * @return static The adapter.
         */
        public function withFile (string $path, string $content = "") : static {
            $this->files[$path] = $content;
            return $this;
        }

        /**
         * Populates the adapter with a directory.
         * @param string $path The directory path (trailing slash is added if absent).
         * @return static The adapter.
         */
        public function withDirectory (string $path) : static {
            $this->directories[] = rtrim($path, "/") . "/";
            return $this;
        }

        /**
         * Checks whether a file or directory exists.
         * @param string $path The path to check.
         * @return bool Whether the path exists.
         */
        public function exists (string $path) : bool {
            return isset($this->files[$path]) || in_array(rtrim($path, "/") . "/", $this->directories, true);
        }

        /**
         * Returns metadata for a file or directory.
         * @param string $path The path.
         * @param array|null $properties The properties to include (null returns all).
         * @return array The metadata.
         */
        public function getMetadata (string $path, ?array $properties = null) : array {
            $trailing = str_ends_with($path, "/");
            $meta = [
                "path" => $path,
                "name" => basename(rtrim($path, "/")),
                "type" => $trailing ? "dir" : "file",
                "size" => strlen($this->files[$path] ?? ""),
                "modified" => 1_700_000_000,
            ];

            if ($properties === null) return $meta;

            return array_intersect_key($meta, array_flip($properties));
        }

        /**
         * Reads a file.
         * @param string $path The file path.
         * @return string The file content.
         */
        public function read (string $path) : string {
            return $this->files[$path] ?? "";
        }

        /**
         * Creates a directory (records the path and returns true).
         * @param string $path The directory path.
         * @param bool $recursive Whether to create parent directories.
         * @param int $permissions The directory permissions.
         * @return bool Whether the directory was created.
         */
        public function createDirectory (string $path, bool $recursive = false, int $permissions = 0775) : bool {
            $this->directories[] = rtrim($path, "/") . "/";
            return true;
        }

        /**
         * Lists direct children of a directory.
         * @param string $path The directory path (trailing slash required to match child paths).
         * @return iterable<string> The child paths.
         */
        public function list (string $path) : iterable {
            $prefix = rtrim($path, "/") . "/";
            $results = [];

            foreach (array_keys($this->files) as $filePath) {
                if (str_starts_with($filePath, $prefix)) {
                    $relative = substr($filePath, strlen($prefix));

                    if ($relative !== "" && !str_contains($relative, "/")) {
                        $results[] = $filePath;
                    }
                }
            }

            foreach ($this->directories as $dirPath) {
                if ($dirPath === $prefix) continue;

                if (str_starts_with($dirPath, $prefix)) {
                    $relative = substr($dirPath, strlen($prefix));
                    $relative = rtrim($relative, "/");

                    if (!str_contains($relative, "/")) {
                        $results[] = $dirPath;
                    }
                }
            }

            return $results;
        }

        /**
         * Creates an empty file.
         * @param string $path The file path.
         * @param string $content The initial content.
         */
        public function create (string $path, string $content = "") : void {
            $this->files[$path] = $content;
        }

        /**
         * Deletes a file.
         * @param string $path The file path.
         * @return bool Whether the file existed and was deleted.
         */
        public function delete (string $path) : bool {
            $this->deletedPaths[] = $path;

            if (isset($this->files[$path])) {
                unset($this->files[$path]);
                return true;
            }

            return false;
        }

        /**
         * Writes content to a file.
         * @param string $path The file path.
         * @param string $content The content to write.
         */
        public function write (string $path, string $content) : void {
            $this->writtenFiles[] = compact("path", "content");
            $this->files[$path] = $content;
        }
    }

    /**
     * A read-only adapter (no writable interface) for testing that RemoteDirectory
     * correctly rejects writes when the adapter cannot perform them.
     */
    class ReadOnlyRemoteAdapter implements DirectoryFilesystemAdapterInterface, ReadableFilesystemAdapterInterface {
        /**
         * Checks whether a path exists (always returns false).
         * @param string $path The path.
         * @return bool Whether the path exists.
         */
        public function exists (string $path) : bool { return false; }

        /**
         * Returns empty metadata.
         * @param string $path The path.
         * @param array|null $properties The properties to include.
         * @return array The metadata.
         */
        public function getMetadata (string $path, ?array $properties = null) : array { return []; }

        /**
         * Returns an empty string.
         * @param string $path The path.
         * @return string The file content.
         */
        public function read (string $path) : string { return ""; }

        /**
         * Creates a directory (no-op).
         * @param string $path The directory path.
         * @param bool $recursive Whether to create parent directories.
         * @param int $permissions The directory permissions.
         * @return bool Whether the directory was created.
         */
        public function createDirectory (string $path, bool $recursive = false, int $permissions = 0775) : bool { return false; }

        /**
         * Lists an empty directory.
         * @param string $path The directory path.
         * @return iterable<string> The child paths.
         */
        public function list (string $path) : iterable { return []; }
    }

    /**
     * Tests for the RemoteFile and RemoteDirectory resource classes.
     *
     * All file system I/O is performed through InMemoryRemoteAdapter — no real
     * files are touched during these tests. Each test constructs resources with
     * the minimum adapter state required to exercise the behaviour under test.
     */
    class RemoteResourcesTest extends Test {
        // ─── RemoteFile ───────────────────────────────────────────────────────

        #[Group("RemoteFile")]
        #[Define(name: "Exists — Returns True When Adapter Reports File Present", description: "exists() delegates to the adapter and returns true when the file is known.")]
        public function testRemoteFileExistsReturnsTrueWhenAdapterReportsFilePresent () : void {
            $adapter = (new InMemoryRemoteAdapter())->withFile("docs/guide.md", "content");
            $file = new RemoteFile("docs/guide.md", $adapter);
            $this->assertTrue($file->exists());
        }

        #[Group("RemoteFile")]
        #[Define(name: "Exists — Returns False When Adapter Reports File Missing", description: "exists() returns false when the adapter has no record of the path.")]
        public function testRemoteFileExistsReturnsFalseWhenAdapterReportsFileMissing () : void {
            $adapter = new InMemoryRemoteAdapter();
            $file = new RemoteFile("docs/missing.md", $adapter);
            $this->assertFalse($file->exists());
        }

        #[Group("RemoteFile")]
        #[Define(name: "GetContent — Returns Adapter Read Result", description: "getContent() returns exactly what the adapter's read() returns.")]
        public function testRemoteFileGetContentReturnsAdapterReadResult () : void {
            $adapter = (new InMemoryRemoteAdapter())->withFile("data/report.json", '{"ok":true}');
            $file = new RemoteFile("data/report.json", $adapter);
            $this->assertEquals('{"ok":true}', $file->getContent());
        }

        #[Group("RemoteFile")]
        #[Define(name: "GetContentStream — Returns Seekable Stream with File Content", description: "getContentStream() returns a Stream positioned at the start and containing the full file content.")]
        public function testRemoteFileGetContentStreamReturnsSteamWithContent () : void {
            $adapter = (new InMemoryRemoteAdapter())->withFile("uploads/note.txt", "stream content");
            $file = new RemoteFile("uploads/note.txt", $adapter);

            $stream = $file->getContentStream();

            $this->assertInstanceOf(Stream::class, $stream);
            $this->assertEquals("stream content", $stream->readAll());
        }

        #[Group("RemoteFile")]
        #[Define(name: "GetSize — Returns File Size From Adapter Metadata", description: "getSize() returns the 'size' key from the adapter's getMetadata() call.")]
        public function testRemoteFileGetSizeReturnsFileSizeFromMetadata () : void {
            $adapter = (new InMemoryRemoteAdapter())->withFile("logs/app.log", str_repeat("X", 42));
            $file = new RemoteFile("logs/app.log", $adapter);
            $this->assertEquals(42, $file->getSize());
        }

        #[Group("RemoteFile")]
        #[Define(name: "GetLastModified — Returns DateTimeImmutable From Adapter Timestamp", description: "getLastModified() wraps the 'modified' timestamp from adapter metadata in a DateTimeImmutable.")]
        public function testRemoteFileGetLastModifiedReturnsMappedDateTimeImmutable () : void {
            $adapter = (new InMemoryRemoteAdapter())->withFile("config/app.json", "{}");
            $file = new RemoteFile("config/app.json", $adapter);

            $result = $file->getLastModified();

            $this->assertInstanceOf(DateTimeImmutable::class, $result);
            $this->assertEquals(1_700_000_000, $result->getTimestamp());
        }

        #[Group("RemoteFile")]
        #[Define(name: "GetMetadata — Returns Full Metadata Array From Adapter", description: "getMetadata() returns the complete metadata array as provided by the adapter.")]
        public function testRemoteFileGetMetadataReturnsFulMetadataArray () : void {
            $adapter = (new InMemoryRemoteAdapter())->withFile("src/index.php", "<?php");
            $file = new RemoteFile("src/index.php", $adapter);
            $meta = $file->getMetadata();

            $this->assertEquals("src/index.php", $meta["path"]);
            $this->assertEquals("file", $meta["type"]);
            $this->assertEquals("index.php", $meta["name"]);
        }

        #[Group("RemoteFile")]
        #[Define(name: "GetPath — Returns Path Given at Construction", description: "getPath() returns the exact path passed to the constructor.")]
        public function testRemoteFileGetPathReturnsConstructorPath () : void {
            $adapter = new InMemoryRemoteAdapter();
            $file = new RemoteFile("some/path/file.txt", $adapter);
            $this->assertEquals("some/path/file.txt", $file->getPath());
        }

        #[Group("RemoteFile")]
        #[Define(name: "GetBaseName — Returns Base Name of the Path", description: "getBaseName() returns only the last segment of the file path.")]
        public function testRemoteFileGetBaseNameReturnsLastPathSegment () : void {
            $adapter = new InMemoryRemoteAdapter();
            $file = new RemoteFile("nested/dir/document.pdf", $adapter);
            $this->assertEquals("document.pdf", $file->getBaseName());
        }

        #[Group("RemoteFile")]
        #[Define(name: "GetUri — Returns URI With Correct Path", description: "getUri() returns a URI object that resolves to the file's path.")]
        public function testRemoteFileGetUriReturnsUriWithCorrectPath () : void {
            $adapter = new InMemoryRemoteAdapter();
            $file = new RemoteFile("assets/logo.png", $adapter);
            $uri = $file->getUri();

            $this->assertInstanceOf(URI::class, $uri);
        }

        // ─── RemoteDirectory ──────────────────────────────────────────────────

        #[Group("RemoteDirectory")]
        #[Define(name: "Exists — Returns True When Adapter Reports Directory Present", description: "exists() delegates to the adapter and returns true for a known directory path.")]
        public function testRemoteDirectoryExistsReturnsTrueWhenAdapterReportsDirectoryPresent () : void {
            $adapter = (new InMemoryRemoteAdapter())->withDirectory("media/");
            $dir = new RemoteDirectory("media/", $adapter);
            $this->assertTrue($dir->exists());
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "Exists — Returns False When Adapter Reports Directory Missing", description: "exists() returns false when the directory is not registered in the adapter.")]
        public function testRemoteDirectoryExistsReturnsFalseWhenAdapterReportsDirectoryMissing () : void {
            $adapter = new InMemoryRemoteAdapter();
            $dir = new RemoteDirectory("phantom/", $adapter);
            $this->assertFalse($dir->exists());
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "GetContents — Returns RemoteFile Children for File Entries", description: "getContents() queries the adapter and wraps each file entry in a RemoteFile.")]
        public function testRemoteDirectoryGetContentsReturnsRemoteFileChildrenForFileEntries () : void {
            $adapter = (new InMemoryRemoteAdapter())
                ->withFile("uploads/a.txt", "aaa")
                ->withFile("uploads/b.txt", "bbb");

            $dir = new RemoteDirectory("uploads/", $adapter);
            $contents = $dir->getContents();

            $this->assertCount(2, $contents);
            foreach ($contents as $item) {
                $this->assertInstanceOf(RemoteFile::class, $item);
            }
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "GetContents — Returns RemoteDirectory Children for Directory Entries", description: "getContents() wraps each directory entry returned by the adapter in a RemoteDirectory.")]
        public function testRemoteDirectoryGetContentsReturnsRemoteDirectoryChildrenForDirectoryEntries () : void {
            $adapter = (new InMemoryRemoteAdapter())
                ->withDirectory("root/sub1/")
                ->withDirectory("root/sub2/");

            $dir = new RemoteDirectory("root/", $adapter);
            $contents = $dir->getContents();

            $this->assertCount(2, $contents);
            foreach ($contents as $item) {
                $this->assertInstanceOf(RemoteDirectory::class, $item);
            }
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "GetFiles — Returns Only File Children", description: "getFiles() returns only RemoteFile instances from the directory contents.")]
        public function testRemoteDirectoryGetFilesReturnsOnlyFileChildren () : void {
            $adapter = (new InMemoryRemoteAdapter())
                ->withFile("root/a.txt", "a")
                ->withDirectory("root/subdir/");

            $dir = new RemoteDirectory("root/", $adapter);
            $files = $dir->getFiles();

            $this->assertCount(1, $files);
            $this->assertEquals("a.txt", $files[0]->getBaseName());
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "GetDirectories — Returns Only Directory Children", description: "getDirectories() returns only RemoteDirectory instances from the directory contents.")]
        public function testRemoteDirectoryGetDirectoriesReturnsOnlyDirectoryChildren () : void {
            $adapter = (new InMemoryRemoteAdapter())
                ->withFile("root/a.txt", "a")
                ->withDirectory("root/subdir/");

            $dir = new RemoteDirectory("root/", $adapter);
            $dirs = $dir->getDirectories();

            $this->assertCount(1, $dirs);
            $this->assertEquals("subdir", $dirs[0]->getBaseName());
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "GetDirectory — Returns Directory by Zero-Based Index", description: "getDirectory(int) returns the child directory at the given zero-based index.")]
        public function testRemoteDirectoryGetDirectoryReturnsByIndex () : void {
            $adapter = (new InMemoryRemoteAdapter())
                ->withDirectory("root/alpha/")
                ->withDirectory("root/beta/");

            $dir = new RemoteDirectory("root/", $adapter);

            $this->assertEquals("alpha", $dir->getDirectory(0)?->getBaseName());
            $this->assertEquals("beta", $dir->getDirectory(1)?->getBaseName());
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "GetDirectory — Returns Directory by Base Name", description: "getDirectory(string) returns the child directory whose basename matches the given string.")]
        public function testRemoteDirectoryGetDirectoryReturnsByBaseName () : void {
            $adapter = (new InMemoryRemoteAdapter())
                ->withDirectory("root/alpha/")
                ->withDirectory("root/beta/");

            $dir = new RemoteDirectory("root/", $adapter);

            $this->assertEquals("beta", $dir->getDirectory("beta")?->getBaseName());
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "GetDirectory — Returns Null for Unknown Index", description: "getDirectory(int) returns null when no directory exists at the given index.")]
        public function testRemoteDirectoryGetDirectoryReturnsNullForUnknownIndex () : void {
            $dir = new RemoteDirectory("root/", new InMemoryRemoteAdapter());
            $this->assertNull($dir->getDirectory(99));
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "GetParent — Returns Null When No Parent Set", description: "getParent() returns null when the directory was created without a parent.")]
        public function testRemoteDirectoryGetParentReturnsNullWhenNoParentSet () : void {
            $dir = new RemoteDirectory("root/", new InMemoryRemoteAdapter());
            $this->assertNull($dir->getParent());
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "GetParent — Returns Parent When Parent Is Provided", description: "getParent() returns the parent directory passed to the constructor.")]
        public function testRemoteDirectoryGetParentReturnsParentWhenProvided () : void {
            $adapter = new InMemoryRemoteAdapter();
            $parent = new RemoteDirectory("root/", $adapter);
            $child = new RemoteDirectory("root/child/", $adapter, $parent);

            $this->assertEquals($parent, $child->getParent());
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "GetLastModified — Returns DateTimeImmutable From Adapter Metadata", description: "getLastModified() wraps the 'modified' timestamp from adapter metadata in a DateTimeImmutable.")]
        public function testRemoteDirectoryGetLastModifiedReturnsMappedDateTimeImmutable () : void {
            $adapter = (new InMemoryRemoteAdapter())->withDirectory("data/");
            $dir = new RemoteDirectory("data/", $adapter);

            $result = $dir->getLastModified();

            $this->assertInstanceOf(DateTimeImmutable::class, $result);
            $this->assertEquals(1_700_000_000, $result->getTimestamp());
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "GetMetadata — Returns Full Metadata Array From Adapter", description: "getMetadata() returns the complete metadata array as provided by the adapter.")]
        public function testRemoteDirectoryGetMetadataReturnsFulMetadataArray () : void {
            $adapter = (new InMemoryRemoteAdapter())->withDirectory("assets/");
            $dir = new RemoteDirectory("assets/", $adapter);
            $meta = $dir->getMetadata();

            $this->assertEquals("assets/", $meta["path"]);
            $this->assertEquals("dir", $meta["type"]);
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "GetUri — Returns URI With Correct Path", description: "getUri() returns a URI object that resolves to the directory's path.")]
        public function testRemoteDirectoryGetUriReturnsUriWithCorrectPath () : void {
            $dir = new RemoteDirectory("media/images/", new InMemoryRemoteAdapter());
            $uri = $dir->getUri();

            $this->assertInstanceOf(URI::class, $uri);
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "Search — Returns Matching Resources by Glob Pattern", description: "search() returns resources whose base names match the given glob pattern.")]
        public function testRemoteDirectorySearchReturnMatchingResourcesByGlobPattern () : void {
            $adapter = (new InMemoryRemoteAdapter())
                ->withFile("root/image.jpg", "img")
                ->withFile("root/document.pdf", "doc")
                ->withFile("root/photo.jpg", "photo");

            $dir = new RemoteDirectory("root/", $adapter);
            $results = $dir->search("*.jpg");

            $this->assertCount(2, $results);
            $names = array_map(fn ($r) => $r->getBaseName(), $results);
            $this->assertContains("image.jpg", $names);
            $this->assertContains("photo.jpg", $names);
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "Search — Recurses Into Child Directories When Recursive", description: "search() descends into child remote directories when recursive=true.")]
        public function testRemoteDirectorySearchRecursesIntoChildDirectoriesWhenRecursive () : void {
            $adapter = (new InMemoryRemoteAdapter())
                ->withDirectory("root/sub/")
                ->withFile("root/sub/nested.txt", "n");

            $dir = new RemoteDirectory("root/", $adapter);
            $results = $dir->search("*.txt", true);

            $this->assertCount(1, $results);
            $this->assertEquals("nested.txt", $results[0]->getBaseName());
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "Add File — Writes Content and Returns RemoteFile", description: "add() with a file resource writes its content via the adapter and returns a RemoteFile at the target path.")]
        public function testRemoteDirectoryAddFileWritesContentAndReturnsRemoteFile () : void {
            $adapter = new InMemoryRemoteAdapter();
            $dir = new RemoteDirectory("uploads/", $adapter);

            $sourceAdapter = (new InMemoryRemoteAdapter())->withFile("tmp/source.txt", "source content");
            $sourceFile = new RemoteFile("tmp/source.txt", $sourceAdapter);

            $result = $dir->add($sourceFile);

            $this->assertInstanceOf(RemoteFile::class, $result);
            $this->assertEquals("source.txt", $result->getBaseName());
            $this->assertCount(1, $adapter->writtenFiles);
            $this->assertEquals("uploads/source.txt", $adapter->writtenFiles[0]["path"]);
            $this->assertEquals("source content", $adapter->writtenFiles[0]["content"]);
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "Add File — Accepts Custom Name Override", description: "add() with a custom name stores the file under the override name rather than the source base name.")]
        public function testRemoteDirectoryAddFileAcceptsCustomNameOverride () : void {
            $adapter = new InMemoryRemoteAdapter();
            $dir = new RemoteDirectory("uploads/", $adapter);

            $sourceAdapter = (new InMemoryRemoteAdapter())->withFile("tmp/original.txt", "data");
            $sourceFile = new RemoteFile("tmp/original.txt", $sourceAdapter);

            $result = $dir->add($sourceFile, "renamed.txt");

            $this->assertEquals("renamed.txt", $result->getBaseName());
            $this->assertEquals("uploads/renamed.txt", $adapter->writtenFiles[0]["path"]);
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "Add File — Throws When Adapter Is Not Writable", description: "add() throws FilesystemException when the adapter does not implement WritableFilesystemAdapterInterface.")]
        public function testRemoteDirectoryAddFileThrowsWhenAdapterIsNotWritable () : void {
            $dir = new RemoteDirectory("uploads/", new ReadOnlyRemoteAdapter());

            $sourceAdapter = (new InMemoryRemoteAdapter())->withFile("tmp/file.txt", "data");
            $sourceFile = new RemoteFile("tmp/file.txt", $sourceAdapter);

            $this->assertThrows(FilesystemException::class, function () use ($dir, $sourceFile) {
                $dir->add($sourceFile);
            });
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "Remove — Deletes File by FileResource", description: "remove() with a FileResource argument calls adapter delete() with the resource's path.")]
        public function testRemoteDirectoryRemoveDeletesFileByFileResource () : void {
            $adapter = (new InMemoryRemoteAdapter())->withFile("root/file.txt", "data");
            $dir = new RemoteDirectory("root/", $adapter);

            $sourceFile = new RemoteFile("root/file.txt", $adapter);
            $dir->remove($sourceFile);

            $this->assertContains("root/file.txt", $adapter->deletedPaths);
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "Remove — Deletes File by String Name", description: "remove() with a string argument resolves the path relative to the directory and calls adapter delete().")]
        public function testRemoteDirectoryRemoveDeletesFileByStringName () : void {
            $adapter = (new InMemoryRemoteAdapter())->withFile("root/file.txt", "data");
            $dir = new RemoteDirectory("root/", $adapter);

            $dir->remove("file.txt");

            $this->assertContains("root/file.txt", $adapter->deletedPaths);
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "Remove — Deletes File by Zero-Based Index", description: "remove() with an integer index resolves the corresponding file and calls adapter delete().")]
        public function testRemoteDirectoryRemoveDeletesFileByZeroBasedIndex () : void {
            $adapter = (new InMemoryRemoteAdapter())
                ->withFile("root/first.txt", "a")
                ->withFile("root/second.txt", "b");

            $dir = new RemoteDirectory("root/", $adapter);
            $dir->remove(0);

            $this->assertCount(1, $adapter->deletedPaths);
        }

        #[Group("RemoteDirectory")]
        #[Define(name: "Remove — Throws When Adapter Is Not Writable", description: "remove() throws FilesystemException when the adapter does not implement WritableFilesystemAdapterInterface.")]
        public function testRemoteDirectoryRemoveThrowsWhenAdapterIsNotWritable () : void {
            $dir = new RemoteDirectory("root/", new ReadOnlyRemoteAdapter());

            $this->assertThrows(FilesystemException::class, function () use ($dir) {
                $dir->remove("file.txt");
            });
        }
    }
?>
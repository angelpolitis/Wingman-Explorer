<?php
    /**
     * Project Name:    Wingman Explorer - Remote Directory
     * Created by:      Angel Politis
     * Creation Date:   Dec 14 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Resources namespace.
    namespace Wingman\Explorer\Resources;

    # Import the following classes to the current scope.
    use DateTimeImmutable;
    use Wingman\Explorer\Exceptions\FilesystemException;
    use Wingman\Explorer\Interfaces\Adapters\DirectoryFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\ReadableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\WritableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Resources\DirectoryResource;
    use Wingman\Explorer\Interfaces\Resources\FileResource;
    use Wingman\Explorer\Interfaces\Resources\RemoteDirectoryResource;
    use Wingman\Explorer\Interfaces\Resources\Resource;
    use Wingman\Locator\Objects\URI;

    /**
     * Represents a remote directory whose contents and metadata are provided by a filesystem adapter.
     *
     * All directory listings and operations are delegated to the injected
     * {@see DirectoryFilesystemAdapterInterface}, keeping the transport layer
     * (FTP, S3, cloud APIs, etc.) transparent to consumers.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class RemoteDirectory extends Directory implements RemoteDirectoryResource {
        /**
         * The adapter used to perform remote filesystem operations.
         * @var DirectoryFilesystemAdapterInterface
         */
        protected DirectoryFilesystemAdapterInterface $adapter;

        /**
         * The parent directory resource, if any.
         * @var DirectoryResource|null
         */
        protected ?DirectoryResource $parent = null;

        /**
         * Creates a new remote directory.
         * @param string $path The remote path or URL of the directory.
         * @param DirectoryFilesystemAdapterInterface $adapter The adapter used to list and operate on this directory.
         * @param DirectoryResource|null $parent The parent directory resource, if any.
         */
        public function __construct (string $path, DirectoryFilesystemAdapterInterface $adapter, ?DirectoryResource $parent = null) {
            parent::__construct($path);

            if ($this->path !== '/') {
                $this->path = $this->path . '/';
            }

            $this->adapter = $adapter;
            $this->parent = $parent;
        }

        /**
         * Adds a resource to this remote directory, writing its content via the adapter.
         * @param Resource $resource The resource to add.
         * @param string|null $newName An optional override name; defaults to the resource's base name.
         * @param bool $move Unused; included for interface compatibility.
         * @return Resource The newly created remote resource at the target path.
         * @throws FilesystemException If the adapter does not support write operations, or if reads are needed but unavailable.
         */
        public function add (Resource $resource, ?string $newName = null, bool $move = false) : Resource {
            if (!$this->adapter instanceof WritableFilesystemAdapterInterface) {
                throw new FilesystemException("This remote directory does not support write operations.");
            }

            $name = $newName ?? $resource->getBaseName();
            $targetPath = rtrim($this->path, "/") . "/" . $name;

            if ($resource instanceof DirectoryResource) {
                $this->adapter->createDirectory($targetPath);
                return new RemoteDirectory($targetPath, $this->adapter, $this);
            }

            $content = ($resource instanceof FileResource) ? $resource->getContent() : "";
            $this->adapter->write($targetPath, $content);

            if (!$this->adapter instanceof ReadableFilesystemAdapterInterface) {
                throw new FilesystemException("The adapter for '{$targetPath}' does not support read operations.");
            }

            return new RemoteFile($targetPath, $this->adapter, $this);
        }

        /**
         * Checks whether this remote directory exists via the adapter.
         * @return bool Whether the directory exists.
         */
        public function exists () : bool {
            return $this->adapter->exists($this->path);
        }

        /**
         * Lists and builds all child resources by querying the adapter.
         * @return array<int, RemoteDirectory|RemoteFile> The child resources.
         */
        public function getContents () : array {
            $results = [];

            foreach ($this->adapter->list($this->path) as $itemPath) {
                $metadata = $this->adapter->getMetadata((string) $itemPath, ["type"]);

                if (($metadata["type"] ?? null) === "dir") {
                    $results[] = new RemoteDirectory((string) $itemPath, $this->adapter, $this);
                } elseif ($this->adapter instanceof ReadableFilesystemAdapterInterface) {
                    $results[] = new RemoteFile((string) $itemPath, $this->adapter, $this);
                }
            }

            return $results;
        }

        /**
         * Gets all direct child directories.
         * @return RemoteDirectory[] The child directories.
         */
        public function getDirectories () : array {
            return array_values(array_filter($this->getContents(), fn ($item) => $item instanceof DirectoryResource));
        }

        /**
         * Gets a child directory by zero-based index (among directories) or by base name.
         * @param int|string $indexOrBaseName The index or base name of the directory.
         * @return static|null The matching child directory, or null if not found.
         */
        public function getDirectory (int|string $indexOrBaseName) : ?static {
            $dirs = $this->getDirectories();

            if (is_int($indexOrBaseName)) {
                return $dirs[$indexOrBaseName] ?? null;
            }

            foreach ($dirs as $dir) {
                if ($dir->getBaseName() === $indexOrBaseName) return $dir;
            }

            return null;
        }

        /**
         * Gets all direct child files.
         * @return RemoteFile[] The child files.
         */
        public function getFiles () : array {
            return array_values(array_filter($this->getContents(), fn ($item) => $item instanceof FileResource));
        }

        /**
         * Gets the last-modified timestamp of this directory from adapter metadata.
         * @return DateTimeImmutable The last-modified timestamp.
         */
        public function getLastModified () : DateTimeImmutable {
            return new DateTimeImmutable("@" . ($this->adapter->getMetadata($this->path, ["modified"])["modified"] ?? 0));
        }

        /**
         * Gets the metadata of this directory as reported by the adapter.
         * @return array The metadata array.
         */
        public function getMetadata () : array {
            return $this->adapter->getMetadata($this->path);
        }

        /**
         * Gets the parent directory resource.
         * @return DirectoryResource|null The parent, or null if this is a root directory.
         */
        public function getParent () : ?DirectoryResource {
            return $this->parent;
        }

        /**
         * Gets the total size in bytes by summing all child resources.
         * @return int The total size in bytes.
         */
        public function getSize () : int {
            return array_sum(array_map(fn ($item) => $item->getSize(), $this->getContents()));
        }

        /**
         * Gets a parsed URI object representing the remote directory's path.
         * @return URI The URI.
         */
        public function getUri () : URI {
            return URI::from($this->path);
        }

        /**
         * Removes a resource from this directory via the adapter.
         * @param FileResource|int|string $resource The resource reference, its name, or its zero-based file index.
         * @return static The directory.
         * @throws FilesystemException If the adapter does not support write operations.
         */
        public function remove (FileResource|int|string $resource) : static {
            if (!$this->adapter instanceof WritableFilesystemAdapterInterface) {
                throw new FilesystemException("This remote directory does not support write operations.");
            }

            if ($resource instanceof FileResource) {
                $this->adapter->delete($resource->getPath());
                return $this;
            }

            if (is_string($resource)) {
                $this->adapter->delete(rtrim($this->path, "/") . "/" . $resource);
                return $this;
            }

            $files = $this->getFiles();

            if (isset($files[$resource])) {
                $this->adapter->delete($files[$resource]->getPath());
            }

            return $this;
        }

        /**
         * Searches for resources whose base names match a glob pattern.
         * @param string $pattern The glob pattern to match against resource base names.
         * @param bool $recursive Whether to recurse into child remote directories.
         * @return Resource[] The matching resources.
         */
        public function search (string $pattern, bool $recursive = true) : array {
            $matches = [];

            foreach ($this->getContents() as $item) {
                if (fnmatch($pattern, $item->getBaseName())) {
                    $matches[] = $item;
                }

                if ($recursive && $item instanceof RemoteDirectory) {
                    array_push($matches, ...$item->search($pattern, true));
                }
            }

            return $matches;
        }
    }
?>
<?php
    /**
     * Project Name:    Wingman Explorer - Local Directory
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
    use DateTimeZone;
    use Wingman\Explorer\Exceptions\FilesystemException;
    use Wingman\Explorer\Exceptions\ResourceNotMemberException;
    use Wingman\Explorer\Exceptions\UnsupportedResourceTypeException;
    use Wingman\Explorer\Interfaces\Resources\DirectoryResource;
    use Wingman\Explorer\Interfaces\Resources\FileResource;
    use Wingman\Explorer\Interfaces\Resources\LocalDirectoryResource;
    use Wingman\Explorer\Interfaces\Resources\LocalFileResource;
    use Wingman\Explorer\Interfaces\Resources\LocalResource;
    use Wingman\Explorer\Interfaces\Resources\Resource;
    use Wingman\Locator\Exceptions\NonexistentDirectoryException;
    use Wingman\Locator\PathUtils;

    /**
     * Represents a local directory.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class LocalDirectory extends Directory implements LocalDirectoryResource {
        /**
         * The filename of a virtual folder manifest file.
         * @var string
         */
        private const VIRTUAL_MANIFEST = ".wm-explorer-virtual";

        /**
         * Cached file resources immediately inside this directory, keyed by base name.
          * @var FileResource[]
         */
        protected array $cachedFiles = [];

        /**
         * Cached directory resources immediately inside this directory, keyed by base name.
         * @var DirectoryResource[]
         */
        protected array $cachedDirectories = [];

        /**
         * The inotify resource handle, or `null` when inotify is unavailable.
         * @var int|null
         */
        protected ?int $inotifyResource = null;

        /**
         * Whether the directory is actively watched via inotify.
         * @var bool
         */
        protected bool $watching = false;

        /**
         * The Unix timestamp of the last poll check.
         * @var float
         */
        protected float $lastPollTime = 0.0;

        /**
         * The polling interval in seconds for systems without inotify.
         * @var int
         */
        protected int $pollInterval = 2;

        /**
         * A MD5 hash signature of the directory contents for change detection.
         * @var string
         */
        protected string $lastModifiedSignature = '';

        /**
         * Whether the directory automatically reacts to filesystem changes.
         * @var bool
         */
        protected bool $reactive = true;

        /**
         * Creates a new local directory.
         * @param string $path Directory path.
         * @param int $pollInterval Poll interval in seconds for non-inotify systems.
         */
        public function __construct (string $path, bool $reactive = true, int $pollInterval = 2) {
            parent::__construct($path);
            $this->pollInterval = $pollInterval;
            $this->reactive = $reactive;
            if ($reactive && function_exists("inotify_init")) {
                /** @disregard P1010 */
                $this->setupInotify();
            }
            $this->lastModifiedSignature = $this->getDirectorySignature();
            $this->refresh();
        }

        /**
         * Destroys a directory instance.
         */
        public function __destruct () {
            if (!$this->inotifyResource) return;
            fclose($this->inotifyResource);
            $this->inotifyResource = null;
        }

        /**
         * Detaches a virtual file from this directory's manifest, effectively removing it from the directory listing without deleting any actual files on disk.
         * @param VirtualFile $file The virtual file to detach.
         * @return bool Whether the file was successfully detached.
         */
        private function detachVirtualFile (VirtualFile $file) : bool {
            $descriptor = $this->path . DIRECTORY_SEPARATOR . self::VIRTUAL_MANIFEST;

            if (!is_file($descriptor)) {
                return false;
            }

            $data = json_decode(file_get_contents($descriptor));
            if (!is_array($data)) {
                return false;
            }

            $data = array_filter($data, fn ($v) => ($v->path ?? null) !== $file->getPath());

            file_put_contents(
                $descriptor,
                json_encode(array_values($data), JSON_PRETTY_PRINT)
            );

            return true;
        }

        /**
         * Searches the directory recursively for entries matching a glob pattern.
         * @param string $directory The absolute path of the directory to search.
         * @param string $pattern The glob pattern to match against.
         * @param bool $recursive Whether to recurse into subdirectories.
         * @param array $results The accumulated result list, passed by reference.
         */
        private function searchRecursive (string $directory, string $pattern, bool $recursive, array &$results): void {
            $fullPattern = $directory . DIRECTORY_SEPARATOR . $pattern;
        
            foreach (glob($fullPattern, GLOB_BRACE) ?: [] as $match) {
                $match = PathUtils::fix($match);
                if (is_file($match)) {
                    $results[] = new LocalFile($match);
                }
                elseif (is_dir($match)) {
                    $results[] = new LocalDirectory($match);
                }
            }
        
            if (!$recursive) {
                return;
            }
        
            foreach (scandir($directory) as $item) {
                if ($item === '.' || $item === '..') continue;
        
                $path = $directory . DIRECTORY_SEPARATOR . $item;
        
                if (is_dir($path)) {
                    $this->searchRecursive($path, $pattern, true, $results);
                }
            }
        }        
        
        /**
         * Generates a hash signature for the directory to detect changes.
         * @return string The directory signature.
         */
        protected function getDirectorySignature () : string {
            if (!is_dir($this->path)) return '';
            $hash = '';
            $scandir = scandir($this->path);
            if ($scandir === false) return '';
            foreach ($scandir as $item) {
                if ($item === '.' || $item === '..' || $item === self::VIRTUAL_MANIFEST) continue;
                $fullPath = $this->path . DIRECTORY_SEPARATOR . $item;
                $mtime = @filemtime($fullPath);
                $hash .= $item . ($mtime !== false ? $mtime : '');
            }
            return md5($hash);
        }

        /**
         * Refreshes files and directories cache if needed.
         * @throws NonexistentDirectoryException If the directory no longer exists.
         */
        protected function maybeRefresh () : void {
            if (!$this->exists()) {
                throw new NonexistentDirectoryException("The directory '{$this->path}' doesn't exist.");
            }

            if (!$this->reactive) return;

            if ($this->watching && $this->inotifyResource !== null) {
                /** @disregard P1010 */
                $events = inotify_read($this->inotifyResource);
                if ($events !== false && count($events) > 0) {
                    $this->refresh();
                }
            }
            else {
                $now = microtime(true);
                if (($now - $this->lastPollTime) >= $this->pollInterval) {
                    $this->lastPollTime = $now;
                    $signature = $this->getDirectorySignature();
                    if ($signature !== $this->lastModifiedSignature) {
                        $this->lastModifiedSignature = $signature;
                        $this->refresh();
                    }
                }
            }
        }

        /**
         * Sets up inotify for reactive watching (Linux only).
         */
        protected function setupInotify () : void {
            /** @disregard P1010 */
            $this->inotifyResource = inotify_init();
            stream_set_blocking($this->inotifyResource, 0);

            /** @disregard P1010,P1011 */
            inotify_add_watch(
                $this->inotifyResource,
                $this->path,
                IN_CREATE | IN_DELETE | IN_MODIFY | IN_MOVED_FROM | IN_MOVED_TO
            );

            $this->watching = true;
        }

        /**
         * Adds a local resource to this directory, optionally moving or renaming it.
         * @param Resource $item The resource to add.
         * @param string|null $newName An optional new base name for the resource.
         * @param bool $move Whether to delete the source resource after copying [default: `false`].
         * @throws UnsupportedResourceTypeException If the resource type is not supported.
         * @return LocalResource The resource created inside this directory.
         */
        public function add (Resource $item, ?string $newName = null, bool $move = false) : LocalResource {
            $name = $newName ?? $item->getBaseName();

            if ($item instanceof LocalDirectoryResource) {
                $target = new static($this->path . DIRECTORY_SEPARATOR . $name);
                $target->create(true, 0755);

                if ($item instanceof DirectoryResource) {
                    foreach ($item->getContents() as $child) {
                        $target->add($child, null, $move);
                    }
                }

                if ($move) {
                    $item->delete();
                }
            }
            else if ($item instanceof LocalFileResource) {
                $target = new LocalFile($this->path . DIRECTORY_SEPARATOR . $name);
                $target->writeStream($item->getContentStream());
                $target->save();

                if ($move) $item->delete();
            }
            else throw new UnsupportedResourceTypeException("Unsupported resource type");

            return $target;
        }
        
        /**
         * Copies a directory to a destination path, returning the new directory.
         * - When `$recursive` is `true` all subdirectories are copied too;
         * - when `false` only the immediate files are copied.
         * The destination directory inherits the permissions of this directory.
         * @param string $destination The absolute path for the copy.
         * @param bool $recursive Whether to copy subdirectories recursively [default: `true`].
         * @throws FilesystemException If any intermediate directory cannot be created.
         * @return static The new directory at `$destination`.
         */
        public function copy (string $destination, bool $recursive = true) : static {
            $permissions = fileperms($this->path) & 0x1FF;
            $target = new static($destination, false);
            $target->create(true, $permissions);

            foreach ($this->getFiles() as $file) {
                \copy($file->getPath(), $destination . DIRECTORY_SEPARATOR . $file->getBaseName());
            }

            if ($recursive) {
                foreach ($this->getDirectories() as $dir) {
                    $dir->copy($destination . DIRECTORY_SEPARATOR . $dir->getBaseName(), true);
                }
            }

            return $target;
        }

        /**
         * Creates the directory on disk.
         * @param bool $recursive Whether to create parent directories as needed [default: `true`].
         * @param int $permissions The directory permissions as an octal integer [default: `0755`].
         * @return static The directory.
         */
        public function create (bool $recursive = true, int $permissions = 0755) : static {
            if (!is_dir($this->path)) {
                mkdir($this->path, $permissions, $recursive);
            }
            return $this;
        }

        /**
         * Creates a new empty file inside this directory.
         * @param string $name The base name for the new file.
         * @return LocalFile The created file instance.
         */
        public function createFile (string $name) : LocalFile {
            return new LocalFile($this->path . DIRECTORY_SEPARATOR . $name);
        }

        /**
         * Deletes this directory from disk.
         * The directory must be empty for the operation to succeed.
         * @return bool Whether the deletion was successful.
         */
        public function delete () : bool {
            return rmdir($this->path);
        }

        /**
         * Deletes this directory and all its contents recursively.
         * Files are deleted first, then subdirectories, then the directory itself.
         * @return bool Whether the deletion was successful.
         */
        public function deleteRecursive () : bool {
            if (!$this->exists()) return false;

            foreach ($this->getFiles() as $file) {
                $file->delete();
            }

            foreach ($this->getDirectories() as $dir) {
                $dir->deleteRecursive();
            }

            return $this->delete();
        }

        /**
         * Checks if the directory exists.
         * @return bool Whether the directory exists.
         */
        public function exists () : bool {
            return is_dir($this->path);
        }

        /**
         * Returns all descendant files as a flat array, descending recursively into subdirectories.
         * @return LocalFile[] All files within this directory and every nested subdirectory.
         */
        public function flatten () : array {
            $result = array_values($this->getFiles());

            foreach ($this->getDirectories() as $dir) {
                array_push($result, ...$dir->flatten());
            }

            return $result;
        }

        /**
         * Gets all resources inside this directory, sorted with directories first.
         * @return array<int, LocalFileResource|LocalDirectoryResource> The directory contents.
         */
        public function getContents () : array {
            $this->maybeRefresh();

            $contents = array_merge($this->cachedDirectories, $this->cachedFiles);

            usort($contents, function ($a, $b) {
                $aIsDir = $a instanceof DirectoryResource ? 0 : 1;
                $bIsDir = $b instanceof DirectoryResource ? 0 : 1;

                if ($aIsDir !== $bIsDir) return $aIsDir - $bIsDir;

                return strcasecmp($a->getBaseName(), $b->getBaseName());
            });

            return $contents;
        }

        /**
         * Finds a subdirectory by its integer index or base name.
         * @param int|string $indexOrBaseName The index or base name of the subdirectory.
         * @return static|null The located directory, or `null` if not found.
         */
        public function getDirectory (int|string $indexOrBaseName) : ?static {
            $this->maybeRefresh();
            foreach ($this->cachedDirectories as $dir) {
                if ($dir->getBaseName() === (string) $indexOrBaseName || array_search($dir, $this->cachedDirectories, true) === $indexOrBaseName) {
                    return $dir;
                }
            }
            return null;
        }

        /**
         * Returns all immediate subdirectories of this directory.
         * @return LocalDirectory[] The list of subdirectories.
         */
        public function getDirectories () : array {
            $this->maybeRefresh();
            return $this->cachedDirectories;
        }

        /**
         * Finds a file by its integer index or base name.
         * @param int|string $key The index or base name of the file.
         * @return FileResource|null The located file, or `null` if not found.
         */
        public function getFile (int|string $key) : ?FileResource {
            $this->maybeRefresh();
            foreach ($this->cachedFiles as $file) {
                if ($file->getBaseName() === (string) $key || array_search($file, $this->cachedFiles, true) === $key) {
                    return $file;
                }
            }
            return null;
        }

        /**
         * Returns all files immediately inside this directory.
         * @return LocalFile[] The list of files.
         */
        public function getFiles () : array {
            $this->maybeRefresh();
            return $this->cachedFiles;
        }

        /**
         * Returns a metadata map for this directory.
         * @throws NonexistentDirectoryException If the directory no longer exists.
         * @throws FilesystemException If the directory metadata cannot be read.
         * @return array<string, mixed> A map of metadata properties.
         */
        public function getMetadata () : array {
            if (!$this->exists()) {
                throw new NonexistentDirectoryException("The directory '{$this->path}' no longer exists.");
            }

            $stat = stat($this->path);
            if ($stat === false) {
                throw new FilesystemException("Unable to read directory metadata: {$this->path}");
            }

            $timezone = new DateTimeZone(date_default_timezone_get());

            return [
                "size" => $this->getSize(),
                "permissions" => $stat["mode"] & 0777,
                "owner" => $stat["uid"],
                "group" => $stat["gid"],
                "last_accessed" => (new DateTimeImmutable('@' . $stat["atime"]))->setTimezone($timezone),
                "last_modified" => (new DateTimeImmutable('@' . $stat["mtime"]))->setTimezone($timezone),
                "created" => (new DateTimeImmutable('@' . $stat["ctime"]))->setTimezone($timezone),
            ];
        }

        /**
         * Returns the parent directory of this directory.
         * @throws NonexistentDirectoryException If this directory no longer exists.
         * @return DirectoryResource|null The parent directory, or `null` if this is the root.
         */
        public function getParent () : ?DirectoryResource {
            if (!$this->exists()) {
                throw new NonexistentDirectoryException("The directory '{$this->path}' no longer exists.");
            }

            if (!$this->parentDirectory) return null;
            
            return new LocalDirectory($this->parentDirectory);
        }

        /**
         * Gets the last modified date of a directory.
         * @return DateTimeImmutable The last modified date.
         * @throws NonexistentDirectoryException If the directory no longer exists.
         * @throws FilesystemException If unable to read last modified time.
         */
        public function getLastModified () : DateTimeImmutable {
            if (!$this->exists()) {
                throw new NonexistentDirectoryException("The directory '{$this->path}' no longer exists.");
            }
            $time = filemtime($this->path);
            if ($time === false) {
                throw new FilesystemException("Unable to read last modified time for: {$this->path}");
            }
            return (new DateTimeImmutable("@$time"))
                ->setTimezone(new DateTimeZone(date_default_timezone_get()));
        }

        /**
         * Returns the total size of this directory and all its contents, in bytes.
         * @return int The total size in bytes.
         */
        public function getSize () : int {
            $size = 0;

            foreach ($this->getFiles() as $file) {
                $size += $file->getSize();
            }

            foreach ($this->getDirectories() as $dir) {
                $size += $dir->getSize();
            }

            return $size;
        }

        /**
         * Check whether a directory contains no children (files or subdirectories).
         * @return bool Whether the directory is empty.
         */
        public function isEmpty () : bool {
            return count($this->getContents()) === 0;
        }

        /**
         * Moves (renames) this directory to `$destination`.
         * The original path becomes invalid after a successful call; use the returned instance.
         * @param string $destination The absolute destination path.
         * @return static The directory at its new location.
         * @throws FilesystemException If the directory cannot be moved.
         */
        public function move (string $destination) : static {
            if (!rename($this->path, $destination)) {
                throw new FilesystemException("Unable to move directory '{$this->path}' to '$destination'.");
            }
            return new static($destination);
        }

        /**
         * Refreshes the files and directories caches of a directory.
         * @return static The directory.
         */
        public function refresh () : static {
            $files = [];
            $dirs = [];

            if (!$this->exists()) {
                $this->cachedFiles = $files;
                $this->cachedDirectories = $dirs;
                return $this;
            }

            $scandir = scandir($this->path);
            if ($scandir === false) return $this;

            foreach ($scandir as $item) {
                if ($item === '.' || $item === '..' || $item === self::VIRTUAL_MANIFEST) continue;

                $fullPath = $this->path . DIRECTORY_SEPARATOR . $item;

                if (is_file($fullPath)) {
                    $files[] = new LocalFile($fullPath);
                } elseif (is_dir($fullPath)) {
                    $dirs[] = new LocalDirectory($fullPath);
                }
            }

            # Handle virtual descriptor
            $virtualFile = $this->path . DIRECTORY_SEPARATOR . self::VIRTUAL_MANIFEST;
            if (is_file($virtualFile)) {
                $json = @file_get_contents($virtualFile);
                if ($json !== false) {
                    $virtualData = json_decode($json, true);
                    if (is_array($virtualData)) {
                        foreach ($virtualData as $v) {
                            if (!isset($v["path"])) continue;
                            $vPath = $v["path"];
                            $vType = $v["type"] ?? "file";
                            if ($vType === "file") {
                                $files[] = new ProxyFile($vPath);
                            }
                            elseif ($vType === "directory") {
                                $dirs[] = new LocalDirectory($vPath);
                            }
                        }
                    }
                }
            }

            $this->cachedFiles = $files;
            $this->cachedDirectories = $dirs;

            return $this;
        }

        /**
         * Removes a resource from this directory and deletes it from disk.
         * Virtual files are detached from the manifest rather than deleted.
         * @param FileResource|int|string $item The resource, its index, or its base name.
         * @throws FilesystemException If the directory does not exist.
         * @throws ResourceNotMemberException If the resource does not belong to this directory.
         * @return static The directory.
         */
        public function remove (FileResource|int|string $item) : static {
            if (!$this->exists()) {
                throw new FilesystemException("Directory does not exist");
            }

            # Resolve the item by index or base name if not already a resource instance.
            if (!($item instanceof FileResource)) {
                $resolved = $this->getFile($item) ?? $this->getDirectory($item);

                if ($resolved === null) return $this;

                $item = $resolved;
            }

            # Safety check: Ensure the resource belongs to this directory.
            if (dirname($item->getPath()) !== $this->path) {
                throw new ResourceNotMemberException("Resource does not belong to this directory.");
            }

            # Virtual file: detach from the manifest only; do not delete from disk.
            if ($item instanceof VirtualFile) {
                $this->detachVirtualFile($item);
                return $this;
            }

            $item->delete();

            return $this;
        }

        /**
         * Searches this directory for resources matching a glob pattern.
         * @param string $pattern The glob pattern to match against resource base names.
         * @param bool $recursive Whether to include nested subdirectories in the search [default: `true`].
         * @return array<int, LocalFileResource|LocalDirectoryResource> The matching resources.
         */
        public function search (string $pattern, bool $recursive = true) : array {
            if (!$this->exists()) {
                throw new NonexistentDirectoryException("The directory '{$this->path}' no longer exists.");
            }

            $results = [];
            $root = rtrim($this->path, DIRECTORY_SEPARATOR);

            $this->searchRecursive($root, $pattern, $recursive, $results);

            return $results;
        }
    }
?>
<?php
    /**
     * Project Name:    Wingman Explorer - Virtual Directory
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
    use JsonSerializable;
    use Wingman\Explorer\Interfaces\Resources\DirectoryResource;
    use Wingman\Explorer\Interfaces\Resources\FileResource;
    use Wingman\Explorer\Interfaces\Resources\Resource;
    use Wingman\Explorer\Interfaces\Resources\VirtualDirectoryResource;

    /**
     * Represents a virtual in-memory directory that is not backed by any physical filesystem.
     *
     * Virtual directories are used to model tree structures in memory, such as those
     * built by {@see VirtualTreeCompiler}. They support adoption, searching, and
     * content manipulation without touching the real filesystem.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class VirtualDirectory extends Directory implements JsonSerializable, VirtualDirectoryResource {
        /**
         * The contents of the directory, keyed by resource name.
         * @var array<string, FileResource|DirectoryResource>
         */
        protected array $contents = [];

        /**
         * The name of the virtual directory.
         * @var string
         */
        protected string $name;

        /**
         * The parent directory, if any.
         * @var DirectoryResource|null
         */
        protected ?DirectoryResource $parent;

        /**
         * Creates a new virtual directory.
         * @param string $name The name of the directory.
         * @param array<string, FileResource|DirectoryResource> $contents The initial contents keyed by resource name.
         * @param DirectoryResource|null $parent The parent directory, if any.
         */
        public function __construct (string $name, array $contents = [], ?DirectoryResource $parent = null) {
            $this->contents = $contents;
            $this->name = $name;
            $this->parent = $parent;
        }

        /**
         * Returns the directory name and contents for serialisation.
         *
         * The parent reference is intentionally excluded to prevent circular reference loops.
         * When deserialised, the parent is null; re-attach it manually if needed.
         * @return array The serialised data.
         */
        public function __serialize () : array {
            return ["name" => $this->name, "contents" => $this->contents];
        }

        /**
         * Restores the directory state from the given serialised data.
         *
         * The parent is set to null; re-attach it manually after deserialisation if required.
         * @param array $data The serialised data.
         */
        public function __unserialize (array $data) : void {
            $this->name = $data["name"];
            $this->contents = $data["contents"];
            $this->parent = null;
        }

        /**
         * Adds a resource to this virtual directory under the given or derived name.
         * @param Resource $resource The resource to add.
         * @param string|null $newName An optional override name; defaults to the resource's base name.
         * @param bool $move Unused for virtual directories.
         * @return Resource The added resource.
         */
        public function add (Resource $resource, ?string $newName = null, bool $move = false) : Resource {
            $name = $newName ?? $resource->getBaseName();
            $this->contents[$name] = $resource;
            return $resource;
        }

        /**
         * Adopts an existing file resource into this virtual directory.
         * @param FileResource $file The file to adopt.
         * @param string|null $newName An optional override name; defaults to the file's base name.
         * @param bool $move Unused for virtual directories.
         * @return FileResource The adopted file.
         */
        public function adoptFile (FileResource $file, ?string $newName = null, bool $move = false) : FileResource {
            $name = $newName ?? $file->getBaseName();
            $this->contents[$name] = $file;
            return $file;
        }

        /**
         * Checks whether this virtual directory exists. Virtual directories always exist.
         * @return bool Whether the directory exists.
         */
        public function exists () : bool {
            return true;
        }

        /**
         * Gets the base name of this virtual directory.
         * @return string The base name.
         */
        public function getBaseName () : string {
            return $this->name;
        }

        /**
         * Gets all resources contained in this directory, indexed by name.
         * @return array<string, FileResource|DirectoryResource> The directory contents.
         */
        public function getContents () : array {
            return $this->contents;
        }

        /**
         * Gets a child directory by integer index (among directories only) or by base name.
         * @param int|string $indexOrBaseName The zero-based index among child directories, or the base name.
         * @return static|null The matching child directory, or null if not found.
         */
        public function getDirectory (int|string $indexOrBaseName) : ?static {
            $dirs = array_values(array_filter($this->contents, fn ($item) => $item instanceof DirectoryResource));

            if (is_int($indexOrBaseName)) {
                return $dirs[$indexOrBaseName] ?? null;
            }

            foreach ($dirs as $dir) {
                if ($dir->getBaseName() === $indexOrBaseName) return $dir;
            }

            return null;
        }

        /**
         * Gets the most recent last-modified timestamp across all contained resources.
         * @return DateTimeImmutable The last-modified timestamp.
         */
        public function getLastModified () : DateTimeImmutable {
            if (empty($this->contents)) {
                return new DateTimeImmutable();
            }

            return max(array_map(fn (mixed $item) => $item->getLastModified(), $this->contents));
        }

        /**
         * Gets synthetic metadata derived from this directory's own state.
         * @return array The metadata array.
         */
        public function getMetadata () : array {
            return [
                "name" => $this->name,
                "path" => $this->getPath(),
                "size" => $this->getSize(),
                "last_modified" => $this->getLastModified(),
                "children" => count($this->contents)
            ];
        }

        /**
         * Gets the name of this virtual directory, identical to its base name.
         * @return string The directory name.
         */
        public function getName () : string {
            return $this->name;
        }

        /**
         * Gets the parent directory.
         * @return DirectoryResource|null The parent, or null if this is a root virtual directory.
         */
        public function getParent () : ?DirectoryResource {
            return $this->parent;
        }

        /**
         * Gets the virtual path by traversing the parent chain.
         * @return string The virtual path.
         */
        public function getPath () : string {
            if ($this->parent) {
                return $this->parent->getPath() . "/" . $this->name;
            }
            return $this->name;
        }

        /**
         * Gets the total size in bytes of all contained resources.
         * @return int The total size in bytes.
         */
        public function getSize () : int {
            return array_sum(array_map(fn (mixed $item) => $item->getSize(), $this->contents));
        }

        /**
         * Returns a JSON-serialisable representation of this virtual directory.
         * Each child resource that implements {@see JsonSerializable} is serialised recursively;
         * others are cast to string.
         * @return mixed The JSON-serialisable data.
         */
        public function jsonSerialize () : mixed {
            return [
                "type" => "virtual_directory",
                "name" => $this->name,
                "contents" => array_map(
                    fn (mixed $item) => $item instanceof JsonSerializable ? $item->jsonSerialize() : (string) $item,
                    $this->contents
                )
            ];
        }

        /**
         * Removes a resource from this directory by reference, by name, or by file index.
         * @param FileResource|int|string $resource The resource reference, its name key, or its zero-based index among files.
         * @return static The directory.
         */
        public function remove (FileResource|int|string $resource) : static {
            if ($resource instanceof FileResource) {
                $this->contents = array_filter($this->contents, fn ($item) => $item !== $resource);
                return $this;
            }

            if (is_string($resource)) {
                unset($this->contents[$resource]);
                return $this;
            }

            $fileKeys = array_keys(array_filter($this->contents, fn ($item) => $item instanceof FileResource));

            if (isset($fileKeys[$resource])) {
                unset($this->contents[$fileKeys[$resource]]);
            }

            return $this;
        }

        /**
         * Searches for resources whose names match a glob pattern.
         * @param string $pattern The glob pattern to match against resource names.
         * @param bool $recursive Whether to recurse into child virtual directories.
         * @return Resource[] The matching resources.
         */
        public function search (string $pattern, bool $recursive = true) : array {
            $matches = [];

            foreach ($this->contents as $name => $item) {
                if (fnmatch($pattern, $name)) {
                    $matches[] = $item;
                }

                if ($recursive && $item instanceof VirtualDirectory) {
                    array_push($matches, ...$item->search($pattern, true));
                }
            }

            return $matches;
        }
    }
?>
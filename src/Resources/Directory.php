<?php
    /**
     * Project Name:    Wingman Explorer - Directory
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
    use Wingman\Explorer\Interfaces\Resources\DirectoryResource;
    use Wingman\Explorer\Interfaces\Resources\FileResource;

    /**
     * Represents a directory.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    abstract class Directory implements DirectoryResource {
        /**
         * The contents of a directory.
         * @var array<int, FileResource|DirectoryResource>
         */
        protected array $contents = [];

        /**
         * The name of a directory.
         * @var string
         */
        protected string $name;

        /**
         * The path of a directory.
         * @var string|null
         */
        protected string $path;

        /**
         * The parent directory of a file.
         * @var string|null
         */
        protected ?string $parentDirectory = null;

        /**
         * Creates a new directory resource at the specified path.
         * @param string $path The path of the directory.
         */
        public function __construct (string $path) {
            if ($path == '/') {
                $parentDirectory = null;
            }
            else {
                $path = rtrim($path, '/');
                $parentDirectory = dirname($path);
            }
            
            $this->path = $path;
            $this->parentDirectory = $parentDirectory;
        }

        /**
         * Creates a new directory resource at the specified path.
         * @param string $path The path of the directory.
         * @return static The directory.
         */
        public static function at (string $path) : static {
            return new static($path);
        }

        /**
         * Checks whether a directory exists.
         * @return bool Whether the directory exists.
         */
        abstract public function exists () : bool;

        /**
         * Gets the base name of a directory (name + extension).
         * @return string The base name of the directory.
         */
        public function getBaseName () : string {
            return basename($this->path);
        }

        /**
         * Gets the entire content of a directory resource as an array of file and directory resources.
         * ⚠ May load the whole directory resource into memory.
         * @return array<int, FileResource|DirectoryResource> The contents of the directory.
         */
        abstract public function getContents () : array;
        
        /**
         * Gets the directories contained in a directory.
         * @return array<int, DirectoryResource> An array of directory resources.
         */
        public function getDirectories () : array {
            $dirs = [];
            foreach ($this->contents as $item) {
                if ($item instanceof DirectoryResource) {
                    $dirs[] = $item;
                }
            }
            return $dirs;
        }

        /**
         * Gets a directory by index or by base name.
         * @param int|string $key The index (int) or base name (string).
         * @return static|null The directory if found, null otherwise.
         */
        public function getDirectory (int|string $key) : ?static {
            $dirs = $this->getDirectories();

            if (is_int($key)) {
                return $dirs[$key] ?? null;
            }

            foreach ($dirs as $dir) {
                if ($dir->getBaseName() === $key) {
                    return $dir;
                }
            }

            return null;
        }

        /**
         * Gets a file by index or by base name.
         * @param int|string $key The index (int) or file name (string).
         * @return FileResource|null The file if found, null otherwise.
         */
        public function getFile (int|string $key) : ?FileResource {
            $files = $this->getFiles();

            if (is_int($key)) {
                return $files[$key] ?? null;
            }

            if (is_string($key)) {
                foreach ($files as $file) {
                    if ($file->getBaseName() === $key) {
                        return $file;
                    }
                }
            }

            return null;
        }

        /**
         * Gets the files contained in a directory.
         * @return array<int, FileResource> An array of file resources.
         */
        public function getFiles () : array {
            $files = [];
            foreach ($this->contents as $item) {
                if ($item instanceof FileResource) {
                    $files[] = $item;
                }
            }
            return $files;
        }

        /**
         * Gets the last modified date of a directory.
         * @return DateTimeImmutable The last modified date of the directory.
         */
        abstract public function getLastModified () : DateTimeImmutable;

        /**
         * Gets the name of the directory.
         * @return string The name of the directory.
         */
        public function getName () : string {
            return basename($this->path);
        }

        /**
         * Gets the parent directory resource of this directory, if any.
         * @return DirectoryResource|null The parent directory resource, or null if this is a root directory.
         */
        abstract public function getParent () : ?DirectoryResource;

        /**
         * Gets the parent directory path of this directory, if any.
         * @return string|null The parent directory path, or null if this is a root directory.
         */
        public function getParentDirectory () : ?string {
            return $this->parentDirectory;
        }

        /**
         * Gets the full absolute path of the directory.
         * @return string The full absolute path of the directory.
         */
        public function getPath () : string {
            return $this->path;
        }

        /**
         * Gets the size of a directory resource in bytes.
         * @return int The size of the directory resource in bytes.
         */
        abstract public function getSize () : int;
    }
?>
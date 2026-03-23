<?php
    /**
     * Project Name:    Wingman Explorer - Directory Resource
     * Created by:      Angel Politis
     * Creation Date:   Dec 14 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Interfaces.Resources namespace.
    namespace Wingman\Explorer\Interfaces\Resources;

    # Import the following classes to the current scope.
    use DateTimeImmutable;

    /**
     * Represents a directory resource.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface DirectoryResource extends Resource {
        /**
         * Adopts a resource in a directory resource.
         * @param string $name The name of the resource in the directory.
         * @return Resource The file resource.
         */
        public function add (Resource $resource, ?string $newName = null, bool $move = false) : Resource;

        /**
         * Gets the basename (last segment of path).
         * @return string The basename of the directory.
         */
        public function getBaseName () : string;
        
        /**
         * Gets the directories and files in a directory, with directories coming first, then files, both alphabetically by name.
         * @return array<FileResource|static> The contents of this directory.
         */
        public function getContents () : array;
    
        /**
         * Gets a file in a directory by index or base name.
         * @param int|string $indexOrBaseName The index or base name of the file.
         * @return FileResource|null The file, if any.
         */
        public function getFile (int|string $indexOrBaseName) : ?FileResource;
    
        /**
         * Gets the files in a directory.
         * @return FileResource[] An array of child file resources.
         */
        public function getFiles () : array;
    
        /**
         * Gets a directory in a directory by index or base name.
         * @param int|string $indexOrBaseName The index or base name of the file.
         * @return static|null The file, if any.
         */
        public function getDirectory (int|string $indexOrBaseName) : ?static;
    
        /**
         * Gets the directories in a directory.
         * @return static[] An array of child directory resources.
         */
        public function getDirectories () : array;
    
        /**
         * Gets the last modified timestamp of the directory.
         * @return DateTimeImmutable The last modified timestamp of the directory.
         */
        public function getLastModified () : DateTimeImmutable;

        /**
         * Gets the name of a directory {@see static::getBaseName}.
         * @return string The name of the directory.
         */
        public function getName () : string;
        
        /**
         * Gets the full absolute path or URL of the directory.
         * @return string The full absolute path or URL of the directory.
         */
        public function getPath () : string;
    
        /**
         * Gets the size of the directory (sum of all child files, optional).
         * @return int The size of the directory in bytes.
         */
        public function getSize () : int;

        /**
         * Removes a resource from a directory.
         * @param FileResource|int|string $resource The resource to remove.
         * @return static The directory resource.
         */
        public function remove (FileResource|int|string $resource) : static;

        /**
         * Searches recursively for resources matching a glob pattern.
         * @param string $pattern Glob pattern (relative to this directory).
         * @param bool $recursive Whether to search recursively.
         * @return Resource[] The matching resources.
         */
        public function search (string $pattern, bool $recursive = true) : array;
    }
?>
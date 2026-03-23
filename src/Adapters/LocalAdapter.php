<?php
    /**
     * Project Name:    Wingman Explorer - Filesystem Adapter Interface
     * Created by:      Angel Politis
     * Creation Date:   Dec 13 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */
    
    # Use the Explorer.Adapters namespace.
    namespace Wingman\Explorer\Adapters;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\FilesystemException;
    use Wingman\Explorer\Interfaces\Adapters\DirectoryFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\MovableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\PermissionFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\ReadableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\SymlinkFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\WritableFilesystemAdapterInterface;

    /**
     * Represents a local filesystem adapter.
     * @package Wingman\Explorer\Adapters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class LocalAdapter implements
        ReadableFilesystemAdapterInterface,
        WritableFilesystemAdapterInterface,
        MovableFilesystemAdapterInterface,
        DirectoryFilesystemAdapterInterface,
        PermissionFilesystemAdapterInterface,
        SymlinkFilesystemAdapterInterface
    {
        /**
         * Recursively deletes a directory and its contents.
         * @param string $dir The directory path.
         * @return bool Whether the operation was successful.
         */
        protected function deleteDirectoryRecursive (string $dir) : bool {
            $items = scandir($dir);
            if ($items === false) return false;

            foreach ($items as $item) {
                if ($item === '.' || $item === "..") continue;

                $path = $dir . DIRECTORY_SEPARATOR . $item;
                is_dir($path) && !is_link($path)
                    ? $this->deleteDirectoryRecursive($path)
                    : @unlink($path);
            }

            return @rmdir($dir);
        }

        /**
         * Copies a file from one local path to another.
         * @param string $source The source path.
         * @param string $destination The destination path.
         * @throws FilesystemException If the copy operation fails.
         */
        public function copy (string $source, string $destination) : void {
            if (!@copy($source, $destination)) {
                throw new FilesystemException("Copy failed: {$source} → {$destination}");
            }
        }

        /**
         * Creates a file at the given path with optional initial content.
         * @param string $path The path of the file to create.
         * @param string $content The initial content of the file.
         * @throws FilesystemException If the file cannot be written.
         */
        public function create (string $path, string $content = "") : void {
            $this->write($path, $content);
        }

        /**
         * Creates a directory at the given path.
         * @param string $path The path of the directory to create.
         * @param bool $recursive Whether to create all parent directories as needed.
         * @param int $permissions The permissions to set on the created directory.
         * @throws FilesystemException If the directory cannot be created.
         * @return bool Whether the directory was successfully created.
         */
        public function createDirectory (string $path, bool $recursive = false, int $permissions = 0775) : bool{
            if (is_dir($path)) {
                return true;
            }

            if (!@mkdir($path, $permissions, $recursive) && !is_dir($path)) {
                throw new FilesystemException("Failed to create directory: {$path}");
            }

            return true;
        }

        /**
         * Deletes a file or directory at the given path.
         * @param string $path The path of the resource to delete.
         * @return bool Whether the resource was successfully deleted.
         */
        public function delete (string $path) : bool {
            if (!file_exists($path)) {
                return true;
            }

            if (is_dir($path)) {
                return $this->deleteDirectoryRecursive($path);
            }

            return @unlink($path);
        }

        /**
         * Checks whether a resource exists at the given path.
         * @param string $path The path to check.
         * @return bool Whether the resource exists.
         */
        public function exists (string $path) : bool {
            return file_exists($path);
        }

        /**
         * Returns metadata about a local resource.
         * @param string $path The path of the resource.
         * @param string[]|null $properties The specific properties to retrieve, or null for all defaults.
         * @throws FilesystemException If the path does not exist.
         * @return array<string, mixed> The requested metadata.
         */
        public function getMetadata (string $path, ?array $properties = null) : array {
            if (!file_exists($path)) {
                throw new FilesystemException("Path does not exist: {$path}");
            }

            $result = [];
            
            $properties ??= ["path", "name", "type", "hidden", "size", "accessed", "modified", "created", "owner", "group", "permissions"];

            foreach ($properties as $prop) {
                switch ($prop) {
                    case "path":
                        $result["path"] = $path;
                        break;
                    case "name":
                        $result["name"] = basename($path);
                        break;
                    case "type":
                        $result["type"] = is_link($path) ? "link" : (is_dir($path) ? "dir" : "file");
                        break;
                    case "hidden":
                        $result["hidden"] = str_starts_with(basename($path), '.');
                        break;
                    case "size":
                        $result["size"] = is_file($path) ? filesize($path) : null;
                        break;
                    case "accessed":
                        $result["accessed"] = fileatime($path);
                        break;
                    case "modified":
                        $result["modified"] = filemtime($path);
                        break;
                    case "created":
                        $result["created"] = filectime($path);
                        break;
                    case "owner":
                        $result["owner"] = fileowner($path);
                        break;
                    case "group":
                        $result["group"] = filegroup($path);
                        break;
                    case "permissions":
                        $result["permissions"] = substr(sprintf('%o', fileperms($path)), -4);
                        break;
                    default:
                        $result[$prop] = null;
                        break;
                }
            }

            return $result;
        }

        /**
         * Lists the contents of a local directory.
         * @param string $path The path of the directory to list.
         * @throws FilesystemException If the path is not a directory.
         * @return iterable<string> An iterable of child resource paths.
         */
        public function list (string $path) : iterable {
            if (!is_dir($path)) {
                throw new FilesystemException("Not a directory: {$path}");
            }

            foreach (scandir($path) as $entry) {
                if ($entry === '.' || $entry === "..") continue;
                yield $path . DIRECTORY_SEPARATOR . $entry;
            }
        }

        /**
         * Moves a resource from one local path to another.
         * @param string $source The source path.
         * @param string $destination The destination path.
         * @throws FilesystemException If the move operation fails.
         */
        public function move (string $source, string $destination) : void {
            if (!@rename($source, $destination)) {
                throw new FilesystemException("Move failed: {$source} → {$destination}");
            }
        }

        /**
         * Reads and returns the full contents of a local file.
         * @param string $path The path of the file to read.
         * @throws FilesystemException If the file cannot be found or read.
         * @return string The file contents.
         */
        public function read (string $path) : string {
            if (!is_file($path)) {
                throw new FilesystemException("File not found: {$path}");
            }

            $data = @file_get_contents($path);
            if ($data === false) {
                throw new FilesystemException("Failed to read file: {$path}");
            }

            return $data;
        }

        /**
         * Renames a resource by delegating to {@see move()}.
         * @param string $source The current path.
         * @param string $destination The new path.
         * @throws FilesystemException If the underlying move fails.
         */
        public function rename (string $source, string $destination) : void {
            $this->move($source, $destination);
        }

        /**
         * Reads and returns the target of a symbolic link.
         * @param string $path The path of the symbolic link to resolve.
         * @throws FilesystemException If the path is not a symbolic link or cannot be resolved.
         * @return string The target path of the symbolic link.
         */
        public function readlink (string $path) : string {
            if (!is_link($path)) {
                throw new FilesystemException("Not a symbolic link: {$path}");
            }

            $target = readlink($path);

            if ($target === false) {
                throw new FilesystemException("Failed to resolve symbolic link: {$path}");
            }

            return $target;
        }

        /**
         * Writes content to a local file atomically, creating it if it does not exist.
         * @param string $path The path of the file to write.
         * @param string $content The content to write.
         * @throws FilesystemException If the file cannot be written or the atomic rename fails.
         */
        public function write (string $path, string $content) : void {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                $this->createDirectory($dir, true);
            }

            $tmp = $dir . "/." . uniqid("write_", true);
            if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
                throw new FilesystemException("Failed to write temp file: {$tmp}");
            }

            if (!@rename($tmp, $path)) {
                @unlink($tmp);
                throw new FilesystemException("Atomic rename failed: {$path}");
            }
        }

        /**
         * Changes the permissions of a resource.
         * @param string $path The path of the resource.
         * @param int $permissions The octal permissions to apply.
         * @throws FilesystemException If the chmod operation fails.
         */
        public function chmod (string $path, int $permissions) : void {
            if (!@chmod($path, $permissions)) {
                throw new FilesystemException("chmod failed on: {$path}");
            }
        }

        /**
         * Changes the owner of a resource.
         * @param string $path The path of the resource.
         * @param int|string $owner The new owner (user name or UID).
         * @throws FilesystemException If the chown operation fails.
         */
        public function chown (string $path, int|string $owner) : void {
            if (!@chown($path, $owner)) {
                throw new FilesystemException("chown failed on: {$path}");
            }
        }

        /**
         * Changes the group ownership of a resource.
         * @param string $path The path of the resource.
         * @param int|string $group The new group (group name or GID).
         * @throws FilesystemException If the chgrp operation fails.
         */
        public function chgrp (string $path, int|string $group) : void {
            if (!@chgrp($path, $group)) {
                throw new FilesystemException("chgrp failed on: {$path}");
            }
        }

        /**
         * Creates a symbolic link pointing to the given target.
         * @param string $target The path the symbolic link should point to.
         * @param string $link The path at which the symbolic link will be created.
         * @throws FilesystemException If the symlink cannot be created.
         */
        public function symlink (string $target, string $link) : void {
            if (!@symlink($target, $link)) {
                throw new FilesystemException("Failed to create symbolic link '{$link}' → '{$target}'");
            }
        }

        /**
         * Checks whether the given path is a symbolic link.
         * @param string $path The path to check.
         * @return bool Whether the path is a symbolic link.
         */
        public function isSymlink (string $path) : bool {
            return is_link($path);
        }
    }
?>
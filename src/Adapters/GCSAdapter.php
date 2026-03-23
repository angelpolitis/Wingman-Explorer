<?php
    /**
     * Project Name:    Wingman Explorer - Google Cloud Services Adapter
     * Created by:      Angel Politis
     * Creation Date:   Dec 21 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */
    
    # Use the Explorer.Adapters namespace.
    namespace Wingman\Explorer\Adapters;

    # Import the following classes to the current scope.
    use Google\Cloud\Storage\StorageClient;
    use Google\Cloud\Core\Exception\GoogleException;
    use Wingman\Explorer\Exceptions\FilesystemException;
    use Wingman\Explorer\Interfaces\Adapters\CloudAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\DirectoryFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\MovableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\ReadableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\WritableFilesystemAdapterInterface;

    /**
     * Represents a Google Cloud Storage filesystem adapter.
     * @package Wingman\Explorer\Adapters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class GCSAdapter implements
        CloudAdapterInterface,
        DirectoryFilesystemAdapterInterface,
        MovableFilesystemAdapterInterface,
        ReadableFilesystemAdapterInterface,
        WritableFilesystemAdapterInterface
    {
        /**
         * The GCS storage client.
         * @var StorageClient
         * @disregard P1009
         */
        private StorageClient $client;

        /**
         * The GCS bucket name.
         * @var object
         */
        protected object $bucket;

        /**
         * Creates a new adapter.
         * @param array $config The GCS client configuration.
         * @param string $bucket The GCS bucket name.
         * @throws FilesystemException If the the Google Cloud SDK for PHP is not installed.
         */
        public function __construct (array $config, string $bucket) {
            /** @disregard P1009 */
            if (!class_exists(StorageClient::class)) {
                throw new FilesystemException("Google Cloud SDK for PHP is not installed. The adapter cannot be used.");
            }

            /** @disregard P1009 */
            $this->client = new StorageClient($config);
            $this->bucket = $this->client->bucket($bucket);
        }

        /**
         * Recursively deletes all objects stored under a given prefix by listing every object with
         * that prefix and deleting each one individually.
         * @param string $path The directory path whose objects should be deleted.
         * @throws FilesystemException If any object deletion fails.
         * @return bool Whether all objects were deleted.
         */
        private function deleteDirectoryRecursive (string $path) : bool {
            $prefix = rtrim(ltrim($path, '/'), '/') . '/';

            /** @disregard P1009 */
            try {
                $objects = $this->bucket->objects(["prefix" => $prefix]);
                foreach ($objects as $obj) {
                    $obj->delete();
                }
                return true;
            }
            catch (GoogleException $e) {
                throw new FilesystemException("Failed to delete GCS directory: $path. " . $e->getMessage());
            }
        }

        /**
         * Copies an object from one path to another within the same bucket.
         * @param string $source The source object path.
         * @param string $destination The destination object path.
         * @throws FilesystemException If the object cannot be copied.
         */
        public function copy (string $source, string $destination) : void {
            $object = $this->bucket->object(ltrim($source, '/'));
            if (!$object->exists()) {
                throw new FilesystemException("GCS object not found for copy: {$source}");
            }
            /** @disregard P1009 */
            try {
                $object->copy($this->bucket, ['name' => ltrim($destination, '/')]);
            } catch (GoogleException $e) {
                throw new FilesystemException("Failed to copy GCS object from $source to $destination. " . $e->getMessage());
            }
        }

        /**
         * Creates an object at the given path with optional initial content.
         * @param string $path The path of the object to create.
         * @param string $content The initial content of the object.
         * @throws FilesystemException If the object cannot be written.
         */
        public function create (string $path, string $content = "") : void {
            $this->write($path, $content);
        }

        /**
         * Creates an emulated directory by uploading a zero-byte placeholder object with a trailing slash.
         * Google Cloud Storage has no native directory concept. The <code>$recursive</code> and
         * <code>$permissions</code> parameters are accepted for interface compatibility but have no effect.
         * @param string $path The emulated directory path.
         * @param bool $recursive Unused; accepted for interface compatibility.
         * @param int $permissions Unused; accepted for interface compatibility.
         * @throws FilesystemException If the placeholder object cannot be created.
         * @return bool Whether the placeholder was successfully created.
         */
        public function createDirectory (string $path, bool $recursive = false, int $permissions = 0775) : bool {
            $key = rtrim(ltrim($path, '/'), '/') . '/';
            /** @disregard P1009 */
            try {
                # GCS has no real directories; uploading zero-byte object as placeholder.
                $this->bucket->upload('', ["name" => $key]);
                return true;
            } catch (GoogleException $e) {
                throw new FilesystemException("Failed to create GCS 'directory': {$path}. " . $e->getMessage());
            }
        }

        /**
         * Deletes an object at the given path, or recursively deletes all objects under a directory
         * prefix when the path ends with a trailing slash.
         * @param string $path The path of the object or directory prefix to delete.
         * @throws FilesystemException If any object cannot be deleted.
         * @return bool Whether the object or all objects in the directory were deleted.
         */
        public function delete (string $path) : bool {
            if (str_ends_with(ltrim($path, '/'), '/')) {
                return $this->deleteDirectoryRecursive($path);
            }

            $object = $this->bucket->object(ltrim($path, '/'));
            if (!$object->exists()) return true;

            /** @disregard P1009 */
            try {
                $object->delete();
                return true;
            }
            catch (GoogleException $e) {
                throw new FilesystemException("Failed to delete GCS object: {$path}. " . $e->getMessage());
            }
        }

        /**
         * Checks whether an object exists at the given path.
         * @param string $path The path to check.
         * @return bool Whether the object exists.
         */
        public function exists (string $path) : bool {
            $object = $this->bucket->object(ltrim($path, '/'));
            return $object->exists();
        }

        /**
         * Returns metadata for the object at the given path.
         * @param string $path The path of the object.
         * @param string[]|null $properties The specific properties to retrieve, or null for all defaults.
         * @throws FilesystemException If the object is not found or metadata cannot be retrieved.
         * @return array<string, mixed> The requested metadata.
         */
        public function getMetadata (string $path, ?array $properties = null) : array {
            $properties ??= ["path", "name", "type", "size", "modified"];
            $object = $this->bucket->object(ltrim($path, '/'));
            if (!$object->exists()) {
                throw new FilesystemException("GCS object not found for metadata: {$path}");
            }

            $info = $object->info();
            $result = [];
            foreach ($properties as $prop) {
                switch ($prop) {
                    case "path":
                        $result["path"] = $path;
                        break;
                    case "name":
                        $result["name"] = basename($path);
                        break;
                    case "type":
                        $result["type"] = str_ends_with(ltrim($path, '/'), '/') ? "dir" : "file";
                        break;
                    case "size":
                        $result["size"] = $info["size"] ?? null;
                        break;
                    case "modified": 
                        $result["modified"] = isset($info["updated"]) ? strtotime($info["updated"]) : null; 
                        break;
                    default: $result[$prop] = null;
                }
            }
            return $result;
        }

        /**
         * Gets the cloud provider name.
         * @return string The cloud provider name.
         */
        public function getProvider () : string {
            return "Google Cloud Storage";
        }

        /**
         * Lists the direct children of the given bucket path. Because the Google Cloud Storage PHP SDK
         * does not expose directory prefixes through its delimiter-based iterator, this method performs
         * a prefix-only listing and manually extracts first-level children, deduplicating subdirectory
         * prefixes. The SDK iterator auto-paginates, so all objects are covered regardless of count.
         * @param string $path The bucket path to list.
         * @throws FilesystemException If the path cannot be listed.
         * @return iterable<string> An iterable of direct child object paths and subdirectory prefixes.
         */
        public function list (string $path) : iterable {
            $prefix = rtrim(ltrim($path, '/'), '/') . '/';

            /** @disregard P1009 */
            try {
                $seen = [];
                $objects = $this->bucket->objects(["prefix" => $prefix]);

                foreach ($objects as $obj) {
                    $name = $obj->name();
                    if ($name === $prefix) continue;

                    $remainder = substr($name, strlen($prefix));
                    $slashPos = strpos($remainder, '/');

                    if ($slashPos === false) {
                        yield $name;
                    } else {
                        $childPrefix = $prefix . substr($remainder, 0, $slashPos + 1);
                        if (!isset($seen[$childPrefix])) {
                            $seen[$childPrefix] = true;
                            yield $childPrefix;
                        }
                    }
                }
            }
            catch (GoogleException $e) {
                throw new FilesystemException("Failed to list GCS directory: {$path}. " . $e->getMessage());
            }
        }

        /**
         * Moves an object by copying it to the destination and deleting the source.
         * GCS does not support native object renaming; this is implemented as copy then delete.
         * @param string $source The source path.
         * @param string $destination The destination path.
         * @throws FilesystemException If the copy or deletion fails.
         */
        public function move (string $source, string $destination) : void {
            $this->copy($source, $destination);
            $this->delete($source);
        }

        /**
         * Reads and returns the full contents of an object.
         * @param string $path The path of the object to read.
         * @throws FilesystemException If the object cannot be read.
         * @return string The object contents.
         */
        public function read (string $path) : string {
            $object = $this->bucket->object(ltrim($path, '/'));
            if (!$object->exists()) {
                throw new FilesystemException("GCS object not found: {$path}");
            }
            /** @disregard P1009 */
            try {
                return $object->downloadAsString();
            }
            catch (GoogleException $e) {
                throw new FilesystemException("Failed to read GCS object: {$path}. " . $e->getMessage());
            }
        }

        /**
         * Renames an object by delegating to {@see move()}.
         * @param string $source The current path.
         * @param string $destination The new path.
         * @throws FilesystemException If the underlying move fails.
         */
        public function rename (string $source, string $destination) : void {
            $this->move($source, $destination);
        }

        /**
         * Writes content to an object, creating it if it does not exist.
         * @param string $path The path of the object to write.
         * @param string $content The content to write.
         * @throws FilesystemException If the object cannot be written.
         */
        public function write (string $path, string $content) : void {
            /** @disregard P1009 */
            try {
                $this->bucket->upload($content, ["name" => ltrim($path, '/')]);
            }
            catch (GoogleException $e) {
                throw new FilesystemException("Failed to write GCS object: {$path}. " . $e->getMessage());
            }
        }
    }
?>
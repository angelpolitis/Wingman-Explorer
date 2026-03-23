<?php
    /**
     * Project Name:    Wingman Explorer - Azure Adapter
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
    use DateTimeInterface;
    use MicrosoftAzure\Storage\Blob\BlobRestProxy;
    use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;
    use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
    use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
    use Wingman\Explorer\Exceptions\FilesystemException;
    use Wingman\Explorer\Interfaces\Adapters\CloudAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\DirectoryFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\MovableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\ReadableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\WritableFilesystemAdapterInterface;

    /**
     * Represents a Microsoft Azure Blob Storage filesystem adapter.
     * @package Wingman\Explorer\Adapters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class AzureAdapter implements
        CloudAdapterInterface,
        DirectoryFilesystemAdapterInterface,
        MovableFilesystemAdapterInterface,
        ReadableFilesystemAdapterInterface,
        WritableFilesystemAdapterInterface
    {
        /**
         * The client of an Azure service.
         * @var BlobRestProxy
         * @disregard P1009
         */
        protected BlobRestProxy $client;

        /**
         * The Azure container name.
         * @var string
         */
        protected string $container;

        /**
         * Creates a new adapter.
         * @param string $connectionString The Azure connection string.
         * @param string $container The Azure container name.
         * @throws FilesystemException If the Azure SDK for PHP is not installed.
         */
        public function __construct (string $connectionString, string $container) {
            /** @disregard P1009 */
            if (!class_exists(BlobRestProxy::class)) {
                throw new FilesystemException("Azure SDK for PHP is not installed. The adapter cannot be used.");
            }

            /** @disregard P1009 */
            $this->client = BlobRestProxy::createBlobService($connectionString);
            $this->container = $container;
        }

        /**
         * Recursively deletes all blobs stored under a given prefix by paginating through all
         * list results and deleting each blob individually.
         * @param string $path The directory path whose blobs should be deleted.
         * @throws FilesystemException If any blob deletion fails.
         * @return bool Whether all blobs were deleted.
         */
        private function deleteDirectoryRecursive (string $path) : bool {
            $prefix = rtrim(ltrim($path, '/'), '/') . '/';

            /** @disregard P1009 */
            $options = new ListBlobsOptions();
            $options->setPrefix($prefix);

            /** @disregard P1009 */
            try {
                do {
                    $result = $this->client->listBlobs($this->container, $options);

                    foreach ($result->getBlobs() as $blob) {
                        $this->client->deleteBlob($this->container, $blob->getName());
                    }

                    $marker = $result->getNextMarker();
                    $options->setMarker($marker);
                } while ($marker !== null && $marker !== '');

                return true;
            }
            catch (ServiceException $e) {
                throw new FilesystemException("Failed to delete Azure directory: $path. " . $e->getMessage());
            }
        }

        /**
         * Copies a blob from one path to another.
         * @param string $source The source path.
         * @param string $destination The destination path.
         * @throws FilesystemException If the blob cannot be copied.
         */
        public function copy (string $source, string $destination) : void {
            /** @disregard P1009 */
            try {
                $url = $this->client->getBlobUrl($this->container, ltrim($source, '/'));
                $this->client->copyBlob($this->container, ltrim($destination, '/'), $url);
            }
            catch (ServiceException $e) {
                throw new FilesystemException("Failed to copy Azure blob from {$source} to {$destination}. " . $e->getMessage());
            }
        }

        /**
         * Creates a blob at the given path with optional initial content.
         * @param string $path The path of the blob to create.
         * @param string $content The initial content of the blob.
         * @throws FilesystemException If the blob cannot be written.
         */
        public function create (string $path, string $content = "") : void {
            $this->write($path, $content);
        }

        /**
         * Creates an emulated directory by uploading a zero-byte placeholder blob with a trailing slash.
         * Azure Blob Storage has no native directory concept. The <code>$recursive</code> and
         * <code>$permissions</code> parameters are accepted for interface compatibility but have no effect.
         * @param string $path The emulated directory path.
         * @param bool $recursive Unused; accepted for interface compatibility.
         * @param int $permissions Unused; accepted for interface compatibility.
         * @throws FilesystemException If the placeholder blob cannot be created.
         * @return bool Whether the placeholder was successfully created.
         */
        public function createDirectory (string $path, bool $recursive = false, int $permissions = 0775) : bool {
            $key = rtrim(ltrim($path, '/'), '/') . '/';

            /** @disregard P1009 */
            try {
                # Azure has no real directories; create zero-byte blob as placeholder.
                $this->client->createBlockBlob($this->container, $key, '');
                return true;
            }
            catch (ServiceException $e) {
                throw new FilesystemException("Failed to create Azure 'directory': $path. " . $e->getMessage());
            }
        }

        /**
         * Deletes a blob at the given path, or recursively deletes all blobs under a directory prefix
         * when the path ends with a trailing slash.
         * @param string $path The path of the blob or directory prefix to delete.
         * @throws FilesystemException If any blob cannot be deleted.
         * @return bool Whether the blob or all blobs in the directory were deleted.
         */
        public function delete (string $path) : bool {
            if (str_ends_with(ltrim($path, '/'), '/')) {
                return $this->deleteDirectoryRecursive($path);
            }

            /** @disregard P1009 */
            try {
                $this->client->deleteBlob($this->container, ltrim($path, '/'));
                return true;
            }
            catch (ServiceException $e) {
                throw new FilesystemException("Failed to delete Azure blob: $path. " . $e->getMessage());
            }
        }

        /**
         * Checks whether a blob exists at the given path.
         * @param string $path The path to check.
         * @return bool Whether the blob exists.
         */
        public function exists (string $path) : bool {
            /** @disregard P1009 */
            try {
                $props = $this->client->getBlobProperties($this->container, ltrim($path, '/'));
                return $props !== null;
            }
            catch (ServiceException $e) {
                return false;
            }
        }

        /**
         * Returns metadata for the blob at the given path.
         * @param string $path The path of the blob.
         * @param string[]|null $properties The specific properties to retrieve, or null for all defaults.
         * @throws FilesystemException If the metadata cannot be retrieved.
         * @return array<string, mixed> The requested metadata.
         */
        public function getMetadata (string $path, ?array $properties = null) : array {
            $properties ??= ["path", "name", "type", "size", "modified"];

            /** @disregard P1009 */
            try {
                $props = $this->client->getBlobProperties($this->container, ltrim($path, '/'));
            }
            catch (ServiceException $e) {
                throw new FilesystemException("Failed to get metadata for Azure blob: $path. " . $e->getMessage());
            }

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
                        $result["size"] = $props->getContentLength();
                        break;
                    case "modified":
                        $modified = $props->getLastModified();
                        if ($modified instanceof DateTimeInterface) {
                            $result["modified"] = $modified->getTimestamp();
                        }
                        elseif (is_string($modified)) {
                            $result["modified"] = strtotime($modified) ?: null;
                        }
                        else $result["modified"] = null;
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
            return "Microsoft Azure";
        }

        /**
         * Lists the blobs within the given container path, paginating transparently through all
         * result pages. The directory placeholder itself is excluded from the results.
         * @param string $path The container path to list.
         * @throws FilesystemException If the path cannot be listed.
         * @return iterable<string> An iterable of blob and prefix paths.
         */
        public function list (string $path) : iterable {
            $prefix = rtrim(ltrim($path, '/'), '/') . '/';

            /** @disregard P1009 */
            $options = new ListBlobsOptions();
            $options->setPrefix($prefix);
            $options->setDelimiter('/');

            /** @disregard P1009 */
            try {
                do {
                    $result = $this->client->listBlobs($this->container, $options);

                    foreach ($result->getBlobs() as $blob) {
                        $name = $blob->getName();
                        if ($name !== $prefix) yield $name;
                    }

                    foreach ($result->getBlobPrefixes() as $dir) {
                        yield $dir->getName();
                    }

                    $marker = $result->getNextMarker();
                    $options->setMarker($marker);
                } while ($marker !== null && $marker !== '');
            }
            catch (ServiceException $e) {
                throw new FilesystemException("Failed to list Azure directory: $path. " . $e->getMessage());
            }
        }

        /**
         * Moves a blob by copying it to the destination and deleting the source.
         * Azure does not support native object renaming; this is implemented as copy then delete.
         * @param string $source The source path.
         * @param string $destination The destination path.
         * @throws FilesystemException If the copy or deletion fails.
         */
        public function move (string $source, string $destination) : void {
            $this->copy($source, $destination);
            $this->delete($source);
        }

        /**
         * Reads and returns the full contents of a blob.
         * @param string $path The path of the blob to read.
         * @throws FilesystemException If the blob cannot be read.
         * @return string The blob contents.
         */
        public function read (string $path) : string {
            /** @disregard P1009 */
            try {
                $result = $this->client->getBlob($this->container, ltrim($path, '/'));
                return stream_get_contents($result->getContentStream());
            }
            catch (ServiceException $e) {
                throw new FilesystemException("Failed to read Azure blob: $path. " . $e->getMessage());
            }
        }

        /**
         * Renames a blob by delegating to {@see move()}.
         * @param string $source The current path.
         * @param string $destination The new path.
         * @throws FilesystemException If the underlying move fails.
         */
        public function rename (string $source, string $destination) : void {
            $this->move($source, $destination);
        }

        /**
         * Writes content to a blob, creating it if it does not exist.
         * @param string $path The path of the blob to write.
         * @param string $content The content to write.
         * @throws FilesystemException If the blob cannot be written.
         */
        public function write (string $path, string $content) : void {
            /** @disregard P1009 */
            try {
                /** @disregard P1009 */
                $options = new CreateBlockBlobOptions();
                $this->client->createBlockBlob($this->container, ltrim($path, '/'), $content, $options);
            }
            catch (ServiceException $e) {
                throw new FilesystemException("Failed to write Azure blob: $path. " . $e->getMessage());
            }
        }
    }
?>
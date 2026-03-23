<?php
    /**
     * Project Name:    Wingman Explorer - S3 Adapter
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
    use Aws\S3\S3Client;
    use Aws\Exception\AwsException;
    use Wingman\Explorer\Exceptions\FilesystemException;
    use Wingman\Explorer\Interfaces\Adapters\DirectoryFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\MovableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\MultipartUploadAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\PresignedUrlAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\ReadableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\WritableFilesystemAdapterInterface;

    /**
     * Represents an Amazon S3 filesystem adapter.
     * @package Wingman\Explorer\Adapters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class S3Adapter implements
        DirectoryFilesystemAdapterInterface,
        MovableFilesystemAdapterInterface,
        MultipartUploadAdapterInterface,
        PresignedUrlAdapterInterface,
        ReadableFilesystemAdapterInterface,
        WritableFilesystemAdapterInterface
    {
        /**
         * The client of an S3 service.
         * @var S3Client
         * @disregard P1009
         */
        protected mixed $client;

        /**
         * The S3 bucket name.
         * @var string
         */
        protected string $bucket;

        /**
         * Creates a new adapter.
         * @param array $config The S3 client configuration.
         * @param string $bucket The S3 bucket name.
         * @throws FilesystemException If the AWS SDK for PHP is not installed.
         */
        public function __construct (array $config, string $bucket) {
            /** @disregard P1009 */
            if (!class_exists(S3Client::class)) {
                throw new FilesystemException("AWS SDK for PHP is not installed. The adapter cannot be used.");
            }

            /** @disregard P1009 */
            $this->client = new S3Client($config);
            $this->bucket = $bucket;
        }

        /**
         * Recursively deletes all objects stored under a given key prefix by listing and batch-deleting
         * every page of results until the prefix is empty.
         * @param string $path The directory path whose objects should be deleted.
         * @throws FilesystemException If any batch deletion fails.
         * @return bool Whether all objects were deleted.
         */
        private function deleteDirectoryRecursive (string $path) : bool {
            $prefix = rtrim(ltrim($path, '/'), '/') . '/';
            $params = ["Bucket" => $this->bucket, "Prefix" => $prefix];

            /** @disregard P1009 */
            try {
                do {
                    $result = $this->client->listObjectsV2($params);

                    if (!empty($result["Contents"])) {
                        $objects = array_map(fn ($obj) => ["Key" => $obj["Key"]], $result["Contents"]);
                        $this->client->deleteObjects([
                            "Bucket" => $this->bucket,
                            "Delete" => ["Objects" => $objects]
                        ]);
                    }

                    unset($params["ContinuationToken"]);
                    if ($result["IsTruncated"] ?? false) {
                        $params["ContinuationToken"] = $result["NextContinuationToken"];
                    }
                } while ($result["IsTruncated"] ?? false);

                return true;
            }
            catch (AwsException $e) {
                throw new FilesystemException("Failed to delete S3 directory: {$path}. " . $e->getMessage());
            }
        }

        /**
         * Aborts a multipart upload session and removes all uploaded parts.
         * @param string $uploadId The upload ID returned by {@see initiateMultipartUpload()}.
         * @param string $path The destination path of the object.
         * @throws FilesystemException If the upload cannot be aborted.
         */
        public function abortMultipartUpload (string $uploadId, string $path) : void {
            /** @disregard P1009 */
            try {
                $this->client->abortMultipartUpload([
                    "Bucket" => $this->bucket,
                    "Key" => ltrim($path, '/'),
                    "UploadId" => $uploadId
                ]);
            }
            catch (AwsException $e) {
                throw new FilesystemException("Failed to abort multipart upload for {$path}. " . $e->getMessage());
            }
        }

        /**
         * Completes a multipart upload by assembling all uploaded parts into a single object.
         * @param string $uploadId The upload ID returned by {@see initiateMultipartUpload()}.
         * @param string $path The destination path of the object.
         * @param array $parts The ordered list of part descriptors returned by {@see uploadPart()}.
         * @throws FilesystemException If the upload cannot be completed.
         */
        public function completeMultipartUpload (string $uploadId, string $path, array $parts) : void {
            /** @disregard P1009 */
            try {
                $this->client->completeMultipartUpload([
                    "Bucket" => $this->bucket,
                    "Key" => ltrim($path, '/'),
                    "UploadId" => $uploadId,
                    "MultipartUpload" => ["Parts" => $parts]
                ]);
            }
            catch (AwsException $e) {
                throw new FilesystemException("Failed to complete multipart upload for {$path}. " . $e->getMessage());
            }
        }

        /**
         * Copies an object from one key to another within the same bucket.
         * @param string $source The source object key.
         * @param string $destination The destination object key.
         * @throws FilesystemException If the object cannot be copied.
         */
        public function copy (string $source, string $destination) : void {
            /** @disregard P1009 */
            try {
                $this->client->copyObject([
                    "Bucket" => $this->bucket,
                    "CopySource" => "{$this->bucket}/" . ltrim($source, '/'),
                    "Key" => ltrim($destination, '/')
                ]);
            }
            catch (AwsException $e) {
                throw new FilesystemException("Failed to copy S3 object from {$source} to {$destination}. " . $e->getMessage());
            }
        }

        /**
         * Creates an object at the given key with optional initial content.
         * @param string $path The key of the object to create.
         * @param string $content The initial content of the object.
         * @throws FilesystemException If the object cannot be written.
         */
        public function create (string $path, string $content = "") : void {
            $this->write($path, $content);
        }

        /**
         * Creates an emulated directory by uploading a zero-byte placeholder object with a trailing slash.
         * Amazon S3 has no native directory concept. The <code>$recursive</code> and
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
                // S3 has no real directories, we create a zero-byte object as a placeholder
                $this->client->putObject([
                    "Bucket" => $this->bucket,
                    "Key" => $key,
                    "Body" => ''
                ]);
                return true;
            }
            catch (AwsException $e) {
                throw new FilesystemException("Failed to create S3 'directory': $path. " . $e->getMessage());
            }
        }

        /**
         * Deletes an object at the given key, or recursively deletes all objects under a directory
         * prefix when the path ends with a trailing slash.
         * @param string $path The key of the object or directory prefix to delete.
         * @throws FilesystemException If the object or any object in the directory cannot be deleted.
         * @return bool Whether the object or all objects in the directory were deleted.
         */
        public function delete (string $path) : bool {
            if (str_ends_with(ltrim($path, '/'), '/')) {
                return $this->deleteDirectoryRecursive($path);
            }

            /** @disregard P1009 */
            try {
                $this->client->deleteObject([
                    "Bucket" => $this->bucket,
                    "Key" => ltrim($path, '/')
                ]);
                return true;
            }
            catch (AwsException $e) {
                throw new FilesystemException("Failed to delete S3 object: $path. " . $e->getMessage());
            }
        }

        /**
         * Checks whether an object exists at the given key.
         * @param string $path The key to check.
         * @throws FilesystemException If the existence check fails.
         * @return bool Whether the object exists.
         */
        public function exists (string $path) : bool {
            /** @disregard P1009 */
            try {
                return $this->client->doesObjectExist($this->bucket, ltrim($path, '/'));
            }
            catch (AwsException $e) {
                throw new FilesystemException("Failed to check existence of S3 object: {$path}. " . $e->getMessage());
            }
        }

        /**
         * Returns metadata for the object at the given key.
         * @param string $path The key of the object.
         * @param string[]|null $properties The specific properties to retrieve, or null for all defaults.
         * @throws FilesystemException If the metadata cannot be retrieved.
         * @return array<string, mixed> The requested metadata.
         */
        public function getMetadata (string $path, ?array $properties = null) : array {
            $properties ??= ["path", "name", "type", "size", "modified"];
            $key = ltrim($path, '/');

            /** @disregard P1009 */
            try {
                $head = $this->client->headObject([
                    "Bucket" => $this->bucket,
                    "Key" => $key
                ]);
            }
            catch (AwsException $e) {
                throw new FilesystemException("Failed to get metadata for S3 object: $path. " . $e->getMessage());
            }

            $result = [];
            foreach ($properties as $prop) {
                switch ($prop) {
                    case "path": $result["path"] = $path; break;
                    case "name": $result["name"] = basename($path); break;
                    case "type": $result["type"] = str_ends_with($key, '/') ? "dir" : "file"; break;
                    case "size": $result["size"] = $head["ContentLength"] ?? null; break;
                    case "modified": $result["modified"] = isset($head["LastModified"]) ? $head["LastModified"]->getTimestamp() : null; break;
                    default: $result[$prop] = null;
                }
            }
            return $result;
        }

        /**
         * Generates a presigned URL granting temporary access to the given object.
         * @param string $path The path to the S3 object.
         * @param int $expiresInSeconds The number of seconds until the URL expires.
         * @param string $method The HTTP method the URL should permit.
         * @throws FilesystemException If the URL cannot be generated.
         * @return string The presigned URL.
         */
        public function getPresignedUrl (string $path, int $expiresInSeconds = 3600, string $method = "GET") : string {
            /** @disregard P1009 */
            try {
                $command = $this->client->getCommand(strtoupper($method) === "GET" ? "GetObject" : "PutObject", [
                    "Bucket" => $this->bucket,
                    "Key" => ltrim($path, '/')
                ]);
                $request = $this->client->createPresignedRequest($command, "+{$expiresInSeconds} seconds");
                return (string) $request->getUri();
            }
            catch (AwsException $e) {
                throw new FilesystemException("Failed to generate presigned URL for {$path}. " . $e->getMessage());
            }
        }

        /**
         * Gets the cloud provider name.
         * @return string The cloud provider name.
         */
        public function getProvider () : string {
            return "Amazon S3";
        }

        /**
         * Begins a multipart upload session for the object at the given path.
         * @param string $path The destination path of the object.
         * @param array $options Additional S3 parameters merged into the request.
         * @throws FilesystemException If the upload session cannot be initiated.
         * @return string The upload ID that must be passed to subsequent multipart calls.
         */
        public function initiateMultipartUpload (string $path, array $options = []) : string {
            /** @disregard P1009 */
            try {
                $result = $this->client->createMultipartUpload(array_merge($options, [
                    "Bucket" => $this->bucket,
                    "Key" => ltrim($path, '/')
                ]));
                return $result["UploadId"];
            }
            catch (AwsException $e) {
                throw new FilesystemException("Failed to initiate multipart upload for {$path}. " . $e->getMessage());
            }
        }

        /**
         * Lists the objects within the given bucket prefix, paginating transparently through all
         * result pages. The prefix placeholder itself is excluded from the results.
         * @param string $path The bucket prefix to list.
         * @throws FilesystemException If the prefix cannot be listed.
         * @return iterable<string> An iterable of object keys and common prefixes.
         */
        public function list (string $path) : iterable {
            $prefix = rtrim(ltrim($path, '/'), '/') . '/';
            $params = [
                "Bucket" => $this->bucket,
                "Prefix" => $prefix,
                "Delimiter" => '/'
            ];

            /** @disregard P1009 */
            try {
                do {
                    $result = $this->client->listObjectsV2($params);

                    foreach ($result["Contents"] ?? [] as $obj) {
                        if ($obj["Key"] !== $prefix) yield $obj["Key"];
                    }

                    foreach ($result["CommonPrefixes"] ?? [] as $dir) {
                        yield $dir["Prefix"];
                    }

                    unset($params["ContinuationToken"]);
                    if ($result["IsTruncated"] ?? false) {
                        $params["ContinuationToken"] = $result["NextContinuationToken"];
                    }
                } while ($result["IsTruncated"] ?? false);
            }
            catch (AwsException $e) {
                throw new FilesystemException("Failed to list S3 directory: $path. " . $e->getMessage());
            }
        }

        /**
         * Moves an object by copying it to the destination key and deleting the source.
         * S3 does not support native object renaming; this is implemented as copy then delete.
         * @param string $source The source key.
         * @param string $destination The destination key.
         * @throws FilesystemException If the copy or deletion fails.
         */
        public function move (string $source, string $destination) : void {
            $this->copy($source, $destination);
            $this->delete($source);
        }

        /**
         * Reads and returns the full contents of an object.
         * @param string $path The key of the object to read.
         * @throws FilesystemException If the object cannot be read.
         * @return string The object contents.
         */
        public function read (string $path) : string {
            /** @disregard P1009 */
            try {
                $result = $this->client->getObject([
                    "Bucket" => $this->bucket,
                    "Key" => ltrim($path, '/')
                ]);
                return (string) $result["Body"];
            }
            catch (AwsException $e) {
                throw new FilesystemException("Failed to read S3 object: $path. " . $e->getMessage());
            }
        }

        /**
         * Renames an object by delegating to {@see move()}.
         * @param string $source The current key.
         * @param string $destination The new key.
         * @throws FilesystemException If the underlying move fails.
         */
        public function rename (string $source, string $destination) : void {
            $this->move($source, $destination);
        }

        /**
         * Uploads a single part of an active multipart upload.
         * @param string $uploadId The upload ID returned by {@see initiateMultipartUpload()}.
         * @param string $path The destination path of the object.
         * @param int $partNumber The 1-based part number (must be between 1 and 10 000).
         * @param string $content The binary content of the part.
         * @throws FilesystemException If the part cannot be uploaded.
         * @return array Part descriptor containing at minimum an <code>ETag</code> and <code>PartNumber</code> key.
         */
        public function uploadPart (string $uploadId, string $path, int $partNumber, string $content) : array {
            /** @disregard P1009 */
            try {
                $result = $this->client->uploadPart([
                    "Bucket" => $this->bucket,
                    "Key" => ltrim($path, '/'),
                    "UploadId" => $uploadId,
                    "PartNumber" => $partNumber,
                    "Body" => $content
                ]);
                return [
                    "ETag" => $result["ETag"],
                    "PartNumber" => $partNumber
                ];
            }
            catch (AwsException $e) {
                throw new FilesystemException("Failed to upload part $partNumber for $path. " . $e->getMessage());
            }
        }

        /**
         * Writes content to an object, creating it if it does not exist.
         * @param string $path The key of the object to write.
         * @param string $content The content to write.
         * @throws FilesystemException If the object cannot be written.
         */
        public function write (string $path, string $content) : void {
            /** @disregard P1009 */
            try {
                $this->client->putObject([
                    "Bucket" => $this->bucket,
                    "Key" => ltrim($path, '/'),
                    "Body" => $content
                ]);
            }
            catch (AwsException $e) {
                throw new FilesystemException("Failed to write S3 object: $path. " . $e->getMessage());
            }
        }
    }
?>
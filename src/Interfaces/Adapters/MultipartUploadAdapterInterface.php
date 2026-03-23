<?php
    /**
     * Project Name:    Wingman Explorer - Multipart Upload Adapter Interface
     * Created by:      Angel Politis
     * Creation Date:   Mar 20 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Interfaces.Adapters namespace.
    namespace Wingman\Explorer\Interfaces\Adapters;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\FilesystemException;

    /**
     * Defines the contract for performing multipart uploads.
     *
     * Multipart uploads allow large objects to be transferred to cloud storage
     * in discrete parts, enabling resumable transfers and parallel uploads.
     * Implementations should follow the three-phase lifecycle: initiate → upload
     * parts → complete (or abort on failure).
     *
     * @package Wingman\Explorer\Interfaces\Adapters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface MultipartUploadAdapterInterface extends CloudAdapterInterface {
        /**
         * Begins a multipart upload session for the object at the given path.
         * @param string $path The destination path of the object.
         * @param array $options Additional provider-specific options (e.g. content type, metadata).
         * @throws FilesystemException If the upload session cannot be initiated.
         * @return string An opaque upload ID that must be passed to subsequent calls.
         */
        public function initiateMultipartUpload (string $path, array $options = []) : string;

        /**
         * Uploads a single part of a multipart upload.
         * @param string $uploadId The upload ID returned by {@see initiateMultipartUpload()}.
         * @param string $path The destination path of the object.
         * @param int $partNumber The 1-based part number (must be between 1 and 10 000).
         * @param string $content The binary content of the part.
         * @throws FilesystemException If the part cannot be uploaded.
         * @return array Provider metadata for the uploaded part (e.g. <code>['ETag' => '...']</code>).
         */
        public function uploadPart (string $uploadId, string $path, int $partNumber, string $content) : array;

        /**
         * Completes a multipart upload by assembling all uploaded parts.
         * @param string $uploadId The upload ID returned by {@see initiateMultipartUpload()}.
         * @param string $path The destination path of the object.
         * @param array $parts The ordered list of part descriptors returned by {@see uploadPart()}.
         * @throws FilesystemException If the upload cannot be completed.
         */
        public function completeMultipartUpload (string $uploadId, string $path, array $parts) : void;

        /**
         * Aborts a multipart upload session and removes any uploaded parts.
         * @param string $uploadId The upload ID returned by {@see initiateMultipartUpload()}.
         * @param string $path The destination path of the object.
         * @throws FilesystemException If the upload cannot be aborted.
         */
        public function abortMultipartUpload (string $uploadId, string $path) : void;
    }
?>
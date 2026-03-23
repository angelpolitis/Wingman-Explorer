<?php
    /**
     * Project Name:    Wingman Explorer - Presigned URL Adapter Interface
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
     * Defines the contract for generating time-limited presigned access URLs.
     *
     * Cloud adapters that support presigned URLs (e.g. Amazon S3, Google Cloud
     * Storage) should implement this interface to allow callers to produce
     * short-lived, unauthenticated download or upload URLs for objects.
     *
     * @package Wingman\Explorer\Interfaces\Adapters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface PresignedUrlAdapterInterface extends CloudAdapterInterface {
        /**
         * Generates a presigned URL that grants temporary access to the resource at the given path.
         * @param string $path The path to the object.
         * @param int $expiresInSeconds The number of seconds until the URL expires.
         * @param string $method The HTTP method the URL should permit (typically "GET" or "PUT").
         * @throws FilesystemException If the URL cannot be generated.
         * @return string The presigned URL.
         */
        public function getPresignedUrl (string $path, int $expiresInSeconds = 3600, string $method = "GET") : string;
    }
?>
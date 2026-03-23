<?php
    /**
     * Project Name:    Wingman Explorer - HTTP Adapter
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
    use Wingman\Explorer\Exceptions\FilesystemException;
    use Wingman\Explorer\Interfaces\Adapters\ReadableFilesystemAdapterInterface;

    /**
     * Represents an HTTP filesystem adapter for read-only access to remote resources over HTTP.
     * @package Wingman\Explorer\Adapters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class HTTPAdapter implements ReadableFilesystemAdapterInterface {
        /**
         * The number of seconds to wait before timing out an HTTP request.
         * @var int
         */
        protected int $timeout;

        /**
         * Creates a new HTTP adapter.
         * @param int $timeout The number of seconds to wait before timing out requests.
         */
        public function __construct (int $timeout = 30) {
            $this->timeout = $timeout;
        }

        /**
         * Validates that a URL uses an allowed scheme (http or https only).
         * @param string $url The URL to validate.
         * @throws FilesystemException If the URL scheme is not http or https.
         */
        private function requireSafeUrl (string $url) : void {
            $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
            if (!in_array($scheme, ["http", "https"], true)) {
                throw new FilesystemException("HTTPAdapter only permits http and https URLs; rejected: $url");
            }
        }

        /**
         * Checks if a remote resource exists at the given URL by issuing a HEAD request and
         * verifying that the final HTTP response carries a 2xx status code.
         * @param string $path The URL of the remote file.
         * @throws FilesystemException If the URL scheme is not permitted.
         * @return bool Whether the resource exists and is reachable.
         */
        public function exists (string $path) : bool {
            $this->requireSafeUrl($path);
            $context = stream_context_create(["http" => ["method" => "HEAD", "timeout" => $this->timeout]]);
            $headers = @get_headers($path, false, $context);
            if (!is_array($headers)) return false;

            $statusCode = 0;
            foreach ($headers as $header) {
                if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                    $statusCode = (int) $matches[1];
                }
            }

            return $statusCode >= 200 && $statusCode < 300;
        }

        /**
         * @throws FilesystemException If metadata cannot be retrieved.
         */
        public function getMetadata (string $path, ?array $properties = null) : array {
            $this->requireSafeUrl($path);
            $result = [];
            $properties ??= ["path", "name", "type", "size", "modified"];

            $needHeaders = array_intersect($properties, ["size", "modified"]);
            $headers = [];
            if ($needHeaders) {
                $context = stream_context_create(["http" => ["timeout" => $this->timeout]]);
                $headers = @get_headers($path, true, $context);
                if (!is_array($headers)) {
                    throw new FilesystemException("Failed to fetch HTTP headers for: $path");
                }
            }

            foreach ($properties as $prop) {
                switch ($prop) {
                    case "path":
                        $result["path"] = $path;
                        break;
                    case "name":
                        $result["name"] = basename(parse_url($path, PHP_URL_PATH));
                        break;
                    case "type":
                        $result["type"] = "file";
                        break;
                    case "size":
                        $raw = $headers["Content-Length"] ?? null;
                        $result["size"] = $raw !== null ? (int) (is_array($raw) ? $raw[0] : $raw) : null;
                        break;
                    case "modified":
                        $raw = $headers["Last-Modified"] ?? null;
                        $result["modified"] = $raw !== null ? strtotime(is_array($raw) ? $raw[0] : $raw) : 0;
                        break;
                    default:
                        $result[$prop] = null;
                        break;
                }
            }

            return $result;
        }

        /**
         * @throws FilesystemException If the file cannot be read.
         */
        public function read (string $path) : string {
            $this->requireSafeUrl($path);
            $context = stream_context_create(["http" => ["timeout" => $this->timeout]]);
            $content = file_get_contents($path, false, $context);

            if ($content === false) {
                throw new FilesystemException("Failed to read remote file: $path");
            }

            return $content;
        }
    }
?>
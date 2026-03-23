<?php
    /**
     * Project Name:    Wingman Explorer - File Utilities
     * Created by:      Angel Politis
     * Creation Date:   Dec 13 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer namespace.
    namespace Wingman\Explorer;

    # Use the required types.
    use Wingman\Explorer\Enums\FileVariant;
    use Wingman\Explorer\Exceptions\HashComputationException;
    use Wingman\Explorer\Exceptions\InvalidDecimalPlacesException;
    use Wingman\Explorer\Exceptions\InvalidFileSizeException;

    /**
     * A static class that groups together various pure file-related operations.
     * @package Wingman\Explorer
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    final class FileUtils {
        /**
         * Ensures the static class cannot be instantiated.
         */
        private function __construct () {}

        /**
         * Gets the MD5 hash of a file at a given path.
         * @param string $path The path to the file.
         * @param bool $binary Whether to return the hash in raw binary format [default: `false`].
         * @throws HashComputationException If the hash cannot be computed.
         * @return string The MD5 hash of the file.
         */
        public static function getMD5 (string $path, bool $binary = false) : string {
            $hash = @md5_file($path, $binary);

            if ($hash === false) {
                throw new HashComputationException("Unable to compute MD5 for: {$path}");
            }

            return $hash;
        }

        /**
         * Produces a different variant of a name (useful for UNIX systems, which are case-sensitive).
         * @param string $name The name.
         * @param FileVariant $variant The variant to use.
         * @return string The transformed name.
         */
        public static function getVariant (string $name, FileVariant $variant) : string {
            return match ($variant) {
                FileVariant::AsIs => $name,
                FileVariant::Uppercase => strtoupper($name),
                FileVariant::Lowercase => strtolower($name),
                FileVariant::Capitalised => ucfirst(strtolower($name)),
                FileVariant::WordsCapitalised => ucwords(strtolower($name))
            };
        }
        
        /**
         * Gets the SHA1 hash of a file at a given path.
         * @param string $path The path to the file.
         * @param bool $binary Whether to return the hash in raw binary format [default: `false`].
         * @throws HashComputationException If the hash cannot be computed.
         * @return string The SHA1 hash of the file.
         */
        public static function getSHA1 (string $path, bool $binary = false) : string {
            $hash = @sha1_file($path, $binary);

            if ($hash === false) {
                throw new HashComputationException("Unable to compute SHA1 for: {$path}");
            }

            return $hash;
        }

        /**
         * Converts raw bytes into a human-readable string (e.g., "1.5 MB").
         * @param int $bytes The file size in bytes.
         * @param int $decimals The number of decimal places to use.
         * @return string The human-readable file size string (e.g., "1.5 MB" or "500 B").
         * @throws InvalidFileSizeException If the byte count is negative.
         * @throws InvalidDecimalPlacesException If the decimal places count is negative.
         */
        public static function getReadableSize (int $bytes, int $decimals = 2) : string {
            if ($bytes < 0) {
                throw new InvalidFileSizeException("File size cannot be negative: $bytes");
            }
            if ($decimals < 0) {
                throw new InvalidDecimalPlacesException("Decimal places cannot be negative: $decimals");
            }

            if ($bytes === 0) return "0 B";

            $units = ["B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"];

            $factor = (int) floor(log($bytes, 1024));
            $factor = min($factor, count($units) - 1);

            $size = $bytes / pow(1024, $factor);
            
            return sprintf("%.{$decimals}f %s", $size, $units[$factor]);
        }
    }
?>
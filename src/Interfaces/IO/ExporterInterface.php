<?php
    /**
     * Project Name:    Wingman Explorer - Exporter Interface
     * Created by:      Angel Politis
     * Creation Date:   Dec 19 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Interfaces.IO namespace.
    namespace Wingman\Explorer\Interfaces\IO;

    /**
     * A class useful for exporting files of various types.
     * @package Wingman\Explorer\Interfaces\IO
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface ExporterInterface {
        /**
         * Imports and parses content.
         * @param mixed $data The data to export.
         * @param string $path The path to the file.
         * @param array $options Additional options for exporting.
         * @return static The exporter instance.
         */
        public function export (mixed $data, string $path, array $options = []) : static;

        /**
         * Gets the confidence level of an exporter for a given file.
         * @param mixed $data The data to evaluate.
         * @param string|null $ext The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return float A confidence score between 0.0 and 1.0.
         */
        public function getConfidence (mixed $data, ?string $extension, ?string $mime) : float;

        /**
         * Prepares data for exporting.
         * @param mixed $data The data to prepare.
         * @param array $options Additional options for preparation.
         * @return string The prepared data as a string.
         */
        public function prepare (mixed $data, array $options = []) : string;

        /**
         * Determines whether an exporter supports a given file.
         * @param mixed $data The data to evaluate.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return bool Whether the exporter supports the file.
         */
        public function supports (mixed $data, ?string $extension = null, ?string $mime = null) : bool;

        /**
         * Determines whether an exporter supports a given file extension.
         * @param string $extension The file extension.
         * @return bool Whether the exporter supports the extension.
         */
        public function supportsExtension (string $extension) : bool;

        /**
         * Determines whether an exporter supports a given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the exporter supports the MIME type.
         */
        public function supportsMime (string $mime) : bool;
    }
?>
<?php
    /**
     * Project Name:    Wingman Explorer - Importer Interface
     * Created by:      Angel Politis
     * Creation Date:   Dec 18 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Interfaces.IO namespace.
    namespace Wingman\Explorer\Interfaces\IO;

    /**
     * A class useful for importing files of various types.
     * @package Wingman\Explorer\Interfaces\IO
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface ImporterInterface {
        /**
         * Gets the confidence level of the importer for a given file.
         * @param string $path The path to the file.
         * @param string|null $ext The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @param string $sample A sample of the file's content.
         * @return float A confidence score between 0.0 and 1.0.
         */
        public function getConfidence (string $path, ?string $extension, ?string $mime, string $sample) : float;
        
        /**
         * Imports and parses content.
         * @param string $path The path to the file.
         * @param array $options Additional options for importing.
         * @return mixed The imported content.
         */
        public function import (string $path, array $options = []) : mixed;

        /**
         * Checks whether an importer supports a given file.
         * @param string $path The path to the file.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return bool True if the importer supports the file, false otherwise.
         */
        public function supports (string $path, ?string $extension = null, ?string $mime = null) : bool;

        /**
         * Determines whether an importer supports a given file extension.
         * @param string $extension The file extension.
         * @return bool Whether the importer supports the extension.
         */
        public function supportsExtension (string $extension) : bool;

        /**
         * Determines whether an importer supports a given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the importer supports the MIME type.
         */
        public function supportsMime (string $mime) : bool;
    }
?>
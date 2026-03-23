<?php
    /**
     * Project Name:    Wingman Explorer - FileResource
     * Created by:      Angel Politis
     * Creation Date:   Dec 14 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Interfaces.Resources namespace.
    namespace Wingman\Explorer\Interfaces\Resources;

    # Import the following classes to the current scope.
    use DateTimeImmutable;
    use Exception;
    use Wingman\Explorer\IO\Stream;

    /**
     * Represents a file resource.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface FileResource extends Resource {
        /**
         * Gets the base name of a file (name + extension).
         * @return string The base name of the file.
         */
        public function getBaseName () : string;

        /**
         * Gets the entire content of a file resource as a string.
         * ⚠ May load the whole file resource into memory.
         */
        public function getContent () : string;

        /**
         * Gets a readable stream for the content of a file resource.
         * @return Stream The content stream of the file resource.
         */
        public function getContentStream () : Stream;

        /**
         * Gets the extension of a file.
         * @return string The extension of the file.
         */
        public function getExtension () : ?string;

        /**
         * Gets the last modified date of a file.
         * @return DateTimeImmutable The last modified date of the file.
         */
        public function getLastModified () : DateTimeImmutable;

        /**
         * Gets the name of a file.
         * @return string|null The name of the file.
         */
        public function getName () : ?string;
        
        /**
         * Gets the full absolute path or URL of a file resource.
         * @return string The full absolute path or URL of a file resource.
         */
        public function getPath () : string;

        /**
         * Gets the size of a file resource.
         * @return int The size of the file resource in bytes.
         * @throws Exception If the size cannot be determined.
         */
        public function getSize () : int;

        /**
         * Renders a file resource as a string.
         * @return string The string representation of the file resource.
         */
        public function render () : string;
    }
?>
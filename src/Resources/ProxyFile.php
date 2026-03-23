<?php
    /**
     * Project Name:    Wingman Explorer - Proxy File
     * Created by:      Angel Politis
     * Creation Date:   Dec 16 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Resources namespace.
    namespace Wingman\Explorer\Resources;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Interfaces\Resources\FileResource;

    /**
     * Represents a proxy file.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ProxyFile extends VirtualFile {
        /**
         * The location of the source file.
         * @var string
         */
        protected string $source;

        /**
         * Creates a new proxy file.
         * @param string $source The location of the source file.
         */
        public function __construct (string $source) {
            $this->source = $source;
        }

        public function getContent () : string {
            return @file_get_contents($this->source) ?: "";
        }

        public function getMetadata () : array {
            return $this->getSourceFile()->getMetadata();
        }

        public function getSize () : int {
            return filesize($this->source) ?: 0;
        }

        /**
         * Gets the location of the source file.
         * @return string The location of the source file.
         */
        public function getSource () : string {
            return $this->source;
        }

        /**
         * Gets the source file.
         * @return FileResource The source file.
         */
        public function getSourceFile () : FileResource {
            return LocalFile::at($this->source);
        }
    }
?>
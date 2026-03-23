<?php
    /**
     * Project Name:    Wingman Explorer - Resource
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
    
    /**
     * Represents a resource.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface Resource {
        /**
         * Creates a new resource that exists in the file system.
         * @param string $path The path where the resource is located.
         * @return Resource The resource.
         */
		public static function at (string $path) : Resource;

        /**
         * Gets the base name of a resource.
         * @return string The base name of the resource.
         */
        public function getBaseName () : string;

        /**
         * Gets whether a resource exists.
         * @return bool Whether the resource exists.
         */
        public function exists () : bool;

        /**
         * Gets the metadata of a resource.
         * @return array<string, mixed> The metadata of the resource.
         */
        public function getMetadata () : array;
    
        /**
         * Gets the parent directory of a resource, if it has one.
         * @return DirectoryResource|null The parent directory resource.
         */
        public function getParent () : ?DirectoryResource;

        /**
         * Gets the path of the parent directory of a resource.
         * @return string The parent directory of the resource.
         */
        public function getParentDirectory () : ?string;
    }
?>
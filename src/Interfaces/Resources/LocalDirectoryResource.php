<?php
    /**
     * Project Name:    Wingman Explorer - Local Directory Resource
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
     * Represents a local directory resource.
     * @package Wingman\Explorer\Interfaces\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface LocalDirectoryResource extends LocalResource, DirectoryResource, CreatableResource {
        /**
         * Adds a resource to a local directory, copying or moving it.
         * @param Resource $resource The resource to add.
         * @param string|null $newName The new name of the resource. If null, the original name is kept.
         * @param bool $move Whether to move the resource instead of copying it.
         * @return Resource The added resource.
         */
        public function add (Resource $resource, ?string $newName = null, bool $move = false) : Resource;
        
        /**
         * Creates a new file resource in a directory resource.
         * @param string $name The name of the file to create.
         * @return LocalFileResource The created file resource.
         */
        public function createFile (string $name) : LocalFileResource;
    }
?>
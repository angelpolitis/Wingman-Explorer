<?php
    /**
     * Project Name:    Wingman Explorer - Creatable Resource
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
     * Represents a resource that can be created/written into.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface CreatableResource {
        /**
         * Creates a new resource and persists it in the file system.
         * @param bool $recursive Whether to create parent directories if they do not exist [default: `true`].
         * @return CreatableResource The new resource.
         */
		public function create (bool $recursive = true) : CreatableResource;

        /**
         * Deletes a local resource from the system it's saved at.
         * @return bool Whether the resource has been deleted.
         */
        public function delete () : bool;
    }
?>
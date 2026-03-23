<?php
    /**
     * Project Name:    Wingman Explorer - Generated File
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
     * Represents a resource that can be hashed.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface HashableResource {
        /**
         * Gets the MD5 hash of a file at a given path.
         * @param bool $binary Whether to return the hash in raw binary format [default: `false`].
         * @return string The MD5 hash of the file.
         */
		public function getMD5 (bool $binary = false) : string;
        
        /**
         * Gets the SHA1 hash of a file at a given path.
         * @param bool $binary Whether to return the hash in raw binary format [default: `false`].
         * @return string The SHA1 hash of the file.
         */
		public function getSHA1 (bool $binary = false) : string;
    }
?>
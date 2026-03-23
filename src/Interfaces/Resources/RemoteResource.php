<?php
    /**
     * Project Name:    Wingman Explorer - Remote Resource
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
    use Wingman\Locator\Objects\URI;

    /**
     * Represents a directory resource.
     * @package Wingman\Explorer\Interfaces\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface RemoteResource extends Resource {
        /**
         * Gets the URI of a resource.
         * @return URI The URI of the resource.
         */
        public function getUri () : URI;
    }
?>
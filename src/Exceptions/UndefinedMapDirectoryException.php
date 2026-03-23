<?php
    /**
     * Project Name:    Wingman Explorer - UndefinedMapDirectory Exception
     * Created by:      Angel Politis
     * Creation Date:   Apr 21 2022
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2022-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Exceptions namespace.
    namespace Wingman\Explorer\Exceptions;

    # Import the following classes to the current scope.
    use RuntimeException;

    class UndefinedMapDirectoryException extends RuntimeException implements ExplorerException {
        /**
         * The default message of an exception.
         * @var string
         */
        protected $message = "The directory isn't defined in the locator map of the specified root.";
    }
?>
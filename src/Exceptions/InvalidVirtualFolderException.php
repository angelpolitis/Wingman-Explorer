<?php
    /**
     * Project Name:    Wingman Explorer - InvalidVirtualFolder Exception
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
    use InvalidArgumentException;

    class InvalidVirtualFolderException extends InvalidArgumentException implements ExplorerException {
        /**
         * The default message of an exception.
         * @var string
         */
        protected $message = "The value isn't valid for a virtual folder.";
    }
?>
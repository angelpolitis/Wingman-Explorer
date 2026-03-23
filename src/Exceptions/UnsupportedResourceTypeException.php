<?php
    /**
     * Project Name:    Wingman Explorer - Unsupported Resource Type Exception
     * Created by:      Angel Politis
     * Creation Date:   Mar 22 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Exceptions namespace.
    namespace Wingman\Explorer\Exceptions;

    # Import the following classes to the current scope.
    use LogicException;

    /**
     * Thrown when a resource type is encountered that is not handled by the
     * current operation (e.g. an unknown subtype in a dispatch branch).
     *
     * @package Wingman\Explorer\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class UnsupportedResourceTypeException extends LogicException implements ExplorerException {}

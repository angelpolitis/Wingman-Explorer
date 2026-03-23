<?php
    /**
     * Project Name:    Wingman Explorer - Scanner Configuration Exception
     * Created by:      Angel Politis
     * Creation Date:   Dec 21 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Exceptions namespace.
    namespace Wingman\Explorer\Exceptions;

    # Import the following classes to the current scope.
    use LogicException;

    /**
     * Thrown when a {@see Scanner} is configured or used incorrectly.
     *
     * This includes scanning without a configured adapter, using an adapter that
     * does not support the required operations, or combining incompatible options.
     * @package Wingman\Explorer\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ScannerConfigurationException extends LogicException implements ExplorerException {}
?>
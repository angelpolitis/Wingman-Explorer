<?php
    /**
     * Project Name:    Wingman Explorer - Unseekable Stream Exception
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

    /**
     * Thrown when a seek operation is attempted on a stream that does not support
     * random access (e.g. network sockets or write-only pipes).
     *
     * @package Wingman\Explorer\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class UnseekableStreamException extends StreamException {
        protected $message = "This stream is unseekable.";
    }
?>
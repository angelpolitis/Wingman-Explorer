<?php
    /**
     * Project Name:    Wingman Explorer - Stream Not Writable Exception
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
     * Thrown when a write operation is attempted on a stream that is not writable,
     * or that has been detached and is no longer usable.
     *
     * @package Wingman\Explorer\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class StreamNotWritableException extends StreamException {
        protected $message = "The stream is not writable.";
    }
?>
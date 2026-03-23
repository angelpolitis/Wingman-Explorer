<?php
    /**
     * Project Name:    Wingman Explorer - Configurable
     * Created by:      Angel Politis
     * Creation Date:   Mar 21 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Bridge.Cortex.Attributes namespace.
    namespace Wingman\Explorer\Bridge\Cortex\Attributes;

    # Guard against double-inclusion (e.g. via symlinked paths resolving to different strings
    # under require_once). If the alias or stub is already in place there is nothing to do.
    if (class_exists(__NAMESPACE__ . '\Configurable', false)) return;

    # If Cortex is available, alias the real attribute to this namespace and exit.
    if (class_exists(\Wingman\Cortex\Attributes\Configurable::class)) {
        class_alias(\Wingman\Cortex\Attributes\Configurable::class, __NAMESPACE__ . '\Configurable');
        return;
    }

    # Import the following classes to the current scope.
    use Attribute;

    /**
     * Marks a property as configurable via a Cortex-compatible configuration source.
     *
     * When Cortex is not installed this is a functional stub that stores its key and description,
     * so that the {@see Configuration} stub's hydration method can resolve property bindings
     * correctly when a non-empty configuration array is provided directly.
     * @package Wingman\Explorer\Bridge\Cortex\Attributes
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute]
    class Configurable {
        /**
         * The dot-notation configuration key this property is bound to.
         * @var string
         */
        private string $description;

        /**
         * A human-readable description of this configuration entry.
         * @var string
         */
        private string $key;

        /**
         * Creates a new configurable attribute.
         * @param string $key The dot-notation configuration key.
         * @param string $description A human-readable description of this configuration entry.
         */
        public function __construct (string $key = "", string $description = "") {
            $this->key = $key;
            $this->description = $description;
        }

        /**
         * Gets the human-readable description of this configuration entry.
         * @return string The configuration description.
         */
        public function getDescription () : string {
            return $this->description;
        }

        /**
         * Gets the dot-notation configuration key.
         * @return string The configuration key.
         */
        public function getKey () : string {
            return $this->key;
        }
    }
?>
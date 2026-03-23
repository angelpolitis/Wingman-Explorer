<?php
    /**
     * Project Name:    Wingman Explorer - Corvus Emitter Bridge
     * Created by:      Angel Politis
     * Creation Date:   Mar 21 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Bridge.Corvus namespace.
    namespace Wingman\Explorer\Bridge\Corvus;

    # Guard against double-inclusion (e.g. via symlinked paths resolving to different strings
    # under require_once). If the alias or stub is already in place there is nothing to do.
    if (class_exists(__NAMESPACE__ . '\Emitter', false)) return;

    # If Corvus is not installed, define a silent no-op stub so that any code depending on this
    # bridge continues to function without Corvus being a hard dependency of Explorer.
    if (class_exists(\Wingman\Corvus\Emitter::class)) {
        class_alias(\Wingman\Corvus\Emitter::class, __NAMESPACE__ . '\Emitter');
        return;
    }

    # Import the following classes to the current scope.
    use BackedEnum;

    /**
     * A no-op stub used when Corvus is not available.
     * All emission methods return silently without dispatching any signals.
     * @package Wingman\Explorer\Bridge\Corvus
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Emitter {
        /**
         * Creates a new inert emitter; Corvus is unavailable so no signals will be dispatched.
         */
        protected function __construct () {}

        /**
         * Returns an inert emitter instance; Corvus is unavailable.
         * @return static An inert emitter instance.
         */
        public static function create () : static {
            return new static();
        }

        /**
         * No-op; Corvus is unavailable. No signals will be dispatched.
         * @param array|string|BackedEnum ...$signalPatterns Ignored.
         * @return static The emitter, for fluent chaining.
         */
        public function emit (array|string|BackedEnum ...$signalPatterns) : static {
            return $this;
        }

        /**
         * Returns an inert emitter instance; Corvus is unavailable.
         * @param object ...$targets Ignored.
         * @return static An inert emitter instance.
         */
        public static function for (object ...$targets) : static {
            return new static();
        }

        /**
         * No-op; Corvus is unavailable. Returns an empty payload.
         * @return array An empty array.
         */
        public function getPayload () : array {
            return [];
        }

        /**
         * No-op; Corvus is unavailable. Returns `false`.
         * @return bool Always `false`.
         */
        public function hasPredicates () : bool {
            return false;
        }

        /**
         * No-op; Corvus is unavailable.
         * @param callable ...$predicates Ignored.
         * @return static The emitter, for fluent chaining.
         */
        public function if (callable ...$predicates) : static {
            return $this;
        }

        /**
         * No-op; Corvus is unavailable.
         * @param callable ...$predicates Ignored.
         * @return static The emitter, for fluent chaining.
         */
        public function ifAll (callable ...$predicates) : static {
            return $this;
        }

        /**
         * No-op; Corvus is unavailable.
         * @param callable ...$predicates Ignored.
         * @return static The emitter, for fluent chaining.
         */
        public function ifAny (callable ...$predicates) : static {
            return $this;
        }

        /**
         * No-op; Corvus is unavailable.
         * @param string $bus Ignored.
         * @return static The emitter, for fluent chaining.
         */
        public function useBus (string $bus) : static {
            return $this;
        }

        /**
         * No-op; Corvus is unavailable.
         * @param mixed ...$data Ignored.
         * @return static The emitter, for fluent chaining.
         */
        public function with (mixed ...$data) : static {
            return $this;
        }

        /**
         * No-op; Corvus is unavailable.
         * @param mixed ...$data Ignored.
         * @return static The emitter, for fluent chaining.
         */
        public function withOnly (mixed ...$data) : static {
            return $this;
        }
    }
?>
<?php
    /**
     * Project Name:    Wingman Explorer - Permissions Mode
     * Created by:      Angel Politis
     * Creation Date:   Mar 19 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Objects namespace.
    namespace Wingman\Explorer\Objects;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Enums\Permission;

    /**
     * Represents a Unix-style filesystem permissions mode as a value object.
     *
     * Stores the mode as its integer value, matching what PHP's built-in
     * functions such as fileperms() and chmod() operate on. For example, the
     * symbolic mode "rwxr-xr-x" and the octal literal 0755 both resolve to
     * the integer 493.
     * @package Wingman\Explorer\Objects
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    readonly class PermissionsMode {
        /**
         * The file mode as a PHP integer (e.g., 493 for octal 0755).
         * @var int
         */
        public int $octal;

        /**
         * Creates a new permissions mode.
         * @param int $octal The file mode as a PHP integer (e.g., 493 for octal 0755).
         */
        public function __construct (int $octal) {
            $this->octal = $octal;
        }

        /**
         * Parses a symbolic permissions string (e.g., "rwxr-xr-x" or
         * "-rwxr-xr-x") into its integer equivalent.
         * @param string $symbolic The symbolic permissions string.
         * @return int The integer representation of the parsed permissions.
         */
        private static function parseSymbolic (string $symbolic) : int {
            $symbolic = preg_replace("/^[-d]/", "", $symbolic);

            $map = ['r' => 4, 'w' => 2, 'x' => 1, '-' => 0];
            $mode = 0;

            foreach (str_split($symbolic, 3) as $i => $chunk) {
                $value = 0;
                foreach (str_split($chunk) as $char) {
                    $value += $map[$char] ?? 0;
                }

                # Shift each triplet (owner, group, other) into the right position.
                $mode |= ($value << ((2 - $i) * 3));
            }

            return $mode;
        }

        /**
         * Whether the mode grants the given permission for the specified scope.
         * @param Permission $permission The permission bit to check.
         * @param string $scope The permission scope: "owner", "group", or "other" (default: "owner").
         * @return bool Whether the permission bit is set for the given scope.
         */
        public function has (Permission $permission, string $scope = "owner") : bool {
            $shift = match ($scope) {
                "owner" => 6,
                "group" => 3,
                "other" => 0
            };
            return ($this->octal >> $shift & $permission->value) === $permission->value;
        }

        /**
         * Resolves a permissions mode from either a PHP integer, a numeric
         * octal string, or a symbolic string.
         *
         * Accepted forms:
         * - int    — a PHP integer as produced by octal literals (e.g., 0755 → 493)
         *            or by fileperms() masked to the last 9 bits;
         * - string of digits — treated as an octal shorthand (e.g., "755");
         * - string of rwx    — treated as symbolic notation (e.g., "rwxr-xr-x").
         * @param int|string $input The permissions mode to resolve.
         * @return self The resolved permissions mode.
         */
        public static function resolve (int|string $input) : self {
            if (is_int($input)) {
                return new self($input);
            }

            if (ctype_digit($input)) {
                return new self((int) octdec($input));
            }

            return new self(self::parseSymbolic($input));
        }

        /**
         * Returns the octal string representation of the mode (e.g., "755").
         * @return string The octal string representation.
         */
        public function toString () : string {
            return sprintf("%o", $this->octal);
        }
    }
?>

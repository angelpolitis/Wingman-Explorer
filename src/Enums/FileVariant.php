<?php
    /**
     * Project Name:    Wingman Explorer - File Variant Enum
     * Created by:      Angel Politis
     * Creation Date:   Mar 20 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Enums namespace.
    namespace Wingman\Explorer\Enums;

    /**
     * Enumerates the casing variants applicable to a file or directory name.
     * @package Wingman\Explorer\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum FileVariant : int {
        /**
         * The name is kept exactly as-is with no transformation applied.
         * @var int
         */
        case AsIs = 1;

        /**
         * The name is converted to uppercase (e.g., "hello.txt" → "HELLO.TXT").
         * @var int
         */
        case Uppercase = 2;

        /**
         * The name is converted to lowercase (e.g., "HELLO.TXT" → "hello.txt").
         * @var int
         */
        case Lowercase = 3;

        /**
         * The first character of the name is uppercased and the rest lowercased
         * (e.g., "hELLO.txt" → "Hello.txt").
         * @var int
         */
        case Capitalised = 4;

        /**
         * Every word in the name is capitalised (e.g., "hello world.txt" → "Hello World.txt").
         * @var int
         */
        case WordsCapitalised = 5;

        /**
         * Resolves a file variant from an integer or returns the existing instance.
         * @param static|int $variant The variant to resolve.
         * @return static The resolved instance.
         */
        public static function resolve (self|int $variant) : static {
            return $variant instanceof static ? $variant : static::from($variant);
        }
    }
?>
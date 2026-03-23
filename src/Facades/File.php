<?php
    /**
     * Project Name:    Wingman Explorer - File Facade
     * Created by:      Angel Politis
     * Creation Date:   Mar 21 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Facades namespace.
    namespace Wingman\Explorer\Facades;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Resources\LocalFile;

    /**
     * A static entry-point for opening local files.
     *
     * Resolves logical or virtual paths through any registered callable before
     * constructing and returning a {@see LocalFile} instance, which exposes a
     * fluent stream-based mutation API.
     *
     * Register a resolver once at bootstrap, then use the facade freely:
     *
     * @example
     * ```php
     * File::setPathResolver(fn(string $path) => $locator->getPathFor($path));
     *
     * File::at("@namespace/log.txt")
     *     ->append("New entry\n")
     *     ->save();
     *
     * File::at("@namespace/config.json")
     *     ->replaceRange(120, 231, '"updated": true')
     *     ->save();
     * ```
     *
     * @package Wingman\Explorer\Facades
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    final class File {
        /**
         * The optional callable used to resolve a path expression before a
         * {@see LocalFile} is constructed.
         * @var callable|null
         */
        private static $pathResolver = null;

        /**
         * Opens a local file at the given path, resolving the path through any
         * registered path resolver before instantiation.
         * @param string $path The path or virtual path expression of the file.
         * @return LocalFile The local file at the resolved path.
         */
        public static function at (string $path) : LocalFile {
            $resolved = self::$pathResolver ? (self::$pathResolver)($path) : $path;
            return LocalFile::at($resolved);
        }

        /**
         * Registers a callable to resolve path expressions before {@see File::at()}
         * constructs a LocalFile. Pass `null` to remove any existing resolver.
         * @param callable|null $resolver A callable that accepts a path string and returns the resolved path.
         */
        public static function setPathResolver (?callable $resolver) : void {
            self::$pathResolver = $resolver;
        }
    }
?>
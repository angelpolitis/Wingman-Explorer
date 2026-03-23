<?php
    /**
     * Project Name:    Wingman Explorer - Directory Facade
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
    use Wingman\Explorer\Resources\LocalDirectory;

    /**
     * A static entry-point for opening local directories.
     *
     * Resolves logical or virtual paths through any registered callable before
     * constructing and returning a {@see LocalDirectory} instance.
     *
     * Register a resolver once at bootstrap, then use the facade freely:
     *
     * @example
     * ```php
     * Directory::setPathResolver(fn(string $path) => $locator->getPathToDirectory($path));
     *
     * Directory::at("@namespace/uploads")->create();
     *
     * $files = Directory::at("@namespace/data")->getFiles();
     * ```
     *
     * @package Wingman\Explorer\Facades
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    final class Directory {
        /**
         * The optional callable used to resolve a path expression before a
         * {@see LocalDirectory} is constructed.
         * @var callable|null
         */
        private static $pathResolver = null;

        /**
         * Opens a local directory at the given path, resolving the path through
         * any registered path resolver before instantiation.
         * @param string $path The path or virtual path expression of the directory.
         * @return LocalDirectory The local directory at the resolved path.
         */
        public static function at (string $path) : LocalDirectory {
            $resolved = self::$pathResolver ? (self::$pathResolver)($path) : $path;
            return LocalDirectory::at($resolved);
        }

        /**
         * Registers a callable to resolve path expressions before {@see Directory::at()}
         * constructs a LocalDirectory. Pass `null` to remove any existing resolver.
         * @param callable|null $resolver A callable that accepts a path string and returns the resolved path.
         */
        public static function setPathResolver (?callable $resolver) : void {
            self::$pathResolver = $resolver;
        }
    }
?>
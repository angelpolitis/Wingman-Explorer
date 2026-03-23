<?php
    /**
     * Project Name:    Wingman Explorer - Virtual Tree Compiler
     * Created by:      Angel Politis
     * Creation Date:   Dec 16 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer namespace.
    namespace Wingman\Explorer;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\VirtualTreeException;
    use Wingman\Explorer\Resources\InlineFile;
    use Wingman\Explorer\Resources\ProxyFile;
    use Wingman\Explorer\Resources\VirtualDirectory;
    use Wingman\Explorer\Resources\VirtualFile;

    /**
     * Represents a virtual tree compiler.
     * @package Wingman\Explorer
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    final class VirtualTreeCompiler {
        /**
         * Compiles a virtual tree definition into a tree of virtual resource objects.
         * @param array $json The virtual tree definition as an associative array.
         * @return VirtualDirectory The root of the compiled virtual tree.
         * @throws VirtualTreeException If the input is invalid.
         */
        private static function compileDirectory (array $node, string $path) : VirtualDirectory {
            if (($node["type"] ?? null) !== "directory") {
                throw new VirtualTreeException("Expected directory at $path");
            }
    
            $children = [];
    
            if (isset($node['content'])) {
                if (!is_array($node['content'])) {
                    throw new VirtualTreeException("Directory content must be an object at $path");
                }
    
                foreach ($node['content'] as $name => $child) {
                    $childPath = $path . '/' . $name;
    
                    if (!is_array($child) || !isset($child["type"])) {
                        throw new VirtualTreeException("Invalid virtual node at $childPath");
                    }
    
                    $children[$name] = match ($child["type"]) {
                        "directory" => self::compileDirectory($child, $childPath),
                        "file" => self::compileFile($child, $childPath),
                        default => throw new VirtualTreeException("Unknown type '{$child["type"]}' at $childPath")
                    };
                }
            }
    
            return new VirtualDirectory(basename($path), $children);
        }
    
        /**
         * Compiles a virtual file definition into a virtual file resource.
         * @param array $node The virtual file definition as an associative array.
         * @return VirtualFile The compiled virtual file resource.
         * @throws VirtualTreeException If the input is invalid.
         */
        private static function compileFile (array $node, string $path) : VirtualFile {
            if (($node["type"] ?? null) !== "file") {
                throw new VirtualTreeException("Expected file at $path");
            }
    
            $hasSource  = array_key_exists("source", $node);
            $hasContent = array_key_exists("content", $node);
    
            if ($hasSource && $hasContent) {
                throw new VirtualTreeException("File at $path cannot have both source and content.");
            }
    
            if (!$hasSource && !$hasContent) {
                throw new VirtualTreeException("File at $path must have either source or content.");
            }
    
            if ($hasSource) {
                return new ProxyFile($node["source"]);
            }
    
            return new InlineFile((string) $node["content"]);
        }

        /**
         * Compiles a virtual tree definition into a tree of virtual resource objects.
         * @param array $json The virtual tree definition as an associative array.
         * @return VirtualDirectory The root of the compiled virtual tree.
         */
        public static function compile (array $json) : VirtualDirectory {
            return self::compileDirectory($json, "<root>");
        }
    }
?>
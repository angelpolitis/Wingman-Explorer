<?php
    /**
     * Project Name:    Wingman Explorer - Directory Diff
     * Created by:      Angel Politis
     * Creation Date:   Mar 20 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer namespace.
    namespace Wingman\Explorer;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Interfaces\Resources\DirectoryResource;
    use Wingman\Explorer\Interfaces\Resources\FileResource;
    use Wingman\Explorer\Interfaces\Resources\Resource;

    /**
     * Compares two directory trees and produces a diff report describing what was
     * added, removed, or modified between them.
     *
     * Usage:
     * <code>
     * $result = DirectoryDiff::compare($dirA, $dirB);
     * // $result['added']    — resources present in $b but absent in $a
     * // $result['removed']  — resources present in $a but absent in $b
     * // $result['modified'] — resources present in both but whose content differs
     * </code>
     *
     * @package Wingman\Explorer
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class DirectoryDiff {
        /**
         * Indexes an array of resources by their base name.
         * @param Resource[] $resources The resources to index.
         * @return array<string, Resource> Resources keyed by base name.
         */
        private static function indexByBaseName (array $resources) : array {
            $index = [];

            foreach ($resources as $key => $resource) {
                $index[is_string($key) ? $key : $resource->getBaseName()] = $resource;
            }

            return $index;
        }

        /**
         * Computes the diff for two resources that share the same base name.
         * @param Resource $a The base resource.
         * @param Resource $b The comparison resource.
         * @param bool $recursive Whether to recurse into matching directory pairs.
         * @return array{added: Resource[], removed: Resource[], modified: Resource[]} The diff result.
         */
        private static function diffPair (Resource $a, Resource $b, bool $recursive) : array {
            $empty = ["added" => [], "removed" => [], "modified" => []];

            $aIsDir = $a instanceof DirectoryResource;
            $bIsDir = $b instanceof DirectoryResource;

            # Type mismatch — treat $b as a modification of $a.
            if ($aIsDir !== $bIsDir) {
                return array_merge($empty, ["modified" => [$b]]);
            }

            # Both are directories.
            if ($aIsDir) {
                /** @var DirectoryResource $a */
                /** @var DirectoryResource $b */
                if ($recursive) {
                    return static::compare($a, $b, true);
                }

                $changed = $a->getLastModified() != $b->getLastModified();

                return array_merge($empty, $changed ? ["modified" => [$b]] : []);
            }

            # Both are files — compare by size. Additional checks use metadata.
            /** @var FileResource $a */
            /** @var FileResource $b */
            $sizeChanged = $a->getSize() !== $b->getSize();

            if ($sizeChanged) {
                return array_merge($empty, ["modified" => [$b]]);
            }

            # Same size — check modification timestamp via metadata if available.
            $metaA = $a->getMetadata();
            $metaB = $b->getMetadata();

            if (isset($metaA["modified"], $metaB["modified"]) && $metaA["modified"] != $metaB["modified"]) {
                return array_merge($empty, ["modified" => [$b]]);
            }

            return $empty;
        }

        /**
         * Compares two directory resources and returns a structured diff.
         *
         * When <code>$recursive</code> is true, subdirectory pairs are compared
         * recursively and results are merged into the top-level report.
         * When false, a directory whose last-modified timestamp has changed is
         * recorded as modified without further inspection.
         *
         * @param DirectoryResource $a The base directory (the "before" snapshot).
         * @param DirectoryResource $b The comparison directory (the "after" snapshot).
         * @param bool $recursive Whether to recurse into matching subdirectory pairs.
         * @return array{added: Resource[], removed: Resource[], modified: Resource[]} The diff result.
         */
        public static function compare (DirectoryResource $a, DirectoryResource $b, bool $recursive = true) : array {
            $result = ["added" => [], "removed" => [], "modified" => []];

            $indexA = static::indexByBaseName($a->getContents());
            $indexB = static::indexByBaseName($b->getContents());

            foreach ($indexB as $name => $resourceB) {
                if (!isset($indexA[$name])) {
                    $result["added"][] = $resourceB;
                    continue;
                }

                $resourceA = $indexA[$name];
                $nested = static::diffPair($resourceA, $resourceB, $recursive);

                $result["added"] = array_merge($result["added"],    $nested["added"]);
                $result["removed"] = array_merge($result["removed"],  $nested["removed"]);
                $result["modified"] = array_merge($result["modified"], $nested["modified"]);
            }

            foreach ($indexA as $name => $resourceA) {
                if (!isset($indexB[$name])) {
                    $result["removed"][] = $resourceA;
                }
            }

            return $result;
        }
    }
?>
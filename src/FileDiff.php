<?php
    /**
     * Project Name:    Wingman Explorer - File Diff
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
    use Wingman\Explorer\Exceptions\FileDiffException;
    use Wingman\Explorer\Interfaces\Resources\FileResource;

    /**
     * Computes a line-by-line diff between two files using the Longest Common Subsequence (LCS) algorithm.
     *
     * Usage:
     * <code>
     * $result = FileDiff::compare($fileA, $fileB);
     * foreach ($result['hunks'] as $hunk) {
     *     // $hunk['operation'] — 'unchanged', 'added', or 'removed'
     *     // $hunk['lineA']    — 1-based line number in file A (null for 'added' hunks)
     *     // $hunk['lineB']    — 1-based line number in file B (null for 'removed' hunks)
     *     // $hunk['content']  — the line content (without trailing newline)
     * }
     * </code>
     *
     * The algorithm runs in O(m × n) time and space, where m and n are the line counts
     * of each file. For typical source files this is acceptable; for very large files
     * consider chunking before calling this method.
     *
     * @package Wingman\Explorer
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class FileDiff {
        /**
         * Builds the LCS DP table for the two line arrays.
         *
         * Returns a (m+1) × (n+1) matrix where entry [i][j] holds the length of the
         * Longest Common Subsequence of <code>$linesA[0..i-1]</code> and
         * <code>$linesB[0..j-1]</code>.
         * @param string[] $linesA Lines of the base file.
         * @param string[] $linesB Lines of the comparison file.
         * @return array<int, array<int, int>> The DP table.
         */
        private static function computeLcs (array $linesA, array $linesB) : array {
            $m = count($linesA);
            $n = count($linesB);
            $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

            for ($i = 1; $i <= $m; $i++) {
                for ($j = 1; $j <= $n; $j++) {
                    if ($linesA[$i - 1] === $linesB[$j - 1]) {
                        $dp[$i][$j] = $dp[$i - 1][$j - 1] + 1;
                    }
                    else {
                        $dp[$i][$j] = max($dp[$i - 1][$j], $dp[$i][$j - 1]);
                    }
                }
            }

            return $dp;
        }

        /**
         * Backtracks through the LCS DP table to produce the ordered hunk list.
         * @param string[] $linesA Lines of the base file.
         * @param string[] $linesB Lines of the comparison file.
         * @param array<int, array<int, int>> $dp The precomputed LCS table.
         * @return list<array{operation: string, lineA: int|null, lineB: int|null, content: string}> The hunk list in file order.
         */
        private static function buildHunks (array $linesA, array $linesB, array $dp) : array {
            $m = count($linesA);
            $n = count($linesB);
            $i = $m;
            $j = $n;
            $hunks = [];

            while ($i > 0 || $j > 0) {
                if ($i > 0 && $j > 0 && $linesA[$i - 1] === $linesB[$j - 1]) {
                    $hunks[] = [
                        "operation" => "unchanged",
                        "lineA" => $i,
                        "lineB" => $j,
                        "content" => $linesA[$i - 1]
                    ];
                    $i--;
                    $j--;
                }
                elseif ($j > 0 && ($i === 0 || $dp[$i][$j - 1] >= $dp[$i - 1][$j])) {
                    $hunks[] = [
                        "operation" => "added",
                        "lineA" => null,
                        "lineB" => $j,
                        "content" => $linesB[$j - 1]
                    ];
                    $j--;
                }
                else {
                    $hunks[] = [
                        "operation" => "removed",
                        "lineA" => $i,
                        "lineB" => null,
                        "content" => $linesA[$i - 1]
                    ];
                    $i--;
                }
            }

            return array_reverse($hunks);
        }

        /**
         * Compares two files line-by-line and returns a hunk list describing the diff.
         *
         * Each hunk is an associative array with the following keys:
         * <ul>
         *   <li><code>operation</code> — one of <code>'unchanged'</code>, <code>'added'</code>, <code>'removed'</code></li>
         *   <li><code>lineA</code> — the 1-based line number in <code>$a</code>, or <code>null</code> for added lines</li>
         *   <li><code>lineB</code> — the 1-based line number in <code>$b</code>, or <code>null</code> for removed lines</li>
         *   <li><code>content</code> — the line content without a trailing newline</li>
         * </ul>
         * @param FileResource $a The base file (the "before" snapshot).
         * @param FileResource $b The comparison file (the "after" snapshot).
         * @param int $maxLines The maximum number of lines per file allowed for in-memory diffing.
         * @return array{hunks: list<array{operation: string, lineA: int|null, lineB: int|null, content: string}>} The diff result.
         * @throws FileDiffException If either file exceeds <code>$maxLines</code> lines.
         */
        public static function compare (FileResource $a, FileResource $b, int $maxLines = 50000) : array {
            $contentA = $a->getContent();
            $contentB = $b->getContent();
            $linesA = $contentA !== "" ? (preg_split('/\r\n|\n|\r/', $contentA) ?: []) : [];
            $linesB = $contentB !== "" ? (preg_split('/\r\n|\n|\r/', $contentB) ?: []) : [];

            if (count($linesA) > $maxLines || count($linesB) > $maxLines) {
                throw new FileDiffException("FileDiff: file too large for in-memory diff (max $maxLines lines per file).");
            }

            $dp = static::computeLcs($linesA, $linesB);
            $hunks = static::buildHunks($linesA, $linesB, $dp);

            return ["hunks" => $hunks];
        }
    }
?>
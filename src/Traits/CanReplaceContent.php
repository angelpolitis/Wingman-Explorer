<?php
    /**
     * Project Name:    Wingman Explorer - Can Replace Content
     * Created by:      Angel Politis
     * Creation Date:   Mar 22 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Traits namespace.
    namespace Wingman\Explorer\Traits;

    /**
     * Provides content-replacement methods for file resources.
     *
     * Classes using this trait must:
     * - Implement `getContent(): string` returning the current effective content
     *   (including any pending unsaved changes).
     * - Implement `write(string $content): static` to commit new content to the
     *   resource's internal buffer.
     *
     * For every method, `$search` and `$replacement` each accept:
     * - A `string` — used directly.
     * - An `array` — each element is handled in parallel (like `str_replace`
     *   array mode).
     * - A `callable` — invoked once with no arguments and must return a
     *   `string` or `array`; the result is then treated as above.
     *
     * @package Wingman\Explorer\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait CanReplaceContent {
        /**
         * Escapes a plain-string replacement so that `preg_replace` treats it
         * as a literal value rather than interpreting `$n` or `\n` backreferences.
         * @param string $replacement The unescaped replacement string.
         * @return string The escaped replacement string.
         */
        private function escapeReplacement (string $replacement) : string {
            return addcslashes($replacement, '$\\');
        }

        /**
         * Shared implementation for line-level replacement methods.
         * Lines are split on `\n`; the reconstructed content uses the same separator.
         * When `$search` is an array each element operates independently: paired
         * replacements are aligned by index, falling back to the last replacement
         * element as a default.
         * @param string|array|callable $search The needle(s) or pattern(s) to match against each line.
         * @param string|array|callable $replacement The replacement line(s).
         * @param bool $replaceAll Whether to replace every matching line or only the first per needle.
         * @param bool $usePattern Whether to test each line with `preg_match` instead of `str_contains`.
         * @return static The file.
         */
        private function replaceMatchingLines (
            string|array|callable $search,
            string|array|callable $replacement,
            bool $replaceAll,
            bool $usePattern
        ) : static {
            $searches = array_values((array) $this->resolveReplaceArgument($search));
            $replacements = array_values((array) $this->resolveReplaceArgument($replacement));
            $lastReplacementIndex = array_key_last($replacements);
            $replacedFirst = array_fill(0, count($searches), false);

            $lines = explode("\n", $this->getContent());

            foreach ($lines as &$line) {
                foreach ($searches as $index => $needle) {
                    if (!$replaceAll && $replacedFirst[$index]) continue;

                    $matched = $usePattern
                        ? (bool) preg_match((string) $needle, $line)
                        : str_contains($line, (string) $needle);

                    if (!$matched) continue;

                    $line = (string) ($replacements[$index] ?? $replacements[$lastReplacementIndex] ?? '');
                    $replacedFirst[$index] = true;
                }
            }

            unset($line);

            return $this->write(implode("\n", $lines));
        }

        /**
         * Resolves a callable, string, or array value into a concrete string or array.
         * If `$value` is callable it is invoked once with no arguments.
         * @param string|array|callable $value The value to resolve.
         * @return string|array The resolved value.
         */
        private function resolveReplaceArgument (string|array|callable $value) : string|array {
            return is_callable($value) ? $value() : $value;
        }

        /**
         * Replaces all (or up to `$limit`) occurrences of `$search` with `$replacement`.
         * When `$limit` is provided the replacement is performed via `preg_replace` with
         * the search string properly escaped so no backreferences are applied.
         * @param string|array|callable $search The value(s) to search for. A callable is invoked once to produce the value.
         * @param string|array|callable $replacement The replacement value(s). A callable is invoked once to produce the value.
         * @param int|null $limit The maximum number of replacements per search term, or `null` for no limit.
         * @return static The file.
         */
        public function replace (string|array|callable $search, string|array|callable $replacement, ?int $limit = null) : static {
            $search = $this->resolveReplaceArgument($search);
            $replacement = $this->resolveReplaceArgument($replacement);
            $content = $this->getContent();

            if ($limit === null) {
                $content = str_replace($search, $replacement, $content);
            }
            else {
                $searches = array_values((array) $search);
                $replacements = array_values((array) $replacement);
                $lastIndex = array_key_last($replacements);

                foreach ($searches as $index => $needle) {
                    $replace = (string) ($replacements[$index] ?? $replacements[$lastIndex] ?? '');
                    $pattern = '/' . preg_quote((string) $needle, '/') . '/';
                    $content = preg_replace($pattern, $this->escapeReplacement($replace), $content, $limit) ?? $content;
                }
            }

            return $this->write($content);
        }

        /**
         * Replaces every line that contains `$needle`.
         * @param string|array|callable $needle The string(s) to search for within each line. A callable is invoked once to produce the value.
         * @param string|array|callable $replacement The replacement line(s). A callable is invoked once to produce the value.
         * @return static The file.
         */
        public function replaceAllLinesContaining (string|array|callable $needle, string|array|callable $replacement) : static {
            return $this->replaceMatchingLines($needle, $replacement, replaceAll: true, usePattern: false);
        }

        /**
         * Replaces every line matching `$pattern`.
         * @param string|array|callable $pattern The regex pattern(s) to test against each line. A callable is invoked once to produce the value.
         * @param string|array|callable $replacement The replacement line(s). A callable is invoked once to produce the value.
         * @return static The file.
         */
        public function replaceAllLinesMatchingPattern (string|array|callable $pattern, string|array|callable $replacement) : static {
            return $this->replaceMatchingLines($pattern, $replacement, replaceAll: true, usePattern: true);
        }

        /**
         * Replaces only the first occurrence of each `$search` term with its corresponding `$replacement`.
         * @param string|array|callable $search The value(s) to search for. A callable is invoked once to produce the value.
         * @param string|array|callable $replacement The replacement value(s). A callable is invoked once to produce the value.
         * @return static The file.
         */
        public function replaceFirst (string|array|callable $search, string|array|callable $replacement) : static {
            $search = $this->resolveReplaceArgument($search);
            $replacement = $this->resolveReplaceArgument($replacement);
            $searches = array_values((array) $search);
            $replacements = array_values((array) $replacement);
            $lastIndex = array_key_last($replacements);
            $content = $this->getContent();

            foreach ($searches as $index => $needle) {
                $replace = (string) ($replacements[$index] ?? $replacements[$lastIndex] ?? '');
                $pattern = '/' . preg_quote((string) $needle, '/') . '/';
                $content = preg_replace($pattern, $this->escapeReplacement($replace), $content, 1) ?? $content;
            }

            return $this->write($content);
        }

        /**
         * Replaces only the last occurrence of each `$search` term with its corresponding `$replacement`.
         * @param string|array|callable $search The value(s) to search for. A callable is invoked once to produce the value.
         * @param string|array|callable $replacement The replacement value(s). A callable is invoked once to produce the value.
         * @return static The file.
         */
        public function replaceLast (string|array|callable $search, string|array|callable $replacement) : static {
            $search = $this->resolveReplaceArgument($search);
            $replacement = $this->resolveReplaceArgument($replacement);
            $searches = array_values((array) $search);
            $replacements = array_values((array) $replacement);
            $lastIndex = array_key_last($replacements);
            $content = $this->getContent();

            foreach ($searches as $index => $needle) {
                $replace = (string) ($replacements[$index] ?? $replacements[$lastIndex] ?? '');
                $pos = strrpos($content, (string) $needle);
                if ($pos !== false) {
                    $content = substr_replace($content, $replace, $pos, strlen((string) $needle));
                }
            }

            return $this->write($content);
        }

        /**
         * Replaces the entire first line that contains `$needle`.
         * @param string|array|callable $needle The string(s) to search for within a line. A callable is invoked once to produce the value.
         * @param string|array|callable $replacement The replacement line(s). A callable is invoked once to produce the value.
         * @return static The file.
         */
        public function replaceLineContaining (string|array|callable $needle, string|array|callable $replacement) : static {
            return $this->replaceMatchingLines($needle, $replacement, replaceAll: false, usePattern: false);
        }

        /**
         * Replaces the entire first line matching `$pattern`.
         * @param string|array|callable $pattern The regex pattern(s) to test against each line. A callable is invoked once to produce the value.
         * @param string|array|callable $replacement The replacement line(s). A callable is invoked once to produce the value.
         * @return static The file.
         */
        public function replaceLineMatchingPattern (string|array|callable $pattern, string|array|callable $replacement) : static {
            return $this->replaceMatchingLines($pattern, $replacement, replaceAll: false, usePattern: true);
        }

        /**
         * Performs a regex-based replacement; `$replacement` supports backreferences (`$1`, `\1`, etc.).
         * @param string|array|callable $pattern The regex pattern(s). A callable is invoked once to produce the value.
         * @param string|array|callable $replacement The replacement value(s), which may contain backreferences. A callable is invoked once to produce the value.
         * @param int|null $limit The maximum number of replacements per pattern, or `null` for no limit.
         * @return static The file.
         */
        public function replacePattern (string|array|callable $pattern, string|array|callable $replacement, ?int $limit = null) : static {
            $pattern = $this->resolveReplaceArgument($pattern);
            $replacement = $this->resolveReplaceArgument($replacement);
            $content = $this->getContent();
            $content = preg_replace($pattern, $replacement, $content, $limit ?? -1) ?? $content;
            return $this->write($content);
        }
    }
?>
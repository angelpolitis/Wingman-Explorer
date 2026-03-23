<?php
    /**
     * Project Name:    Wingman Explorer - Stream Mode
     * Created by:      Angel Politis
     * Creation Date:   Nov 12 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Enums namespace.
    namespace Wingman\Explorer\Enums;

    /**
     * Enumerates all PHP stream-open modes accepted by {@see fopen()}, covering the
     * base, binary-safe, and text-mode variants for every access pattern.
     * @package Wingman\Explorer\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum StreamMode : string {
        /**
         * The read-only mode.
         * Opens the stream for reading only. The file pointer is positioned at the
         * beginning of the file. The file must already exist.
         * @var string
         */
        case READ = 'r';

        /**
         * The read-write mode.
         * Opens the stream for both reading and writing. The file pointer is positioned
         * at the beginning of the file. The file must already exist.
         * @var string
         */
        case READ_WRITE = "r+";

        /**
         * The write mode.
         * Opens the stream for writing only. Truncates the file to zero length if it
         * already exists, or creates a new file. The file pointer is positioned at the
         * beginning of the file.
         * @var string
         */
        case WRITE = 'w';

        /**
         * The write-read mode.
         * Opens the stream for both reading and writing. Truncates the file to zero
         * length if it already exists, or creates a new file. The file pointer is
         * positioned at the beginning of the file.
         * @var string
         */
        case WRITE_READ = "w+";

        /**
         * The append mode.
         * Opens the stream for writing only. The file pointer is positioned at the end
         * of the file. Creates a new file if it does not already exist. Writes always
         * append to the end regardless of the current pointer position.
         * @var string
         */
        case APPEND = 'a';

        /**
         * The append-read mode.
         * Opens the stream for both reading and writing. The file pointer is positioned
         * at the end of the file. Creates a new file if it does not already exist.
         * @var string
         */
        case APPEND_READ = "a+";

        /**
         * The create mode.
         * Opens the stream for writing only. Returns an error if the file already exists.
         * If the file does not exist, it is created. The file pointer is positioned at
         * the beginning of the file.
         * @var string
         */
        case CREATE = 'x';

        /**
         * The create-read mode.
         * Opens the stream for both reading and writing. Returns an error if the file
         * already exists. If the file does not exist, it is created. The file pointer
         * is positioned at the beginning of the file.
         * @var string
         */
        case CREATE_READ = "x+";

        /**
         * The write-no-truncate mode.
         * Opens the stream for writing only. Creates the file if it does not already
         * exist. Unlike {@see WRITE}, the file is not truncated. The file pointer is
         * positioned at the beginning of the file.
         * @var string
         */
        case WRITE_NO_TRUNCATE = 'c';

        /**
         * The write-read-no-truncate mode.
         * Opens the stream for both reading and writing. Creates the file if it does not
         * already exist. Unlike {@see WRITE_READ}, the file is not truncated. The file
         * pointer is positioned at the beginning of the file.
         * @var string
         */
        case WRITE_READ_NO_TRUNCATE = "c+";

        /**
         * The binary read-only mode.
         * Equivalent to {@see READ} with the binary flag, disabling newline translation
         * on Windows and ensuring binary-safe I/O.
         * @var string
         */
        case READ_BINARY = "rb";

        /**
         * The binary read-write mode.
         * Equivalent to {@see READ_WRITE} with the binary flag, disabling newline
         * translation on Windows and ensuring binary-safe I/O.
         * @var string
         */
        case READ_WRITE_BINARY = "r+b";

        /**
         * The binary write mode.
         * Equivalent to {@see WRITE} with the binary flag, disabling newline translation
         * on Windows and ensuring binary-safe I/O.
         * @var string
         */
        case WRITE_BINARY = "wb";

        /**
         * The binary write-read mode.
         * Equivalent to {@see WRITE_READ} with the binary flag, disabling newline
         * translation on Windows and ensuring binary-safe I/O.
         * @var string
         */
        case WRITE_READ_BINARY = "w+b";

        /**
         * The binary append mode.
         * Equivalent to {@see APPEND} with the binary flag, disabling newline translation
         * on Windows and ensuring binary-safe I/O.
         * @var string
         */
        case APPEND_BINARY = 'ab';

        /**
         * The binary append-read mode.
         * Equivalent to {@see APPEND_READ} with the binary flag, disabling newline
         * translation on Windows and ensuring binary-safe I/O.
         * @var string
         */
        case APPEND_READ_BINARY = "a+b";

        /**
         * The binary create mode.
         * Equivalent to {@see CREATE} with the binary flag, disabling newline translation
         * on Windows and ensuring binary-safe I/O.
         * @var string
         */
        case CREATE_BINARY = "xb";

        /**
         * The binary create-read mode.
         * Equivalent to {@see CREATE_READ} with the binary flag, disabling newline
         * translation on Windows and ensuring binary-safe I/O.
         * @var string
         */
        case CREATE_READ_BINARY = "x+b";

        /**
         * The binary write-no-truncate mode.
         * Equivalent to {@see WRITE_NO_TRUNCATE} with the binary flag, disabling newline
         * translation on Windows and ensuring binary-safe I/O.
         * @var string
         */
        case WRITE_NO_TRUNCATE_BINARY = "cb";

        /**
         * The binary write-read-no-truncate mode.
         * Equivalent to {@see WRITE_READ_NO_TRUNCATE} with the binary flag, disabling
         * newline translation on Windows and ensuring binary-safe I/O.
         * @var string
         */
        case WRITE_READ_NO_TRUNCATE_BINARY = "c+b";

        /**
         * The text read-only mode.
         * Equivalent to {@see READ} with the text flag, enabling CRLF translation on
         * Windows. Has no effect on POSIX systems.
         * @var string
         */
        case READ_TEXT = "rt";

        /**
         * The text read-write mode.
         * Equivalent to {@see READ_WRITE} with the text flag, enabling CRLF translation
         * on Windows. Has no effect on POSIX systems.
         * @var string
         */
        case READ_WRITE_TEXT = "r+t";

        /**
         * The text write mode.
         * Equivalent to {@see WRITE} with the text flag, enabling CRLF translation on
         * Windows. Has no effect on POSIX systems.
         * @var string
         */
        case WRITE_TEXT = "wt";

        /**
         * The text write-read mode.
         * Equivalent to {@see WRITE_READ} with the text flag, enabling CRLF translation
         * on Windows. Has no effect on POSIX systems.
         * @var string
         */
        case WRITE_READ_TEXT = "w+t";

        /**
         * The text append mode.
         * Equivalent to {@see APPEND} with the text flag, enabling CRLF translation on
         * Windows. Has no effect on POSIX systems.
         * @var string
         */
        case APPEND_TEXT = "at";

        /**
         * The text append-read mode.
         * Equivalent to {@see APPEND_READ} with the text flag, enabling CRLF translation
         * on Windows. Has no effect on POSIX systems.
         * @var string
         */
        case APPEND_READ_TEXT = "a+t";

        /**
         * The text create mode.
         * Equivalent to {@see CREATE} with the text flag, enabling CRLF translation on
         * Windows. Has no effect on POSIX systems.
         * @var string
         */
        case CREATE_TEXT = "xt";

        /**
         * The text create-read mode.
         * Equivalent to {@see CREATE_READ} with the text flag, enabling CRLF translation
         * on Windows. Has no effect on POSIX systems.
         * @var string
         */
        case CREATE_READ_TEXT = "x+t";

        /**
         * The text write-no-truncate mode.
         * Equivalent to {@see WRITE_NO_TRUNCATE} with the text flag, enabling CRLF
         * translation on Windows. Has no effect on POSIX systems.
         * @var string
         */
        case WRITE_NO_TRUNCATE_TEXT = "ct";

        /**
         * The text write-read-no-truncate mode.
         * Equivalent to {@see WRITE_READ_NO_TRUNCATE} with the text flag, enabling CRLF
         * translation on Windows. Has no effect on POSIX systems.
         * @var string
         */
        case WRITE_READ_NO_TRUNCATE_TEXT = "c+t";

        /**
         * Checks whether the mode is an append mode.
         * @return bool Whether the mode is in append mode.
         */
        public function isAppendMode () : bool {
            return str_contains($this->value, static::APPEND->value);
        }

        /**
         * Checks whether the mode is a binary mode.
         * @return bool Whether the mode is binary.
         */
        public function isBinary () : bool {
            return str_contains($this->value, 'b');
        }

        /**
         * Checks whether the mode is a create mode.
         * @return bool Whether the mode is in create mode.
         */
        public function isCreateMode () : bool {
            return str_contains($this->value, static::CREATE->value);
        }

        /**
         * Checks whether the mode is a no-truncate mode.
         * @return bool Whether the mode is in no-truncate mode.
         */
        public function isNoTruncateMode () : bool {
            return str_contains($this->value, static::WRITE_NO_TRUNCATE->value);
        }

        /**
         * Checks whether the mode is readable.
         * @return bool Whether the mode is readable.
         */
        public function isReadable () : bool {
            return strpbrk($this->value, "r+") !== false;
        }

        /**
         * Checks whether the mode is read-only.
         * @return bool Whether the mode is read-only.
         */
        public function isReadOnly () : bool {
            return $this === static::READ
                || $this === static::READ_BINARY
                || $this === static::READ_TEXT;
        }

        /**
         * Checks whether the mode is a text mode.
         * @return bool Whether the mode is a text mode.
         */
        public function isText () : bool {
            return str_contains($this->value, 't');
        }

        /**
         * Checks whether the mode is a truncate mode.
         * @return bool Whether the mode is in truncate mode.
         */
        public function isTruncateMode () : bool {
            return str_contains($this->value, static::WRITE->value);
        }

        /**
         * Checks whether the mode is writable.
         * @return bool Whether the mode is writable.
         */
        public function isWritable () : bool {
            return strpbrk($this->value, "waxc+") !== false;
        }

        /**
         * Normalises a mode string so that the <code>'b'</code> or <code>'t'</code>
         * modifier always appears at the end, then resolves it to a case.
         * @param string $mode A raw stream mode string (e.g. <code>"br+"</code>).
         * @return static The matching case.
         */
        public static function fromNormalised (string $mode) : static {
            $isBinary = str_contains($mode, 'b');
            $isText = str_contains($mode, 't');

            $mode = str_replace(['b', 't'], '', $mode);

            if ($isBinary) $mode .= 'b';
            if ($isText) $mode .= 't';

            return static::from($mode);
        }

        /**
         * Resolves a stream mode from a string or returns the existing instance.
         * @param static|string $mode The mode to resolve.
         * @return static The resolved instance.
         */
        public static function resolve (self|string $mode) : static {
            return $mode instanceof static ? $mode : static::from($mode);
        }
    }
?>
<?php
    /**
     * Project Name:    Wingman Explorer - Exceptions Tests
     * Created by:      Angel Politis
     * Creation Date:   Mar 21 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Tests namespace.
    namespace Wingman\Explorer\Tests;

    # Import the following classes to the current scope.
    use Exception;
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Explorer\Exceptions\ExtensionRejectedUploadException;
    use Wingman\Explorer\Exceptions\FileSizeLimitExceededException;
    use Wingman\Explorer\Exceptions\FilesystemException;
    use Wingman\Explorer\Exceptions\InvalidVirtualFolderException;
    use Wingman\Explorer\Exceptions\MimeTypeRejectedUploadException;
    use Wingman\Explorer\Exceptions\NotAStreamException;
    use Wingman\Explorer\Exceptions\ScannerConfigurationException;
    use Wingman\Explorer\Exceptions\StreamNotWritableException;
    use Wingman\Explorer\Exceptions\UndefinedMapDirectoryException;
    use Wingman\Explorer\Exceptions\UnseekableStreamException;
    use Wingman\Explorer\Exceptions\UploadException;

    /**
     * Tests for all Explorer exception classes — instantiation, messages and hierarchy.
     */
    class ExceptionsTest extends Test {
        // ─── Instantiation ─────────────────────────────────────────────────────

        #[Group("Exceptions")]
        #[Define(name: "FilesystemException — Instantiates With a Message", description: "FilesystemException can be created with a message and getMessage() returns it.")]
        public function testFilesystemExceptionInstantiatesWithMessage () : void {
            $exception = new FilesystemException("disk full");

            $this->assertTrue($exception->getMessage() === "disk full", "FilesystemException must store the provided message.");
        }

        #[Group("Exceptions")]
        #[Define(name: "UploadException — Instantiates With a Message", description: "UploadException can be created with a message and getMessage() returns it.")]
        public function testUploadExceptionInstantiatesWithMessage () : void {
            $exception = new UploadException("upload failed");

            $this->assertTrue($exception->getMessage() === "upload failed", "UploadException must store the provided message.");
        }

        #[Group("Exceptions")]
        #[Define(name: "ExtensionRejectedUploadException — Instantiates With a Message", description: "ExtensionRejectedUploadException can be created with a message and getMessage() returns it.")]
        public function testExtensionRejectedUploadExceptionInstantiatesWithMessage () : void {
            $exception = new ExtensionRejectedUploadException("extension rejected");

            $this->assertTrue($exception->getMessage() === "extension rejected", "ExtensionRejectedUploadException must store the provided message.");
        }

        #[Group("Exceptions")]
        #[Define(name: "FileSizeLimitExceededException — Instantiates With a Message", description: "FileSizeLimitExceededException can be created with a message and getMessage() returns it.")]
        public function testFileSizeLimitExceededExceptionInstantiatesWithMessage () : void {
            $exception = new FileSizeLimitExceededException("file too large");

            $this->assertTrue($exception->getMessage() === "file too large", "FileSizeLimitExceededException must store the provided message.");
        }

        #[Group("Exceptions")]
        #[Define(name: "MimeTypeRejectedUploadException — Instantiates With a Message", description: "MimeTypeRejectedUploadException can be created with a message and getMessage() returns it.")]
        public function testMimeTypeRejectedUploadExceptionInstantiatesWithMessage () : void {
            $exception = new MimeTypeRejectedUploadException("MIME type rejected");

            $this->assertTrue($exception->getMessage() === "MIME type rejected", "MimeTypeRejectedUploadException must store the provided message.");
        }

        #[Group("Exceptions")]
        #[Define(name: "ScannerConfigurationException — Instantiates With a Message", description: "ScannerConfigurationException can be created with a message and getMessage() returns it.")]
        public function testScannerConfigurationExceptionInstantiatesWithMessage () : void {
            $exception = new ScannerConfigurationException("no adapter");

            $this->assertTrue($exception->getMessage() === "no adapter", "ScannerConfigurationException must store the provided message.");
        }

        #[Group("Exceptions")]
        #[Define(name: "NotAStreamException — Instantiates With a Default Message", description: "NotAStreamException has a default message when no message is explicitly provided.")]
        public function testNotAStreamExceptionHasDefaultMessage () : void {
            $exception = new NotAStreamException();

            $this->assertTrue($exception->getMessage() !== "", "NotAStreamException must have a non-empty default message.");
        }

        #[Group("Exceptions")]
        #[Define(name: "StreamNotWritableException — Instantiates With a Default Message", description: "StreamNotWritableException has a default message when no message is explicitly provided.")]
        public function testStreamNotWritableExceptionHasDefaultMessage () : void {
            $exception = new StreamNotWritableException();

            $this->assertTrue($exception->getMessage() !== "", "StreamNotWritableException must have a non-empty default message.");
        }

        #[Group("Exceptions")]
        #[Define(name: "UnseekableStreamException — Instantiates With a Default Message", description: "UnseekableStreamException has a default message when no message is explicitly provided.")]
        public function testUnseekableStreamExceptionHasDefaultMessage () : void {
            $exception = new UnseekableStreamException();

            $this->assertTrue($exception->getMessage() !== "", "UnseekableStreamException must have a non-empty default message.");
        }

        #[Group("Exceptions")]
        #[Define(name: "InvalidVirtualFolderException — Instantiates With a Default Message", description: "InvalidVirtualFolderException has a default message when no message is explicitly provided.")]
        public function testInvalidVirtualFolderExceptionHasDefaultMessage () : void {
            $exception = new InvalidVirtualFolderException();

            $this->assertTrue($exception->getMessage() !== "", "InvalidVirtualFolderException must have a non-empty default message.");
        }

        #[Group("Exceptions")]
        #[Define(name: "UndefinedMapDirectoryException — Instantiates With a Default Message", description: "UndefinedMapDirectoryException has a default message when no message is explicitly provided.")]
        public function testUndefinedMapDirectoryExceptionHasDefaultMessage () : void {
            $exception = new UndefinedMapDirectoryException();

            $this->assertTrue($exception->getMessage() !== "", "UndefinedMapDirectoryException must have a non-empty default message.");
        }

        // ─── Exception Hierarchy ───────────────────────────────────────────────

        #[Group("Exceptions")]
        #[Define(name: "FilesystemException — Extends Exception", description: "FilesystemException must be a subclass of the base Exception class.")]
        public function testFilesystemExceptionExtendsException () : void {
            $exception = new FilesystemException("test");

            $this->assertTrue($exception instanceof Exception, "FilesystemException must extend the base Exception class.");
        }

        #[Group("Exceptions")]
        #[Define(name: "UploadException — Extends Exception", description: "UploadException must be a subclass of the base Exception class.")]
        public function testUploadExceptionExtendsException () : void {
            $exception = new UploadException("test");

            $this->assertTrue($exception instanceof Exception, "UploadException must extend the base Exception class.");
        }

        #[Group("Exceptions")]
        #[Define(name: "ExtensionRejectedUploadException — Extends UploadException", description: "ExtensionRejectedUploadException must be a subclass of UploadException.")]
        public function testExtensionRejectedUploadExceptionExtendsUploadException () : void {
            $exception = new ExtensionRejectedUploadException("ext");

            $this->assertTrue($exception instanceof UploadException, "ExtensionRejectedUploadException must extend UploadException.");
        }

        #[Group("Exceptions")]
        #[Define(name: "FileSizeLimitExceededException — Extends UploadException", description: "FileSizeLimitExceededException must be a subclass of UploadException.")]
        public function testFileSizeLimitExceededExceptionExtendsUploadException () : void {
            $exception = new FileSizeLimitExceededException("size");

            $this->assertTrue($exception instanceof UploadException, "FileSizeLimitExceededException must extend UploadException.");
        }

        #[Group("Exceptions")]
        #[Define(name: "MimeTypeRejectedUploadException — Extends UploadException", description: "MimeTypeRejectedUploadException must be a subclass of UploadException.")]
        public function testMimeTypeRejectedUploadExceptionExtendsUploadException () : void {
            $exception = new MimeTypeRejectedUploadException("mime");

            $this->assertTrue($exception instanceof UploadException, "MimeTypeRejectedUploadException must extend UploadException.");
        }

        #[Group("Exceptions")]
        #[Define(name: "ScannerConfigurationException — Extends Exception", description: "ScannerConfigurationException must be a subclass of the base Exception class.")]
        public function testScannerConfigurationExceptionExtendsException () : void {
            $exception = new ScannerConfigurationException("scan");

            $this->assertTrue($exception instanceof Exception, "ScannerConfigurationException must extend the base Exception class.");
        }

        #[Group("Exceptions")]
        #[Define(name: "NotAStreamException — Extends Exception", description: "NotAStreamException must be a subclass of the base Exception class.")]
        public function testNotAStreamExceptionExtendsException () : void {
            $exception = new NotAStreamException();

            $this->assertTrue($exception instanceof Exception, "NotAStreamException must extend the base Exception class.");
        }

        #[Group("Exceptions")]
        #[Define(name: "StreamNotWritableException — Extends Exception", description: "StreamNotWritableException must be a subclass of the base Exception class.")]
        public function testStreamNotWritableExceptionExtendsException () : void {
            $exception = new StreamNotWritableException();

            $this->assertTrue($exception instanceof Exception, "StreamNotWritableException must extend the base Exception class.");
        }

        #[Group("Exceptions")]
        #[Define(name: "UnseekableStreamException — Extends Exception", description: "UnseekableStreamException must be a subclass of the base Exception class.")]
        public function testUnseekableStreamExceptionExtendsException () : void {
            $exception = new UnseekableStreamException();

            $this->assertTrue($exception instanceof Exception, "UnseekableStreamException must extend the base Exception class.");
        }

        // ─── Upload Exceptions Are Catchable as UploadException ────────────────

        #[Group("Exceptions")]
        #[Define(name: "ExtensionRejectedUploadException — Catchable as UploadException", description: "An ExtensionRejectedUploadException can be caught by a catch block targeting UploadException.")]
        public function testExtensionRejectedExceptionCatchableAsUploadException () : void {
            $caught = false;

            try {
                throw new ExtensionRejectedUploadException("ext rejected");
            }
            catch (UploadException) {
                $caught = true;
            }

            $this->assertTrue($caught, "ExtensionRejectedUploadException must be catchable as UploadException.");
        }

        #[Group("Exceptions")]
        #[Define(name: "FileSizeLimitExceededException — Catchable as UploadException", description: "A FileSizeLimitExceededException can be caught by a catch block targeting UploadException.")]
        public function testFileSizeLimitExceptionCatchableAsUploadException () : void {
            $caught = false;

            try {
                throw new FileSizeLimitExceededException("too big");
            }
            catch (UploadException) {
                $caught = true;
            }

            $this->assertTrue($caught, "FileSizeLimitExceededException must be catchable as UploadException.");
        }

        #[Group("Exceptions")]
        #[Define(name: "MimeTypeRejectedUploadException — Catchable as UploadException", description: "A MimeTypeRejectedUploadException can be caught by a catch block targeting UploadException.")]
        public function testMimeTypeRejectedExceptionCatchableAsUploadException () : void {
            $caught = false;

            try {
                throw new MimeTypeRejectedUploadException("MIME rejected");
            }
            catch (UploadException) {
                $caught = true;
            }

            $this->assertTrue($caught, "MimeTypeRejectedUploadException must be catchable as UploadException.");
        }
    }
?>
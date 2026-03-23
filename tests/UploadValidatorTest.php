<?php
    /**
     * Project Name:    Wingman Explorer - Upload Validator Tests
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
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Explorer\Exceptions\ExtensionRejectedUploadException;
    use Wingman\Explorer\Exceptions\FileSizeLimitExceededException;
    use Wingman\Explorer\Exceptions\MimeTypeRejectedUploadException;
    use Wingman\Explorer\Exceptions\UploadException;
    use Wingman\Explorer\Resources\TempFile;
    use Wingman\Explorer\UploadValidator;

    /**
     * Tests for UploadValidator — size, extension, MIME, custom constraints and configuration hydration.
     */
    class UploadValidatorTest extends Test {
        // ─── Happy Paths ───────────────────────────────────────────────────────

        #[Group("UploadValidator")]
        #[Define(name: "Validate — Passes for Valid File With No Constraints", description: "A valid TempFile passes validation when no constraints have been registered.")]
        public function testValidationPassesForValidFileWithNoConstraints () : void {
            $tempFile = TempFile::create("document.txt", "text/plain", "content");
            $validator = UploadValidator::create();

            $threw = false;

            try {
                $validator->validate($tempFile);
            }
            catch (UploadException) {
                $threw = true;
            }

            $this->assertTrue(!$threw, "validate() must not throw when no constraints are set and the file is valid.");
        }

        #[Group("UploadValidator")]
        #[Define(name: "Validate — Passes When File Size Is Within Max", description: "validate() does not throw when the file size is within the configured maximum.")]
        public function testValidationPassesWhenFileSizeIsWithinMax () : void {
            $tempFile = TempFile::create("small.txt", "text/plain", "hi"); # 2 bytes
            $validator = UploadValidator::create()->setMaxSize(1024);

            $threw = false;

            try {
                $validator->validate($tempFile);
            }
            catch (UploadException) {
                $threw = true;
            }

            $this->assertTrue(!$threw, "validate() must not throw when file size is within the configured maximum.");
        }

        #[Group("UploadValidator")]
        #[Define(name: "Validate — Passes When Extension Is Allowed", description: "validate() does not throw when the file extension is in the allowed list.")]
        public function testValidationPassesWhenExtensionIsAllowed () : void {
            $tempFile = TempFile::create("safe.txt", "text/plain", "data");
            $validator = UploadValidator::create()->allowExtension("txt");

            $threw = false;

            try {
                $validator->validate($tempFile);
            }
            catch (UploadException) {
                $threw = true;
            }

            $this->assertTrue(!$threw, "validate() must not throw when the file's extension is in the allowed list.");
        }

        // ─── Sad Paths ─────────────────────────────────────────────────────────

        #[Group("UploadValidator")]
        #[Define(name: "Validate — Throws UploadException for Non-Zero PHP Error Code", description: "validate() throws UploadException when the TempFile was created with a write error, resulting in a non-zero PHP error code.")]
        public function testValidationThrowsForNonZeroPhpErrorCode () : void {
            $tempFile = TempFile::create("bad.txt", "text/plain", fn() => throw new \RuntimeException("forced write error"));
            $validator = UploadValidator::create();

            $threw = false;

            try {
                $validator->validate($tempFile);
            }
            catch (UploadException) {
                $threw = true;
            }

            $this->assertTrue($threw, "validate() must throw UploadException when the TempFile has a non-zero PHP error code.");
        }

        #[Group("UploadValidator")]
        #[Define(name: "Validate — Throws FileSizeLimitExceededException When Size Exceeds Max", description: "validate() throws FileSizeLimitExceededException when the file size is greater than the configured maximum.")]
        public function testValidationThrowsFileSizeLimitExceededException () : void {
            $tempFile = TempFile::create("large.txt", "text/plain", str_repeat("x", 100));
            $validator = UploadValidator::create()->setMaxSize(10);

            $threw = false;

            try {
                $validator->validate($tempFile);
            }
            catch (FileSizeLimitExceededException) {
                $threw = true;
            }

            $this->assertTrue($threw, "validate() must throw FileSizeLimitExceededException when the file exceeds the max size.");
        }

        #[Group("UploadValidator")]
        #[Define(name: "Validate — Throws ExtensionRejectedUploadException for Disallowed Extension", description: "validate() throws ExtensionRejectedUploadException when the file extension is not in the allowed list.")]
        public function testValidationThrowsForDisallowedExtension () : void {
            $tempFile = TempFile::create("evil.exe", "application/octet-stream", "binary");
            $validator = UploadValidator::create()->allowExtension("txt", "pdf");

            $threw = false;

            try {
                $validator->validate($tempFile);
            }
            catch (ExtensionRejectedUploadException) {
                $threw = true;
            }

            $this->assertTrue($threw, "validate() must throw ExtensionRejectedUploadException for a disallowed file extension.");
        }

        #[Group("UploadValidator")]
        #[Define(name: "Validate — Custom Constraint Exception Propagates", description: "An exception thrown by a custom constraint callable propagates out of validate().")]
        public function testCustomConstraintExceptionPropagates () : void {
            $tempFile = TempFile::create("file.txt", "text/plain", "data");
            $validator = UploadValidator::create()
                ->addConstraint(fn(TempFile $f) => throw new UploadException("Custom constraint rejected."));

            $threw = false;

            try {
                $validator->validate($tempFile);
            }
            catch (UploadException) {
                $threw = true;
            }

            $this->assertTrue($threw, "An UploadException thrown by a custom constraint must propagate from validate().");
        }

        #[Group("UploadValidator")]
        #[Define(name: "Validate — MIME Check Throws When Fileinfo Extension Is Absent", description: "validate() throws UploadException when a MIME type list is set but the 'fileinfo' extension is not loaded.")]
        public function testValidationThrowsWhenFileinfoIsAbsent () : void {
            if (extension_loaded("fileinfo")) {
                $this->assertTrue(true, "Skipped: 'fileinfo' extension is loaded. Cannot test its absence.");
                return;
            }

            $tempFile = TempFile::create("image.jpg", "image/jpeg", "fake jpeg data");
            $validator = UploadValidator::create()->allowMimeType("image/jpeg");

            $threw = false;

            try {
                $validator->validate($tempFile);
            }
            catch (UploadException) {
                $threw = true;
            }

            $this->assertTrue($threw, "validate() must throw UploadException when fileinfo is not loaded but MIME validation is configured.");
        }

        #[Group("UploadValidator")]
        #[Define(name: "Validate — MIME Check Rejects Unexpected MIME Type", description: "validate() throws MimeTypeRejectedUploadException when the detected MIME type is not in the allowed list.")]
        public function testValidationThrowsForDisallowedMimeType () : void {
            if (!extension_loaded("fileinfo")) {
                $this->assertTrue(true, "Skipped: 'fileinfo' extension is not available in this environment.");
                return;
            }

            $tempFile = TempFile::create("script.txt", "text/plain", "plain text content");
            $validator = UploadValidator::create()->allowMimeType("image/jpeg", "image/png");

            $threw = false;

            try {
                $validator->validate($tempFile);
            }
            catch (MimeTypeRejectedUploadException) {
                $threw = true;
            }

            $this->assertTrue($threw, "validate() must throw MimeTypeRejectedUploadException for a disallowed MIME type.");
        }

        // ─── Extension Normalisation ───────────────────────────────────────────

        #[Group("UploadValidator")]
        #[Define(name: "AllowExtension — Strips Leading Dot for Comparison", description: "When an extension is registered with a leading dot (e.g. '.txt'), it matches files named 'file.txt'.")]
        public function testAllowExtensionStripsLeadingDot () : void {
            $tempFile = TempFile::create("note.txt", "text/plain", "data");
            $validator = UploadValidator::create()->allowExtension(".txt");

            $threw = false;

            try {
                $validator->validate($tempFile);
            }
            catch (ExtensionRejectedUploadException) {
                $threw = true;
            }

            $this->assertTrue(!$threw, "allowExtension('.txt') must accept files with the 'txt' extension.");
        }

        #[Group("UploadValidator")]
        #[Define(name: "AllowExtension — Extension Matching Is Case-Insensitive", description: "Extension comparison normalises both sides to lowercase, so 'TXT' matches 'txt'.")]
        public function testAllowExtensionIsCaseInsensitive () : void {
            $tempFile = TempFile::create("UPPER.TXT", "text/plain", "data");
            $validator = UploadValidator::create()->allowExtension("txt");

            $threw = false;

            try {
                $validator->validate($tempFile);
            }
            catch (ExtensionRejectedUploadException) {
                $threw = true;
            }

            $this->assertTrue(!$threw, "Extension matching must be case-insensitive.");
        }

        // ─── Multiple Constraints ──────────────────────────────────────────────

        #[Group("UploadValidator")]
        #[Define(name: "Validate — Multiple Custom Constraints Run in Order", description: "When multiple custom constraints are added, they are run in the order they were registered; the first rejection terminates validation.")]
        public function testMultipleCustomConstraintsRunInOrder () : void {
            $order = [];
            $tempFile = TempFile::create("file.txt", "text/plain", "data");

            $validator = UploadValidator::create()
                ->addConstraint(function (TempFile $f) use (&$order) {
                    $order[] = 1;
                })
                ->addConstraint(function (TempFile $f) use (&$order) {
                    $order[] = 2;
                    throw new UploadException("Second constraint rejects.");
                })
                ->addConstraint(function (TempFile $f) use (&$order) {
                    $order[] = 3; # Must not be reached.
                });

            $threw = false;

            try {
                $validator->validate($tempFile);
            }
            catch (UploadException) {
                $threw = true;
            }

            $this->assertTrue($threw, "validate() must throw when a custom constraint rejects the file.");
            $this->assertCount(2, $order, "Validation must stop after the first rejecting constraint.");
        }

        // ─── Configuration Hydration ───────────────────────────────────────────

        #[Group("UploadValidator")]
        #[Define(name: "Config — MaxSize Hydrated from Array Config", description: "Passing 'explorer.upload.maxSize' in the config array sets the maximum size constraint.")]
        public function testConfigHydratesMaxSize () : void {
            $tempFile = TempFile::create("large.txt", "text/plain", str_repeat("z", 50));
            $validator = UploadValidator::create(["explorer.upload.maxSize" => 10]);

            $threw = false;

            try {
                $validator->validate($tempFile);
            }
            catch (FileSizeLimitExceededException) {
                $threw = true;
            }

            $this->assertTrue($threw, "The maxSize constraint must be applied when provided via the config array.");
        }

        #[Group("UploadValidator")]
        #[Define(name: "Config — Extensions Hydrated from Comma-Separated Config String", description: "Passing 'explorer.upload.extensions' as a comma-separated string registers those extensions as allowed.")]
        public function testConfigHydratesExtensions () : void {
            $rejectedFile = TempFile::create("script.js", "application/javascript", "data");
            $validator = UploadValidator::create(["explorer.upload.extensions" => "txt,pdf"]);

            $threw = false;

            try {
                $validator->validate($rejectedFile);
            }
            catch (ExtensionRejectedUploadException) {
                $threw = true;
            }

            $this->assertTrue($threw, "Extensions provided via config must be enforced during validation.");
        }
    }
?>
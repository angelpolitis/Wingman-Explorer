<?php
    /**
     * Project Name:    Wingman Explorer - Upload Validator
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
    use finfo;
    use Wingman\Explorer\Bridge\Cortex\Attributes\Configurable;
    use Wingman\Explorer\Bridge\Cortex\Configuration;
    use Wingman\Explorer\Exceptions\ExtensionRejectedUploadException;
    use Wingman\Explorer\Exceptions\FileSizeLimitExceededException;
    use Wingman\Explorer\Exceptions\MimeTypeRejectedUploadException;
    use Wingman\Explorer\Exceptions\UploadException;
    use Wingman\Explorer\Resources\TempFile;

    /**
     * Validates uploaded files against configurable size, MIME type, and extension constraints.
     *
     * Construct a validator using the static {@see create()} factory, chain constraint methods,
     * then call {@see validate()} for each {@see TempFile}. Any violation throws a typed
     * subclass of {@see UploadException}.
     *
     * Built-in constraints cover PHP upload errors, file size, MIME type, and extension.
     * File-intrinsic custom constraints (e.g. image dimensions, PDF page count) can be
     * added with {@see addConstraint()}. Context-dependent rules (quota, geo-restrictions)
     * belong one layer up and should not be added here.
     *
     * <code>
     * UploadValidator::create()
     *     ->allowExtension('jpg', 'png', 'gif')
     *     ->allowMimeType('image/jpeg', 'image/png', 'image/gif')
     *     ->setMaxSize(5 * 1024 * 1024)
     *     ->addConstraint(function (TempFile $file) {
     *         [$width, $height] = getimagesize($file->toArray()['tmp_name']);
     *         if ($width > 4096 || $height > 4096) {
     *             throw new UploadException('Image dimensions exceed the 4096px limit.');
     *         }
     *     })
     *     ->validate($tempFile);
     * </code>
     *
     * @package Wingman\Explorer
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class UploadValidator {
        /**
         * The list of allowed file extensions (lower-cased).
         * An empty list means extensions are not constrained.
         * @var string[]
         */
        private array $allowedExtensions = [];

        /**
         * The list of allowed MIME types.
         * An empty list means MIME types are not constrained.
         * @var string[]
         */
        private array $allowedMimeTypes = [];

        /**
         * The list of custom file-intrinsic constraint callbacks.
         * Each callable receives a {@see TempFile} and must throw an {@see UploadException}
         * subclass to signal rejection.
         * @var callable[]
         */
        private array $customConstraints = [];

        /**
         * A comma-separated list of allowed file extensions supplied via configuration.
         * Parsed into {@see $allowedExtensions} during construction; empty means unconstrained.
         * @var string
         */
        #[Configurable("explorer.upload.extensions", "Comma-separated list of allowed file extensions (e.g. \"jpg,png,pdf\").")]
        protected string $allowedExtensionsConfig = "";

        /**
         * A comma-separated list of allowed MIME types supplied via configuration.
         * Parsed into {@see $allowedMimeTypes} during construction; empty means unconstrained.
         * @var string
         */
        #[Configurable("explorer.upload.mimeTypes", "Comma-separated list of allowed MIME types (e.g. \"image/jpeg,image/png\").")]
        protected string $allowedMimeTypesConfig = "";

        /**
         * The maximum allowed file size in bytes, or null if unconstrained.
         * Settable via configuration key `explorer.upload.maxSize`.
         * @var int|null
         */
        #[Configurable("explorer.upload.maxSize", "Maximum permitted upload size in bytes.")]
        protected ?int $maxSizeBytes = null;

        /**
         * Creates a new upload validator and applies any settings present in `$config`.
         *
         * Scalar properties annotated with `#[Configurable]` are hydrated automatically.
         * The `explorer.upload.extensions` and `explorer.upload.mimeTypes` keys accept
         * comma-separated strings that are split and registered as allowed values.
         * @param array|Configuration $config A flat dot-notation configuration map or a Cortex
         * `Configuration` instance. Supported keys: `explorer.upload.maxSize`,
         * `explorer.upload.extensions`, `explorer.upload.mimeTypes`.
         */
        public function __construct (array|Configuration $config = []) {
            Configuration::hydrate($this, $config);

            if (!empty($this->allowedExtensionsConfig)) {
                $this->allowExtension(...array_filter(array_map("trim", explode(",", $this->allowedExtensionsConfig))));
            }

            if (!empty($this->allowedMimeTypesConfig)) {
                $this->allowMimeType(...array_filter(array_map("trim", explode(",", $this->allowedMimeTypesConfig))));
            }
        }

        /**
         * Adds one or more allowed file extensions to this validator.
         * Extension comparisons are case-insensitive.
         * @param string ...$extensions The extensions to allow (with or without a leading dot).
         * @return static The validator, for chaining.
         */
        public function allowExtension (string ...$extensions) : static {
            foreach ($extensions as $ext) {
                $this->allowedExtensions[] = strtolower(ltrim($ext, '.'));
            }

            return $this;
        }

        /**
         * Registers a custom file-intrinsic constraint.
         *
         * The callable receives the {@see TempFile} being validated and must throw an
         * {@see UploadException} subclass to signal rejection. Returning normally means
         * the constraint passed. Custom constraints are evaluated after all built-in ones,
         * in the order they were added.
         * @param callable(TempFile): void $constraint The constraint callback.
         * @return static The validator, for chaining.
         */
        public function addConstraint (callable $constraint) : static {
            $this->customConstraints[] = $constraint;
            return $this;
        }

        /**
         * Adds one or more allowed MIME types to this validator.
         * @param string ...$mimes The MIME types to allow (e.g. <code>"image/jpeg"</code>).
         * @return static The validator, for chaining.
         */
        public function allowMimeType (string ...$mimes) : static {
            foreach ($mimes as $mime) {
                $this->allowedMimeTypes[] = strtolower($mime);
            }

            return $this;
        }

        /**
         * Creates a new upload validator, optionally pre-configured from a Cortex configuration
         * source or a flat dot-notation array.
         * @param array|Configuration $config A flat dot-notation configuration map or a Cortex
         * `Configuration` instance. Supported keys: `explorer.upload.maxSize`,
         * `explorer.upload.extensions`, `explorer.upload.mimeTypes`.
         * @return static A new upload validator instance.
         */
        public static function create (array|Configuration $config = []) : static {
            return new static($config);
        }

        /**
         * Sets the maximum permitted file size.
         * @param int $bytes The maximum size in bytes.
         * @return static The validator, for chaining.
         */
        public function setMaxSize (int $bytes) : static {
            $this->maxSizeBytes = $bytes;
            return $this;
        }

        /**
         * Validates the given file against all configured constraints.
         *
         * Validation is performed in the following order: PHP upload error code, file
         * size, MIME type, and extension. The first failing constraint throws its typed
         * exception and subsequent constraints are not evaluated.
         * @param TempFile $file The uploaded file to validate.
         * @throws UploadException If the file has a non-zero PHP upload error code or a custom constraint rejects it.
         * @throws FileSizeLimitExceededException If the file exceeds the configured maximum size.
         * @throws MimeTypeRejectedUploadException If the file's detected MIME type is not allowed.
         * @throws ExtensionRejectedUploadException If the file's extension is not allowed.
         */
        public function validate (TempFile $file) : void {
            $data = $file->toArray();

            if ($data["error"] !== UPLOAD_ERR_OK) {
                throw new UploadException("File upload failed with PHP error code: {$data["error"]}.");
            }

            if ($this->maxSizeBytes !== null && $data["size"] > $this->maxSizeBytes) {
                throw new FileSizeLimitExceededException(
                    "File '{$data["name"]}' ({$data["size"]} bytes) exceeds the maximum allowed size of {$this->maxSizeBytes} bytes."
                );
            }

            if (!empty($this->allowedMimeTypes)) {
                if (!extension_loaded("fileinfo")) {
                    throw new UploadException("The 'fileinfo' extension is required for MIME type validation but is not loaded.");
                }

                $detectedMime = strtolower((new finfo(FILEINFO_MIME_TYPE))->file($data["tmp_name"]));

                if (!in_array($detectedMime, $this->allowedMimeTypes, true)) {
                    throw new MimeTypeRejectedUploadException(
                        "File '{$data["name"]}' has a disallowed MIME type: '$detectedMime'."
                    );
                }
            }

            if (!empty($this->allowedExtensions)) {
                $extension = strtolower(pathinfo($data["name"], PATHINFO_EXTENSION));

                if (!in_array($extension, $this->allowedExtensions, true)) {
                    throw new ExtensionRejectedUploadException("File '{$data["name"]}' has a disallowed extension: '$extension'.");
                }
            }

            foreach ($this->customConstraints as $constraint) {
                $constraint($file);
            }
        }
    }
?>
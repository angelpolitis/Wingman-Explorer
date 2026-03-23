<?php
    /**
     * Project Name:    Wingman Explorer - Upload Handler
     * Created by:      Angel Politis
     * Creation Date:   Mar 21 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer namespace.
    namespace Wingman\Explorer;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\FilesystemException;
    use Wingman\Explorer\Exceptions\UploadException;
    use Wingman\Explorer\Interfaces\Adapters\WritableFilesystemAdapterInterface;
    use Wingman\Explorer\Resources\TempFile;

    /**
     * Handles moving validated uploaded files to their final destination via a filesystem adapter.
     *
     * Accepts a {@see TempFile} (wrapping a PHP HTTP upload), runs it through an injected
     * {@see UploadValidator}, performs the PHP upload security check, then reads the
     * temporary file content and writes it atomically to the destination path via the
     * configured {@see WritableFilesystemAdapterInterface}.
     *
     * The adapter abstraction means uploads can be persisted to local disk, S3, Azure,
     * GCS, SFTP, or any other registered adapter without changing application code.
     *
     * Usage:
     * <code>
     * $handler = new UploadHandler(
     *     adapter: new LocalAdapter(),
     *     validator: UploadValidator::create()
     *         ->allowExtension('jpg', 'png')
     *         ->allowMimeType('image/jpeg', 'image/png')
     *         ->setMaxSize(5 * 1024 * 1024)
     * );
     *
     * $localFile = $handler->upload($tempFile, '/var/www/uploads/profile.jpg');
     * </code>
     *
     * @package Wingman\Explorer
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class UploadHandler {
        /**
         * The filesystem adapter used to write uploaded files.
         * @var WritableFilesystemAdapterInterface
         */
        private WritableFilesystemAdapterInterface $adapter;

        /**
         * The validator applied to every upload before it is persisted.
         * @var UploadValidator
         */
        private UploadValidator $validator;

        /**
         * Creates a new upload handler.
         * @param WritableFilesystemAdapterInterface $adapter The filesystem adapter to write files through.
         * @param UploadValidator $validator The validator applied to each upload.
         */
        public function __construct (WritableFilesystemAdapterInterface $adapter, UploadValidator $validator) {
            $this->adapter = $adapter;
            $this->validator = $validator;
        }

        /**
         * Validates and persists a single uploaded file to the given destination path.
         *
         * Validation is run first via {@see UploadValidator::validate()}, followed by a
         * PHP-level security check that confirms the file was submitted through an actual
         * HTTP POST upload. The temporary file content is then read into memory and written
         * atomically to the destination via the configured adapter.
         *
         * The {@see TempFile} is not explicitly deleted here; PHP automatically purges
         * temporary upload files at the end of the request, and {@see TempFile::__destruct()}
         * handles early removal when the object goes out of scope.
         * @param TempFile $file The uploaded temporary file to process.
         * @param string $destination The full destination path the file should be written to.
         * @return string The destination path the file was written to.
         * @throws UploadException If validation fails, the security check fails, or the temporary file cannot be read.
         * @throws FilesystemException If the adapter cannot write to the destination.
         */
        public function upload (TempFile $file, string $destination) : string {
            $this->validator->validate($file);

            $data = $file->toArray();

            if (!is_uploaded_file($data["tmp_name"])) {
                throw new UploadException("Security check failed: the file was not submitted via HTTP POST.");
            }

            $content = file_get_contents($data["tmp_name"]);

            if ($content === false) {
                throw new UploadException("Failed to read temporary file '{$data["name"]}'.");
            }

            $this->adapter->write($destination, $content);

            return $destination;
        }
    }
?>
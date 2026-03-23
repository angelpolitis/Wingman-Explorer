<?php
    /**
     * Project Name:    Wingman Explorer - SFTP Adapter
     * Created by:      Angel Politis
     * Creation Date:   Dec 21 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */
    
    # Use the Explorer.Adapters namespace.
    namespace Wingman\Explorer\Adapters;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\FilesystemException;
    use Wingman\Explorer\Interfaces\Adapters\DirectoryFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\MovableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\ReadableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\WritableFilesystemAdapterInterface;

    /**
     * Represents an SFTP filesystem adapter.
     * @package Wingman\Explorer\Adapters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class SFTPAdapter implements
        ReadableFilesystemAdapterInterface,
        WritableFilesystemAdapterInterface,
        MovableFilesystemAdapterInterface,
        DirectoryFilesystemAdapterInterface
    {
        /**
         * The active SSH2 connection resource.
         * @var resource|false|null
         */
        protected $connection;

        /**
         * The SFTP server hostname or IP address.
         * @var string
         */
        protected string $host;

        /**
         * The SFTP login username.
         * @var string
         */
        protected string $username;

        /**
         * The login password, or null when using key-based authentication.
         * @var string|null
         */
        protected ?string $password;

        /**
         * The SFTP server port.
         * @var int
         */
        protected int $port;

        /**
         * Whether passive mode is enabled (unused for SFTP; retained for symmetry with FTPAdapter).
         * @var bool
         */
        protected bool $passive;

        /**
         * The SFTP subsystem resource.
         * @var resource
         */
        protected $sftp;

        /**
         * The path to the private key file for public key authentication, or null if not used.
         * @var string|null
         */
        protected ?string $privateKey;

        /**
         * The passphrase for the private key, or null if the key has no passphrase.
         * @var string|null
         */
        protected ?string $passphrase;

        /**
         * Creates a new SFTP adapter and establishes the server connection.
         * @param string $host The SFTP server hostname or IP address.
         * @param string $username The SFTP login username.
         * @param string|null $password The login password, or null when using key-based authentication.
         * @param int $port The SFTP server port.
         * @param string|null $privateKey The path to the private key file, or null if not used.
         * @param string|null $passphrase The passphrase for the private key, or null if not applicable.
         * @throws FilesystemException If the connection or authentication fails.
         */
        public function __construct(
            string $host,
            string $username,
            ?string $password = null,
            int $port = 22,
            ?string $privateKey = null,
            ?string $passphrase = null
        ) {
            $this->host = $host;
            $this->username = $username;
            $this->password = $password;
            $this->port = $port;
            $this->privateKey = $privateKey;
            $this->passphrase = $passphrase;

            $this->connect();
        }

        /**
         * Establishes an SFTP connection and initialises the SSH2 subsystem.
         * @throws FilesystemException If the connection or authentication fails.
         */
        protected function connect () : void {
            /** @disregard P1010 */
            $this->connection = @ssh2_connect($this->host, $this->port);
            if (!$this->connection) {
                throw new FilesystemException("Failed to connect to SFTP server {$this->host}:{$this->port}");
            }

            /** @disregard P1010 */
            if ($this->privateKey) {
                /** @disregard P1010 */
                if (!@ssh2_auth_pubkey_file($this->connection, $this->username, $this->privateKey . '.pub', $this->privateKey, $this->passphrase)) {
                    throw new FilesystemException("Public key authentication failed for user {$this->username}");
                }
            }
            else if (!@ssh2_auth_password($this->connection, $this->username, $this->password)) {
                throw new FilesystemException("Password authentication failed for user {$this->username}");
            }

            /** @disregard P1010 */
            $this->sftp = @ssh2_sftp($this->connection);
            if (!$this->sftp) {
                throw new FilesystemException("Failed to initialize SFTP subsystem.");
            }
        }

        /**
         * Recursively deletes a directory and all its contents over SFTP.
         * @param string $dir The path of the directory to delete.
         * @return bool Whether the directory was deleted.
         */
        protected function deleteDirectoryRecursive (string $dir) : bool {
            foreach ($this->list($dir) as $item) {
                $meta = $this->getMetadata($item, ["type"]);
                if ($meta["type"] === "dir") {
                    $this->deleteDirectoryRecursive($item);
                }
                else {
                    @unlink("ssh2.sftp://{$this->sftp}{$item}");
                }
            }
            return @rmdir("ssh2.sftp://{$this->sftp}{$dir}");
        }

        /**
         * Copies a file from one remote path to another.
         * @param string $source The source path.
         * @param string $destination The destination path.
         * @throws FilesystemException If the read or write operation fails.
         */
        public function copy (string $source, string $destination) : void {
            $content = $this->read($source);
            $this->write($destination, $content);
        }

        /**
         * Creates a file at the given remote path with optional initial content.
         * @param string $path The path of the file to create.
         * @param string $content The initial content of the file.
         * @throws FilesystemException If the file cannot be written.
         */
        public function create (string $path, string $content = "") : void {
            $this->write($path, $content);
        }
        
        /**
         * Creates a directory at the given remote path.
         * @param string $path The path of the directory to create.
         * @param bool $recursive Whether to create all parent directories as needed.
         * @param int $permissions Unused; accepted for interface compatibility.
         * @throws FilesystemException If the directory cannot be created.
         * @return bool Whether the directory was successfully created.
         */
        public function createDirectory (string $path, bool $recursive = false, int $permissions = 0775) : bool {
            if ($recursive) {
                $parts = explode('/', trim($path, '/'));
                $current = '';
                foreach ($parts as $part) {
                    $current .= '/' . $part;
                    if (!$this->exists($current)) {
                        /** @disregard P1010 */
                        if (!@ssh2_sftp_mkdir($this->sftp, $current)) {
                            throw new FilesystemException("Failed to create remote directory: {$current}");
                        }
                    }
                }
                return true;
            }
        
            /** @disregard P1010 */
            if (!@ssh2_sftp_mkdir($this->sftp, $path)) {
                throw new FilesystemException("Failed to create remote directory: {$path}");
            }
        
            return true;
        }

        /**
         * Deletes a file or directory at the given remote path.
         * @param string $path The path of the resource to delete.
         * @return bool Whether the resource was successfully deleted.
         */
        public function delete (string $path) : bool {
            $info = $this->getMetadata($path, ["type"]);
            if ($info["type"] === "dir") {
                return $this->deleteDirectoryRecursive($path);
            }

            $sftpPath = "ssh2.sftp://{$this->sftp}{$path}";
            return @unlink($sftpPath);
        }

        /**
         * Checks whether a resource exists at the given remote path.
         * @param string $path The path to check.
         * @return bool Whether the resource exists.
         */
        public function exists (string $path) : bool {
            $sftpPath = "ssh2.sftp://{$this->sftp}{$path}";
            return file_exists($sftpPath);
        }

        /**
         * Returns metadata about a remote resource.
         * @param string $path The path of the resource.
         * @param string[]|null $properties The specific properties to retrieve, or null for all defaults.
         * @throws FilesystemException If the remote stat call fails.
         * @return array<string, mixed> The requested metadata.
         */
        public function getMetadata (string $path, ?array $properties = null) : array {
            $properties ??= ["path", "name", "type", "size", "modified", "permissions", "owner", "group"];

            $result = [];

            foreach ($properties as $prop) {
                switch ($prop) {
                    case "path":
                        $result["path"] = $path;
                        break;

                    case "name":
                        $result["name"] = basename($path);
                        break;

                    case "type":
                        /** @disregard P1010 */
                        $stat = @ssh2_sftp_stat($this->sftp, $path);
                        if ($stat === false) {
                            throw new FilesystemException("Failed to stat remote path: {$path}");
                        }
                        $result["type"] = ($stat["mode"] & 0x4000) ? "dir" : "file";
                        break;

                    case "size":
                        /** @disregard P1010 */
                        $stat ??= @ssh2_sftp_stat($this->sftp, $path);
                        if ($stat === false) {
                            throw new FilesystemException("Failed to stat remote path: {$path}");
                        }
                        $result["size"] = $stat["size"] ?? null;
                        break;

                    case "modified":
                        /** @disregard P1010 */
                        $stat ??= @ssh2_sftp_stat($this->sftp, $path);
                        if ($stat === false) {
                            throw new FilesystemException("Failed to stat remote path: {$path}");
                        }
                        $result["modified"] = $stat["mtime"] ?? null;
                        break;

                    case "permissions":
                        /** @disregard P1010 */
                        $stat ??= @ssh2_sftp_stat($this->sftp, $path);
                        if ($stat === false) {
                            throw new FilesystemException("Failed to stat remote path: {$path}");
                        }
                        $result["permissions"] = sprintf("%04o", $stat["mode"] & 0x0FFF);
                        break;

                    case "owner":
                        /** @disregard P1010 */
                        $stat ??= @ssh2_sftp_stat($this->sftp, $path);
                        if ($stat === false) {
                            throw new FilesystemException("Failed to stat remote path: {$path}");
                        }
                        $result["owner"] = $stat["uid"] ?? null;
                        break;

                    case "group":
                        /** @disregard P1010 */
                        $stat ??= @ssh2_sftp_stat($this->sftp, $path);
                        if ($stat === false) {
                            throw new FilesystemException("Failed to stat remote path: {$path}");
                        }
                        $result["group"] = $stat["gid"] ?? null;
                        break;

                    default:
                        $result[$prop] = null;
                        break;
                }
            }

            return $result;
        }

        /**
         * Lists the contents of a remote directory.
         * @param string $path The path of the directory to list.
         * @throws FilesystemException If the directory cannot be listed.
         * @return iterable<string> An iterable of child resource paths.
         */
        public function list (string $path) : iterable {
            $sftpPath = "ssh2.sftp://{$this->sftp}{$path}";
            $handle = @opendir($sftpPath);
            if (!$handle) {
                throw new FilesystemException("Failed to open remote directory: {$path}");
            }

            while (($item = readdir($handle)) !== false) {
                if ($item === '.' || $item === "..") continue;
                yield "$path/$item";
            }

            closedir($handle);
        }

        /**
         * Moves a resource from one remote path to another using SFTP rename.
         * @param string $source The source path.
         * @param string $destination The destination path.
         * @throws FilesystemException If the move operation fails.
         */
        public function move (string $source, string $destination) : void {
            $sourcePath = "ssh2.sftp://{$this->sftp}{$source}";
            $destPath   = "ssh2.sftp://{$this->sftp}{$destination}";

            if (!@rename($sourcePath, $destPath)) {
                throw new FilesystemException("Failed to move {$source} to {$destination}");
            }
        }

        /**
         * Reads and returns the full contents of a remote file.
         * @param string $path The path of the file to read.
         * @throws FilesystemException If the file cannot be read.
         * @return string The file contents.
         */
        public function read (string $path) : string {
            $stream = @fopen("ssh2.sftp://{$this->sftp}{$path}", 'rb');
            if (!$stream) {
                throw new FilesystemException("Failed to open remote file for reading: {$path}");
            }
            $content = stream_get_contents($stream);
            fclose($stream);
            if ($content === false) {
                throw new FilesystemException("Failed to read content from remote file: {$path}");
            }
            return $content;
        }

        /**
         * Renames a resource by delegating to {@see move()}.
         * @param string $source The current path.
         * @param string $destination The new path.
         * @throws FilesystemException If the underlying move fails.
         */
        public function rename (string $source, string $destination) : void {
            $this->move($source, $destination);
        }

        /**
         * Writes content to a remote file atomically, creating it if it does not exist.
         * @param string $path The path of the file to write.
         * @param string $content The content to write.
         * @throws FilesystemException If the file cannot be written or the atomic rename fails.
         */
        public function write (string $path, string $content) : void {
            $dir = dirname($path);
            if (!$this->exists($dir)) {
                $this->createDirectory($dir, true);
            }

            $tmpPath = "$path.tmp";
            $stream = @fopen("ssh2.sftp://{$this->sftp}{$tmpPath}", "wb");
            if (!$stream) {
                throw new FilesystemException("Failed to open temporary remote file: {$tmpPath}");
            }

            if (fwrite($stream, $content) === false) {
                fclose($stream);
                throw new FilesystemException("Failed to write to temporary file: {$tmpPath}");
            }
            fclose($stream);

            /** @disregard P1010 */
            if (!@ssh2_sftp_rename($this->sftp, $tmpPath, $path)) {
                /** @disregard P1010 */
                @ssh2_sftp_unlink($this->sftp, $tmpPath);
                throw new FilesystemException("Failed to atomically move {$tmpPath} to {$path}");
            }
        }
    }
?>
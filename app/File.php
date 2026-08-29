<?php

namespace Gazelle;

abstract class File extends BaseObject {
    /**
     * Path to stored file
     */
    abstract public function path(): string;

    protected function setupWarningHandler(): void {
        // PHP should really make it easier to deal with missing files than this.
        set_error_handler(
            function (
                int    $severity,
                string $message,
                string $file,
                int    $line,
            ): bool {
                if ($severity === E_WARNING) {
                    throw new Exception\FileNotFoundException("$file($line): $message");
                }
                return false; // punt to next handler
            }
        );
    }

    /**
     * Does the file exist?
     */
    public function exists(): bool {
        return file_exists($this->path());
    }

    /**
     * Store some data as a file on disk at the specified path.
     */
    public function put(string $contents): bool {
        return file_put_contents($this->path(), $contents) !== false;
    }

    /**
     * Retrieve the contents of the stored file.
     */
    public function get(): string|false {
        $this->setupWarningHandler();
        try {
            $result = file_get_contents($this->path());
        } catch (Exception\FileNotFoundException) {
            $result = false;
        } finally {
            restore_error_handler();
        }
        return $result;
    }

    /**
     * Retrieve the size of the stored file. Note that the size
     * of a file that does not exist will be 0 (which may occur
     * if something bad happened). Use the exists() method to
     * distinguish between a true 0-byte file and missing file.
     */
    public function size(): int {
        return (int)filesize($this->path());
    }

    /**
     * Remove the stored file.
     */
    public function remove(): int {
        return (int)unlink($this->path());
    }

    /**
     * Generate a digest hash of the file
     * @throw \Exception if the file does not exist or it cannot be hashed
     *   (most likely an unknown hash algorithm).
     */
    public function hash(): string {
        $this->setupWarningHandler();
        try {
            $hash = hash_file(DIGEST_ALGO, $this->path());
            if ($hash === false) {
                throw new \Exception("Failed to compute hash for file: {$this->path()}");
            }
        } catch (Exception\FileNotFoundException) {
            throw new \Exception("Failed to compute hash for inexistent file: {$this->path()}");
        } finally {
            restore_error_handler();
        }
        return $hash;
    }
}

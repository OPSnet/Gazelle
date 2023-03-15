<?php

namespace Gazelle;

abstract class File extends Base {
    /**
     * Note that currently, PHP does not allow an abstract class to define a method
     * signature as array|int and then be specialized in the child class as either
     * one or the other. So everything is defined as mixed and we will see if this
     * may be revisited in a future version of PHP.
     * It might also not be the best example of inheritance, but is simplifies much
     * of the implementation as it is.
     */

    /**
     * Path to stored file
     */
    abstract public function path(mixed $id): string;

    /**
     * Does the file exist?
     */
    public function exists(mixed $id): bool {
        return file_exists($this->path($id));
    }

    /**
     * Store a file on disk at the specified path.
     */
    public function put(string $source, mixed $id): bool {
        return file_put_contents($this->path($id), $source) !== false;
    }

    /**
     * Retrieve the contents of the stored file.
     */
    public function get(mixed $id): string|false {
        return file_get_contents($this->path($id));
    }

    /**
     * Remove the stored file.
     */
    public function remove(mixed $id): bool {
        return @unlink($this->path($id));
    }
}

<?php

namespace Gazelle;

abstract class File extends Base {
    /**
     * Store a file on disk at the specified path.
     *
     * @param string $source The contents of the file
     * @param integer|array $id The unique identifier of the object
     * @return boolean Success of the operation
     */
    public function put(string $source, $id) {
        return file_put_contents($this->path($id), $source);
    }

    /**
     * Does the file exist?
     *
     * @param integer|array $id The unique identifier of the object
     * @return boolean Existence
     */
    public function exists($id) {
        return file_exists($this->path($id));
    }

    /**
     * Retrieve the contents of the stored file.
     *
     * @param integer|array $id The unique identifier of the object
     * @return string File contents
     */
    public function get($id) {
        return file_get_contents($this->path($id));
    }

    /**
     * Remove the stored file.
     *
     * @param integer|array $id The unique identifier of the object
     * @return boolean Success of unlink operation
     */
    public function remove(/* mixed */ $id) {
        return @unlink($this->path($id));
    }

    /**
     * Path of stored file
     *
     * @param integer|array $id The unique identifier of the object
     * @return string Fully qualified filename of object
     */
    public function path(/* mixed */ $id) {
        return "/tmp/$id";
    }
}

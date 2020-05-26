<?php

namespace Gazelle;

abstract class File extends Base {

    public function put($source, $id) {
        return copy($source, $this->path($id));
    }

    public function exists($id) {
        return file_exists($this->path($id));
    }

    public function get($id) {
        return file_get_contents($this->path($id));
    }

    public function remove($id) {
        unlink($this->path($id));
    }

    public function path($id) {
        return "/tmp/$id";
    }
}

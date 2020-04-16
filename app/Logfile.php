<?php

namespace Gazelle;

class Logfile {
    protected $checksum;
    protected $details;
    protected $filename;
    protected $name;
    protected $score;
    protected $text;

    public function __construct($filename, $name) {
        $this->filename = $filename; // where the uploaded logfile is stored
        $this->name = $name; // the name of the file e.g. "Artist - Album.log"
        $checker = new \OrpheusNET\Logchecker\Logchecker();
        $checker->new_file($this->filename);
        list($this->score, $this->details, $this->checksum, $this->text) = $checker->parse();
    }

    public function checksum()        { return $this->checksum; }
    public function checksumStatus()  { return $this->checksum ? '1' : '0'; }
    public function details()         { return $this->details; }
    public function detailsAsString() { return implode("\r\n", $this->details); }
    public function filename()        { return $this->filename; }
    public function score()           { return $this->score; }
    public function text()            { return $this->text; }
}

<?php

namespace Gazelle;

use OrpheusNET\Logchecker\Logchecker;
use OrpheusNET\Logchecker\Check\Checksum;

class Logfile {
    protected $checksumState;
    protected $details;
    protected $filepath;
    protected $filename;
    protected $score;
    protected $text;
    protected $ripper;
    protected $ripperVersion;
    protected $language;

    public function __construct(string $filepath, ?string $filename = null) {
        $this->filepath = $filepath; // where the uploaded logfile is stored
        $this->filename = $filename; // the name of the file e.g. "Artist - Album.log"
        $checker = new Logchecker();
        $checker->newFile($this->filepath);
        $checker->parse();
        $this->score         = max(0, $checker->getScore());
        $this->details       = $checker->getDetails();
        $this->checksumState = $checker->getChecksumState();
        $this->text          = $checker->getLog();
        $this->ripper        = $checker->getRipper() ?? '';
        $this->ripperVersion = $checker->getRipperVersion() ?? '';
        $this->language      = $checker->getLanguage();
    }

    public function checksum()        { return $this->checksumState === Checksum::CHECKSUM_OK; }
    public function checksumState()   { return $this->checksumState; }
    public function checksumStatus()  { return $this->checksum() ? '1' : '0'; }
    public function details()         { return $this->details; }
    public function detailsAsString() { return implode("\r\n", $this->details); }
    public function filepath()        { return $this->filepath; }
    public function filename()        { return $this->filename; }
    public function score()           { return $this->score; }
    public function text()            { return $this->text; }
    public function ripper()          { return $this->ripper; }
    public function ripperVersion()   { return $this->ripperVersion; }
    public function language()        { return $this->language; }
}

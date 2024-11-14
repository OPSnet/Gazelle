<?php

namespace Gazelle;

use OrpheusNET\Logchecker\Logchecker;
use OrpheusNET\Logchecker\Check\Checksum;

class Logfile {
    protected string $checksumState;
    protected array $details;
    protected int $score;
    protected string $text;
    protected string $ripper;
    protected string $ripperVersion;
    protected string $language;

    public function __construct(
        protected string $filepath,         // where the uploaded logfile is stored
        protected ?string $filename = null, // the name of the file e.g. "Artist - Album.log"
    ) {
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

    public function checksum(): bool {
        return $this->checksumState === Checksum::CHECKSUM_OK;
    }

    public function checksumState(): string {
        return $this->checksumState;
    }

    public function checksumStatus(): string {
        return $this->checksum() ? '1' : '0';
    }

    public function details(): array {
        return $this->details;
    }

    public function detailsAsString(): string {
        return implode("\r\n", $this->details);
    }

    public function filepath(): string {
        return $this->filepath;
    }

    public function filename(): string {
        return $this->filename;
    }

    public function score(): int {
        return $this->score;
    }

    public function text(): string {
        return $this->text;
    }

    public function ripper(): string {
        return $this->ripper;
    }

    public function ripperVersion(): string {
        return $this->ripperVersion;
    }

    public function language(): string {
        return $this->language;
    }
}

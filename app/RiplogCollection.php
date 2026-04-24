<?php

namespace Gazelle;

class RiplogCollection {
    public function __construct(
        protected array $riplogList,
        protected bool  $allChecksum = true,
        protected int   $lowestScore = 100,
    ) {
        foreach ($this->riplogList as $r) {
            $this->allChecksum = $this->allChecksum && $r->isChecksumOk();
            $this->lowestScore = min($this->lowestScore, $r->score());
        }
    }

    public function checksum(): bool {
        return $this->allChecksum;
    }

    public function checksumStatus(): string {
        return $this->allChecksum ? '1' : '0';
    }

    public function overallScore(): int {
        return $this->lowestScore;
    }

    public function riplogList(): array {
        return $this->riplogList;
    }

    public function total(): int {
        return count($this->riplogList);
    }
}

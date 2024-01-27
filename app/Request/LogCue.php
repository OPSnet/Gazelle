<?php

namespace Gazelle\Request;

class LogCue {
    public function __construct(
        protected bool $needLogChecksum = false,
        protected bool $needCue         = false,
        protected bool $needLog         = false,
        protected int  $minScore        = 0,
    ) {}

    public function isValid(): bool {
        return $this->minScore >= 0 and $this->minScore <= 100;
    }

    public function minScore(): int {
        return $this->minScore;
    }

    public function needLogChecksum(): bool {
        return $this->needLogChecksum;
    }

    public function needCue(): bool {
        return $this->needCue;
    }

    public function needLog(): bool {
        return $this->needLog;
    }

    public function dbValue(): string {
        $value = [];
        if ($this->needLog) {
            $value[] = 'Log';
            if ($this->isValid() && $this->minScore > 0) {
                $value[] = ($this->minScore == 100) ? '(100%)' : "(>= {$this->minScore}%)";
            }
        }
        if ($this->needCue) {
            $value[] = (count($value)) ? '+ Cue' : 'Cue';
        }
        return implode(' ', $value);
    }
}

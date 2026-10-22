<?php

namespace Gazelle\UserMatch;

use Gazelle\Enum\UserMatchQuality;

class MatchResult {
    protected array $usernames = [];
    protected array $emails = [];
    protected array $ips = [];
    protected int $score;
    protected ?\DateTimeImmutable $firstDate = null;
    protected ?\DateTimeImmutable $lastDate = null;

    public function addNameMatch(string $name, string $other, UserMatchQuality $matchType): static {
        $this->usernames[] = [$name, $other, $matchType];
        return $this;
    }

    public function addEmailMatch(string $email, string $other, UserMatchQuality $matchType): static {
        $this->emails[] = [$email, $other, $matchType];
        return $this;
    }

    public function addIpMatch(string $ip, ?\DateTimeImmutable $ipTime, ?\DateTimeImmutable $otherTime, UserMatchQuality $matchType): static {
        $this->ips[] = [$ip, $ipTime, $otherTime, $matchType];
        if ($ipTime) {
            $this->firstDate = min($this->firstDate ?? $ipTime, $ipTime);
            $this->lastDate = max($this->lastDate, $ipTime);
        }
        return $this;
    }

    public function hasMatch(): bool {
        return !empty($this->usernames) || !empty($this->emails) || !empty($this->ips);
    }

    public function usernames(): array {
        return $this->usernames;
    }

    public function emails(): array {
        return $this->emails;
    }

    public function ips(): array {
        return $this->ips;
    }

    public function firstDate(): ?\DateTimeImmutable {
        return $this->firstDate;
    }

    public function lastDate(): ?\DateTimeImmutable {
        return $this->lastDate;
    }

    public function score(): int {
        if (!isset($this->score)) {
            $score = 0;
            foreach (array_column($this->usernames, 2) as $matchType) {
                $score += match ($matchType) {
                    UserMatchQuality::full    => 50,
                    UserMatchQuality::partial => 20,
                    UserMatchQuality::weak    => 10,
                    default => 0
                };
            }
            foreach (array_column($this->emails, 2) as $matchType) {
                $score += match ($matchType) {
                    UserMatchQuality::full    => 100,
                    UserMatchQuality::partial => 25,
                    UserMatchQuality::weak    => 5,
                    default => 0
                };
            }
            foreach (array_column($this->ips, 3) as $matchType) {
                $score += match ($matchType) {
                    UserMatchQuality::full    => 20,
                    UserMatchQuality::partial => 5,
                    UserMatchQuality::weak    => 1,
                    default => 0
                };
            }
            $this->score = $score;
        }
        return $this->score;
    }

    public function summary(): array {
        $summary = [];
        foreach (array_column($this->usernames, 2) as $matchType) {
            $summary['usernames'][$matchType->value] = ($summary['usernames'][$matchType->value] ?? 0) + 1;
        }
        foreach (array_column($this->emails, 2) as $matchType) {
            $summary['emails'][$matchType->value] = ($summary['emails'][$matchType->value] ?? 0) + 1;
        }
        foreach (array_column($this->ips, 3) as $matchType) {
            $summary['ips'][$matchType->value] = ($summary['ips'][$matchType->value] ?? 0) + 1;
        }
        isset($summary['usernames']) && ksort($summary['usernames']);
        isset($summary['emails']) && ksort($summary['emails']);
        isset($summary['ips']) && ksort($summary['ips']);
        return $summary;
    }
}

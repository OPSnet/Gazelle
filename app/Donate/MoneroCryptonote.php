<?php

namespace Gazelle\Donate;

class MoneroCryptonote extends \MoneroIntegrations\MoneroPhp\Cryptonote {
    // fix checksum calculation for integrated addresses
    public function verify_checksum($address): bool {
        $decoded = $this->base58->decode($address);  /** @phpstan-ignore-line */
        $checksum = substr($decoded, -8);
        $checksum_hash = $this->keccak_256(substr($decoded, 0, -8));
        $calculated = substr($checksum_hash, 0, 8);
        return $checksum === $calculated;
    }
}

<?php

namespace Gazelle\Exception;

class BonusPurchaseException extends \Exception {
    public function __construct(
        public readonly \Gazelle\Enum\BonusItemPurchaseStatus $reason
    ) {
        parent::__construct();
    }
}

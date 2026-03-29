<?php

namespace Gazelle\Donate;

use MoneroIntegrations\MoneroPhp;

class Monero {
    use \Gazelle\Pg;

    private readonly string $spendKey;
    private readonly string $viewKey;
    private readonly MoneroPhp\Cryptonote $cryptoNote;
    private const networkByte = '12'; // this is hex, 0x12 == 18

    protected const paymentIdLength = 8;

    /**
     * Initialize with a primary account address (starting with a "4"), which will be
     * used as the receiving address for all incoming donations.
     *
     * @throws MoneroException if accountAddress is not a valid monero primary account address
     */
    public function __construct(string $accountAddress = MONERO_DONATION_ADDRESS) {
        $this->cryptoNote = new MoneroPhp\Cryptonote();
        $decodedAddr = $this->cryptoNote->decode_address($accountAddress);
        $this->spendKey = $decodedAddr['spendKey'];
        $this->viewKey = $decodedAddr['viewKey'];

        if ($decodedAddr['networkByte'] !== self::networkByte) {
            throw new MoneroException("invalid monero source address, expected address starting with 4, got $accountAddress");
        }
    }

    /**
     * Find the current payment id for user.
     *
     * @return string payment id in hex or null if no payment id exists
     */
    protected function findPaymentIdByUserId(int $userId): ?string {
        $token = $this->pg()->scalarex("
            select token
            from donate_monero
            where id_user = $1
            ", $userId
        );
        if ($token) {
            return bin2hex($token);
        }
        return null;
    }

    /**
     * Find the current payment id or create a new one for user.
     *
     * @return string payment id in hex
     */
    protected function createPaymentIdByUserId(int $userId): string {
        $token = $this->findPaymentIdByUserId($userId);
        if ($token) {
            return $token;
        }
        $token = $this->pg()->scalarex("
            insert into donate_monero
                   (id_user, token)
            values ($1,      gen_random_bytes($2))
            returning token
            ", $userId, self::paymentIdLength
        );
        return bin2hex($token);
    }

    /**
     * Create a monero integrated address associated with the user id.
     */
    public function address(int $userId): string {
        return $this->cryptoNote->integrated_addr_from_keys(
            $this->spendKey, $this->viewKey, $this->createPaymentIdByUserId($userId));
    }

    /**
     * Find the user associated with the given payment id.
     *
     * @param string $paymentId in hex (8 bytes / 16 chars)
     * @return int user id or null if no user found
     * @throws MoneroException if paymentId is of invalid format
     */
    public function findUserIdbyPaymentId(string $paymentId): ?int {
        if (strlen($paymentId) !== self::paymentIdLength * 2 || !ctype_xdigit($paymentId)) {
            throw new MoneroException("invalid payment id: $paymentId");
        }
        return $this->pg()->scalarex("
            select id_user
            from donate_monero
            where token = $1
            ", '\\x' . $paymentId
        );
    }

    public function invalidate(int $userId): bool {
        return $this->pg()->query("
                delete from donate_monero
                where id_user = $1
                ", $userId
        ) === 1;
    }
}

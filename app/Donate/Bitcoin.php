<?php

namespace Gazelle\Donate;

use BitWasp\Bitcoin\Address\AddressCreator;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKey;
use BitWasp\Bitcoin\Network\Slip132\BitcoinRegistry;
use BitWasp\Bitcoin\Key\Deterministic\Slip132\Slip132;
use BitWasp\Bitcoin\Key\KeyToScript\KeyToScriptHelper;
use BitWasp\Bitcoin\Key\Deterministic\HdPrefix\GlobalPrefixConfig;
use BitWasp\Bitcoin\Key\Deterministic\HdPrefix\NetworkConfig;
use BitWasp\Bitcoin\Serializer\Key\HierarchicalKey\Base58ExtendedKeySerializer;
use BitWasp\Bitcoin\Serializer\Key\HierarchicalKey\ExtendedKeySerializer;

class Bitcoin {
    use \Gazelle\Pg;

    protected HierarchicalKey $parentKey;
    protected const basePath = '';

    /**
     * Initialize with a xpub/ypub/zpub key of your receive subaccount (HD path m/0'/0 in most cases)
     * for correspondingly P2PKH/P2SH-P2WPKH/P2WPKH (legacy 1... / wrapped segwit 3... / segwit bc1...) addresses.
     *
     * In the electrum (4.3.4) console you can get this key with "wallet.get_keystore().xpub_receive"
     *
     * the last command of this code snipped should return the first address of your wallet,
     * assuming a standard segwit wallet:
     * x = wallet.get_keystore().xpub_receive
     * wallet.pubkeys_to_address([wallet.get_keystore().get_pubkey_from_xpub(x, (0,)).hex()])
     *
     * @throws \Exception if $xyzpub is not valid
     */
    public function __construct(
        protected string $xyzpub = BITCOIN_DONATION_XYZPUB,
        protected \Gazelle\Counter $nextKeyCounter = new \Gazelle\Counter("donation-bitcoin"),
    ) {
        $adapter = \BitWasp\Bitcoin\Bitcoin::getEcAdapter();
        $slip132 = new Slip132(new KeyToScriptHelper($adapter));
        $btcPrefixes = new BitcoinRegistry();

        $prefix = match (substr($xyzpub, 0, 4)) {
            'xpub' => $slip132->p2pkh($btcPrefixes),
            'ypub' => $slip132->p2shP2wpkh($btcPrefixes),
            'zpub' => $slip132->p2wpkh($btcPrefixes),
            default => throw new DonationException("invalid xyzpub key $xyzpub"),
        };
        $network = new \BitWasp\Bitcoin\Network\Networks\Bitcoin;
        $config = new GlobalPrefixConfig([
            new NetworkConfig($network, [$prefix])
        ]);
        $serializer = new Base58ExtendedKeySerializer(
            new ExtendedKeySerializer($adapter, $config)
        );

        $this->parentKey = $serializer->parse($network, $xyzpub);
    }

    protected function path(int $counter): string {
        return self::basePath . $counter;
    }

    protected function createAddress(): string {
        $path = $this->path($this->nextKeyCounter->increment());
        return $this->parentKey->derivePath($path)->getAddress(new AddressCreator())->getAddress();
    }

    /**
     * Get (and create if needed) a bitcoin address for the specified user.
     *
     * @param int $userId user id
     * @return string bitcoin address
     */
    public function address(int $userId): string {
        $addr = $this->pg()->scalar("
            SELECT address
            FROM donate_bitcoin
            WHERE id_user = ?
            ", $userId
        );
        if (is_null($addr)) {
            $addr = $this->createAddress();
            return $this->pg()->scalar("
                INSERT INTO donate_bitcoin
                       (id_user, address)
                VALUES (?,       ?)
                RETURNING address
                ", $userId, $addr
            );
        }
        return $addr;
    }

    /**
     * Find the user associated with the given bitcoin address.
     *
     * @param string $addr bitcoin address
     * @return int user id or null if no user found
     */
    public function findUserIdbyAddress(string $addr): ?int {
        return $this->pg()->scalar("
            SELECT id_user
            FROM donate_bitcoin
            WHERE address = ?
            ", $addr
        );
    }

    public function invalidate(int $userId): bool {
        return $this->pg()->prepared_query("
                DELETE FROM donate_bitcoin
                WHERE id_user = ?
                ", $userId
        ) === 1;
    }
}

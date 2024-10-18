<?php

namespace Gazelle\Util;

class Discogs extends \Gazelle\Base {
    protected const DISCOGS_API_URL = 'https://api.discogs.com/artists/%d';
    protected array $info;

    public function __construct(
        protected int $id,
        ?int $sequence = null,
        ?string $name = null,
        ?string $stem = null,
    ) {
        if (!is_null($sequence)) {
            $this->info = [
                'sequence' => $sequence,
                'name'     => $name,
                'stem'     => $stem,
            ];
        }
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $curl = curl_init();
        if ($curl === false) {
            return [];
        }
        curl_setopt_array($curl, [
            CURLOPT_URL            => sprintf(self::DISCOGS_API_URL, $this->id),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => FAKE_USERAGENT,
        ]);
        $proxy = httpProxy();
        if ($proxy) {
            curl_setopt_array($curl, [
                CURLOPT_HTTPPROXYTUNNEL => true,
                CURLOPT_PROXY           => $proxy,
            ]);
        }

        $result = curl_exec($curl);
        if (!is_string($result) || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200) {
            $this->info = [
                'sequence' => 0,
                'name'     => '',
                'stem'     => '',
            ];
            return $this->info;
        }

        /* Discogs names are e.g. "Spectrum (4)"
         * This is split into "Spectrum" and 4 to detect and handle homonyms.
         * First come, first served. The first homonym is considered preferred,
         * so the artist page will show "Spectrum". Subsequent artists will
         * be shown as "Spectrum (2)", "Spectrum (1)", ...
         * This can be adjusted via a control panel afterwards.
         */
        $payload = json_decode($result);
        $name    = $payload->name;
        if (preg_match('/^(.*) \((\d+)\)$/', $name, $match)) {
            $this->info = [
                'sequence' => (int)$match[2],
                'name'     => $name,
                'stem'     => $match[1],
            ];
        } else {
            $this->info = [
                'sequence' => 1,
                'name'     => $name,
                'stem'     => $name,
            ];
        }
        return $this->info;
    }

    public function id(): int {
        return $this->id;
    }

    public function name(): string {
        return $this->info()['name'];
    }

    public function sequence(): int {
        return $this->info()['sequence'];
    }

    public function stem(): string {
        return $this->info()['stem'];
    }
}

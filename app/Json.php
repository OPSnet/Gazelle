<?php

namespace Gazelle;

abstract class Json extends Base {
    protected $version;
    protected $source;
    protected $mode;

    public function __construct() {
        parent::__construct();
        $this->source = SITE_NAME;
        $this->mode = 0;
        $this->version = 1;
    }

    /**
     * The payload of a valid JSON response, implemented in the child class.
     * @return array Payload to be passed to json_encode()
     *         null if the payload cannot be produced (permissions, id not found, ...).
     */
    abstract public function payload(): ?array;

    /**
     * Configure JSON printing (any of the json_encode  JSON_* constants)
     *
     * @param int $mode the bit-or'ed values to confgure encoding results
     */
    public function setMode(string $mode) {
        $this->mode = $mode;
        return $this;
    }

    /**
     * set the version of the Json payload. Increment the
     * value when there is significant change in the payload.
     * If not called, the version defaults to 1.
     *
     * @param int version
     */
    public function setVersion(int $version) {
        $this->version = $version;
        return $this;
    }

    /**
     * General failure routine for when bad things happen.
     *
     * @param string $message The error set in the JSON response
     */
    public function failure(string $message) {
        print json_encode(
            array_merge([
                    'status' => 'failure',
                    'response' => [],
                    'error' => $message,
                ],
                $this->info(),
                $this->debug(),
            ),
            $this->mode
        );
    }

    public function emit() {
        $payload = $this->payload();
        if (!$payload) {
            return;
        }
        print json_encode(
            array_merge([
                    'status' => 'success',
                    'response' => $payload,
                ],
                $this->info(),
                $this->debug()
            ),
            $this->mode
        );
    }

    protected function debug() {
        if (!check_perms('site_debug')) {
            return [];
        }
        $Debug = new \Gazelle\Debug;
        return [
            'debug' => [
                'queries'  => $Debug->get_queries(),
                'searches' => $Debug->get_sphinxql_queries(),
            ],
        ];
    }

    protected function info() {
        return [
            'info' => [
                'source' => $this->source,
                'version' => $this->version,
            ]
        ];
    }
}

<?php

namespace Gazelle;

abstract class Json extends Base {
    protected int $mode    = JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;
    protected int $version = 1;

    /**
     * The payload of a valid JSON response, implemented in the child class.
     * @return array Payload to be passed to json_encode()
     *         null if the payload cannot be produced (permissions, id not found, ...).
     */
    abstract public function payload(): ?array;

    /**
     * Configure JSON printing (any of the json_encode  JSON_* constants)
     */
    public function setMode(int $mode) {
        $this->mode = $mode;
        return $this;
    }

    /**
     * Set the version of the Json payload. Increment the
     * value when there is significant change in the payload.
     * If not called, the version defaults to 1.
     */
    public function setVersion(int $version) {
        $this->version = $version;
        return $this;
    }

    /**
     * General failure routine for when bad things happen.
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
        try {
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
        } catch (\JsonException $e) {
            $this->failure("JSON encoding failed, look for malformed UTF-8 encoding");
        }
    }

    protected function debug(): array {
        global $Viewer;
        if (!isset($Viewer) || !$Viewer->permitted('site_debug')) {
            return [];
        }
        global $Debug;
        $info = [
            'debug' => [
                'queries' => isset($Debug) ? $Debug->get_queries() : [],
            ],
        ];
        if (class_exists('Sphinxql')) {
            $info['searches'] = \Sphinxql::$Queries;
        }
        return $info;
    }

    protected function info(): array {
        return [
            'info' => [
                'source'  => SITE_NAME,
                'version' => $this->version,
            ]
        ];
    }
}

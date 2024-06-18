<?php

namespace Gazelle;

class BaseRequestContext {
    protected string $module; // previously known as global $Document
    protected bool $isValid;
    protected array $ua;

    public function __construct(
        protected readonly string $scriptName,
        protected string $remoteAddr,
        string $useragent,
    ) {
        $info = pathinfo($scriptName);
        if (!array_key_exists('dirname', $info)) {
            $this->module  = '';
            $this->isValid = false;
        } else {
            $this->module  = $info['filename'];
            $this->isValid = $info['dirname'] === '/';
        }
        $this->ua = \parse_user_agent($useragent);
    }

    public function ua(): array {
        return $this->ua;
    }

    public function browser(): ?string {
        return $this->ua()['Browser'];
    }

    public function browserVersion(): ?string {
        return $this->ua()['BrowserVersion'];
    }

    public function isValid(): bool {
        return $this->isValid;
    }

    public function module(): string {
        return $this->module;
    }

    public function os(): ?string {
        return $this->ua()['OperatingSystem'];
    }

    public function osVersion(): ?string {
        return $this->ua()['OperatingSystemVersion'];
    }

    public function remoteAddr(): string {
        return $this->remoteAddr;
    }

    /**
     * Because we <3 our staff
     */
    public function anonymize(): static {
        $this->ua = [
            'Browser'                => 'staff-browser',
            'BrowserVersion'         => null,
            'OperatingSystem'        => null,
            'OperatingSystemVersion' => null,
        ];
        $this->remoteAddr = '127.0.0.1';
        return $this;
    }

    /**
     * Early in the startup phase, it may be desirable to
     * redirect processing to another section.
     */
    public function setModule(string $module): static {
        $this->module = $module;
        return $this;
    }
}

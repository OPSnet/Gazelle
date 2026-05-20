<?php

namespace Gazelle;

class RequestContext {
    protected string $module; // previously known as global $Document
    protected bool   $isValid;
    protected array  $ua;
    protected Log    $logger;
    protected User   $viewer;
    protected string $sessionId;

    public function __construct(
        protected readonly string $scriptName,
        protected string $remoteAddr,
        protected string $useragent,
    ) {
        $info = pathinfo($scriptName);
        if (!array_key_exists('dirname', $info)) {
            $this->module  = '';
            $this->isValid = false;
        } else {
            $this->module  = $info['filename'];
            $this->isValid = $info['dirname'] === '/';
        }
        $this->ua     = \parse_user_agent($useragent);
        $this->logger = new Log();
    }

    public function ua(): array {
        return $this->ua;
    }

    public function browser(): string {
        return (string)$this->ua()['Browser'];
    }

    public function browserVersion(): string {
        return (string)$this->ua()['BrowserVersion'];
    }

    public function isValid(): bool {
        return $this->isValid;
    }

    public function logger(): Log {
        return $this->logger;
    }

    public function module(): string {
        return $this->module;
    }

    public function os(): string {
        return (string)$this->ua()['OperatingSystem'];
    }

    public function osVersion(): string {
        return (string)$this->ua()['OperatingSystemVersion'];
    }

    public function remoteAddr(): string {
        return $this->remoteAddr;
    }

    public function useragent(): string {
        return $this->useragent;
    }

    public function viewer(): User {
        return $this->viewer;
    }

    public function hasViewer(): bool {
        return isset($this->viewer);
    }

    public function sessionId(): string {
        return $this->hasSessionId() ? $this->sessionId : '';
    }

    public function hasSessionId(): bool {
        return isset($this->sessionId);
    }

    public function setSessionId(string $sessionId): static {
        $this->sessionId = $sessionId;
        return $this;
    }

    /**
     * Because we <3 our staff
     */
    public function anonymize(): static {
        $this->useragent = 'staff-browser';
        $this->ua = [
            'Browser'                => $this->useragent,
            'BrowserVersion'         => null,
            'OperatingSystem'        => null,
            'OperatingSystemVersion' => null,
        ];
        $this->remoteAddr       = '127.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
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

    public function setViewer(User $viewer): static {
        $this->viewer = $viewer;
        return $this;
    }

    /**
     * In normal circumstances, this method never needs to be called. It
     * is required only when running phpunit tests, to prevent contexts
     * bleeding into one another.
     */
    public function unsetViewer(): static {
        if ($this->hasViewer()) {
            unset($this->viewer);
        }
        return $this;
    }
}

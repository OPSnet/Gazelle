<?php

namespace Gazelle\Report;

class Request extends AbstractReport {
    protected bool $isUpdate = false;

    public function __construct(
        protected readonly int $reportId,
        protected readonly \Gazelle\Request $subject,
    ) {}

    public function template(): string {
        return $this->isUpdate ? 'report/request-update.twig' : 'report/request.twig';
    }

    public function bbLink(): string {
        return "the request [url={$this->subject->url()}]" . display_str($this->subject->title()) . '[/url]';
    }

    public function titlePrefix(): string {
        return 'Request Report: ';
    }

    public function title(): string {
        return $this->subject->title();
    }

    public function isUpdate(bool $isUpdate): static {
        $this->isUpdate = $isUpdate;
        return $this;
    }

    public function needReason(): bool {
        /* Don't need to show the report reason for request updates */
        return !$this->isUpdate;
    }
}

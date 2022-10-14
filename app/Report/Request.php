<?php

namespace Gazelle\Report;

class Request extends AbstractReport {
    protected bool $isUpdate = false;

    public function __construct(
        protected int $reportId,
        protected \Gazelle\Request $subject
    ) { }

    public function template(): string {
        return $this->isUpdate ? 'report/request-update.twig' : 'report/request.twig';
    }

    public function bbLink(): string {
        return "the request [url={$this->subject->url()}]" . display_str($this->subject->title()) . '[/url]';
    }

    public function title(): string {
        return 'Request Report: ' . display_str($this->subject->title());
    }

    public function isUpdate(bool $isUpdate) {
        $this->isUpdate = $isUpdate;
        return $this;
    }

    public function needReason(): bool {
        /* Don't need to show the report reason for request updates */
        return !$this->isUpdate;
    }
}

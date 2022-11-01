<?php

namespace Gazelle\Report;

class Collage extends AbstractReport {
    public function __construct(
        protected readonly int $reportId,
        protected readonly \Gazelle\Collage $subject
    ) {}

    public function template(): string {
        return 'report/collage.twig';
    }

    public function bbLink(): string {
        return "the collage [url={$this->subject->url()}]" . display_str($this->subject->name()) . '[/url]';
    }

    public function title(): string {
        return 'Collage Report: ' . display_str($this->subject->name());
    }
}

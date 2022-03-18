<?php

namespace Gazelle\Report;

class Collage extends AbstractReport {
    public function __construct(
        protected \Gazelle\Collage $subject
    ) { }

    public function template(): string {
        return 'report/collage.twig';
    }
}

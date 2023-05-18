<?php

namespace Gazelle\Task;

class Test extends \Gazelle\Task {
    public function run(): void {
        self::log('test message');
        self::debug('debug message');
    }
}

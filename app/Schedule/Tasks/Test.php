<?php

namespace Gazelle\Schedule\Tasks;

class Test extends \Gazelle\Schedule\Task
{
    public function run()
    {
        self::log('test message');
        self::debug('debug message');
    }
}

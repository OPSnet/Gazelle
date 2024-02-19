<?php

use Phinx\Seed\AbstractSeed;

class InviteSource extends AbstractSeed
{
    public function run()
    {
        $this->table('invite_source')->insert(['name' => 'Personal'])->save();
    }
}

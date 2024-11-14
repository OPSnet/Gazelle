<?php

use Phinx\Seed\AbstractSeed;

class BonusDiscount extends AbstractSeed
{
    public function run(): void {
        $this->table('site_options')->insert([
            'Name'    => 'bonus-discount',
            'Value'   => 0,
            'Comment' => 'Bonus store discount (0 = no discount, 100 = everything free)',
        ])->save();
    }
}

<?php

use Phinx\Seed\AbstractSeed;

class EmailBlacklistSeeder extends AbstractSeed {
    public function run(): void {
        /* the first few domains from https://github.com/ivolo/disposable-email-domains/ */
        foreach (
            [
            "0-180.com", "0-30-24.com", "0-420.com", "0-900.com", "0-aa.com",
            "0-mail.com", "0-z.xyz", "00.pe", "000000pay.com", "000476.com",
            "000521.xyz", "00082aa.com", "00082cc.com", "00082ff.com",
            "00082ii.com", "00082mm.com", "00082rr.com", "00082uu.com",
            "00082zz.com", "000865b.com", "000865e.com", "000865g.com",
            "000865j.com", "0009827.com", "000av.app", "000br88.com",
            "0010.monster", "001913.com", "001xs.net", "001xs.org", "001xs.xyz",
            "002288211.com", "002r.com", "002t.com", "0031casino.com",
            "003271.com", "0039.cf", "0039.ga", "0039.gq", "0039.ml", "003j.com",
            "004k.com", "004r.com", "005005.xyz", "005588211.com", "0058.ru",
            "005f4.xyz", "006j.com", "006o.com", "006z.com", "007.surf",
            "007946.com", "007948.com", "007dotcom.com", "007security.com",
            "008106.com", "008g8662shjel9p.xyz", "0094445.com", "009988211.com",
            "009qs.com", "00b2bcr51qv59xst2.cf", "00b2bcr51qv59xst2.ga",
            "00b2bcr51qv59xst2.gq", "00b2bcr51qv59xst2.ml",
            "00b2bcr51qv59xst2.tk", "00daipai.com", "00g0.com", "00xht.com",
            "0100110tomachine.com", "01011099.com", "0101888dns.com",
            "0104445.com", "01080.ru", "010880.com", "01092019.ru",
            "01106.monster", "0111vns.com", "01122200.com", "01122233.com",
            "01122255.com", "01133322.com", "01133333.com", "01144499.com",
            "01155555.com", "0124445.com", "0134445.com", "01502.monster",
            "0164445.com", "01689306707.mobi", "0174445.com", "0184445.com",
            "0188.info", "0188019.com", "01911.ru", "019352.com", "019625.com",
            ] as $email
        ) {
            $this->table('email_blacklist')->insert([
                'Email'   => $email,
                'Comment' => 'Initial seed',
                'UserID'  => 0,
            ])->save();
        }
    }
}

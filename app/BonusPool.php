<?php

namespace Gazelle;

class BonusPool extends Base {
    private $items;

    const CACHE_SENT = 'bonuspool-sent.%d';

    public function __construct (int $id) {
        parent::__construct();
        $this->id = $id;
    }

    public function contribute($user_id, $value_recv, $value_sent) {
        $this->db->prepared_query(
            'INSERT INTO bonus_pool_contrib (BonusPoolID, UserID, AmountRecv, AmountSent) VALUES (?, ?, ?, ?)',
            $this->id, $user_id, $value_recv, $value_sent
        );
        $this->db->prepared_query(
            'UPDATE bonus_pool SET Total = Total + ? WHERE ID = ?',
            $value_sent, $this->id
        );
        $this->cache->delete_value(sprintf(self::CACHE_SENT, $this->id));
    }

    public function getTotalSent() {
        $key = sprintf(self::CACHE_SENT, $this->id);
        $total = $this->cache->get_value($key);
        if ($total == false) {
            $this->db->prepared_query('SELECT Total FROM bonus_pool WHERE ID = ?', $this->id);
            $total = $this->db->has_results() ? $this->db->next_record()[0] : 0;
            $this->cache->cache_value($key, $total, 6 * 3600);
        }
        return $total;
    }
}

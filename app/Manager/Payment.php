<?php

namespace Gazelle\Manager;

class Payment extends \Gazelle\Base {

    const LIST_KEY = 'payment_list';
    const RENT_KEY = 'payment_monthly_rental';

    public function create(array $val) {
        $this->db->prepared_query('
            INSERT INTO payment_reminders
                   (Text, Expiry, AnnualRent, cc, Active)
            VALUES (?,    ?,      ?,          ?,  ?)
            ', $val['text'], $val['expiry'], $val['rent'], $val['cc'], isset($val['active'])
        );
        $this->flush();
        return $this->db->inserted_id();
    }

    public function modify($id, array $val) {
        $this->db->prepared_query("
            UPDATE payment_reminders SET
                Text = ?, Expiry = ?, AnnualRent = ?, cc = ?, Active = ?
            WHERE ID = ?
            ", $val['text'], $val['expiry'], $val['rent'], $val['cc'], isset($val['active']),
            $id
        );
        $this->flush();
        return $this->db->affected_rows();
    }

    public function remove($id) {
        $this->db->prepared_query('
            DELETE
            FROM payment_reminders
            WHERE ID = ?
            ', $id
        );
        $this->flush();
        return $this->db->affected_rows();
    }

    public function flush() {
        $this->cache->deleteMulti([self::LIST_KEY, self::RENT_KEY, 'due_payments']);
    }

    public function list () {
        if (($list = $this->cache->get_value(self::LIST_KEY)) === false) {
            $this->db->prepared_query("
                SELECT ID, Text, Expiry, AnnualRent, cc, Active
                FROM payment_reminders
                ORDER BY Expiry
            ");
            $list = $this->db->to_array('ID', MYSQLI_ASSOC);
            $this->cache->cache_value(self::LIST_KEY, $list, 86400 * 30);
        }

        // update with latest forex rates
        $XBT = new XBT;
        foreach ($list as &$l) {
            if ($l['cc'] == 'XBT') {
                $l['fiatRate'] = 1.0;
                $l['Rent'] = $l['btcRent'] = sprintf('%0.6f', $l['AnnualRent']);
            } else {
                $l['fiatRate'] = $XBT->fetchRate($l['cc']);
                if (!$l['fiatRate']) {
                    // fallback to last known rate if there is one
                    $l['fiatRate'] = $this->db->scalar('
                        SELECT rate
                        FROM xbt_forex
                        WHERE forex_date = (
                                SELECT max(forex_date)
                                FROM xbt_forex
                                WHERE cc = ?
                            )
                            AND cc = ?
                        ', $l['cc'], $l['cc']
                    );
                    if (!$l['fiatRate']) {
                        throw new \Exception(sprintf('XBT id=%d cc=%s', $l['ID'], $l['cc']));
                    }
                }
                $l['Rent'] = sprintf('%0.2f', $l['AnnualRent']);
                $l['btcRent'] = sprintf('%0.6f', $l['AnnualRent'] / $l['fiatRate']);
            }
        }
        return $list;
    }

    public function monthlyRental() {
        if (($rental = $this->cache->get_value(self::RENT_KEY)) === false) {
            $list = $this->list();
            $rental = 0;
            foreach ($list as $l) {
                if ($l['Active']) {
                    $rental += $l['btcRent'];
                }
            }
            $this->cache->cache_value(self::RENT_KEY, $rental / 12, 86400);
        }
        return $rental;
    }

    public function due() {
        if (($due = $this->cache->get_value('due_payments')) === false) {
            $this->db->prepared_query('
                SELECT Text, Expiry
                FROM payment_reminders
                WHERE Active = 1 AND Expiry < now() + INTERVAL 1 WEEK
                ORDER BY Expiry
            ');
            $due = $this->db->to_array(false, MYSQLI_ASSOC);
            $this->cache->cache_value('due_payments', $due, 3600);
        }
        return $due;
    }
}

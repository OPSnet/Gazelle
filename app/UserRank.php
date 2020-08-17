<?php

namespace Gazelle;

/* The purpose of the UserRank classes is to add a level of abstraction
 * to the calculation of user ranks (most uploaded, most forum posts).
 * The aim is to drive as much as possible from a static configuration
 * table and have the code do its work, to allow other users of Gazelle
 * to add different dimensions to ranks without have to monkey patch
 * the internals.
 *
 * It begins with a RANKING_WEIGHT table in the configuration, which
 * specifies the weight a dimension has towards the overall score, and
 * a class name X that points to \Gazelle\UserRank\X.
 *
 * To explore and test in Boris:
 * Consider that there are two users, one who has up/down votes a
 * single release, and another who has voted on two:
 *
 *  > $config = new Gazelle\UserRank\Configuration(RANKING_WEIGHT);
 *  > $config->instance('votes')->build();
 *  // array(
 *  //   0 => 1,
 *  //   1 => 2
 *  // )
 *  > $config->instance('votes')->rank(0);
 *  // 0
 *  > $config->instance('votes')->rank(1);
 *  // 50
 *  > $config->instance('votes')->rank(2);
 *  // 100
 *  > $config->instance('votes')->rank(3);
 *  // 100
 *
 * The UserRank object adds a wrapper over the top of the config
 * object. It ignores the notion of paranoia, so metrics must be
 * set to 0 when calculating the rank of paranoid people.
 *
 * Adding a new dimension should be as simple as adding an entry to the
 * RANKING_WEIGHT table and writing a new class in app/UserRank/<whatever>.php
 * This then has to hooked up to sections/user/user.php and
 * sections/ajax/user.php
 *
 * Future directions: pass a \Gazelle\User object to the UserRank
 * object, and define the appropriate ethod names in the ranking
 * table so that the dimension classes can obtain the metrics
 * directly and not need to have them passed in.
 */

class UserRank extends Base {

    var $config;
    var $rank;
    var $score;

    const PREFIX = 'percentiles_'; // Prefix for memcache keys, to make life easier

    public function score(): int {
        return $this->score;
    }

    public function rank(string $dimension): int {
        return $this->rank[$dimension];
    }

    public function __construct(\Gazelle\UserRank\Configuration $config, array $dimension) {
        parent::__construct();
        $this->config = $config;
        $definition = $this->config->definition();

        $dimension['uploaded'] -= STARTING_UPLOAD;
        $this->rank = [];
        $ok = true;
        foreach ($definition as $d) {
            $this->rank[$d] = $this->config->instance($d)->rank($dimension[$d]);
            if ($this->rank[$d] === false) {
                $ok = false;
            }
        }
        if (!$ok) {
            $this->score = false;
            return;
        }

        $this->score = 0;
        $totalWeight = 0;
        foreach ($definition as $d) {
            $weight = $this->config->weight($d);
            $this->score += $weight * $this->rank[$d];
            $totalWeight += $weight;
        }
        $this->score /= $totalWeight;

        if ($dimension['downloaded'] == 0) {
            $ratio = 1;
        } elseif ($dimension['uploaded'] == 0) {
            $ratio = 0.5;
        } else {
            $ratio = min(1, round($dimension['uploaded'] / $dimension['downloaded']));
        }
        $this->score *= $ratio;
    }
}

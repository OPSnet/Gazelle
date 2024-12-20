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
    protected array $rank;
    protected float $score = 0.0;

    final public const PREFIX = 'percentiles_'; // Prefix for memcache keys, to make life easier

    public function __construct(protected \Gazelle\UserRank\Configuration $config, protected array $dimension) {
        $definition = $this->config->definition();
        foreach ($definition as $d) {
            $this->rank[$d] = $this->config->instance($d)->rank(
                $d === 'uploaded'
                    ? $dimension[$d] - STARTING_UPLOAD
                    : $dimension[$d]
            );
        }

        $totalWeight = 0.0;
        foreach ($definition as $d) {
            $weight = $this->config->weight($d);
            $this->score += $weight * $this->rank[$d];
            $totalWeight += $weight;
        }
        $this->score /= $totalWeight;

        if ($this->dimension['downloaded'] == 0) {
            $ratio = 1;
        } elseif ($this->dimension['uploaded'] <= STARTING_UPLOAD) {
            $ratio = 0.5;
        } else {
            $ratio = min(1, round($this->dimension['uploaded'] / $this->dimension['downloaded']));
        }
        $this->score *= $ratio;
    }

    public function raw(string $dimension): mixed {
        return $this->dimension[$dimension];
    }

    public function rank(string $dimension): int {
        return $this->rank[$dimension];
    }

    public function score(): ?int {
        return (int)round($this->score, 0);
    }
}

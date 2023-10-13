<?php

namespace Gazelle;

abstract class ArtistRole extends \Gazelle\Base {
    protected const RENDER_TEXT = 1;
    protected const RENDER_HTML = 2;

    protected array $artistList;
    protected array $roleList;
    protected array $idList;

    abstract protected function artistListQuery(): \mysqli_result|bool;
    abstract public function idList(): array;
    abstract public function roleList(): array;

    public function __construct(
        protected readonly int $id,
        protected readonly \Gazelle\Manager\Artist $manager,
    ) {}

    protected function artistList(): array {
        if (!isset($this->artistList)) {
            if ($this->artistListQuery()) {
                $this->artistList = self::$db->to_array(false, MYSQLI_ASSOC, false);
            } else {
                $this->artistList = [];
            }
        }
        return $this->artistList;
    }

    /**
     * Generate the artist name. (Individual artists will be clickable, or VA)
     */
    public function link(): string {
        return $this->renderRole(self::RENDER_HTML);
    }

    /**
     * Generate the artist name as text.
     */
    public function text(): string {
        return $this->renderRole(self::RENDER_TEXT);
    }

    /**
     * A readable representation of the artists grouped by their roles in a
     * release group. All artist roles are present as arrays (no need to see if
     * the key exists). Like roleList() but some of the key names change.
     *   'main'     becomes 'artists'
     *   'guest'    becomes 'with'
     *   'remixer'  becomes 'remixedBy'
     *   'composer' becomes 'composers'
     * A role is an array of two keys: ["id" => 801, "name" => "The Group"]
     */
    public function roleListByType(): array {
        $list = $this->idList();
        return [
            'artists'   => $list[ARTIST_MAIN] ?? [],
            'with'      => $list[ARTIST_GUEST] ?? [],
            'remixedBy' => $list[ARTIST_REMIXER] ?? [],
            'composers' => $list[ARTIST_COMPOSER] ?? [],
            'conductor' => $list[ARTIST_CONDUCTOR] ?? [],
            'dj'        => $list[ARTIST_DJ] ?? [],
            'producer'  => $list[ARTIST_PRODUCER] ?? [],
            'arranger'  => $list[ARTIST_ARRANGER] ?? [],
        ];
    }

    protected function renderRole(int $mode): string {
        $roleList       = $this->roleList();
        $arrangerCount  = count($roleList['arranger'] ?? []);
        $composerCount  = count($roleList['composer'] ?? []);
        $conductorCount = count($roleList['conductor'] ?? []);
        $djCount        = count($roleList['dj'] ?? []);
        $mainCount      = count($roleList['main'] ?? []);

        if ($composerCount + $mainCount + $conductorCount + $djCount == 0) {
            return $link = '';
        }

        $and = match ($mode) {
            self::RENDER_HTML => ' &amp; ',
            default           => ' and ',
        };

        $chunk = [];
        if ($djCount > 0) {
            $chunk[] = match ($djCount) {
                1 => $this->artistLink($mode, $roleList['dj'][0]),
                2 => $this->artistLink($mode, $roleList['dj'][0]) . $and . $this->artistLink($mode, $roleList['dj'][1]),
                default => $this->various('DJs', $roleList['dj'], $mode),
            };
        } else {
            if ($composerCount > 0) {
                $chunk[] = match ($composerCount) {
                    1 => $this->artistLink($mode, $roleList['composer'][0]),
                    2 => $this->artistLink($mode, $roleList['composer'][0]) . $and . $this->artistLink($mode, $roleList['composer'][1]),
                    default => $this->various('Composers', $roleList['composer'], $mode),
                };
                if ($arrangerCount > 0) {
                    $chunk[] = 'arranged by';
                    $chunk[] = match ($arrangerCount) {
                        1 => $this->artistLink($mode, $roleList['arranger'][0]),
                        2 => $this->artistLink($mode, $roleList['arranger'][0]) . $and . $this->artistLink($mode, $roleList['arranger'][1]),
                        default => $this->various('Arrangers', $roleList['arranger'], $mode),
                    };
                }
                if ($mainCount + $conductorCount > 0) {
                    $chunk[] = 'performed by';
                }
            }

            if ($composerCount > 0
                && $mainCount > 1
                && $conductorCount > 1
            ) {
                $chunk[] = 'Various Artists';
            } else {
                if ($mainCount > 0) {
                    $chunk[] = match ($mainCount) {
                        1 => $this->artistLink($mode, $roleList['main'][0]),
                        2 => $this->artistLink($mode, $roleList['main'][0]) . $and . $this->artistLink($mode, $roleList['main'][1]),
                        default => $this->various('Artists', $roleList['main'], $mode),
                    };
                }

                if ($conductorCount > 0) {
                    if ($mainCount + $composerCount > 0 && ($composerCount < 3 || $mainCount > 0)) {
                        $chunk[] = 'under';
                    }
                    $chunk[] = match ($conductorCount) {
                        1 => $this->artistLink($mode, $roleList['conductor'][0]),
                        2 => $this->artistLink($mode, $roleList['conductor'][0]) . $and . $this->artistLink($mode, $roleList['conductor'][1]),
                        default => $this->various('Conductors', $roleList['conductor'], $mode),
                    };
                }
            }
        }
        return $link = implode(' ', $chunk);
    }

    protected function various(string $role, array $artistList, int $mode): string {
        return match ($mode) {
            self::RENDER_HTML => '<span class="tooltip" style="float: none"  title="' . implode(' ⁝ ', array_map(fn ($a) => $a['name'], $artistList)) . "\">Various $role</span>",
            default           => "Various $role",
        };
    }

    /**
     * Generate an HTML anchor for an artist
     */
    protected function artistLink(int $mode, array $info): string {
        return match ($mode) {
            self::RENDER_HTML => '<a href="artist.php?id=' . $info['id'] . '" dir="ltr">' . html_escape($info['name']) . '</a>',
            default           => $info['name'],
        };
    }
}

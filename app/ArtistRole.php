<?php

namespace Gazelle;

abstract class ArtistRole extends \Gazelle\Base {
    protected const RENDER_TEXT = 1;
    protected const RENDER_HTML = 2;

    protected array $artistList;
    protected array $roleList;
    protected array $idList;

    abstract protected function artistListQuery(): \mysqli_result;
    abstract public function roleList(): array;

    public function __construct(
        protected readonly int $id,
        protected readonly \Gazelle\Manager\Artist $manager,
    ) {}

    protected function artistList(): array {
        if (!isset($this->artistList)) {
            $this->artistListQuery();
            $this->artistList = self::$db->to_array(false, MYSQLI_ASSOC, false);
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

        $and = match($mode) {
            self::RENDER_HTML => ' &amp; ',
            default           => ' and ',
        };

        $chunk = [];
        if ($djCount > 0) {
            $chunk[] = match($djCount) {
                1 => $this->artistLink($mode, $roleList['dj'][0]),
                2 => $this->artistLink($mode, $roleList['dj'][0]) . $and . $this->artistLink($mode, $roleList['dj'][1]),
                default => 'Various DJs',
            };
        } else {
            if ($composerCount > 0) {
                $chunk[] = match($composerCount) {
                    1 => $this->artistLink($mode, $roleList['composer'][0]),
                    2 => $this->artistLink($mode, $roleList['composer'][0]) . $and . $this->artistLink($mode, $roleList['composer'][1]),
                    default => 'Various Composers',
                };
                if ($arrangerCount > 0) {
                    $chunk[] = 'arranged by';
                    $chunk[] = match($arrangerCount) {
                        1 => $this->artistLink($mode, $roleList['arranger'][0]),
                        2 => $this->artistLink($mode, $roleList['arranger'][0]) . $and . $this->artistLink($mode, $roleList['arranger'][1]),
                        default => 'Various Arrangers',
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
                    $chunk[] = match($mainCount) {
                        1 => $this->artistLink($mode, $roleList['main'][0]),
                        2 => $this->artistLink($mode, $roleList['main'][0]) . $and . $this->artistLink($mode, $roleList['main'][1]),
                        default => 'Various Artists',
                    };
                }

                if ($conductorCount > 0) {
                    if ($mainCount + $composerCount > 0 && ($composerCount < 3 || $mainCount > 0)) {
                        $chunk[] = 'under';
                    }
                    $chunk[] = match($conductorCount) {
                        1 => $this->artistLink($mode, $roleList['conductor'][0]),
                        2 => $this->artistLink($mode, $roleList['conductor'][0]) . $and . $this->artistLink($mode, $roleList['conductor'][1]),
                        default => 'Various Conductors',
                    };
                }
            }
        }
        return $link = implode(' ', $chunk);
    }

    /**
     * Generate an HTML anchor for an artist
     */
    protected function artistLink(int $mode, array $info): string {
        return match ($mode) {
            self::RENDER_HTML => '<a href="artist.php?id=' . $info['id'] . '" dir="ltr">' . display_str($info['name']) . '</a>',
            default           => display_str($info['name']),
        };
    }
}

<?php

namespace Gazelle;

abstract class ArtistRole extends \Gazelle\Base {
    protected const RENDER_TEXT = 1;
    protected const RENDER_HTML = 2;

    abstract function roleList(): array;

    public function __construct(
        protected readonly int $id,
        protected readonly \Gazelle\Manager\Artist $manager,
    ) {}

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
        $arrangerCount  = count($roleList['Arranger'] ?? []);
        $composerCount  = count($roleList['Composer'] ?? []);
        $conductorCount = count($roleList['Conductor'] ?? []);
        $djCount        = count($roleList['DJ'] ?? []);
        $mainCount      = count($roleList['Main'] ?? []);

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
                1 => $this->artistLink($mode, $roleList['DJ'][0]),
                2 => $this->artistLink($mode, $roleList['DJ'][0]) . $and . $this->artistLink($mode, $roleList['DJ'][1]),
                default => 'Various DJs',
            };
        } else {
            if ($composerCount > 0) {
                $chunk[] = match($composerCount) {
                    1 => $this->artistLink($mode, $roleList['Composer'][0]),
                    2 => $this->artistLink($mode, $roleList['Composer'][0]) . $and . $this->artistLink($mode, $roleList['Composer'][1]),
                    default => 'Various Composers',
                };
                if ($arrangerCount > 0) {
                    $chunk[] = 'arranged by';
                    $chunk[] = match($arrangerCount) {
                        1 => $this->artistLink($mode, $roleList['Arranger'][0]),
                        2 => $this->artistLink($mode, $roleList['Arranger'][0]) . $and . $this->artistLink($mode, $roleList['Arranger'][1]),
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
                        1 => $this->artistLink($mode, $roleList['Main'][0]),
                        2 => $this->artistLink($mode, $roleList['Main'][0]) . $and . $this->artistLink($mode, $roleList['Main'][1]),
                        default => 'Various Artists',
                    };
                }

                if ($conductorCount > 0) {
                    if ($mainCount + $composerCount > 0 && ($composerCount < 3 || $mainCount > 0)) {
                        $chunk[] = 'under';
                    }
                    $chunk[] = match($conductorCount) {
                        1 => $this->artistLink($mode, $roleList['Conductor'][0]),
                        2 => $this->artistLink($mode, $roleList['Conductor'][0]) . $and . $this->artistLink($mode, $roleList['Conductor'][1]),
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

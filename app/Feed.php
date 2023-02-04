<?php

namespace Gazelle;

class Feed extends Base {
    function header(): string {
        header('Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0');
        header('Pragma:');
        header('Expires: ' . date('D, d M Y H:i:s', time() + (2 * 60 * 60)) . ' GMT');
        header('Last-Modified: ' . date('D, d M Y H:i:s') . ' GMT');
        header("Content-type: application/xml; charset=UTF-8");

        return self::$twig->render('feed/header.twig');
    }

    function footer(): string {
        return self::$twig->render('feed/footer.twig');
    }

    function channel(string $title, string $description): string {
        return self::$twig->render('feed/channel.twig', [
            'date'        => date('r'),
            'description' => $description,
            'title'       => $title,
        ]);
    }

    function item(string $title, string $description, string $page, string $creator, string $date, string $comments = '', string $feedName = ''): string {
        return self::$twig->render('feed/item.twig', [
            'comments'    => $comments,
            'creator'     => $creator,
            'feedName'    => $feedName,
            'date'        => date('r', strtotime($date)),
            'description' => $description,
            'page'        => SITE_URL . "/$page",
            'title'       => $title,
        ]);
    }

    function retrieve(User $user, string $key): string {
        $list = self::$cache->get_value($key);
        if ($list === false) {
            $list = [];
        }
        $announceKey = $user->announceKey();
        return implode('', array_map(fn ($item) => str_replace('[[PASSKEY]]', $announceKey, $item), $list));
    }

    function populate(string $key, string $item): int {
        $list = self::$cache->get_value($key);
        if ($list === false) {
            $list = [];
        }
        array_unshift($list, $item);
        $list = array_slice($list, 0, 50);
        self::$cache->cache_value($key, $list, 0);
        return count($list);
    }

    ### EMITTER METHODS ###

    public function blocked(): string {
        return $this->wrap($this->channel('Blocked', 'RSS feed.'));
    }

    public function blog(
        Manager\Blog $blogMan,
        Manager\ForumThread $threadMan,
    ): string {
        return $this->wrap(
            $this->channel(
                'Blog',
                'RSS feed for site blog.'
            )
            . implode('',
                array_map(
                    fn ($b) => $this->item(
                        $b->title(),
                        \Text::strip_bbcode($b->body()),
                        $threadMan->findById((int)$b->threadId())?->url() ?? $b->url(),
                        SITE_NAME . ' Staff',
                        $b->created(),
                    ),
                    $blogMan->headlines()
                )
            )
        );
    }

    public function bookmark(User $user, string $feedName): string {
        return $this->wrap(
            $this->channel(
                'Bookmarked torrent notifications',
                'RSS feed for bookmarked torrents.'
            )
            . $this->retrieve($user, $feedName)
        );
    }

    public function byFeedName(User $user, string $feedName): string {
        return $this->wrap(
            $this->channel(
                match($feedName) {
                    'torrents_all'        => 'Everything',
                    'torrents_apps'       => 'Applications',
                    'torrents_abooks'     => 'Audiobooks',
                    'torrents_comedy'     => 'Comedy',
                    'torrents_comics'     => 'Comics',
                    'torrents_ebooks'     => 'E-Books',
                    'torrents_evids'      => 'E-Learning Videos',
                    'torrents_flac'       => 'FLACs',
                    'torrents_lossless'   => 'Lossless',
                    'torrents_music'      => 'Music',
                    'torrents_mp3'        => 'MP3s',
                    'torrents_vinyl'      => 'Vinyl',
                    'torrents_lossless24' => '24bit Lossless',
                },
                match($feedName) {
                    'torrents_all'        => 'RSS feed for new uploads',
                    'torrents_apps'       => 'RSS feed for new application uploads',
                    'torrents_comedy'     => 'RSS feed for new comedy uploads',
                    'torrents_comics'     => 'RSS feed for new comics uploads',
                    'torrents_abooks'     => 'RSS feed for new audiobook torrent uploads',
                    'torrents_ebooks'     => 'RSS feed for new e-books uploads',
                    'torrents_evids'      => 'RSS feed for new e-learning videos uploads',
                    'torrents_flac'       => 'RSS feed for new FLAC uploads',
                    'torrents_lossless'   => 'RSS feed for new lossless uploads',
                    'torrents_mp3'        => 'RSS feed for new MP3s uploads',
                    'torrents_music'      => 'RSS feed for new music uploads',
                    'torrents_vinyl'      => 'RSS feed for new vinyl-sourced uploads',
                    'torrents_lossless24' => 'RSS feed for new 24bit lossless uploads',
                }
            ) . $this->retrieve($user, $feedName)
        );
    }

    public function changelog(Manager\Changelog $manager): string {
        return $this->wrap(
            $this->channel(
                SITE_NAME . ' Changelog',
                'RSS feed for ' . SITE_NAME . '\'s changelog.'
            )
            . implode('',
                array_map(
                    fn ($c) => $this->item(
                        "{$c['created']} by {$c['author']}",
                        $c['message'],
                        'tools.php?action=change_log',
                        SITE_NAME . ' Staff',
                        $c['created'],
                    ),
                    $manager->headlines()
                )
            )
        );
    }

    public function news(Manager\News $manager): string {
        return $this->wrap(
            $this->channel(
                SITE_NAME . ' News',
                'RSS feed for site news.'
            )
            . implode('',
                array_map(
                    fn ($n) => $this->item(
                        $n['title'],
                        \Text::strip_bbcode($n['body']),
                        "index.php#news{$n['id']}",
                        SITE_NAME . ' Staff',
                        $n['created'],
                    ),
                    $manager->list(5, 0)
                )
            )
        );
    }

    public function personal(User $user, string $feedName, ?string $filterName): string {
        return $this->wrap(
            $this->channel(
                'Personal notifications',
                'RSS feed for your ' . (is_null($filterName) ? 'notifications' : ('"' . display_str($filterName) . '" filter')),
            )
            . $this->retrieve($user, $feedName)
        );
    }

    public function wrap(string $body): string {
        return $this->header() . $body . $this->footer();
    }
}

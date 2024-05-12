<?php

namespace Gazelle;

use Gazelle\Util\Textarea;

class Upload extends \Gazelle\Base {
    final public const TORRENT_INPUT_ACCEPT = ['application/x-bittorrent', '.torrent'];
    final public const JSON_INPUT_ACCEPT = ['application/json', '.json'];

    protected bool $isUploaded;

    public function __construct(
        protected \Gazelle\User $user,
        protected array|false $Torrent = false,
        protected string|false $Error = false,
    ) {
        $this->isUploaded = is_array($this->Torrent) && isset($this->Torrent['GroupID']);
    }

    /**
     * This is an awful hack until something better can be figured out.
     * We want to get rid eval()'ing Javascript code, and this produces
     * something that can be added to the DOM and the engine will run it.
     */
    public function albumReleaseJS(): string {
        $x = $this->albumDescription();
        $x = $this->releaseDescription();
        unset($x);
        return Textarea::factory();
    }

    public function descriptionJS(): string {
        $x = $this->textarea('desc', '');
        unset($x);
        return Textarea::factory();
    }

    public function head(int $categoryId): string {
        return self::$twig->render('upload/header.twig', [
            'category_id' => $categoryId,
            'error'       => $this->Error,
            'is_uploaded' => $this->isUploaded,
            'is_upload'   => !$this->isUploaded || isset($this->Torrent['add-format']),
            'info'        => $this->Torrent,
            'user'        => $this->user,
        ]);
    }

    public function textarea(string $name, string $default): Textarea {
        $textarea = new Textarea($name, $default, 60, 5);
        if ($this->isUploaded) {
            $textarea->setDisabled();
        }
        return $textarea;
    }

    public function albumDescription(): Textarea {
        return $this->textarea('album_desc', '');
    }

    public function releaseDescription(): Textarea {
        return new Textarea(
            'release_desc', $this->Torrent['TorrentDescription'] ?? '', 60, 5
        );
    }

    public function foot(bool $showFooter): string {
        $torMan = new \Gazelle\Manager\Torrent();
        return self::$twig->render('upload/footer.twig', [
            'is_upload'    => !$this->isUploaded || isset($this->Torrent['add-format']),
            'info'         => $this->Torrent,
            'leech_type'   => $torMan->leechTypeList(),
            'leech_reason' => $torMan->leechReasonList(),
            'show_footer'  => $showFooter,
            'viewer'       => $this->user,
        ]);
    }

    public function application(): string {
        return self::$twig->render('upload/application.twig', [
            'description' => $this->textarea('desc', ''),
            'is_uploaded' => $this->isUploaded,
            'torrent'     => $this->Torrent,
            'user'        => $this->user,
        ]);
    }

    public function audiobook(): string {
        return self::$twig->render('upload/audiobook.twig', [
            'description_album'   => $this->albumDescription(),
            'description_release' => $this->releaseDescription(),
            'is_uploaded'         => $this->isUploaded,
            'torrent'             => $this->Torrent,
            'user'                => $this->user,
        ]);
    }

    public function comedy(): string {
        return self::$twig->render('upload/comedy.twig', [
            'description_album'   => $this->albumDescription(),
            'description_release' => $this->releaseDescription(),
            'is_uploaded'         => $this->isUploaded,
            'torrent'             => $this->Torrent,
            'user'                => $this->user,
        ]);
    }

    public function comic(): string {
        return self::$twig->render('upload/comic.twig', [
            'description' => $this->textarea('desc', ''),
            'is_uploaded' => $this->isUploaded,
            'torrent'     => $this->Torrent,
            'user'        => $this->user,
        ]);
    }

    public function ebook(): string {
        return self::$twig->render('upload/ebook.twig', [
            'description' => $this->textarea('desc', ''),
            'is_uploaded' => $this->isUploaded,
            'torrent'     => $this->Torrent,
            'user'        => $this->user,
        ]);
    }

    public function elearning(): string {
        return self::$twig->render('upload/elearning.twig', [
            'description' => $this->textarea('desc', ''),
            'is_uploaded' => $this->isUploaded,
            'torrent'     => $this->Torrent,
            'user'        => $this->user,
        ]);
    }

    public function music(array $GenreTags, \Gazelle\Manager\TGroup $manager): string {
        return self::$twig->render('upload/music.twig', [
            'add_format'          => $this->isUploaded && is_array($this->Torrent) && isset($this->Torrent['add-format']),
            'description_album'   => $this->albumDescription(),
            'description_release' => $this->releaseDescription(),
            'is_uploaded'         => $this->isUploaded,
            'logchecker_accept'   => \OrpheusNET\Logchecker\Logchecker::getAcceptValues(),
            'release_type'        => (new \Gazelle\ReleaseType())->list(),
            'tag_list'            => $GenreTags,
            'tgroup'              => $this->isUploaded && is_array($this->Torrent) ? $manager->findById($this->Torrent['GroupID']) : null,
            'torrent'             => $this->Torrent,
            'user'                => $this->user,
        ]);
    }
}

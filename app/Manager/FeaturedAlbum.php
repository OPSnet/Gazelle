<?php

namespace Gazelle\Manager;

use Gazelle\Enum\FeaturedAlbumType;
use Gazelle\Enum\LeechType;
use Gazelle\Enum\LeechReason;

class FeaturedAlbum extends \Gazelle\BaseManager {
    /**
     * Feature an album, for Album of the Month or Showcase
     * If $threshold is positive, any torrent larger than that
     * will automatically be set as neutral leech.
     */
    public function create(
        FeaturedAlbumType        $featureType,
        \Gazelle\Manager\News    $news,
        \Gazelle\Manager\TGroup  $tgMan,
        \Gazelle\Manager\Torrent $torMan,
        \Gazelle\Tracker         $tracker,
        \Gazelle\TGroup          $tgroup,
        \Gazelle\ForumThread     $forumThread,
        \Gazelle\User            $user,
        LeechType                $leechType,
        string                   $title,
        int                      $threshold = 0,
    ): \Gazelle\FeaturedAlbum {
        self::$db->begin_transaction();

        // remove previous featured album(s)
        self::$db->prepared_query("
            SELECT GroupID
            FROM featured_albums
            WHERE Ended IS NULL
                AND type = ?
            ", $featureType->value
        );
        foreach (self::$db->collect(0, false) as $tgroupId) {
            $tgMan->findById($tgroupId)?->setFreeleech(
                torMan:    $torMan,
                tracker:   $tracker,
                user:      $user,
                leechType: LeechType::Normal,
                reason:    LeechReason::Normal,
            );
        }
        self::$db->prepared_query("
            UPDATE featured_albums SET
                Ended = now()
            WHERE Ended IS NULL
                AND Type = ?
            ", $featureType->value
        );

        // create new featured album
        self::$db->prepared_query("
            INSERT INTO featured_albums
                   (GroupID, ThreadID, Type)
            VALUES (?,       ?,        ?)
            ON DUPLICATE KEY UPDATE
                Started = now(),
                Ended = NULL
            ", $tgroup->id(), $forumThread->id(), $featureType->value
        );
        $tgroup->setFreeleech(
            tracker:   $tracker,
            torMan:    $torMan,
            user:      $user,
            leechType: $leechType,
            reason:    $featureType->leechReason(),
            threshold: $threshold,
        );

        // FIXME: There should be a $thread->body() shortcut to get the body of the first post in a thread
        $news->create(
            userId: $user->id(),
            title:  trim($title),
            body:   $forumThread->slice(1, 1)[0]['Body'] . "\r\n\r\n[url={$forumThread->url()}]Come join the discussion[/url]",
        );

        self::$db->commit();
        return (new \Gazelle\FeaturedAlbum($featureType, $tgroup->id()))->flush();
    }

    public function findById(int $tgroupId): ?\Gazelle\FeaturedAlbum {
        $type = self::$db->scalar("
            SELECT Type
            FROM featured_albums
            WHERE GroupID = ?
            ", $tgroupId
        );
        if (is_null($type)) {
            return null;
        }
        return new \Gazelle\FeaturedAlbum(
            match((int)$type) {
                1       => FeaturedAlbumType::Showcase,
                default => FeaturedAlbumType::AlbumOfTheMonth,
            },
            $tgroupId
        );
    }

    public function findByType(FeaturedAlbumType $type): ?\Gazelle\FeaturedAlbum {
        $id = (int)self::$db->scalar("
            SELECT GroupID
            FROM featured_albums
            WHERE Ended IS NULL
                AND Type = ?
            ", $type->value
        );
        return $id ? new \Gazelle\FeaturedAlbum($type, $id) : null;
    }

    public function lookupFeaturedAlbumType(int $featuredAlbumType): FeaturedAlbumType {
        return match ($featuredAlbumType) {
            1       => FeaturedAlbumType::Showcase,
            default => FeaturedAlbumType::AlbumOfTheMonth,
        };
    }
}

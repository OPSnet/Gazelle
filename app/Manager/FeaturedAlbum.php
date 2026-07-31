<?php

declare(strict_types=1);

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
        FeaturedAlbumType $featureType,
        News              $news,
        TGroup            $tgMan,
        Torrent           $torMan,
        ForumThread       $threadMan,
        \Gazelle\Forum    $forum,
        \Gazelle\Tracker  $tracker,
        \Gazelle\TGroup   $tgroup,
        \Gazelle\User     $user,
        LeechType         $leechType,
        string            $title,
        string            $body,
        string            $pitch,
        int               $threshold = 0,
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
        foreach (self::$db->collect(0) as $tgroupId) {
            $tgMan->findById($tgroupId)?->setFreeleech(
                leechType: LeechType::Normal,
                reason:    LeechReason::Normal,
                user:      $user,
                torMan:    $torMan,
                tracker:   $tracker,
            );
        }
        self::$db->prepared_query("
            UPDATE featured_albums SET
                Ended = now()
            WHERE Ended IS NULL
                AND Type = ?
            ", $featureType->value
        );

        $news->create(
            user:      $user,
            title:     trim($title),
            body:      trim($body),
            pitch:     trim($pitch),
            forum:     $forum,
            threadMan: $threadMan,
        );

        // create new featured album
        self::$db->prepared_query("
            INSERT INTO featured_albums
                   (GroupID, ThreadID, Type)
            VALUES (?,       ?,        ?)
            ON DUPLICATE KEY UPDATE
                Started = now(),
                Ended = NULL
            ", $tgroup->id, $forum->lastThreadId(), $featureType->value
        );
        $tgroup->setFreeleech(
            leechType: $leechType,
            reason:    $featureType->leechReason(),
            threshold: $threshold,
            user:      $user,
        );

        self::$db->commit();
        return new \Gazelle\FeaturedAlbum($featureType, $tgroup->id)->flush();
    }

    public function findById(int $id): \Gazelle\FeaturedAlbum|null {
        $type = self::$db->scalar("
            SELECT Type
            FROM featured_albums
            WHERE GroupID = ?
            ", $id
        );
        if (is_null($type)) {
            return null;
        }
        return new \Gazelle\FeaturedAlbum(
            match ((int)$type) {
                1       => FeaturedAlbumType::Showcase,
                default => FeaturedAlbumType::AlbumOfTheMonth,
            },
            $id
        );
    }

    public function findByType(FeaturedAlbumType $type): \Gazelle\FeaturedAlbum|null {
        $tgroupId = self::$db->scalar("
            SELECT GroupID
            FROM featured_albums
            WHERE Ended IS NULL
                AND Type = ?
            ", $type->value
        );
        return is_null($tgroupId) ? null : new \Gazelle\FeaturedAlbum($type, (int)$tgroupId);
    }
}

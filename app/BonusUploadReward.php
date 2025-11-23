<?php

declare(strict_types=1);

namespace Gazelle;

class BonusUploadReward extends Base {
    public function boost(User $user): int {
        if (
            !BONUS_UPLOAD_BOOST_ACTIVE
            ||
            $user->classLevel() > BONUS_UPLOAD_BOOST_MAX_LEVEL
        ) {
            return 0;
        }
        $index = $user->ordinal()->value('bonus-upload-boost');
        if (!isset(BONUS_UPLOAD_BOOST[$index])) {
            return 0;
        }
        $boost = BONUS_UPLOAD_BOOST[$index];
        $user->ordinal()->set('bonus-upload-boost', $index + 1);
        return $boost;
    }

    public function reward(Torrent $torrent): int {
        $categoryId = $torrent->group()->categoryId();
        if ($torrent->isPerfectFlac()) {
            $column = 'high';
        } elseif ($categoryId !== CATEGORY_MUSIC || $torrent->format() === 'FLAC') {
            $column = 'standard';
        } else {
            // Music MP3
            $column = 'low';
        }
        return (int)$this->pg()->scalar("
            select $column
            from bonus_upload_reward
            where id_category = ?
                and valid @> ?::timestamptz
            ", $categoryId, $torrent->created()
        );
    }

    public function modifyCategory(int $categoryId, array $reward): int {
        $this->pg()->begin();
        $this->pg()->prepared_query("
            update bonus_upload_reward set
                valid = tstzrange(lower(valid), now())
            where now() <@ valid
                and id_category = ?
            ", $categoryId
        );
        $id = $this->pg()->insert("
            insert into bonus_upload_reward
                    (id_category, high, standard, low, valid)
             values (?,           ?,    ?,        ?,   tstzrange(now(), 'infinity'))
            ", $categoryId, $reward['high'], $reward['standard'], $reward['low']
        );
        $this->pg()->commit();
        return $id;
    }
}

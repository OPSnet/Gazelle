<?php

declare(strict_types=1);

namespace Gazelle\API;

use Gazelle\Enum\CollageType;

class Collage extends AbstractAPI {
    public function run(): array {
        if (!isset($_GET['collage_id'])) {
            json_error('Missing collage id');
        }

        $collage = self::$db->rowAssoc("
            SELECT ID,
                Name,
                CategoryID
            FROM collages
            WHERE ID = ?
            ", $_GET['collage_id']
        );
        if (is_null($collage)) {
            json_error('Collage not found');
        }
        $collage['Category'] = CollageType::from($collage['CategoryID'])->label();

        return $collage;
    }
}

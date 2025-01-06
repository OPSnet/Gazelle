<?php

namespace Gazelle\API;

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
        $collage['Category'] = $this->config['CollageCats'][$collage['CategoryID']];

        return $collage;
    }
}

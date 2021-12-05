<?php

namespace Gazelle\API;

class Collage extends AbstractAPI {
    public function run() {
        if (!isset($_GET['collage_id'])) {
            json_error('Missing collage id');
        }

        self::$db->prepared_query("
            SELECT
                ID,
                Name,
                CategoryID
            FROM
                collages
            WHERE
                ID = ?", $_GET['collage_id']);
        if (!self::$db->has_results()) {
            json_error('Collage not found');
        }
        $collage = self::$db->next_record(MYSQLI_ASSOC, false);
        $collage['Category'] = $this->config['CollageCats'][$collage['CategoryID']];

        return $collage;
    }
}

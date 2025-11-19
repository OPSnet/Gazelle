<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * rename and sanitize CollageCovers -> CollageEntries, delete abandoned options
 */
final class CleanupUserOptions extends AbstractMigration {
    protected const OLD_ATTRS = [
        'CollageCovers',
        'HttpsTracker',
        'Tooltipster',
        'ShowTags',
        'AutoloadCommStats',
        'DisablePMAvatars',
        'DiscogView',
    ];

    public function up(): void {
        $where = array_map(fn($attr) => sprintf('SiteOptions like \'%%"%s"%%\'', $attr), self::OLD_ATTRS);
        foreach (
            $this->query("
                SELECT UserID, SiteOptions FROM users_info
                WHERE " . implode(' OR ', $where)
            ) as $row
        ) {
            $options = unserialize($row['SiteOptions']);
            if (isset($options['CollageCovers'])) {
                $oldOption = $options['CollageCovers'];
                $options['CollageEntries'] = max(10, min($oldOption, 100));
            }
            foreach (self::OLD_ATTRS as $attr) {
                unset($options[$attr]);
            }
            $this->execute("
                UPDATE users_info
                SET SiteOptions = ?
                WHERE UserID = ?
            ", [serialize($options), $row['UserID']]
            );
        }
    }

    public function down(): void {
        foreach (
            $this->query("
                SELECT UserID, SiteOptions FROM users_info
                WHERE SiteOptions like '%\"CollageEntries\"%'
            ") as $row
        ) {
            $options = unserialize($row['SiteOptions']);
            $oldOption = $options['CollageEntries'];
            $options['CollageCovers'] = $oldOption;
            unset($options['CollageEntries']);
            $this->execute("
                UPDATE users_info
                SET SiteOptions = ?
                WHERE UserID = ?
            ", [serialize($options), $row['UserID']]
            );
        }
    }
}

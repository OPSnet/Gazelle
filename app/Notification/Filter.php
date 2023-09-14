<?php

namespace Gazelle\Notification;

class Filter extends \Gazelle\Base {
    protected int $id;
    protected array $field = [];

    protected array $fieldMap = [
        'artist'          => 'Artists',
        'category'        => 'Categories',
        'encoding'        => 'Encodings',
        'exclude_va'      => 'ExcludeVA',
        'format'          => 'Formats',
        'from_year'       => 'FromYear',
        'to_year'         => 'ToYear',
        'media'           => 'Media',
        'new_groups_only' => 'NewGroupsOnly',
        'not_tag'         => 'NotTags',
        'release_type'    => 'ReleaseTypes',
        'record_label'    => 'RecordLabels',
        'tag'             => 'Tags',
        'user'            => 'Users',
    ];

    public function create(int $userId): int {
        $set = ['UserID', 'Label'];
        $args = [$userId, $this->field['label']];
        foreach ($this->fieldMap as $field => $column) {
            if (isset($this->field[$field])) {
                $set[] = $column;
                $args[] = $this->arg($field);
            }
        }
        self::$db->prepared_query("
            INSERT INTO users_notify_filters
                   (" . implode(', ', $set) . ")
            VALUES (" . placeholders($set) . ")
            ", ...$args
        );
        return self::$db->inserted_id();
    }

    public function isConfigured(): bool {
        return !empty($this->field);
    }

    public function hasLabel(): bool {
        return isset($this->field['label']);
    }

    protected function multiLineSplit(string $data): array {
        return array_unique(array_map('trim', preg_split('/\r\n?|\n/', trim($data)))); /** @phpstan-ignore-line */
    }

    public function setMultiLine(string $field, $data): static {
        $this->field[$field] = $this->multiLineSplit($data);
        return $this;
    }

    public function setMultiValue(string $field, $data): static {
        $this->field[$field] = array_unique(array_map('trim', $data));
        return $this;
    }

    public function setBoolean(string $field, bool $flag): static {
        $this->field[$field] = $flag ? '1' : '0';
        return $this;
    }

    public function setLabel($label): static {
        if (isset($label)) {
            $this->field['label'] = trim($label);
        }
        return $this;
    }

    public function setYears(int $from, int $to): static {
        if ($from) {
            $this->field['from_year'] = $from;
            $this->field['to_year'] = $to ?: date('Y') + 3;
        }
        return $this;
    }

    public function setUsers(\Gazelle\Manager\User $userMan, string $data): static {
        $usernames = $this->multiLineSplit($data);
        foreach ($usernames as $username) {
            $user = $userMan->findByUsername($username);
            if ($user && !$user->isParanoid('notifications')) {
                $this->field['user'][] = $user->id();
            }
        }
        return $this;
    }

    protected function arg(string $field): ?string {
        switch ($field) {
            case 'label':
            case 'exclude_va':
            case 'new_groups_only':
            case 'from_year':
            case 'to_year':
                return $this->field[$field];
            case 'artist':
            case 'user':
            case 'tag':
            case 'not_tag':
            case 'category':
            case 'record_label':
            case 'release_type':
            case 'format':
            case 'encoding':
            case 'media':
                $arg = '|' . implode('|', $this->field[$field]) . '|';
                return $arg === '||' ? '' : $arg;
        }
        return null;
    }

    public function modify(int $userId, int $filterId): int {
        $set = [];
        $args = [];
        foreach ($this->fieldMap as $field => $column) {
            if (!isset($this->field[$field])) {
                // TODO: fix schema to allow null values
                $set[] = "$column = " . (in_array($field, ['from_year', 'to_year']) ? "0" : "''");
            } else {
                $set[] = "$column = ?";
                $args[] = $this->arg($field);
            }
        }
        $args[] = $userId;
        $args[] = $filterId;
        self::$db->prepared_query("
            UPDATE users_notify_filters SET
            " . implode(', ', $set) . "
            WHERE UserID = ? AND ID = ?
            ", ...$args
        );
        return self::$db->affected_rows();
    }
}

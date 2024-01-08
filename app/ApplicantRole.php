<?php

namespace Gazelle;

class ApplicantRole extends BaseObject {
    final public const tableName = 'applicant_role';
    final public const CACHE_KEY = 'approlev2_%d';

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        unset($this->info);
        return $this;
    }
    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), html_escape($this->title())); }
    public function location(): string { return 'apply.php?action=view&id=' . $this->id; }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT Title    AS title,
                    Published   AS published,
                    Description AS description,
                    UserID      AS user_id,
                    Created     AS created,
                    Modified    AS modified
                FROM applicant_role
                WHERE ID = ?
                ", $this->id
            );
            self::$db->prepared_query("
                SELECT user_id
                FROM applicant_role_has_user
                WHERE applicant_role_id = ?
                ORDER BY user_id
                ", $this->id
            );
            $info['viewer_list'] = self::$db->collect(0, false);
            $info['published'] = (bool)$info['published'];
            self::$cache->cache_value($key, $info, 86400);
        }
        $this->info = $info;
        return $this->info;
    }

    public function title(): string {
        return $this->info()['title'];
    }

    public function description(): string {
        return $this->info()['description'];
    }

    public function isPublished(): bool {
        return $this->info()['published'];
    }

    public function isStaffViewer(User $user): bool {
        return in_array($user->id(), $this->viewerList()) || $user->permitted('admin_manage_applicants');
    }

    public function isViewable(User $user): bool {
        return $this->isPublished() || $this->isStaffViewer($user);
    }

    public function userId(): int {
        return $this->info()['user_id'];
    }

    public function viewerList(): array {
        return $this->info()['viewer_list'];
    }

    public function created(): string {
        return $this->info()['created'];
    }

    public function modified(): string {
        return $this->info()['modified'];
    }

    public function apply(User $user, string $body): Applicant {
        self::$db->prepared_query("
            INSERT INTO applicant
                   (RoleID, UserID, Body, ThreadID)
            VALUES (?,      ?,      ?,    ?)
            ", $this->id, $user->id(), $body,
                (new Manager\Thread())->createThread('staff-role')->id()
        );
        (new Manager\Applicant)->flush();
        (new Manager\ApplicantRole)->flush();
        return new \Gazelle\Applicant(self::$db->inserted_id());
    }

    public function modify(): bool {
        $modified = false;
        $userMan  = new Manager\User;
        $list = preg_split('/\s+/', $this->clearField('viewer_list'));
        $viewerList = empty($list)
            ? []
            : array_filter(
                array_map(fn($name) => $userMan->find($name)?->id(), $list),
                fn($user) => $user
            );
        sort($viewerList);
        if ($viewerList != $this->viewerList()) {
            self::$db->begin_transaction();
            self::$db->prepared_query("
                DELETE FROM applicant_role_has_user WHERE applicant_role_id = ?
                ", $this->id
            );
            foreach ($viewerList as $userId) {
                self::$db->prepared_query("
                    INSERT INTO applicant_role_has_user
                           (applicant_role_id, user_id)
                    VALUES (?,                 ?)
                    ", $this->id, $userId
                );
            }
            self::$db->commit();
            $modified = true;
        }
        $modified = parent::modify() || $modified;
        if ($modified) {
            $this->flush();
        }
        return $modified;
    }

    public function remove(): int {
        self::$db->prepared_query("
            DELETE FROM applicant_role_has_user WHERE applicant_role_id = ?
            ", $this->id
        );
        self::$db->prepared_query("
            DELETE FROM applicant_role WHERE ID = ?
            ", $this->id
        );
        $affected = self::$db->affected_rows();
        (new Manager\Applicant)->flush();
        (new Manager\ApplicantRole)->flush();
        $this->flush();
        return $affected;
    }
}

<?php

namespace Gazelle;

class Applicant extends BaseObject {
    final const tableName        = 'applicant';
    final const CACHE_KEY        = 'applicantv2_%d';
    final const ENTRIES_PER_PAGE = 1000; // TODO: change to 50 and implement pagination

    public function flush(): static {
        (new Manager\Applicant)->flush();
        if (isset($this->info)) {
            self::$cache->delete_value("user_applicant_{$this->userId()}");
        }
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        unset($this->info);
        return $this;
    }
    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), html_escape($this->role()->title())); }
    public function location(): string { return 'apply.php?action=view&id=' . $this->id; }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT a.RoleID AS role_id,
                    a.UserID    AS user_id,
                    a.ThreadID  AS thread_id,
                    a.Body      AS body,
                    a.Resolved  AS resolved,
                    a.Created   AS created,
                    a.Modified  AS modified
                FROM applicant a
                WHERE a.ID = ?
                ", $this->id
            );
            self::$cache->cache_value($key, $info, 86400);
        }
        $info['thread'] = new Thread($info['thread_id']);
        $info['role']   = new ApplicantRole($info['role_id']);
        $this->info = $info;
        return $this->info;
    }

    public function body(): string {
        return $this->info()['body'];
    }

    public function created(): string {
        return $this->info()['created'];
    }

    public function isResolved(): bool {
        return $this->info()['resolved'];
    }

    public function isViewable(User $user): bool {
        return $this->userId() == $user->id() || $this->role()->isStaffViewer($user);
    }

    public function thread(): Thread {
        return $this->info()['thread'];
    }

    public function threadId(): int {
        return $this->info()['thread_id'];
    }

    public function role(): ApplicantRole {
        return $this->info()['role'];
    }

    public function userId(): int {
        return $this->info()['user_id'];
    }

    public function resolve(bool $resolved = true): static {
        $this->setField('Resolved', (int)$resolved)->modify();
        return $this;
    }

    // DELEGATES

    /**
     * Save the applicant thread note (see Thread class)
     */
    public function saveNote(User $poster, string $body, string $visibility): int {
        if (!$this->role()->isStaffViewer($poster)) {
            $visibility = 'public';
        }
        $noteId = $this->thread()->saveNote($poster, $body, $visibility);
        if ($visibility == 'public' && $this->role()->isStaffViewer($poster)) {
            (new Manager\User)->sendPM(
                $this->userId(), 0,
                "You have a reply to your {$this->role()->title()} application",
                self::$twig->render('applicant/pm-reply.bbcode.twig', [
                    'applicant' => $this,
                    'poster'    => $poster,
                ])
            );
        }
        $this->flush();
        return $noteId;
    }

    public function removeNote(int $noteId): int {
        $affected = $this->thread()->removeNote($noteId);
        $this->flush();
        return $affected;
    }

    /**
     * Get the applicant thread story (see Thread class)
     * Notes will be filtered out if viewer is not staff
     */
    public function story(User $user): array {
        return $this->role()->isStaffViewer($user)
            ? $this->thread()->story()
            : array_filter($this->thread()->story(), fn($note) => $note['visibility'] == 'public');
    }
}

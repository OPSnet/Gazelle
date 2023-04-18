<?php

namespace Gazelle\Json;

class UserSearch extends \Gazelle\Json {
    public function __construct(
        protected string                  $search,
        protected \Gazelle\User           $viewer,
        protected \Gazelle\Manager\User   $manager,
        protected \Gazelle\Util\Paginator $paginator,
    ) {}

    public function payload(): array {
        $condition = $this->viewer->permitted('site_advanced_search')
            ? "Username LIKE concat('%', ?, '%')"
            : "Username = ?";

        self::$db->prepared_query("
            SELECT um.ID
            FROM users_main AS um
            WHERE $condition
            ORDER BY Username
            LIMIT ? OFFSET ?
            ", $this->search, $this->paginator->limit(), $this->paginator->offset()
        );

        $payload = [];
        foreach (self::$db->collect(0, false) as $userId) {
            $user = $this->manager->findById($userId);
            $payload[] = [
                'userId'   => $user->id(),
                'username' => $user->username(),
                'donor'    => (new \Gazelle\User\Donor($user))->isDonor(),
                'warned'   => $user->isWarned(),
                'enabled'  => $user->isEnabled(),
                'class'    => $user->userclassName(),
                'avatar'   => $user->avatar(),
            ];
        }

        return [
            'results'     => $payload,
            'currentPage' => $this->paginator->page(),
            'pages'       => (int)self::$db->scalar("
                SELECT ceil(count(*) / ?) as pages FROM users_main WHERE $condition
                ", AJAX_USERS_PER_PAGE, $this->search
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Gazelle\Route\Index;

use Gazelle\Route;
use Gazelle\Stats\Users;

class Unauthenticated extends Route {
    public function process(): void {
        if (!SHOW_PUBLIC_INDEX) {
            header('Location: login.php');
            return;
        }
        echo $this->twig->render('index/public.twig', [
            'new' => new Users()->enabledUserTotal() == 0,
        ]);
    }
}

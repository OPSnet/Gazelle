<?php

namespace Gazelle;

use \Gazelle\Manager\Notification;

class Activity extends Base {
    protected $action = [];
    protected $alert = [];

    public function setAction($action) {
        if (!is_null($action)) {
            $this->action[] = $action;
        }
        return $this;
    }

    public function setAlert($alert) {
        if (!is_null($alert)) {
            $this->alert[] = $alert;
        }
        return $this;
    }

    public function setNotification(Notification $n) {
        if ($n->isTraditional(Notification::INBOX)) {
            $this->setAlert($n->inboxAlert());
        }

        if ($n->isTraditional(Notification::TORRENTS)) {
            $this->setAlert($n->torrentAlert());
        }
    }

    public function actionList(): array {
        return $this->action;
    }

    public function alertList(): array {
        return $this->alert;
    }
}

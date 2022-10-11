<?php

namespace Gazelle\Json;

class PM  extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\PM $pm,
        protected \Gazelle\Manager\User $userMan,
    ) { }

    public function payload(): array {
        $user = [];
        $conversation = [];
        foreach ($this->pm->postList($this->pm->postTotal(), 0) as $post) {
            if (!isset($user[$post['sender_id']])) {
                $user[$post['sender_id']] = $this->userMan->findById($post['sender_id']);
            }
            $conversation[] = [
                'messageId'  => $post['id'],
                'senderId'   => $post['sender_id'],
                'senderName' => $user[$post['sender_id']]->username(),
                'sentDate'   => $post['sent_date'],
                'avatar'     => $user[$post['sender_id']]->avatar(),
                'bbBody'     => $post['body'],
                'body'       => \Text::full_format($post['body']),
            ];
        }

        return [
            'convId'   => $this->pm->id(),
            'subject'  => $this->pm->subject()
                . (is_null($this->pm->forwardedTo()) ? '' : " (Forwarded to {$this->pm->forwardedTo()->username()})"),
            'sticky'   => $this->pm->isPinned(),
            'messages' => $conversation,
        ];
    }
}

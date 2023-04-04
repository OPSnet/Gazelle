<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class InboxTest extends TestCase {
    protected array $userList;

    public function tearDown(): void {
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }
    public function testInbox(): void {
        $this->userList = [
            'sender'   => Helper::makeUser('inbox.send', 'inbox'),
            'receiver' => Helper::makeUser('inbox.recv', 'inbox'),
        ];
        $senderId = $this->userList['sender']->id();
        $receiverId = $this->userList['receiver']->id();
        // wipe their inboxes (there is only one message)
        foreach ($this->userList as $user) {
            $pmMan = new Gazelle\Manager\PM($user);
            foreach ((new Gazelle\User\Inbox($user))->messageList($pmMan, 1, 0) as $pm) {
                $pm->remove();
            }
        }

        $sender = new Gazelle\User\Inbox($this->userList['sender']);
        $this->assertEquals('inbox.php?sort=latest', $sender->folderLink('inbox', false), 'inbox-folder-latest');
        $this->assertEquals('inbox.php?sort=unread', $sender->folderLink('inbox', true), 'inbox-folder-unread-first');
        $this->assertEquals('inbox.php?sort=latest&section=sentbox', $sender->folderLink('sentbox', false), 'inbox-sentbox-folder-latest');
        $this->assertEquals('inbox.php?sort=unread&section=sentbox', $sender->folderLink('sentbox', true), 'inbox-sentbox-folder-unread-first');

        $this->assertEquals('inbox', $sender->folder(), 'inbox-sender-inbox');
        $this->assertEquals('Inbox', $sender->folderTitle($sender->folder()), 'inbox-sender-folder-title-inbox');
        $this->assertEquals('Inbox', $sender->title(), 'inbox-sender-title-inbox');
        $this->assertEquals(0, $sender->messageTotal(), 'inbox-initial-message-count');
        $sender->setFolder('sentbox');
        $this->assertEquals('sentbox', $sender->folder(), 'inbox-sender-sentbox');
        $this->assertEquals('Sentbox', $sender->folderTitle($sender->folder()), 'inbox-sender-folder-title-sentbox');
        $this->assertEquals('Sentbox', $sender->title(), 'inbox-sender-title-sentbox');
        $this->assertEquals(0, $sender->messageTotal(), 'inbox-sentbox-message-count');

        // send a PM
        $subject = "phpunit pm subject " . randomString(12);
        $body    = "phpunit pm body " . randomString(12);
        $userMan = new Gazelle\Manager\User;
        $convId  = $userMan->sendPM($receiverId, $sender->user()->id(), $subject, $body);
        $sender->setFolder('inbox');
        $this->assertEquals(0, $sender->messageTotal(), 'inbox-still-0-message-count');

        // details of the PM
        $pmSenderManager = new Gazelle\Manager\PM($sender->user());
        $pmSent          = $pmSenderManager->findById($convId);
        $this->assertInstanceOf(\Gazelle\PM::class, $pmSent, 'inbox-have-a-pm');
        $this->assertEquals($subject, $pmSent->subject(), 'inbox-pm-subject');
        $postList = $pmSent->postList(2, 0);
        $this->assertCount(1, $postList, 'inbox-pm-one-post');
        $this->assertEquals($body, $postList[0]['body'], 'inbox-pm-one-body');
        $this->assertEquals([$senderId, $receiverId], $pmSent->recipientList(), 'pm-recipient-list');

        // receive the PM
        $receiver = new Gazelle\User\Inbox($this->userList['receiver']);
        $pmReceiverManager = new Gazelle\Manager\PM($receiver->user());
        $this->assertEquals(1, $receiver->messageTotal(), 'inbox-initial-message-count');
        $list = $receiver->messageList($pmReceiverManager, 2, 0);
        $this->assertCount(1, $list, 'inbox-recv-list');
        $recvPm = $list[0];
        $this->assertEquals($subject, $recvPm->subject(), 'inbox-pm-subject');
        $recvList = $recvPm->postList(2, 0);
        $this->assertCount(1, $recvList, 'inbox-pm-recv-post');
        $this->assertEquals($body, $recvList[0]['body'], 'inbox-pm-recv-body');
        $this->assertEquals(1, $receiver->user()->inboxUnreadCount(), 'inbox-unread-count');
        $this->assertEquals(1, $receiver->massRead([$convId]), 'inbox-mark-read');
        $this->assertEquals(0, $receiver->user()->inboxUnreadCount(), 'inbox-none-unread');

        // sentbox
        $sender->setFolder('sentbox');
        $this->assertEquals('sentbox', $sender->folder(), 'inbox-sender-sentbox');
        $this->assertEquals(1, $sender->messageTotal(), 'inbox-sentbox-message-count');
        $msgList = $sender->messageList($pmSenderManager, 2, 0);
        $this->assertCount(1, $msgList, 'inbox-sentbox-list');
        $this->assertEquals($subject, $msgList[0]->subject(), 'inbox-sentbox-subject');
        $pmList = $msgList[0]->postList(2, 0);
        $this->assertCount(1, $pmList, 'inbox-sentbox-recv-post');
        $this->assertEquals($body, $pmList[0]['body'], 'inbox-sentbox-recv-body');

        // FIXME: this shows just how funky a reply is with the current code
        // It could be as simple as $pm->reply('body');

        // reply
        $replyBody = 'reply two ' . randomString(10);
        $replyId = $userMan->replyPM($senderId, $receiverId, $subject, 'reply one', $convId);
        $replyId = $userMan->replyPM($senderId, $receiverId, $subject, $replyBody, $convId);
        $this->assertEquals($replyId, $convId, 'inbox-recv-reply');
        $senderList = $sender->messageList($pmSenderManager, 2, 0);
        $this->assertCount(1, $senderList, 'inbox-sender-replylist');
        $msgList = $sender->messageList($pmSenderManager, 2, 0);
        $this->assertCount(1, $msgList, 'inbox-sentbox-still-1-list');
        $pmList = $msgList[0]->postList(4, 0);
        $this->assertCount(3, $pmList, 'inbox-sender-replies');
        $this->assertEquals($replyBody, end($pmList)['body'], 'inbox-sender-recent-reply');

        // several
        $subject  = "phpunit multi " . randomString(12);
        $bodyList = [randomString(), randomString(), randomString(), randomString()];
        $convList = array_map(
            fn($body) => $userMan->sendPM($receiverId, $senderId, $subject, $body),
            $bodyList
        );
        $this->assertEquals(4, $receiver->user()->inboxUnreadCount(), 'inbox-more-count');
        $this->assertEquals(5, $receiver->messageTotal(), 'inbox-more-message-count');
        $rlist = $receiver->messageList($pmReceiverManager, 6, 0);
        $this->assertFalse($rlist[0]->isUnread(), 'inbox-first-is-read');
        $this->assertTrue($rlist[1]->isUnread(), 'inbox-second-is-unread');

        // unread first
        $receiver->setUnreadFirst(true);
        $rlist = $receiver->messageList($pmReceiverManager, 6, 0);
        $this->assertTrue($rlist[0]->isUnread(), 'inbox-unread-first-is-unread');
        $this->assertFalse(end($rlist)->isUnread(), 'inbox-unread-last-is-read');

        // search body
        $receiver->setSearchField('message')->setSearchTerm($bodyList[2]);
        $this->assertEquals(1, $receiver->messageTotal(), 'inbox-search-body');
        $rlist = $receiver->messageList($pmReceiverManager, 2, 0);
        $this->assertCount(1, $rlist, 'inbox-search-list-body');
        $this->assertEquals($convList[2], $rlist[0]->id(), 'inbox-search-found');

        // search user
        $receiver->setSearchField('user')->setSearchTerm('nobody-here');
        $this->assertCount(0, $receiver->messageList($pmReceiverManager, 1, 0), 'inbox-search-no-user');
        $receiver->setSearchField('user')->setSearchTerm($this->userList['sender']->username());
        $this->assertEquals(5, $receiver->messageTotal(), 'inbox-search-sender-name');

        // search subject
        $receiver->setSearchField('subject')->setSearchTerm($subject);
        $this->assertEquals(4, $receiver->messageTotal(), 'inbox-search-subject');
        $rlist = $receiver->messageList($pmReceiverManager, 6, 0);
        $this->assertCount(4, $rlist, 'inbox-search-list-pm');

        // pin
        $this->assertEquals(2, $receiver->massTogglePinned([$rlist[1]->id(), $rlist[2]->id()]), 'inbox-pin-2');
        $receiver->setSearchField('subject')->setSearchTerm('')->setUnreadFirst(false);
        $rlist = $receiver->messageList($pmReceiverManager, 6, 0);
        $this->assertEquals(
            [$convList[1], $convList[2], $pmSent->id(), $convList[0], $convList[3]],
            [$rlist[0]->id(), $rlist[1]->id(), $rlist[2]->id(), $rlist[3]->id(), $rlist[4]->id()],
            'inbox-pinned-order-regular'
        );
        $rlist = $receiver->setUnreadFirst(true)->messageList($pmReceiverManager, 6, 0);
        $this->assertEquals(
            [$convList[1], $convList[2], $convList[0], $convList[3], $pmSent->id()],
            [$rlist[0]->id(), $rlist[1]->id(), $rlist[2]->id(), $rlist[3]->id(), $rlist[4]->id()],
            'inbox-pinned-order-unread'
        );

        // mass unread
        $this->assertEquals(
            1, // $rlist[4] a.k.a $pmSent has been read
            $receiver->massUnread([
                $rlist[2]->id(), $rlist[3]->id(), $rlist[4]->id()
            ]),
            'inbox-toggle-unread'
        );

        // mass remove
        $this->assertEquals(
            2, // $rlist[4] a.k.a $pmSent has been read
            $receiver->massRemove([
                $rlist[1]->id(), $rlist[3]->id()
            ]),
            'inbox-mass-remove'
        );
        $this->assertEquals(3, $receiver->messageTotal(), 'inbox-after-remove');

        $json = new Gazelle\Json\Inbox($receiver->user(), 'inbox', 1, true, $userMan);
        $payload = $json->payload();
        $this->assertCount(3, $payload, 'inbox-json-payload');
        $this->assertEquals(1, $payload['currentPage'], 'inbox-json-current-page');
        $this->assertEquals(1, $payload['pages'], 'inbox-json-pages');
        $this->assertCount(3, $payload['messages'], 'inbox-json-message-list');
        $this->assertEquals($convList[1], $payload['messages'][0]['convId'], 'inbox-json-first-message-id');
    }
}

<?php

use PHPUnit\Framework\TestCase;

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
            'sender'   => Helper::makeUser('inbox.send.' . randomString(6), 'inbox', clearInbox: true),
            'receiver' => Helper::makeUser('inbox.recv.' . randomString(6), 'inbox', clearInbox: true),
        ];
        $senderId = $this->userList['sender']->id();
        $receiverId = $this->userList['receiver']->id();

        $senderInbox = $this->userList['sender']->inbox();
        $this->assertEquals('inbox.php?sort=latest', $senderInbox->folderLink('inbox', false), 'inbox-folder-latest');
        $this->assertEquals('inbox.php?sort=unread', $senderInbox->folderLink('inbox', true), 'inbox-folder-unread-first');
        $this->assertEquals('inbox.php?sort=latest&section=sentbox', $senderInbox->folderLink('sentbox', false), 'inbox-sentbox-folder-latest');
        $this->assertEquals('inbox.php?sort=unread&section=sentbox', $senderInbox->folderLink('sentbox', true), 'inbox-sentbox-folder-unread-first');
        $this->assertEquals('inbox', $senderInbox->folder(), 'inbox-sender-inbox');
        $this->assertEquals('Inbox', $senderInbox->folderTitle($senderInbox->folder()), 'inbox-sender-folder-title-inbox');
        $this->assertEquals('Inbox', $senderInbox->title(), 'inbox-sender-title-inbox');
        $this->assertEquals(0, $senderInbox->messageTotal(), 'inbox-initial-message-count');

        $senderInbox->setFolder('sentbox');
        $this->assertEquals('sentbox', $senderInbox->folder(), 'inbox-sender-sentbox');
        $this->assertEquals('Sentbox', $senderInbox->folderTitle($senderInbox->folder()), 'inbox-sender-folder-title-sentbox');
        $this->assertEquals('Sentbox', $senderInbox->title(), 'inbox-sender-title-sentbox');
        $this->assertEquals(0, $senderInbox->messageTotal(), 'inbox-sentbox-message-count');

        // send a PM
        $receiverInbox = $this->userList['receiver']->inbox();
        $subject  = "phpunit pm subject " . randomString(12);
        $body     = "phpunit pm body " . randomString(12);
        $pmSent   = $receiverInbox->create($senderInbox->user(), $subject, $body);
        $senderInbox->setFolder('inbox');
        $this->assertEquals(0, $senderInbox->messageTotal(), 'inbox-still-0-message-count');

        // details of the PM
        $pmSenderManager = new Gazelle\Manager\PM($senderInbox->user());
        $this->assertInstanceOf(\Gazelle\PM::class, $pmSent, 'inbox-have-a-pm');
        $this->assertEquals($subject, $pmSent->subject(), 'inbox-pm-subject');
        $postList = $pmSent->postList(2, 0);
        $this->assertCount(1, $postList, 'inbox-pm-one-post');
        $this->assertEquals($body, $postList[0]['body'], 'inbox-pm-one-body');
        $this->assertEquals([$receiverId, $senderId], $pmSent->recipientList(), 'pm-recipient-list');

        // receive the PM
        $receiverInbox = $this->userList['receiver']->inbox();
        $pmReceiverManager = new Gazelle\Manager\PM($receiverInbox->user());
        $this->assertEquals(1, $receiverInbox->messageTotal(), 'inbox-initial-message-count');
        $list = $receiverInbox->messageList($pmReceiverManager, 2, 0);
        $this->assertCount(1, $list, 'inbox-recv-list');
        $recvPm = $list[0];
        $this->assertEquals($subject, $recvPm->subject(), 'inbox-pm-subject');
        $recvList = $recvPm->postList(2, 0);
        $this->assertCount(1, $recvList, 'inbox-pm-recv-post');
        $this->assertEquals($body, $recvList[0]['body'], 'inbox-pm-recv-body');
        $this->assertEquals(1, $receiverInbox->unreadTotal(), 'inbox-unread-count');
        $this->assertEquals(1, $receiverInbox->massRead([$pmSent->id()]), 'inbox-mark-read');
        $this->assertEquals(0, $receiverInbox->unreadTotal(), 'inbox-none-unread');

        // sentbox
        $senderInbox->setFolder('sentbox');
        $this->assertEquals('sentbox', $senderInbox->folder(), 'inbox-sender-sentbox');
        $this->assertEquals(1, $senderInbox->messageTotal(), 'inbox-sentbox-message-count');
        $msgList = $senderInbox->messageList($pmSenderManager, 2, 0);
        $this->assertCount(1, $msgList, 'inbox-sentbox-list');
        $this->assertEquals($subject, $msgList[0]->subject(), 'inbox-sentbox-subject');
        $pmList = $msgList[0]->postList(2, 0);
        $this->assertCount(1, $pmList, 'inbox-sentbox-recv-post');
        $this->assertEquals($body, $pmList[0]['body'], 'inbox-sentbox-recv-body');

        // FIXME: this shows just how funky a reply is with the current code
        // It could be as simple as $pm->reply('body');

        // reply
        $userMan  = new Gazelle\Manager\User;
        $replyBody = 'reply two ' . randomString(10);
        $replyId = $userMan->replyPM($senderId, $receiverId, $subject, 'reply one', $pmSent->id());
        $replyId = $userMan->replyPM($senderId, $receiverId, $subject, $replyBody, $pmSent->id());
        $this->assertEquals($replyId, $pmSent->id(), 'inbox-recv-reply');
        $senderList = $senderInbox->messageList($pmSenderManager, 2, 0);
        $this->assertCount(1, $senderList, 'inbox-sender-replylist');
        $msgList = $senderInbox->messageList($pmSenderManager, 2, 0);
        $this->assertCount(1, $msgList, 'inbox-sentbox-still-1-list');
        $pmList = $msgList[0]->postList(4, 0);
        $this->assertCount(3, $pmList, 'inbox-sender-replies');
        $this->assertEquals($replyBody, end($pmList)['body'], 'inbox-sender-recent-reply');

        // several
        $subject  = "phpunit multi " . randomString(12);
        $bodyList = [randomString(), randomString(), randomString(), randomString()];
        $pmList   = [];
        foreach ($bodyList as $body) {
            $pmList[] = $receiverInbox->create($senderInbox->user(), $subject, $body);
        }
        $convList = array_map(fn($p) => $p->id(), $pmList);

        $this->assertEquals(4, $receiverInbox->unreadTotal(), 'inbox-more-count');
        $this->assertEquals(5, $receiverInbox->messageTotal(), 'inbox-more-message-count');
        $rlist = $receiverInbox->messageList($pmReceiverManager, 6, 0);
        $flaky = implode(", ", array_map(fn($m) => "id={$m->id()} sent={$m->sentDate()} unr=" . ($m->isUnread() ? 'y' : 'n'), $rlist));
        $this->assertFalse($rlist[4]->isUnread(), "inbox-last-is-read $flaky");
        $this->assertTrue($rlist[3]->isUnread(), 'inbox-second-last-is-unread');

        // get body
        $postList = $rlist[2]->postList(2, 0);
        $postId = $postList[0]['id'];
        $pm = $pmReceiverManager->findByPostId($postId);
        $this->assertEquals($bodyList[1], $pm->postBody($postId), 'inbox-pm-post-body');

        // unread first
        $receiverInbox->setUnreadFirst(true);
        $rlist = $receiverInbox->messageList($pmReceiverManager, 6, 0);
        $this->assertTrue($rlist[0]->isUnread(), 'inbox-unread-first-is-unread');
        $this->assertFalse(end($rlist)->isUnread(), 'inbox-unread-last-is-read');

        // search body
        $receiverInbox->setSearchField('message')->setSearchTerm($bodyList[1]);
        $this->assertEquals(1, $receiverInbox->messageTotal(), 'inbox-search-body');
        $rlist = $receiverInbox->messageList($pmReceiverManager, 2, 0);
        $this->assertCount(1, $rlist, 'inbox-search-list-body');
        $this->assertEquals($convList[1], $rlist[0]->id(), 'inbox-search-found');

        // search user
        $receiverInbox->setSearchField('user')->setSearchTerm('nobody-here');
        $this->assertCount(0, $receiverInbox->messageList($pmReceiverManager, 1, 0), 'inbox-search-no-user');
        $receiverInbox->setSearchField('user')->setSearchTerm($this->userList['sender']->username());
        $this->assertEquals(5, $receiverInbox->messageTotal(), 'inbox-search-sender-name');

        // search subject
        $receiverInbox->setSearchField('subject')->setSearchTerm($subject);
        $this->assertEquals(4, $receiverInbox->messageTotal(), 'inbox-search-subject');
        $rlist = $receiverInbox->messageList($pmReceiverManager, 6, 0);
        $this->assertCount(4, $rlist, 'inbox-search-list-pm');

        // pin
        $this->assertEquals(2, $receiverInbox->massTogglePinned([$rlist[1]->id(), $rlist[2]->id()]), 'inbox-pin-2');
        $receiverInbox->setSearchField('subject')->setSearchTerm('')->setUnreadFirst(false);
        $rlist = $receiverInbox->messageList($pmReceiverManager, 6, 0);
        $this->assertEquals(
            [$convList[2], $convList[1], $convList[3], $convList[0], $pmSent->id()],
            [$rlist[0]->id(), $rlist[1]->id(), $rlist[2]->id(), $rlist[3]->id(), $rlist[4]->id()],
            'inbox-pinned-order-regular'
        );
        $rlist = $receiverInbox->setUnreadFirst(true)->messageList($pmReceiverManager, 6, 0);
        $this->assertEquals(
            [$convList[2], $convList[1], $convList[3], $convList[0], $pmSent->id()],
            [$rlist[0]->id(), $rlist[1]->id(), $rlist[2]->id(), $rlist[3]->id(), $rlist[4]->id()],
            'inbox-pinned-order-unread'
        );

        // mass unread
        $this->assertEquals(
            1, // $rlist[4] a.k.a $pmSent has been read
            $receiverInbox->massUnread([
                $rlist[2]->id(), $rlist[3]->id(), $rlist[4]->id()
            ]),
            'inbox-toggle-unread'
        );

        // mass remove
        $this->assertEquals(
            2, // $rlist[4] a.k.a $pmSent has been read
            $receiverInbox->massRemove([
                $rlist[1]->id(), $rlist[3]->id()
            ]),
            'inbox-mass-remove'
        );
        $this->assertEquals(3, $receiverInbox->messageTotal(), 'inbox-after-remove');

        $json = new Gazelle\Json\Inbox($receiverInbox->user(), 'inbox', 1, true, $userMan);
        $payload = $json->payload();
        $this->assertCount(3, $payload, 'inbox-json-payload');
        $this->assertEquals(1, $payload['currentPage'], 'inbox-json-current-page');
        $this->assertEquals(1, $payload['pages'], 'inbox-json-pages');
        $this->assertCount(3, $payload['messages'], 'inbox-json-message-list');
        $this->assertEquals($convList[2], $payload['messages'][0]['convId'], 'inbox-json-first-message-id');
    }

    public function testSystem(): void {
        $this->userList = [
            Helper::makeUser('inbox.recv.' . randomString(6), 'inbox'),
        ];
        $pm = $this->userList[0]->inbox()->createSystem('system', 'body');
        $this->assertEquals(0, $pm->senderId(), 'inbox-system-sender-id');
        $this->assertEquals(1, $pm->remove(), 'inbox-system-remove');
    }
}

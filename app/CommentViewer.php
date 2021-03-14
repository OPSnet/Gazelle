<?php

namespace Gazelle;

abstract class CommentViewer {

    /** @var \Gazelle\User */
    protected $viewer;

    /** @var string */
    protected $page;

    /** @var string */
    protected $baseLink;

    /** @var \Twig\Environment */
    protected $twig;

    public function __construct(\Twig\Environment $twig, int $viewerId) {
        $this->twig = $twig;
        $this->viewer = new User($viewerId);
    }

    protected function baseLink(int $postId): string {
        return sprintf($this->baseLink, $postId, $postId);
    }

    /**
     * Render a thread of comments
     * @param array [total comments, page, comment list, last read]
     * @param int PostID of the last read post
     */
    public function renderThread(array $Thread, int $lastRead) {
        $userMan = new Manager\User;
        foreach ($Thread as $Post) {
            [$PostID, $AuthorID, $AddedTime, $CommentBody, $EditedUserID, $EditedTime, $EditedUsername]
                = array_values($Post);
            $this->render($userMan, $AuthorID, $PostID, $CommentBody, $AddedTime, (int)$EditedUserID, $EditedTime, ($PostID > $lastRead));
        }
    }

    /**
     * Render a comment
     * @param int $AuthorID
     * @param int $PostID
     * @param string $Body
     * @param string $AddedTime
     * @param int $EditedUserID
     * @param string $EditedTime
     * @param string $Header The header used in the post
     * @param bool $Tools Whether or not to show [Edit], [Report] etc.
     */
    public function render($userMan, $AuthorID, $PostID, $Body, $AddedTime, $EditedUserID, $EditedTime, $Unread = false, $Header = '') {
        $author = new User($AuthorID);
        $ownProfile = $AuthorID == $this->viewer->id();
        echo $this->twig->render('comment/comment.twig', [
            'added_time'  => $AddedTime,
            'author'      => $author,
            'avatar'      => (new Manager\User)->avatarMarkup($this->viewer, $author),
            'body'        => $Body,
            'editor'      => $userMan->findById($EditedUserID),
            'edit_time'   => time_diff($EditedTime, 2, true, true),
            'id'          => $PostID,
            'is_admin'    => check_perms('site_admin_forums'),
            'heading'     => $Header,
            'page'        => $this->page,
            'show_avatar' => $this->viewer->showAvatars(),
            'show_delete' => check_perms('site_forum_post_delete'),
            'show_edit'   => check_perms('site_moderate_forums') || $ownProfile,
            'show_warn'   => check_perms('users_warn') && !$ownProfile && $this->viewer->classLevel() >= $author->classLevel(),
            'unread'      => $Unread,
            'url'         => $this->baseLink($PostID),
        ]);
    }
}

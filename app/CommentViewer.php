<?php

namespace Gazelle;

abstract class CommentViewer extends Base {

    /** @var \Gazelle\User */
    protected $viewer;

    /** @var string */
    protected $page;

    /** @var string */
    protected $baseLink;

    public function __construct(int $viewerId) {
        parent::__construct();
        $this->viewer = new User($viewerId);
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
            'avatar'      => $userMan->avatarMarkup($this->viewer, $author),
            'body'        => $Body,
            'editor'      => $userMan->findById($EditedUserID),
            'edit_time'   => time_diff($EditedTime, 2, true, true),
            'id'          => $PostID,
            'is_admin'    => $this->viewer->permitted('site_admin_forums'),
            'heading'     => $Header,
            'page'        => $this->page,
            'show_avatar' => $this->viewer->showAvatars(),
            'show_delete' => $this->viewer->permitted('site_forum_post_delete'),
            'show_edit'   => $this->viewer->permitted('site_moderate_forums') || $ownProfile,
            'show_warn'   => $this->viewer->permitted('users_warn') && !$ownProfile && $this->viewer->classLevel() >= $author->classLevel(),
            'unread'      => $Unread,
            'url'         => $this->baseLink . "&postid=$PostID#post$PostID",
        ]);
    }
}

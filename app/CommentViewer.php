<?php

namespace Gazelle;

abstract class CommentViewer {

    /** @var \Gazelle\User */
    protected $user;

    /** @var string */
    protected $page;

    /** @var string */
    protected $baseLink;

    /** @var \Twig\Environment */
    protected $twig;

    public function __construct(\Twig\Environment $twig, int $viewerId) {
        $this->twig = $twig;
        $this->user = new User($viewerId);
    }

    protected function baseLink(int $postId): string {
        return sprintf($this->baseLink, $postId, $postId);
    }

    /**
     * Render a thread of comments
     * @param array $Thread An array as returned by Comments::load
     * @param int $LastRead PostID of the last read post
     */
    public function renderThread(array $Thread, int $LastRead) {
        foreach ($Thread as $Post) {
            [$PostID, $AuthorID, $AddedTime, $CommentBody, $EditedUserID, $EditedTime, $EditedUsername]
                = array_values($Post);
            $this->render($AuthorID, $PostID, $CommentBody, $AddedTime, $EditedUserID, $EditedTime, ($PostID > $LastRead));
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
    public function render($AuthorID, $PostID, $Body, $AddedTime, $EditedUserID, $EditedTime, $Unread = false, $Header = '') {
        $author = \Users::user_info($AuthorID);
        $ownProfile = $AuthorID == $this->user->id();
        echo $this->twig->render('comment/comment.twig', [
            'avatar'      => \Users::show_avatar($author['Avatar'], $AuthorID, $author['Username'], $this->user->avatarMode()),
            'body'        => \Text::full_format($Body),
            'edited'      => $EditedUserID,
            'editor'      => \Users::format_username($EditedUserID, false, false, false),
            'edit_time'   => time_diff($EditedTime, 2, true, true),
            'id'          => $PostID,
            'is_admin'    => check_perms('site_admin_forums'),
            'header'      => '<strong>' . \Users::format_username($AuthorID, true, true, true, true, false) . '</strong> ' . time_diff($AddedTime) . $Header,
            'page'        => $this->page,
            'show_avatar' => $this->user->avatarMode() != '1',
            'show_delete' => check_perms('site_moderate_forums'),
            'show_edit'   => $ownProfile || check_perms('site_moderate_forums'),
            'show_warn'   => check_perms('users_warn') && !$ownProfile && $this->user->classLevel() >= $author['Class'],
            'unread'      => $Unread,
            'url'         => $this->baseLink($PostID),
            'username'    => $author['Username'],
        ]);
    }
}

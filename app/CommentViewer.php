<?php

namespace Gazelle;

abstract class CommentViewer extends BaseUser {

    protected string $page;
    protected string $baseLink;

    /**
     * Render a thread of comments
     * @param array $Thread [total comments, page, comment list, last read]
     * @param int $lastRead PostID of the last read post
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
     */
    public function render(Manager\User $userMan, int $AuthorID, int $PostID, string $Body, string $AddedTime, $EditedUserID, $EditedTime, bool $Unread = false, string $Header = '') {
        $author = new User($AuthorID);
        $ownProfile = $AuthorID == $this->user->id();
        echo self::$twig->render('comment/comment.twig', [
            'added_time'  => $AddedTime,
            'author'      => $author,
            'avatar'      => $userMan->avatarMarkup($this->user, $author),
            'body'        => $Body,
            'editor'      => $userMan->findById($EditedUserID),
            'edit_time'   => $EditedTime,
            'id'          => $PostID,
            'is_admin'    => $this->user->permitted('site_admin_forums'),
            'heading'     => $Header,
            'page'        => $this->page,
            'show_avatar' => $this->user->showAvatars(),
            'show_delete' => $this->user->permitted('site_forum_post_delete'),
            'show_edit'   => $this->user->permitted('site_moderate_forums') || $ownProfile,
            'show_warn'   => $this->user->permitted('users_warn') && !$ownProfile && $this->user->classLevel() >= $author->classLevel(),
            'unread'      => $Unread,
            'url'         => $this->baseLink . "&postid=$PostID#post$PostID",
        ]);
    }
}

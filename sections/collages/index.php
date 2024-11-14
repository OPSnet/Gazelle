<?php

require_once match ($_REQUEST['action'] ?? '') {
    'add_torrent',
    'add_torrent_batch'     => 'add_torrent.php',
    'add_artist',
    'add_artist_batch'      => 'add_artist.php',
    'ajax_add'              => 'ajax_add.php',
    'autocomplete'          => 'autocomplete.php',
    'comments'              => 'all_comments.php',
    'delete'                => 'delete.php',
    'take_delete'           => 'delete_handle.php',
    'download'              => 'download.php',
    'edit'                  => 'edit.php',
    'edit_handle'           => 'edit_handle.php',
    'manage'                => 'manage.php',
    'manage_handle'         => 'manage_handle.php',
    'manage_artists'        => 'manage_artists.php',
    'manage_artists_handle' => 'manage_artists_handle.php',
    'new'                   => 'new.php',
    'new_handle'            => 'new_handle.php',
    'recover'               => 'recover.php',
    default                 => empty($_GET['id']) ? 'browse.php' : 'collage.php',
};

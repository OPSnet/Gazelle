<?php

switch ($_GET['p'] ?? '') {
    case 'chat':
        require('chat.php');
        break;
    case 'clients':
        require('clients.php');
        break;
    case 'collages';
        require('collages.php');
        break;
    case 'ratio':
        require('ratio.php');
        break;
    case 'requests';
        require('requests.php');
        break;
    case 'tag':
        require('tag.php');
        break;
    case 'upload':
        require('upload.php');
        break;
    default:
        require('rules.php');
        break;
}

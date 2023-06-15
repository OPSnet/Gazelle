<?php

require_once(match ($_REQUEST['action'] ?? '') {
'webirc' => 'webirc.php',
default  => 'join.php',
});

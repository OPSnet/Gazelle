<h1>Error: 403</h1> Forbidden.
<?php
if (substr($_SERVER['REQUEST_URI'],0,9) !== '/static/') {
    notify(STATUS_CHAN,'403');
}

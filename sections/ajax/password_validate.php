<?php
echo $DB->scalar("
    SELECT Password FROM bad_passwords WHERE Password = ?
    ", $_POST['password']
) ? 'false' : 'true';

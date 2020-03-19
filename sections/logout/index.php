<?php
enforce_login(); // authorize() doesn't work if we're not logged in
authorize();
logout(G::$LoggedUser['ID']);

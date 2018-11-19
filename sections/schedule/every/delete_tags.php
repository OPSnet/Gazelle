<?php

//------------- Delete unpopular tags -----------------------------------//
$DB->query("
	DELETE FROM torrents_tags
	WHERE NegativeVotes > 1
		AND NegativeVotes > PositiveVotes");

<?php

namespace Gazelle;

class Artist {
	/** @var \DB_MYSQL */
	private $db;

	/** @var \CACHE */
	private $cache;

	const CACHE_ALIAS = 'artist_alias_%d_%s';

	public function __construct ($db, $cache) {
		$this->db = $db;
		$this->cache = $cache;
	}

	public function get_alias($id, $name) {
		$key = sprintf(self::CACHE_ALIAS, $id, $name); // use this later on
		$this->db->prepared_query('
			SELECT AliasID
			FROM artists_alias
			WHERE ArtistID = ?
				AND ArtistID != AliasID
				AND Name = ?',
			$id, $name);
		list($alias) = $this->db->next_record();
		return empty($alias) ? $id : $alias;
	}
}

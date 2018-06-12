<?
class G {
	/** @var DB_MYSQL */
	public static $DB;
	/** @var CACHE */
	public static $Cache;
	public static $LoggedUser;

	public static function initialize() {
		global $DB, $Cache, $LoggedUser;
		self::$DB = $DB;
		self::$Cache = $Cache;
		self::$LoggedUser =& $LoggedUser;
	}
}
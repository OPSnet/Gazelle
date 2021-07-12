<?php
use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class DeleteTorrent extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function up() {
        $this->table('torrents')
             ->changeColumn('last_action', 'datetime', ['null' => true])
             ->update();
        $this->execute("UPDATE torrents SET last_action = NULL WHERE last_action = '0000-00-00 00:00:00'");

        $this->table('deleted_torrents', ['id' => false, 'primary_key' => 'ID'])
             ->addColumn('ID', 'integer', ['length' => 10])
             ->addColumn('GroupID', 'integer', ['length' => 10])
             ->addColumn('UserID', 'integer', ['length' => 10, 'null' => true])
             ->addColumn('Media', 'string', ['length' => 20, 'null' => true])
             ->addColumn('Format', 'string', ['length' => 10, 'null' => true])
             ->addColumn('Encoding', 'string', ['length' => 15, 'null' => true])
             ->addColumn('Remastered', 'enum', ['values' => ['0','1']])
             ->addColumn('RemasterYear', 'integer', ['length' => 4, 'null' => true])
             ->addColumn('RemasterTitle', 'string', ['length' => 80])
             ->addColumn('RemasterCatalogueNumber', 'string', ['length' => 80])
             ->addColumn('RemasterRecordLabel', 'string', ['length' => 80])
             ->addColumn('Scene', 'enum', ['values' => ['0','1']])
             ->addColumn('HasLog', 'enum', ['values' => ['0','1']])
             ->addColumn('HasCue', 'enum', ['values' => ['0','1']])
             ->addColumn('HasLogDB', 'enum', ['values' => ['0','1']])
             ->addColumn('LogScore', 'integer', ['length' => 6])
             ->addColumn('LogChecksum', 'enum', ['values' => ['0','1']])
             ->addColumn('info_hash', 'blob')
             ->addColumn('FileCount', 'integer', ['length' => 6])
             ->addColumn('FileList', 'text', ['length' => MysqlAdapter::TEXT_MEDIUM])
             ->addColumn('FilePath', 'string', ['length' => 255])
             ->addColumn('Size', 'biginteger', ['length' => 12])
             ->addColumn('FreeTorrent', 'enum', ['values' => ['0','1','2']])
             ->addColumn('FreeLeechType', 'enum', ['values' => ['0','1','2','3','4','5','6','7']])
             ->addColumn('Time', 'timestamp', ['null' => true])
             ->addColumn('Description', 'text', ['null' => true])
             ->addColumn('LastReseedRequest', 'timestamp', ['null' => true])
             ->addColumn('TranscodedFrom', 'integer', ['length' => 10])
             ->create();

        $this->table('deleted_users_notify_torrents', ['id' => false, 'primary_key' => ['UserID', 'TorrentID']])
             ->addColumn('UserID', 'integer', ['length' => 10])
             ->addColumn('FilterID', 'integer', ['length' => 10])
             ->addColumn('GroupID', 'integer', ['length' => 10])
             ->addColumn('TorrentID', 'integer', ['length' => 10])
             ->addColumn('UnRead', 'integer', ['length' => 4])
             ->create();

        $this->table('deleted_torrents_files', ['id' => false, 'primary_key' => 'TorrentID'])
             ->addColumn('TorrentID', 'integer', ['length' => 10])
             ->addColumn('File', 'blob', ['length' => MysqlAdapter::BLOB_MEDIUM])
             ->create();

        $this->table('deleted_torrents_bad_files', ['id' => false, 'primary_key' => 'TorrentID'])
             ->addColumn('TorrentID', 'integer', ['length' => 11])
             ->addColumn('UserID', 'integer', ['length' => 11])
             ->addColumn('TimeAdded', 'timestamp', ['null' => true])
             ->create();

        $this->table('deleted_torrents_bad_folders', ['id' => false, 'primary_key' => 'TorrentID'])
             ->addColumn('TorrentID', 'integer', ['length' => 11])
             ->addColumn('UserID', 'integer', ['length' => 11])
             ->addColumn('TimeAdded', 'timestamp', ['null' => true])
             ->create();

        $this->table('deleted_torrents_bad_tags', ['id' => false, 'primary_key' => 'TorrentID'])
             ->addColumn('TorrentID', 'integer', ['length' => 11])
             ->addColumn('UserID', 'integer', ['length' => 11])
             ->addColumn('TimeAdded', 'timestamp', ['null' => true])
             ->create();

        $this->table('deleted_torrents_cassette_approved', ['id' => false, 'primary_key' => 'TorrentID'])
             ->addColumn('TorrentID', 'integer', ['length' => 10])
             ->addColumn('UserID', 'integer', ['length' => 10])
             ->addColumn('TimeAdded', 'timestamp', ['null' => true])
             ->create();

        $this->table('deleted_torrents_lossymaster_approved', ['id' => false, 'primary_key' => 'TorrentID'])
             ->addColumn('TorrentID', 'integer', ['length' => 10])
             ->addColumn('UserID', 'integer', ['length' => 10])
             ->addColumn('TimeAdded', 'timestamp', ['null' => true])
             ->create();

        $this->table('deleted_torrents_lossyweb_approved', ['id' => false, 'primary_key' => 'TorrentID'])
             ->addColumn('TorrentID', 'integer', ['length' => 10])
             ->addColumn('UserID', 'integer', ['length' => 10])
             ->addColumn('TimeAdded', 'timestamp', ['null' => true])
             ->create();

        $this->table('deleted_torrents_missing_lineage', ['id' => false, 'primary_key' => 'TorrentID'])
             ->addColumn('TorrentID', 'integer', ['length' => 11])
             ->addColumn('UserID', 'integer', ['length' => 11])
             ->addColumn('TimeAdded', 'timestamp', ['null' => true])
             ->create();
    }

    public function down() {
        $this->execute("UPDATE torrents SET last_action = '0000-00-00 00:00:00' WHERE last_action IS NULL");
        $this->table('torrents')
             ->changeColumn('last_action', 'datetime', ['null' => false])
             ->update();

        $this->table('deleted_users_notify_torrents')->drop()->update();
        $this->table('deleted_torrents_files')->drop()->update();
        $this->table('deleted_torrents_bad_files')->drop()->update();
        $this->table('deleted_torrents_bad_folders')->drop()->update();
        $this->table('deleted_torrents_bad_tags')->drop()->update();
        $this->table('deleted_torrents_cassette_approved')->drop()->update();
        $this->table('deleted_torrents_lossymaster_approved')->drop()->update();
        $this->table('deleted_torrents_lossyweb_approved')->drop()->update();
        $this->table('deleted_torrents_missing_lineage')->drop()->update();
        $this->table('deleted_torrents')->drop()->update();
    }
}

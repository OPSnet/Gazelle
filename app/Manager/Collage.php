<?php

namespace Gazelle\Manager;

class Collage extends \Gazelle\Base {

    public function create(\Gazelle\User $user, int $categoryId, string $name, string $description, string $tagList, \Gazelle\Log $logger) {
        $this->db->prepared_query("
            INSERT INTO collages
                   (UserID, CategoryID, Name, Description, TagList)
            VALUES (?,      ?,          ?,    ?,           ?)
            ", $user->id(), $categoryId, trim($name), trim($description), trim($tagList)
        );
        $id = $this->db->inserted_id();
        $logger->general("Collage $id ($name) was created by " . $user->username());
        return new \Gazelle\Collage($id);
    }

    /**
     * Does another collage already have this name (deleted or otherwise)
     *
     * @param string Name of collage to search
     * @return array [ID of other collage, Deleted 0/1] or null if no match
     */
    public function exists(string $name): ?array {
        return $this->db->row("
            SELECT ID, Deleted
            FROM collages
            WHERE Name = ?
            LIMIT 1
            ", trim($name)
        );
    }

    public function recoverById(int $id) {
        return $this->recover(
            $this->db->scalar("SELECT ID FROM collages WHERE ID = ?", $id)
        );
    }

    public function recoverByName(string $name) {
        return $this->recover(
            $this->db->scalar("SELECT ID FROM collages WHERE Name = ?", $name)
        );
    }

    protected function recover(int $id) {
        if (!$id) {
            return null;
        }
        $this->db->prepared_query("
            UPDATE collages SET
                Deleted = '0'
            WHERE ID = ?
            ", $id
        );
        return new \Gazelle\Collage($id);
    }

    /**
     * Create a generic collage name for a personal collage.
     * Used for people who lack the privileges create personal collages with arbitrary names
     *
     * @param string name of the user
     * @return string name of the collage
     */
    public function personalCollageName(string $name): string {
        $new = $name . "'s personal collage";
        $this->db->prepared_query('
            SELECT ID
            FROM collages
            WHERE Name = ?
            ', $new
        );
        $i = 1;
        $basename = $new;
        while ($this->db->has_results()) {
            $new = "$basename no. " . ++$i;
            $this->db->prepared_query('
                SELECT ID
                FROM collages
                WHERE Name = ?
                ', $new
            );
        }
        return $new;
    }
}

<?php

namespace Gazelle\Manager;

class Privilege extends \Gazelle\Base {

    protected $classList;
    protected $privilege;

    public function __construct() {
        parent::__construct();
        $this->init();
    }

    /**
     * The list of primary and secondary user classes.
     *
     * @return array
     *      - id
     *      - name
     *      - primary 0, 1
     */
    public function classList() {
        return $this->classList;
    }

    /**
     * The list of defined privileges. The `can` field
     * in the returned array acts as a sparse matrix.
     *
     * @return array
     *      - name (Short name of privilege)
     *      - description (Longer description of privilege)
     *      - orphan (Is this a privileges that no longer exists)
     *      - can (array of user class permission IDs that have this privilege)
     */
    public function privilege() {
        return $this->privilege;
    }

    /**
     * Fully initialize the object
     */
    protected function init() {
        // Grab the privileges defined in the Permissions class
        // TODO:: migrate here
        $this->privilege = [];
        $plist = \Permissions::list();
        foreach ($plist as $name => $description) {
            $this->privilege[$name] = [
                'can'         => [],
                'description' => $description,
                'name'        => $name,
                'orphan'      => 0
            ];
        }

        // lowercase column names, because this is going straight to twig
        $this->db->prepared_query("
            SELECT ID as id,
                Name as name,
                CASE WHEN Secondary = 1 THEN 0 ELSE 1 END AS \"primary\"
            FROM permissions
            ORDER BY Secondary DESC, Level, Name
        ");
        $this->classList = $this->db->to_array('id', MYSQLI_ASSOC);

        // decorate the privilges with those user classes that have benn granted access
        foreach ($this->classList as $c) {
            $perm = \Permissions::get_permissions($c['id'])['Permissions'];
            foreach (array_keys($perm) as $p) {
                if (!isset($this->privilege[$p])) {
                    // orphan permissions in the db that no longer do anything
                    $this->privilege[$p] = [
                        'can'         => [],
                        'description' => $p,
                        'name'        => $p,
                        'orphan'      => 1
                    ];
                }
                $this->privilege[$p]['can'][] = $c['id'];
            }
        }
    }
}

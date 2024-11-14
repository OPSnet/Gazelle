<?php
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

class SphinxqlResult {
    /**
     * Create Sphinxql result object
     *
     * @param mysqli_result $Result query results
     * @param array $Meta meta data for the query
     * @param int $Errno error code returned by the query upon failure
     * @param string $Error error message returned by the query upon failure
     */
    public function __construct(
        private $Result,
        private $Meta,
        public $Errno,
        public $Error,
    ) {}

    /**
     * Did the query find anything?
     *
     * @return bool results were found
     */
    public function has_results() {
        return $this->get_meta('total') > 0;
    }

    /**
     * Collect and return the specified key of all results as a list
     *
     * @param string $Key key containing the desired data
     * @return array with the $Key value of all results
     */
    public function collect($Key) {
        $Return = [];
        while ($Row = $this->Result->fetch_array()) {
            $Return[] = $Row[$Key];
        }
        $this->Result->data_seek(0);
        return $Return;
    }

    /**
     * Collect and return all available data for the matches optionally indexed by a specified key
     */
    public function to_array(string|false $Key, int $ResultType = MYSQLI_ASSOC): array {
        $Return = [];
        while ($Row = $this->Result->fetch_array($ResultType)) {
            if ($Key !== false) {
                $Return[$Row[$Key]] = $Row;
            } else {
                $Return[] = $Row;
            }
        }
        $this->Result->data_seek(0);
        return $Return;
    }

    /**
     * Collect pairs of keys for all matches
     *
     * @param string $Key1 key to use as indexing value
     * @param string $Key2 key to use as value
     * @return array with $Key1 => $Key2 pairs for matches
     */
    public function to_pair($Key1, $Key2) {
        $Return = [];
        while ($Row = $this->Result->fetch_array()) {
            $Return[$Row[$Key1]] = $Row[$Key2];
        }
        $this->Result->data_seek(0);
        return $Return;
    }

    /**
     * Return specified portions of the current Sphinxql result object's meta data
     *
     * @param mixed $Keys scalar or array with keys to return. Default is false, which returns all meta data
     * @return mixed array with meta data if $Keys is false, else the value of the specified key(s)
     */
    public function get_meta(mixed $Keys = false) {
        if ($Keys !== false) {
            if (is_array($Keys)) {
                $Return = [];
                foreach ($Keys as $Key) {
                    if (!isset($this->Meta[$Key])) {
                        continue;
                    }
                    $Return[$Key] = $this->Meta[$Key];
                }
                return $Return;
            } else {
                return $this->Meta[$Keys] ?? false;
            }
        } else {
            return $this->Meta;
        }
    }

    /**
     * Return specified portions of the current Mysqli result object's information
     *
     * @param mixed $Keys scalar or array with keys to return. Default is false, which returns all available information
     */
    public function get_result_info(mixed $Keys = false): mixed {
        if ($Keys !== false) {
            if (is_array($Keys)) {
                $Return = [];
                foreach ($Keys as $Key) {
                    if (!isset($this->Result->$Key)) {
                        continue;
                    }
                    $Return[$Key] = $this->Result->$Key;
                }
                return $Return;
            } else {
                return $this->Result->$Keys ?? false;
            }
        } else {
            return $this->Result;
        }
    }
}

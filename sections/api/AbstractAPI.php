<?php

abstract class AbstractAPI {
    protected $db;
    
    public function __construct(DB_MYSQL $db) {
        $this->db = $db;
    }
    
    abstract public function run();
}
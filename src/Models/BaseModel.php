<?php
namespace Models;

abstract class BaseModel {
    protected $db;
    
    public function __construct() {
        $this->db = \Services\Database::getInstance()->getConnection();
    }
    
    abstract public function getTableName();
    
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->getTableName()} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
} 
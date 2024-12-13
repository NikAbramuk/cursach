<?php
namespace Services;

class FileUploader {
    private $uploadDir;
    private $allowedTypes;
    
    public function __construct($uploadDir, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif']) {
        $this->uploadDir = $uploadDir;
        $this->allowedTypes = $allowedTypes;
    }
    
    public function upload($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \Exception('Файл не был загружен');
        }
        
        if (!in_array($file['type'], $this->allowedTypes)) {
            throw new \Exception('Недопустимый тип файла');
        }
        
        $fileName = uniqid() . '_' . basename($file['name']);
        $targetPath = $this->uploadDir . '/' . $fileName;
        
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \Exception('Ошибка при сохранении файла');
        }
        
        return $fileName;
    }
} 
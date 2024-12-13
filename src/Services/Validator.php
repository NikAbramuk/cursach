<?php
namespace Services;

class Validator {
    private $errors = [];
    
    public function validate($data, $rules) {
        foreach ($rules as $field => $rule) {
            if (!isset($data[$field])) {
                $this->errors[$field] = "Поле {$field} обязательно";
                continue;
            }
            
            $value = $data[$field];
            
            foreach ($rule as $validation => $param) {
                switch ($validation) {
                    case 'min':
                        if (strlen($value) < $param) {
                            $this->errors[$field] = "Минимальная длина поля {$field}: {$param}";
                        }
                        break;
                    case 'max':
                        if (strlen($value) > $param) {
                            $this->errors[$field] = "Максимальная длина поля {$field}: {$param}";
                        }
                        break;
                    // Добавьте другие правила валидации
                }
            }
        }
        
        return empty($this->errors);
    }
    
    public function getErrors() {
        return $this->errors;
    }
} 
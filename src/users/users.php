<?php
    function checkUser($username, $password, $pdo) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
    
        return false;
    }
    
    function createUser($pdo, $name, $username, $password, $age, $gender, $role) {
        $stmt = $pdo->prepare("INSERT INTO users (name, username, password, age, gender, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $username, $password, $age, $gender, $role]);
        return $pdo->lastInsertId();
    }

    function isUserExists($pdo, $username) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetchColumn() > 0; 
    }

    function validateUserData($postData) {
        $errors = [];
        
        $name = trim($postData['name']);
        $username = trim($postData['username']);
        $password = password_hash(trim($postData['password']), PASSWORD_DEFAULT);
        $age = $postData['age'];
        $gender = $postData['gender'];
        $role = $postData['role'];
    
        if (filter_var($age, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]) === false) {
            $errors[] = "Возраст должен быть положительным числом от 1 до 100.";
        }
        if (strlen($name) <= 4 || strlen($name) >= 256) {
            $errors[] = "Имя должно содержать более 4 символов и менее 256 (пробелы не считаются).";
        }
        if (strlen($username) <= 4 || strlen($username) >= 256) {
            $errors[] = "Логин должен содержать более 4 символов и менее 256 (пробелы не считаются).";
        }
        if (strlen($password) <= 4 || strlen($password) >= 256) {
            $errors[] = "Пароль должен содержать более 4 символов и менее 256 (пробелы не считаются).";
        }
    
        return [$errors, compact('name', 'username', 'password', 'age', 'gender', 'role')];
    }
?>
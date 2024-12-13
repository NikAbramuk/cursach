<?php
function createUser($pdo, $name, $username, $password, $age, $gender, $role) {
    $stmt = $pdo->prepare("INSERT INTO users (name, username, password, age, gender, role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $username, $password, $age, $gender, $role]);
    return $pdo->lastInsertId();
}
function userExists($pdo, $username) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetchColumn() > 0; 
}
?>
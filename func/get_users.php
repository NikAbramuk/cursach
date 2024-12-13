<?php
function getUsers($pdo) {
    $query = "SELECT * FROM users";
    $stmt = $pdo->query($query);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
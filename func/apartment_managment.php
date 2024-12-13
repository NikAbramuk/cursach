<?php
function validateApartmentData($data) {
    $errors = [];
    
    if (!is_numeric($data['price']) || $data['price'] < 0) {
        $errors[] = "Цена должна быть положительным числом.";
    }
    if (!is_numeric($data['rooms']) || $data['rooms'] <= 0) {
        $errors[] = "Количество комнат должно быть положительным числом.";
    }
    if (!is_numeric($data['area']) || $data['area'] <= 0) {
        $errors[] = "Площадь должна быть положительным числом.";
    }
    if (strlen($data['location']) <= 4 || strlen($data['name']) <= 4 || strlen($data['description']) <= 4) {
        $errors[] = "Адрес, описание и имя должны содержать более 4 символов.";
    }
    
    return $errors;
}

function createApartment($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO apartments (name, price, description, location, rooms, area, available) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['name'],
        $data['price'],
        $data['description'],
        $data['location'],
        $data['rooms'],
        $data['area'],
        isset($data['available']) ? 1 : 0
    ]);
}

function updateApartment($pdo, $data) {
    $stmt = $pdo->prepare("UPDATE apartments SET name = ?, price = ?, description = ?, location = ?, rooms = ?, area = ?, available = ? WHERE id = ?");
    $stmt->execute([
        $data['name'],
        $data['price'],
        $data['description'],
        $data['location'],
        $data['rooms'],
        $data['area'],
        isset($data['available']) ? 1 : 0,
        $data['id']
    ]);
}

function deleteApartment($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM apartments WHERE id = ?");
    $stmt->execute([$id]);
}

function searchApartments($pdo, $searchTerm) {
    $query = "SELECT * FROM apartments WHERE name LIKE ? OR description LIKE ? OR location LIKE ?";
    $params = ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"];
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllApartments($pdo) {
    $stmt = $pdo->query("SELECT * FROM apartments");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
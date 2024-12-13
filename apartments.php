<?php
require_once __DIR__ . '/src/Services/Session.php';
require_once __DIR__ . '/src/Services/Database.php';
require_once __DIR__ . '/src/Services/FileUploader.php';
require_once __DIR__ . '/src/Services/ErrorHandler.php';

use Services\Session;
use Services\Database;
use Services\FileUploader;

// Инициализация сессии
Session::start();

// Проверка авторизации
if (!Session::get('user_id')) {
    header("Location: login.php");
    exit();
}

// В начале файла после Session::start()
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Удалить этот блок в начале файла
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== Session::get('csrf_token')) {
    //     Session::set('error', 'Ошибка безопасности: недействительный токен');
    //     header("Location: apartments.php");
    //     exit();
    // }
}

// Удалить эти строки
// $csrf_token = bin2hex(random_bytes(32));
// Session::set('csrf_token', $csrf_token);

// Константы для работы с файлами
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif'];
const UPLOAD_DIR = 'files/';
const MAX_FILES_PER_APARTMENT = 10;

try {
    $db = Database::getInstance()->getConnection();
    $userId = Session::get('user_id');
    
    $isLandlord = Session::get('role') === 'landlord';
    $isClient = Session::get('role') === 'client';
    $isAdmin = Session::get('role') === 'admin';

    // Получение состояния умного поиска
    $stmt = $db->query("SELECT * FROM smart_search LIMIT 1");
    $smartSearch = $stmt->fetch(PDO::FETCH_ASSOC);
    $isActive = $smartSearch ? $smartSearch['is_active'] : 0;

    error_log("Smart Search active: " . ($isActive ? 'yes' : 'no'));
    error_log("User is client: " . ($isClient ? 'yes' : 'no'));

    // Обработка поискового запроса
    $searchTerm = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
        $searchTerm = trim($_POST['searchTerm']);
        if (strlen($searchTerm) > 1000) {
            Session::set('error', "Поисковый запрос не должен превышать 1000 символов.");
            $searchTerm = '';
        }
    }

    // Обработка загрузки файлов
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
        $file = $_FILES['file'];
        $apartmentId = $_POST['app_id'];
        
        try {
            // Создание директории для файлов квартиры
            $targetDir = UPLOAD_DIR . $apartmentId . '/';
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0777, true)) {
                    throw new \Exception('Не удалось создать директорию для загрузки');
                }
                // Установка прав на директорию после создания
                chmod($targetDir, 0777);
            }

            // Проверки файла
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception(match($file['error']) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Превышен максимальный размер файла',
                    UPLOAD_ERR_PARTIAL => 'Файл загружен частично',
                    UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
                    UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка',
                    UPLOAD_ERR_CANT_WRITE => 'Ошибка записи файла на диск',
                    UPLOAD_ERR_EXTENSION => 'PHP-расширение остановило загрузку файла',
                    default => 'Неизвестная ошибка при загрузке файла'
                });
            }

            if ($file['size'] > MAX_FILE_SIZE) {
                throw new \Exception('Размер файла превышает допустимый (10 МБ)');
            }

            $actualMimeType = mime_content_type($file['tmp_name']);
            if (!in_array($actualMimeType, ALLOWED_TYPES)) {
                throw new \Exception('Недопустимый тип файла');
            }

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($fileExtension, $allowedExtensions)) {
                throw new \Exception('Недопустимое расширение файла');
            }

            $fileName = preg_replace("/[^a-zA-Z0-9.]/", "", $file['name']);
            $fileName = uniqid() . '_' . $fileName;
            $targetPath = $targetDir . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new \Exception('Ошибка при сохранении файла');
            }
            
            // Установка прав на загруженный файл
            chmod($targetPath, 0777);

            // Проверка количества существующих файлов
            $existingFiles = glob($targetDir . '*');
            if (count($existingFiles) >= MAX_FILES_PER_APARTMENT) {
                throw new \Exception('Достигнут лимит файлов для этой квартиры');
            }

            Session::set('success', "Файл успешно загружен.");
        } catch (\Exception $e) {
            Session::set('error', 'Ошибка при загрузке файла: ' . $e->getMessage());
        }
    }

    // Обработка удаления файлов
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
        try {
            $filePath = $_POST['file_path'];
            
            // Проверяем, что путь к файлу находится в разрешенной директории
            $realFilePath = realpath($filePath);
            $realUploadDir = realpath(UPLOAD_DIR);
            
            if (!$realFilePath || !$realUploadDir || strpos($realFilePath, $realUploadDir) !== 0) {
                throw new \Exception("Недопустимый путь к файлу");
            }

            // Получаем ID квартиры из пути к файлу
            preg_match('/files\/(\d+)\//', $filePath, $matches);
            if (!isset($matches[1])) {
                throw new \Exception("Некорректный путь к файлу");
            }
            $apartmentId = $matches[1];

            // Проверяем права доступа (только владелец квартиры или админ могут удалять файлы)
            if (!$isAdmin) {
                $stmt = $db->prepare("SELECT landlordID FROM apartments WHERE id = ?");
                $stmt->execute([$apartmentId]);
                $apartment = $stmt->fetch();
                
                if (!$apartment || $apartment['landlordID'] !== $userId) {
                    throw new \Exception("У вас нет прав на удаление этого файла");
                }
            }

            // Проверяем существование файла
            if (!file_exists($filePath)) {
                throw new \Exception("Файл не найден");
            }

            // Проверяем права на запись
            if (!is_writable($filePath)) {
                throw new \Exception("Нет прав на удаление файла");
            }

            // Пытаемся удалить файл
            if (!unlink($filePath)) {
                throw new \Exception("Не удалось удалить файл");
            }

            Session::set('success', "Файл успешно удален");
            
        } catch (\Exception $e) {
            error_log('Error deleting file: ' . $e->getMessage() . ' Path: ' . $filePath);
            Session::set('error', "Ошибка при удалении файла: " . $e->getMessage());
        }
        
        // Перенаправляем обратно на страницу с квартирами
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    // Получение списка квартир
    if ($isActive && $isClient) {
        $apartments = getSmartSearchApartments($db, $userId, $searchTerm);
    } else {
        $apartments = getRegularApartments($db, $userId, $isLandlord, $searchTerm);
    }

    // Получение избранного и заявок
    $favoriteIDs = getFavoriteApartments($db, $userId);
    $applicationIDs = getApplicationApartments($db, $userId);

    // Обработка создания квартиры
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
        try {
            $name = trim($_POST['name']);
            $price = $_POST['price'];
            $description = trim($_POST['description']);
            $location = trim($_POST['location']);
            $rooms = $_POST['rooms'];
            $area = $_POST['area'];
            $available = isset($_POST['available']) ? 1 : 0;

            // Валидация данных
            if (empty($name) || strlen($name) < 4 || strlen($name) > 255) {
                throw new \Exception('Название должно содержать от 4 до 255 символов');
            }
            if (!is_numeric($price) || $price <= 0) {
                throw new \Exception('Цена должна быть положительным числом');
            }
            if (empty($description) || strlen($description) < 4 || strlen($description) > 1000) {
                throw new \Exception('Описание должно содержать от 4 до 1000 символов');
            }
            if (empty($location) || strlen($location) < 4 || strlen($location) > 255) {
                throw new \Exception('Адрес должен содержать от 4 до 255 символов');
            }
            if (!is_numeric($rooms) || $rooms <= 0 || $rooms > 100) {
                throw new \Exception('Количество комнат должно быть положительным числом не более 100');
            }
            if (!is_numeric($area) || $area <= 0 || $area > 1000) {
                throw new \Exception('Площадь должна быть положительным числом не более 1000');
            }

            $stmt = $db->prepare("INSERT INTO apartments (name, price, description, location, rooms, area, available, landlordID) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $price, $description, $location, $rooms, $area, $available, $userId]);
            Session::set('success', "Квартира успешно добавлена.");
            
            // Добавляем редирект
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
            
        } catch (\Exception $e) {
            Session::set('error', 'Ошибка при создании квартиры: ' . $e->getMessage());
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Обработка редактирования квартиры
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_save'])) {
        try {
            $id = $_POST['id'];
            $name = trim($_POST['name']);
            $price = $_POST['price'];
            $description = trim($_POST['description']);
            $location = trim($_POST['location']);
            $rooms = $_POST['rooms'];
            $area = $_POST['area'];
            $available = isset($_POST['available']) ? 1 : 0;

            // Проверка прав доступа
            $stmt = $db->prepare("SELECT landlordID FROM apartments WHERE id = ?");
            $stmt->execute([$id]);
            $apartment = $stmt->fetch();
            
            if (!$apartment) {
                throw new \Exception('Квартира не найдена');
            }
            
            if (!$isAdmin && $apartment['landlordID'] !== $userId) {
                throw new \Exception('У вас нет прав на редактирование этой квартиры');
            }

            // Валидация данных (аналогично созданию)
            if (empty($name) || strlen($name) < 4 || strlen($name) > 255) {
                throw new \Exception('Название должно содержать от 4 до 255 символов');
            }
            if (!is_numeric($price) || $price <= 0) {
                throw new \Exception('Цена должна быть положительным числом');
            }
            if (empty($description) || strlen($description) < 4 || strlen($description) > 1000) {
                throw new \Exception('Описание должно содержать от 4 до 1000 символов');
            }
            if (empty($location) || strlen($location) < 4 || strlen($location) > 255) {
                throw new \Exception('Адрес должен содержать от 4 до 255 символов');
            }
            if (!is_numeric($rooms) || $rooms <= 0 || $rooms > 100) {
                throw new \Exception('Количество комнат должно быть положительным числом не более 100');
            }
            if (!is_numeric($area) || $area <= 0 || $area > 1000) {
                throw new \Exception('Площадь должна быть положительным числом не более 1000');
            }

            $stmt = $db->prepare("UPDATE apartments SET name = ?, price = ?, description = ?, location = ?, rooms = ?, area = ?, available = ? WHERE id = ?");
            $stmt->execute([$name, $price, $description, $location, $rooms, $area, $available, $id]);
            Session::set('success', "Квартира успешно обновлена.");
            
            // Добавляем редирект
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
            
        } catch (\Exception $e) {
            Session::set('error', 'Ошибка при обновлении квартиры: ' . $e->getMessage());
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Обработка удаления квартиры
    if (isset($_GET['delete'])) {
        try {
            $id = $_GET['delete'];
            
            // Проверка прав доступа
            $stmt = $db->prepare("SELECT landlordID FROM apartments WHERE id = ?");
            $stmt->execute([$id]);
            $apartment = $stmt->fetch();
            
            if (!$apartment) {
                throw new \Exception('Квартира не найдена');
            }
            
            if (!$isAdmin && $apartment['landlordID'] !== $userId) {
                throw new \Exception('У вас нет прав на удаление этой квартиры');
            }

            // Удаление связанных файлов
            $dir = UPLOAD_DIR . $id . '/';
            if (is_dir($dir)) {
                $files = glob($dir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($dir);
            }

            // Удаление связанных записей
            $db->beginTransaction();
            
            $stmt = $db->prepare("DELETE FROM favorites WHERE apartmentID = ?");
            $stmt->execute([$id]);
            
            $stmt = $db->prepare("DELETE FROM applications WHERE apartmentID = ?");
            $stmt->execute([$id]);
            
            $stmt = $db->prepare("DELETE FROM apartments WHERE id = ?");
            $stmt->execute([$id]);
            
            $db->commit();
            
            Session::set('success', "Квартира успешно удалена.");
            
            // Добавляем редирект после успешного удаления
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
            
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Session::set('error', 'Ошибка при удалении квартиры: ' . $e->getMessage());
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Обработка добавления в избранное
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
        try {
            $apartmentId = $_POST['save'];
            
            // Проверка существования квартиры
            $stmt = $db->prepare("SELECT id FROM apartments WHERE id = ?");
            $stmt->execute([$apartmentId]);
            if (!$stmt->fetch()) {
                throw new \Exception('Квартир не найдена');
            }
            
            // Проверка дубликата
            $stmt = $db->prepare("SELECT id FROM favorites WHERE userID = ? AND apartmentID = ?");
            $stmt->execute([$userId, $apartmentId]);
            if ($stmt->fetch()) {
                throw new \Exception('Квартира уже в избранном');
            }

            $stmt = $db->prepare("INSERT INTO favorites (userID, apartmentID) VALUES (?, ?)");
            $stmt->execute([$userId, $apartmentId]);
            Session::set('success', "Квартира добавлена в избранное.");
            
            // Добавляем редирект
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
            
        } catch (\Exception $e) {
            Session::set('error', 'Ошибка при добавлении в избранное: ' . $e->getMessage());
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Обработка создания заявки
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request'])) {
        try {
            $apartmentId = $_POST['request'];
            
            // Проверка существования квартиры
            $stmt = $db->prepare("SELECT id, available FROM apartments WHERE id = ?");
            $stmt->execute([$apartmentId]);
            $apartment = $stmt->fetch();
            
            if (!$apartment) {
                throw new \Exception('Квартира не найдена');
            }
            
            if (!$apartment['available']) {
                throw new \Exception('Квартира недоступна для заявок');
            }
            
            // Проверка существующей заявки
            $stmt = $db->prepare("SELECT id FROM applications WHERE userID = ? AND apartmentID = ?");
            $stmt->execute([$userId, $apartmentId]);
            if ($stmt->fetch()) {
                throw new \Exception('Заявка на эту квартиру уже существует');
            }

            $stmt = $db->prepare("INSERT INTO applications (userID, apartmentID) VALUES (?, ?)");
            $stmt->execute([$userId, $apartmentId]);
            Session::set('success', "Заявка успешно отправлена.");
            
            // Добавляем редирект
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
            
        } catch (\Exception $e) {
            Session::set('error', 'Ошибка при создании заявки: ' . $e->getMessage());
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

} catch (\PDOException $e) {
    Session::set('error', 'Ошибка базы данных: ' . $e->getMessage());
    header("Location: logout.php");
    exit();
}

// Вспомогательные функции
function getSmartSearchApartments($db, $userId, $searchTerm) {
    // Логируем входные параметры
    error_log("Smart Search for user: $userId, searchTerm: $searchTerm");

    // Получаем средние цены одобренных заявок
    $stmt = $db->prepare("
        SELECT AVG(a.price) as avg_price
        FROM applications app
        JOIN apartments a ON app.apartmentID = a.id
        WHERE app.userID = ? AND app.status = 'accepted'
    ");
    $stmt->execute([$userId]);
    $avgPrice = $stmt->fetch(PDO::FETCH_ASSOC)['avg_price'];
    error_log("Average approved price: " . $avgPrice);

    // Получаем среднюю площадь избранных квартир
    $stmt = $db->prepare("
        SELECT AVG(a.area) as avg_area
        FROM favorites f
        JOIN apartments a ON f.apartmentID = a.id
        WHERE f.userID = ?
    ");
    $stmt->execute([$userId]);
    $avgArea = $stmt->fetch(PDO::FETCH_ASSOC)['avg_area'];
    error_log("Average favorite area: " . $avgArea);

    // Основной SQL запрос с весами
    $sql = "
    WITH price_weights AS (
        SELECT 
            a.id,
            CASE 
                WHEN ? > 0 THEN ROUND((1 - ABS(a.price - ?) / ?), 2)
                ELSE 0 
            END as price_weight
        FROM apartments a
    ),
    area_weights AS (
        SELECT 
            a.id,
            CASE 
                WHEN ? > 0 THEN ROUND((1 - ABS(a.area - ?) / ?), 2)
                ELSE 0 
            END as area_weight
        FROM apartments a
    )
    SELECT 
        a.*,
        COALESCE(f.favorite_count, 0) as favorite_count,
        COALESCE(app.application_count, 0) as application_count,
        pw.price_weight,
        aw.area_weight,
        (
            COALESCE(f.favorite_count, 0) * ss.favorite_coefficient +
            COALESCE(app.application_count, 0) * ss.application_coefficient +
            COALESCE(pw.price_weight, 0) * ss.price_coefficient +
            COALESCE(aw.area_weight, 0) * ss.area_coefficient
        ) as total_weight
    FROM 
        apartments a
    LEFT JOIN (
        SELECT apartmentID, COUNT(*) as favorite_count
        FROM favorites
        GROUP BY apartmentID
    ) f ON a.id = f.apartmentID
    LEFT JOIN (
        SELECT apartmentID, COUNT(*) as application_count
        FROM applications
        GROUP BY apartmentID
    ) app ON a.id = app.apartmentID
    LEFT JOIN price_weights pw ON a.id = pw.id
    LEFT JOIN area_weights aw ON a.id = aw.id
    CROSS JOIN smart_search ss
    WHERE ss.id = 1
    ";

    if ($searchTerm) {
        $sql .= " AND (a.name LIKE ? OR a.description LIKE ? OR a.location LIKE ?)";
    }

    $sql .= " ORDER BY total_weight DESC";

    // Подготавливаем параметры
    $params = [
        $avgPrice, $avgPrice, $avgPrice,  // для price_weights
        $avgArea, $avgArea, $avgArea      // для area_weights
    ];

    if ($searchTerm) {
        $params = array_merge($params, ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"]);
    }

    // Выполняем запрос
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Логируем результаты
    error_log("Smart Search results count: " . count($results));
    error_log("First result weights: " . print_r($results[0] ?? [], true));

    return $results;
}

function getRegularApartments($db, $userId, $isLandlord, $searchTerm) {
    $params = [];
    $query = "SELECT * FROM apartments WHERE 1=1";

    if ($isLandlord) {
        $query .= " AND landlordID = ?";
        $params[] = $userId;
    }
    
    if ($searchTerm) {
        $query .= " AND (name LIKE ? OR description LIKE ? OR location LIKE ?)";
        $params = array_merge($params, ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"]);
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFavoriteApartments($db, $userId) {
    $stmt = $db->prepare("SELECT apartmentID FROM favorites WHERE userID = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

function getApplicationApartments($db, $userId) {
    $stmt = $db->prepare("SELECT apartmentID FROM applications WHERE userID = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

// Подключаем шаблон header
require 'common/header.php';
?>

<!-- HTML и стили остаются прежними -->

<main>

<style>
    button {
    background-color: #27ae60; /* Черный цвет кнопки */
    color: white; /* Белый цвет текста кнопки */
    padding: 10px 15px;
    border: none; /* Без границы */
    border-radius: 4px; /* Закругленные углы */
    cursor: pointer; /* Курсор указывает на кликабельность */
}

button:hover {
    background-color: #27ae60; /* Темно-серый цвет кнопки при наведении */
}
body, main {
    font-family: 'Roboto', sans-serif;
   background: linear-gradient(to right, #28a745, #1e3c72) !important;
    margin: 0;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    color: #333; /* Темно-серый текст */
}
button {
    background-color: #27ae60; /* Черный цвет кнопки */
    color: white; /* Белый цвет текста кнопки */
    padding: 10px 15px;
    border: none; /* Без границы */
    border-radius: 4px; /* Закругленные углы */
    cursor: pointer; /* Курсор указывает на кликабельность */
}

button:hover {
    background-color: #27ae60; /* Темно-серый цвет кнопки при наведении */
}


</style>
<style>
 /* Общие стили для всей страницы */
body {
    font-family: 'Roboto', sans-serif;
    background-color: #f9f9f9;
    margin: 0;
    padding: 0;
}

form{
    background: #5FD1A5 !important;
    border:0 !important;
    box-shadow: none !important;
}

main {
    padding: 20px;
}

.no-apartments {
    text-align: center;
    font-size: 18px;
    color: #666;
}

/* Сетка карточек */
.apartment-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: center;
}

/* Карточка квартиры */
.apartment-card {
    background: #5FD1A5;
    padding: 20px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    border-radius: 10px;
    width: 400px; /* Увеличенная ширина карточки */
    display: flex;
    flex-direction: column;
    align-items: center;
}

.apartment-header {
    display: flex;
    justify-content: space-between;
    width: 100%;
}

.apartment-title {
    font-size: 22px;
    margin: 0;
}

.apartment-price {
    color: #3498db;
    font-size: 20px;
}

/* Детали квартиры */
.apartment-details {
    width: 100%;
    margin: 10px 0;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
}

.detail-label {
    font-weight: bold;
}

.detail-value {
    text-align: right;
}

.status-badge {
    padding: 5px 10px;
    border-radius: 20px;
    color: #fff;
}

.status-available {
    background-color: #4CAF50;
}

.status-unavailable {
    background-color: #f44336;
}

/* Описание квартиры */
.apartment-description {
    width: 100%;
    margin: 10px 0;
    color: #666;
}

/* Изображения квартиры */
.apartment-images {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
}

.apartment-image {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 5px;
}

.no-images {
    color: #999;
    font-size: 14px;
    text-align: center;
}

.image-error .error-message {
    color: red;
    font-size: 14px;
    text-align: center;
}

/* Стили для кнопок */
.btn-edit, .btn-delete, .btn-favorite, .btn-request, .btn-upload {
    padding: 10px 15px;
    color: #fff;
    border: none;
    border-radius: 25px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    margin-top: 10px;
}

.btn-edit {
    background-color: #3498db;
}

.btn-edit:hover {
    background-color: #2980b9;
}

.btn-delete {
    background-color: #f44336;
}

.btn-delete:hover {
    background-color: #e31b0c;
}

.btn-favorite {
    background-color: #e67e22;
}

.btn-favorite:hover {
    background-color: #d35400;
}

.btn-request {
    background-color: #2ecc71;
}

.btn-request:hover {
    background-color: #27ae60;
}

.btn-upload {
    background-color: #9b59b6;
}

.btn-upload:hover {
    background-color: #8e44ad;
}

.btn-disabled {
    background-color: #bdc3c7;
    color: #fff;
    padding: 10px 15px;
    border: none;
    border-radius: 25px;
    cursor: default;
    margin-top: 10px;
    text-align: center;
}

.status-form, .delete-form, .upload-form form {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
}

.action-buttons {
    display: flex;
    flex-wrap:wrap;
    justify-content: space-between;
    width: 100%;
    gap: 5px;
    margin-top:10px;
}

.action-buttons> form{
    display: flex !important;
    border: 0;
    box-shadow: none;
    padding: 0;
    justify-content: center;
 
}
.action-buttons> form> button{
    width: 150px;
 
}
/* Стили для формы поиска */
.search-form {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 20px;
    background-color: #fff;
    padding: 10px 20px;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.search-input {
    flex: 1;
    padding: 10px 15px;
    font-size: 16px;
    border: 1px solid #ccc;
    border-radius: 25px;
    margin-right: 10px;
    transition: border-color 0.3s ease;
}

.search-input:focus {
    border-color: #3498db;
    outline: none;
}

.search-form .btn {
    padding: 10px 15px;
    font-size: 16px;
    border: none;
    border-radius: 25px;
    cursor: pointer;
    background-color: #3498db;
    color: #fff;
    transition: background-color 0.3s ease;
}

.search-form .btn:hover {
    background-color: #2980b9;
}

/* Общие стили для всей страницы */
body {
    font-family: 'Roboto', sans-serif;
    background-color: #f9f9f9;
    margin: 0;
    padding: 0;
}

main {
    padding: 20px;
}

/* Стили для формы добавления квартиры */
.add-apartment-form {
    background: #5FD1A5;
    padding: 20px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    border-radius: 10px;
    max-width: 600px;
    margin: 20px auto;
}

.add-apartment-form h3 {
    text-align: center;
    color: #333;
    margin-bottom: 20px;
}

.apartment-form .form-group {
    margin-bottom: 15px;
}

.apartment-form label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
    color: #444;
}

.apartment-form input[type="text"],
.apartment-form input[type="number"],
.apartment-form textarea {
    width: 100%;
    padding: 10px;
    font-size: 16px;
    border: 1px solid #ccc;
    border-radius: 5px;
    transition: border-color 0.3s ease;
}

.apartment-form input[type="text"]:focus,
.apartment-form input[type="number"]:focus,
.apartment-form textarea:focus {
    border-color: #3498db;
    outline: none;
}

.apartment-form textarea {
    resize: vertical;
    height: 100px;
}

.apartment-form .form-group input[type="checkbox"] {
    margin-right: 10px;
}

.apartment-form .btn-success {
    display: block;
    width: 100%;
    padding: 15px;
    background-color: #2ecc71;
    color: white;
    border: none;
    border-radius: 25px;
    cursor: pointer;
    font-size: 16px;
    transition: background-color 0.3s;
}

.apartment-form .btn-success:hover {
    background-color: #27ae60;
}

</style>

    <div class="apartments-container">
        <h2>Квартиры</h2>

        <!-- Форма поиска -->
        <form method="post" class="search-form">
            <input type="text" 
                   name="searchTerm" 
                   value="<?= htmlspecialchars($searchTerm) ?>" 
                   placeholder="Поиск по названию, описанию и локации..."
                   class="search-input">
            <button type="submit" name="search" class="btn btn-primary">Поиск</button>
        </form>

        <!-- Сетка квартир -->
        <main>
            <?php if (empty($apartments)): ?>
                <p class="no-apartments">Нет доступных квартир.</p>
            <?php else: ?>
                <div class="apartment-grid">
                    <?php foreach ($apartments as $apartment): ?>
                        <div class="apartment-card">
                            <div class="apartment-header">
                                <h3 class="apartment-title"><?= htmlspecialchars($apartment['name']) ?></h3>
                                <div class="apartment-price"><?= number_format($apartment['price'], 0, ',', ' ') ?> ₽</div>
                            </div>

                            <div class="apartment-details">
                                <div class="detail-item">
                                    <span class="detail-label">Комнаты:</span>
                                    <span class="detail-value"><?= htmlspecialchars($apartment['rooms']) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Площадь:</span>
                                    <span class="detail-value"><?= htmlspecialchars($apartment['area']) ?> м²</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Адрес:</span>
                                    <span class="detail-value"><?= htmlspecialchars($apartment['location']) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Статус:</span>
                                    <span class="status-badge <?= $apartment['available'] ? 'status-available' : 'status-unavailable' ?>">
                                        <?= $apartment['available'] ? 'Доступна' : 'Недоступна' ?>
                                    </span>
                                </div>
                            </div>

                            <div class="apartment-description">
                                <?= htmlspecialchars($apartment['description']) ?>
                            </div>

                            <div class="apartment-images">
                                <?php
                                $dir = UPLOAD_DIR . $apartment['id'] . '/';
                                if (is_dir($dir)) {
                                    $images = glob($dir . "*.{jpg,jpeg,png,gif}", GLOB_BRACE);
                                    if (empty($images)): ?>
                                        <p class="no-images">Нет доступных изображений</p>
                                    <?php else:
                                        foreach ($images as $image): 
                                            try {
                                                if (!is_readable($image) || !file_exists($image) || @getimagesize($image) === false || !in_array(mime_content_type($image), ALLOWED_TYPES)) {
                                                    throw new \Exception("Неверный файл");
                                                }
                                                ?>
                                                <div class="image-container">
                                                    <img src="<?= htmlspecialchars($image) ?>" 
                                                        alt="Фото квартиры" 
                                                        class="apartment-image"
                                                        onerror="this.onerror=null; this.src='images/error-image.png'; this.classList.add('error-image');">
                                                    <?php if ($isLandlord || $isAdmin): ?>
                                                        <form method="post" style="display:inline;">
                                                            <input type="hidden" name="file_path" value="<?= htmlspecialchars($image) ?>">
                                                            <button type="submit" name="delete_file" class="image-delete-btn" title="Удалить">×</button>
                                                        </form>
                                                    <?php endif; ?>
                                
                                                </div>
                                                
                                            <?php
                                            } catch (\Exception $e) {
                                                error_log("Ошибка при обработке изображения {$image}: " . $e->getMessage());
                                                ?>
                                                <div class="image-error">
                                                    <p class="error-message">Ошибка загрузки изображения: <?= htmlspecialchars($e->getMessage()) ?></p>
                                                </div>
                                                <?php
                                            }
                                        endforeach;
                                    endif;
                                } else { ?>
                                    <p class="no-images">Изображений нет или они недоступны</p>
                                <?php } ?>
                            </div>
                        
                            <?php if ($isLandlord || $isAdmin): ?>
                                <div class="upload-form">
                                    <div class="requirements">
                                        <p>Максимальный размер: 10 МБ</p>
                                        <p>Форматы: JPG, PNG, GIF</p>
                                    </div>
                                    <form method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="app_id" value="<?= $apartment['id'] ?>">
                                        <input type="file" name="file" required accept="image/jpeg,image/png,image/gif">
                                        <button type="submit" class="btn-upload">Загрузить</button>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <div class="action-buttons">
                                <?php if ($isLandlord || $isAdmin): ?>
                                    <form method="post" style="display:inline;">
                                        <button type="submit" name="edit" formaction="apartments.php?id=<?= $apartment['id'] ?>" class="btn-edit">Редактировать</button>
                                    </form>
                                    <form method="get" style="display:inline;">
                                        <input type="hidden" name="delete" value="<?= $apartment['id'] ?>">
                                        <button type="submit" onclick="return confirm('Вы уверены?')" class="btn-delete">Удалить</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($isClient || $isAdmin): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="save" value="<?= $apartment['id'] ?>">
                                        <?php if (!in_array($apartment['id'], $favoriteIDs)): ?>
                                            <button type="submit" class="btn-favorite">В избранное</button>
                                        <?php else: ?>
                                            <span class="btn-disabled">В избранном</span>
                                        <?php endif; ?>
                                    </form>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="request" value="<?= $apartment['id'] ?>">
                                        <?php if (!in_array($apartment['id'], $applicationIDs)): ?>
                                            <button type="submit" class="btn-request">Оставить заявку</button>
                                        <?php else: ?>
                                            <span class="btn-disabled">Заявка отправлена</span>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>

        <!-- Форма добавления новой квартиры -->
        <?php if ($isLandlord || $isAdmin): ?>
            <div class="add-apartment-form">
                <h3>Добавить квартиру</h3>
                <form method="post" class="apartment-form">
                    <div class="form-group">
                        <label for="name">Название:</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="price">Цена:</label>
                        <input type="number" name="price" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Описание:</label>
                        <textarea name="description" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="location">Адрес:</label>
                        <input type="text" name="location" required>
                    </div>
                    <div class="form-group">
                        <label for="rooms">Количество комнат:</label>
                        <input type="number" name="rooms" required>
                    </div>
                    <div class="form-group">
                        <label for="area">Площадь (м²):</label>
                        <input type="number" name="area" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="available" checked>
                            Доступна
                        </label>
                    </div>
                    <button type="submit" name="create" class="btn btn-success">Добавить квартиру</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Форма редактирования квартиры -->
        <?php if (isset($_GET['id']) && ($isLandlord || $isAdmin)): ?>
            <?php
            // Получаем данные квартиры для редактирования
            $editId = $_GET['id'];
            $stmt = $db->prepare("SELECT * FROM apartments WHERE id = ?");
            $stmt->execute([$editId]);
            $editApartment = $stmt->fetch();
            
            // Проверяем права доступа
            if ($editApartment && ($isAdmin || $editApartment['landlordID'] === $userId)):
            ?>
                <div class="edit-apartment-form" style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <h3>Редактировать квартиру</h3>
                    <form method="post" class="apartment-form">
                        <input type="hidden" name="id" value="<?= $editApartment['id'] ?>">
                        
                        <div class="form-group">
                            <label for="name">Название:</label>
                            <input type="text" 
                                   name="name" 
                                   value="<?= htmlspecialchars($editApartment['name']) ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Цена:</label>
                            <input type="number" 
                                   name="price" 
                                   step="0.01" 
                                   value="<?= htmlspecialchars($editApartment['price']) ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Описание:</label>
                            <textarea name="description" 
                                      required><?= htmlspecialchars($editApartment['description']) ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="location">Адрес:</label>
                            <input type="text" 
                                   name="location" 
                                   value="<?= htmlspecialchars($editApartment['location']) ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="rooms">Количество комнат:</label>
                            <input type="number" 
                                   name="rooms" 
                                   value="<?= htmlspecialchars($editApartment['rooms']) ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="area">Площадь (м²):</label>
                            <input type="number" 
                                   name="area" 
                                   step="0.01" 
                                   value="<?= htmlspecialchars($editApartment['area']) ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" 
                                       name="available" 
                                       <?= $editApartment['available'] ? 'checked' : '' ?>>
                                Доступна
                            </label>
                        </div>
                        
                        <div class="form-buttons" style="margin-top: 20px;">
                            <button type="submit" name="edit_save" class="btn btn-success">
                                Сохранить изменения
                            </button>
                            <a href="apartments.php" class="btn btn-danger">Отмена</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php require 'common/footer.php'; ?>
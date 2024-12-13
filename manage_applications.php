<?php
session_start();

$dsn = 'mysql:host=localhost;dbname=php_laba5;charset=utf8';
$username = 'root';
$password = '';

require 'common/header.php';

$isLandlord = $_SESSION['role'] === 'landlord';
$isClient = $_SESSION['role'] === 'client';
$isAdmin = $_SESSION['role'] === 'admin';

$userID = $_SESSION['user_id'];
try {
    $pdo = new PDO($dsn, $username, $password);
} catch (PDOException $e) {
    $_SESSION['db_error'] = 'Ошибка подключения к базе данных: ' . $e->getMessage();
}
if (isset($_GET['applicationID'])) {
    $applicationID = htmlspecialchars($_GET['applicationID']);
    // Обработка параметра, например, получение данных из базы данных
    echo "Получен applicationID: " . $applicationID;
} else {
    // echo "Параметр applicationID не найден.";
}

if (!$pdo) {
    $_SESSION['db_error'] = 'База данных недоступна. Пожалуйста, попробуйте позже.'  . $e->getMessage();
    unset($_SESSION['user_id']);
    unset($_SESSION['username']);
    unset($_SESSION['role']);
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['user_id'] )) {
    header("Location: login.php");
    exit();
}

$params = [];
$query = "
    SELECT applications.id, 
           applications.apartmentID,
           apartments.name AS apartment_name, 
           users.name AS user_name, 
           applications.status
    FROM applications
    JOIN apartments ON applications.apartmentID = apartments.id
    JOIN users ON applications.userID = users.id
    WHERE 1=1";

if ($isLandlord || $isAdmin) {
    $landlordID = $_SESSION['user_id'];
    $query .= " AND apartments.landlordID = ?";
    $params[] = $landlordID;
}

if ($isClient) {
    $userID = $_SESSION['user_id'];
    $query .= " AND applications.userID = ?";
    $params[] = $userID;
}

$stmt = $pdo->prepare($query);

$stmt->execute($params);

$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'])) {
    // Обработка изменения статуса заявки
    try {
        if (isset($_POST['status'])) {
            $applicationID = $_POST['application_id'];
            $status = $_POST['status'];

            $stmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
            $stmt->execute([$status, $applicationID]);

            $_SESSION['success'] = "Статус заявки успешно обновлен.";
            header('Location: manage_applications.php'); // Перенаправление после обновления
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['db_error'] = 'Ошибка при обновлении коэффициента избранного: ' . htmlspecialchars($e->getMessage());
        // header("Location: logout.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_application_id'])) {
    try {
        $applicationID = $_POST['delete_application_id'];

        // Проверка, является ли пользователь клиентом
        if ($isClient) {
            $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ? AND userID = ?");
            $stmt->execute([$applicationID, $userID]);

            $_SESSION['success'] = "Заявка успешно удалена.";
            header('Location: manage_applications.php'); // Перенаправление после удаления
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['db_error'] = 'Ошибка при обновлении коэффициента избранного: ' . htmlspecialchars($e->getMessage());
        header("Location: logout.php");
        exit();
    }
}

// После определения констант в начале файла
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif'];
const UPLOAD_DIR = 'files/';

// Добавим обработчик загрузки файлов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    try {
        $file = $_FILES['file'];
        $apartmentId = $_POST['apartment_id'];

        // Проверяем права доступа
        if (!$isLandlord && !$isAdmin) {
            throw new \Exception('У вас нет прав на загрузку файлов');
        }

        // Создаем директорию если её нет
        $targetDir = UPLOAD_DIR . $apartmentId . '/';
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0777, true)) {
                throw new \Exception('Не удалось создать директорию для загрузки');
            }
            chmod($targetDir, 0777);
        }

        // Проверяем ошибки загрузки
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

        // Проверяем размер файла
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new \Exception('Размер файла превышает допустимый (10 МБ)');
        }

        // Проверяем тип файла
        $mimeType = mime_content_type($file['tmp_name']);
        if (!in_array($mimeType, ALLOWED_TYPES)) {
            throw new \Exception('Недопустимый тип файла');
        }

        // Генерируем уникальное имя файла
        $fileName = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $file['name']);
        $targetPath = $targetDir . $fileName;

        // Сохраняем файл
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \Exception('Ошибка при сохранении файла');
        }

        chmod($targetPath, 0777);
        $_SESSION['success'] = "Файл успешно загружен.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();

    } catch (\Exception $e) {
        $_SESSION['error'] = 'Ошибка при загрузке файла: ' . $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}
?>

<main>
    <h2>Заявки</h2>
    <?php if (empty($applications)): ?>
        <p class="no-applications">Нет заявок для обработки.</p>
    <?php else: ?>
        <div class="cards-container">
            <?php foreach ($applications as $application): ?>
                <div class="card">
                    <h2><?= htmlspecialchars($application['apartment_name']) ?></h2>
                    <p><strong>Имя пользователя:</strong> <?= htmlspecialchars($application['user_name']) ?></p>
                    <p><strong>Статус:</strong> <?= htmlspecialchars($application['status']) ?></p>
                    
                    <div class="apartment-images">
                        <?php
                        $dir = 'files/' . $application['apartmentID'] . '/';
                        if (is_dir($dir)) {
                            $images = glob($dir . "*.{jpg,jpeg,png,gif}", GLOB_BRACE);
                            if (empty($images)): ?>
                                <p class="no-images">Нет изображений</p>
                            <?php else:
                                foreach ($images as $image): 
                                    if (is_readable($image)): ?>
                                        <div class="image-container">
                                            <img src="<?= htmlspecialchars($image) ?>" 
                                                 alt="Фото квартиры" 
                                                 class="thumbnail"
                                                 onerror="this.style.display='none'">
                                        </div>
                                    <?php endif;
                                endforeach;
                            endif;
                        } else { ?>
                            <p class="no-images">Нет изображений</p>
                        <?php } ?>
                    </div>
                    
                    <?php if ($isLandlord || $isAdmin): ?>
                        <form method="post" class="status-form">
                            <input type="hidden" name="application_id" value="<?= $application['id'] ?>">
                            <select name="status">
                                <option value="in progress" <?= $application['status'] === 'in progress' ? 'selected' : '' ?>>Ожидает</option>
                                <option value="accepted" <?= $application['status'] === 'accepted' ? 'selected' : '' ?>>Согласовано</option>
                                <option value="rejected" <?= $application['status'] === 'rejected' ? 'selected' : '' ?>>Отклонено</option>
                            </select>
                            <button type="submit" class="btn-update">Обновить статус</button>
                        </form>
                    <?php endif; ?>
                    <form action="chat.php" method="get">
                        <input type="hidden" name="applicationID" value="<?= htmlspecialchars($application['id']) ?>">
                        <button type="submit">Перейти в чат</button> 
                    </form>
                    <?php if ($isClient): ?>
                        <form method="post" class="delete-form">
                            <input type="hidden" name="delete_application_id" value="<?= $application['id'] ?>">
                            <button type="submit" class="btn-delete" onclick="return confirm('Вы уверены, что хотите удалить эту заявку?');">Удалить</button>
                        </form>
                    <?php endif; ?>
                    
                    <!-- <?php if ($isLandlord || $isAdmin): ?>
                        <div class="upload-form">
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="apartment_id" value="<?= $application['apartmentID'] ?>">
                                <input type="file" name="file" accept="image/jpeg,image/png,image/gif" required>
                                <button type="submit" class="btn-upload">Загрузить фото</button>
                            </form>
                        </div>
                    <?php endif; ?> -->
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>


<style>

.upload-form{
    display: flex;
    border: 0;
    width: 100%;
}

.status-form,.upload-form > form{
    display: flex;
    border: 0;
    width: 100%;
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

.no-applications {
    text-align: center;
    font-size: 18px;
    color: #666;
}

/* Контейнер для карточек */
.cards-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: center;
}

/* Карточка */
.card {
    background: #5FD1A5 !important;
    padding: 20px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    border-radius: 10px;
    width: 300px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

form{
    background: #5FD1A5 !important;
    border:0 !important;
    box-shadow: none !important;
}


.card h2 {
    font-size: 22px;
    margin-bottom: 10px;
    text-align: center;
}

.card p {
    margin: 5px 0;
    text-align: center;
}

.apartment-images {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
}

.thumbnail {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 5px;
}

/* Стили для кнопок */
.btn-update, .btn-delete, .btn-upload {
    padding: 10px 15px;
    color: #fff;
    border: none;
    border-radius: 25px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    margin-top: 10px;
    width: 100%;
    max-width: 200px;
    text-align: center;
}

.btn-update {
    background-color: #3498db;
}

.btn-update:hover {
    background-color: #2980b9;
}

.btn-delete {
    background-color: #f44336;
}

.btn-delete:hover {
    background-color: #e31b0c;
}

.btn-upload {
    background-color: #2196F3;
}

.btn-upload:hover {
    background-color: #1e88e5;
}

.status-form, .delete-form, .upload-form form {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
}

.no-images {
    color: #999;
    font-size: 14px;
    text-align: center;
}

.error-placeholder {
    color: red;
    font-size: 14px;
    text-align: center;
}

.warning {
    color: orange;
    font-size: 14px;
    margin-top: 10px;
    text-align: center;
}
</style>

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

</style>
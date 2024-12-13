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
    header("Location: logout.php");
    exit();
}

// Константы для работы с файлами
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif'];

try {
    $db = Database::getInstance()->getConnection();
    $userId = Session::get('user_id');
    
    // Обработка удаления фото профиля
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo'])) {
        try {
            $stmt = $db->prepare("SELECT image FROM profile_pictures WHERE user_id = ?");
            $stmt->execute([$userId]);
            $existingImage = $stmt->fetch();

            if ($existingImage) {
                $stmt = $db->prepare("DELETE FROM profile_pictures WHERE user_id = ?");
                $stmt->execute([$userId]);
                Session::set('success', "Фото профиля успешно удалено.");
            } else {
                Session::set('error', "Фото профиля не найдено.");
            }
        } catch (\PDOException $e) {
            Session::set('error', 'Ошибка при удалении фото: ' . $e->getMessage());
        }
    }
    
    // Обработка загрузки изображения
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
        $file = $_FILES['profile_picture'];
        
        try {
            // Проверка наличия ошибок при загрузке
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

            // Проверка размера файла
            if ($file['size'] > MAX_FILE_SIZE) {
                throw new \Exception('Размер файла превышает допустимый (10 МБ)');
            }

            // Проверка типа файла
            if (!in_array($file['type'], ALLOWED_TYPES)) {
                throw new \Exception('Недопустимый тип файла. Разрешены только JPG, PNG и GIF');
            }

            // Проверка целостности файла изображения
            if (!getimagesize($file['tmp_name'])) {
                throw new \Exception('Файл поврежден или не является изображением');
            }

            // Чтение файла
            $imageData = @file_get_contents($file['tmp_name']);
            if ($imageData === false) {
                throw new \Exception('Ошибка чтения файла');
            }
            
            // Проверяем существующее изображение
            $stmt = $db->prepare("SELECT id FROM profile_pictures WHERE user_id = ?");
            $stmt->execute([$userId]);
            $existingImage = $stmt->fetch();

            if ($existingImage) {
                // Обновляем существующее изображение
                $stmt = $db->prepare("UPDATE profile_pictures SET image = ? WHERE user_id = ?");
                $stmt->execute([$imageData, $userId]);
            } else {
                // Вставляем новое изображение
                $stmt = $db->prepare("INSERT INTO profile_pictures (user_id, image) VALUES (?, ?)");
                $stmt->execute([$userId, $imageData]);
            }

            Session::set('success', "Фото успешно загружено.");
        } catch (\Exception $e) {
            Session::set('error', 'Ошибка при загрузке фото: ' . $e->getMessage());
        }
    }

    // Получение изображения профиля
    $stmt = $db->prepare("SELECT image FROM profile_pictures WHERE user_id = ?");
    $stmt->execute([$userId]);
    $profileImage = $stmt->fetch();
    
    $imageSrc = null;
    if ($profileImage && !empty($profileImage['image'])) {
        try {
            // Проверяем, является ли данные действительным изображением
            $tempFile = tempnam(sys_get_temp_dir(), 'img_');
            if ($tempFile === false) {
                throw new \Exception("Не удалось создать временный файл");
            }

            // Записываем данные во временный файл
            if (file_put_contents($tempFile, $profileImage['image']) === false) {
                throw new \Exception("Не удалось записать данные во временный файл");
            }

            // Проверяем, является ли файл изображением
            $imageInfo = @getimagesize($tempFile);
            if ($imageInfo === false) {
                throw new \Exception("Данные повреждены или не являются изображением");
            }

            // Проверяем MIME-тип
            $mimeType = $imageInfo['mime'];
            if (!in_array($mimeType, ALLOWED_TYPES)) {
                throw new \Exception("Недопустимый тип изображения");
            }

            // Если все проверки пройдены, создаем data URL
            $imageSrc = 'data:' . $mimeType . ';base64,' . base64_encode($profileImage['image']);

            // Удаляем временный файл
            unlink($tempFile);

        } catch (\Exception $e) {
            error_log("Ошибка при обработке изображения профиля: " . $e->getMessage());
            Session::set('error', "Ошибка при загрузке изображения профиля: " . $e->getMessage());
            
            // Удаляем поврежденное изображение из БД
            try {
                $stmt = $db->prepare("DELETE FROM profile_pictures WHERE user_id = ?");
                $stmt->execute([$userId]);
                Session::set('warning', "Поврежденное изображение было удалено");
            } catch (\PDOException $e) {
                error_log("Ошибка при удалении поврежденного изображения: " . $e->getMessage());
            }
            
            $imageSrc = null;
        }
    }

} catch (\PDOException $e) {
    Session::set('error', 'Ошибка базы данных: ' . $e->getMessage());
    header("Location: logout.php");
    exit();
}

// Подключаем шаблон header
require 'common/header.php';
?>

<main>
    <div class="profile-container">
        <header class="profile-header">
            <h1>Профиль пользователя</h1>
        </header>

        <section class="upload-section">
            <h2>Загрузка фото профиля</h2>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="file" name="profile_picture" required accept="image/jpeg,image/png,image/gif">
                <button type="submit">Загрузить</button>
            </form>
            <div class="requirements">
                <h3>Требования к файлу:</h3>
                <ul>
                    <li>Максимальный размер: 10 МБ</li>
                    <li>Допустимые форматы: JPG, PNG, GIF</li>
                    <li>Файл не должен быть поврежден</li>
                </ul>
            </div>
        </section>

        <section class="profile-image-section">
            <h2>Ваше фото профиля:</h2>
            <div class="profile-image-wrapper">
                <?php if ($imageSrc): ?>
                    <img src="<?= htmlspecialchars($imageSrc) ?>" alt="Фото профиля" class="profile-image" onerror="this.parentElement.innerHTML='<div class=\'error-placeholder\'>Ошибка загрузки изображения</div>'">
                <?php else: ?>
                    <div class="error-placeholder">Фото не загружено</div>
                <?php endif; ?>
            </div>
            <?php if (isset($_SESSION['warning'])): ?>
                <div class="warning">
                    <?= htmlspecialchars($_SESSION['warning']) ?>
                </div>
                <?php unset($_SESSION['warning']); ?>
            <?php endif; ?>
        </section>
    </div>
</main>
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
form{
    background: #5FD1A5 !important;
    border:0 !important;
    box-shadow: none !important;
}

</style>
<style>
/* Общие стили для всей страницы */
body {
    font-family: 'Roboto', sans-serif;
    background-color: #f9f9f9; /* Светлый фон для всей страницы */
    margin: 0;
    padding: 0;
}

/* Контейнер профиля */
.profile-container {
    max-width: 800px;
    margin: 50px auto;
    background: #5FD1A5 !important;
    padding: 40px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    border-radius: 10px;
}
.profile-header{
    background: none;
}

/* Заголовки */
.profile-header h1,
.upload-section h2,
.profile-image-section h2 {
    text-align: center;
    color: #333;
    font-family: 'Roboto', sans-serif;
}

.requirements h3 {
    color: #444;
    font-family: 'Roboto', sans-serif;
}

.requirements ul {
    list-style-type: disc;
    padding-left: 20px;
    color: #666;
}

/* Форма загрузки */
.upload-section {
    margin-top: 30px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.upload-section form {
    width: 100%;
    max-width: 400px;
}

.upload-section input[type="file"] {
    display: block;
    margin: 10px 0;
    padding: 10px;
    width: 100%;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 16px;
}

/* Обновленные стили для кнопок */
.upload-section button {
    display: block;
    width: 100%;
    padding: 15px;
    background-color: #3498db; /* Новый цвет кнопки */
    color: white;
    border: none;
    border-radius: 25px; /* Измененная форма кнопки */
    cursor: pointer;
    font-size: 16px;
    transition: background-color 0.3s;
    margin-top: 10px;
}

.upload-section button:hover {
    background-color: #2980b9; /* Цвет кнопки при наведении */
}

/* Контейнер для фото профиля */
.profile-image-section {
    text-align: center;
    margin-top: 40px;
}

.profile-image-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 300px;
    background-color: #f1f1f1;
    border-radius: 10px;
    margin-top: 20px;
    position: relative;
}

.profile-image-wrapper img {
    max-width: 100%;
    max-height: 100%;
    border-radius: 10px;
}

.error-placeholder {
    color: red;
    font-size: 14px;
    position: absolute;
}

.warning {
    color: orange;
    font-size: 14px;
    margin-top: 10px;
}

</style>

<?php require 'common/footer.php'; ?>
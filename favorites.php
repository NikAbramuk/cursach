<?php
session_start();

$dsn = 'mysql:host=localhost;dbname=php_laba5;charset=utf8';
$username = 'root';
$password = '';

require 'common/header.php';

$isLandlord = $_SESSION['role'] === 'landlord';
$isClient = $_SESSION['role'] === 'client';
$isAdmin = $_SESSION['role'] === 'admin';

try {
    $pdo = new PDO($dsn, $username, $password);
} catch (PDOException $e) {
    $_SESSION['db_error'] = 'Ошибка подключения к базе данных: ' . $e->getMessage();
}

if (!$pdo) {
    $_SESSION['db_error'] = 'База данных недоступна. Пожалуйста, попробуйте позже.'  . $e->getMessage();
    unset($_SESSION['user_id']);
    unset($_SESSION['username']);
    unset($_SESSION['role']);
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); 
    exit();
}

$userID = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT apartments.id, apartments.name, apartments.price, apartments.description, apartments.location, apartments.rooms, apartments.area
    FROM favorites 
    JOIN apartments ON favorites.apartmentID = apartments.id 
    WHERE favorites.userID = ?
");
$stmt->execute([$userID]);
$favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

$applications = [];
$stmt = $pdo->prepare("SELECT apartmentID FROM applications WHERE userID = ?");
$stmt->execute([$userID]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $applications[] = $row['apartmentID'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['remove'])) {
        $apartmentID = $_POST['remove'];
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE userID = ? AND apartmentID = ?");
        $stmt->execute([$userID, $apartmentID]);
        $_SESSION['success'] = "Квартира удалена из избранного.";
        header('Location: favorites.php'); 
        exit();
    }

    if (isset($_POST['request'])) {
        $apartmentID = $_POST['request'];
        $stmt = $pdo->prepare("INSERT INTO applications (userID, apartmentID) VALUES (?, ?)");
        $stmt->execute([$userID, $apartmentID]);
        $_SESSION['success'] = "Заявка успешно отправлена.";
        header('Location: favorites.php');
        exit();
    }
}
?>

<main>
    <?php if (empty($favorites)): ?>
        <p>Ваши избранные квартиры пусты.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Цена</th>
                    <th>Описание</th>
                    <th>Местоположение</th>
                    <th>Комнаты</th>
                    <th>Площадь</th>
                    <th>Изображения</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($favorites as $favorite): ?>
                    <tr>
                        <td><?= htmlspecialchars($favorite['id']) ?></td>
                        <td><?= htmlspecialchars($favorite['name']) ?></td>
                        <td><?= htmlspecialchars($favorite['price']) ?></td>
                        <td><?= htmlspecialchars($favorite['description']) ?></td>
                        <td><?= htmlspecialchars($favorite['location']) ?></td>
                        <td><?= htmlspecialchars($favorite['rooms']) ?></td>
                        <td><?= htmlspecialchars($favorite['area']) ?></td>
                        <td class="apartment-images">
                            <?php
                            $dir = 'files/' . $favorite['id'] . '/';
                            if (is_dir($dir)) {
                                $images = glob($dir . "*.{jpg,jpeg,png,gif}", GLOB_BRACE);
                                if (empty($images)): ?>
                                    <p class="no-images">Нет изображений</p>
                                <?php else:
                                    foreach ($images as $image): 
                                        try {
                                            if (!is_readable($image)) {
                                                continue;
                                            }
                                            ?>
                                            <div class="image-container">
                                                <img src="<?= htmlspecialchars($image) ?>" 
                                                     alt="Фото квартиры" 
                                                     class="apartment-image"
                                                     onerror="this.style.display='none'">
                                            </div>
                                        <?php
                                        } catch (\Exception $e) {
                                            error_log("Ошибка при загрузке изображения: " . $e->getMessage());
                                        }
                                    endforeach;
                                endif;
                            } else { ?>
                                <p class="no-images">Нет изображений</p>
                            <?php } ?>
                        </td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="remove" value="<?= $favorite['id'] ?>">
                                <button type="submit">Удалить из избранного</button>
                            </form>
                            <?php if (!in_array($favorite['id'], $applications)): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="request" value="<?= $favorite['id'] ?>">
                                    <button type="submit">Оставить заявку</button>
                                </form>
                            <?php else: ?>
                                <span>Заявка отправлена</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</main>
    
<style>
    table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th, td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

th {
    background-color: #f2f2f2;
}

button {
    margin-left: 5px;
    cursor: pointer;
}

.apartment-images {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    padding: 5px;
}

.image-container {
    width: 80px;
    height: 80px;
    overflow: hidden;
    border-radius: 4px;
    border: 1px solid #ddd;
}

.apartment-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.2s;
}

.image-container:hover .apartment-image {
    transform: scale(1.1);
}

.no-images {
    color: #666;
    font-style: italic;
    font-size: 0.9em;
    margin: 0;
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
<?php require 'common/footer.php'; ?>
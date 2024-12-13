<?php
session_start();
require 'func/db.php';

$dsn = 'mysql:host=localhost;dbname=php_laba5;charset=utf8';
$username = 'root';
$password = '';

try {
    $pdo = new PDO($dsn, $username, $password);
} catch (PDOException $e) {
    $_SESSION['db_error'] = 'Ошибка подключения к базе данных: ' . htmlspecialchars($e->getMessage());
    header("Location: logout.php");
    exit();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: logout.php");
    exit();
}

try {
    $stmt = $pdo->query("SELECT * FROM smart_search WHERE id = 1");
    $smartSearch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$smartSearch) {
        // Создаем новую запись с коэффициентами по умолчанию
        $pdo->exec("INSERT INTO smart_search (is_active, favorite_coefficient, application_coefficient, price_coefficient) VALUES (0, 0, 0, 0)");
        $smartSearch = [
            'is_active' => 0,
            'favorite_coefficient' => 0,
            'application_coefficient' => 0,
            'price_coefficient' => 0 // Изменено с city_coefficient на price_coefficient
        ];
    }
} catch (PDOException $e) {
    $_SESSION['db_error'] = 'Ошибка при получении состояния умного поиска: ' . htmlspecialchars($e->getMessage());
    header("Location: logout.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   // Обработка включения/выключения умного поиска
   try {
    if (isset($_POST['activate'])) {
        $newState = 1;
        $pdo->prepare("UPDATE smart_search SET is_active = ? WHERE id = 1")->execute([$newState]);
        $_SESSION['success'] = "Умный поиск включен.";
        header("Location: admin_board.php");
        exit();
    } elseif (isset($_POST['deactivate'])) {
        $newState = 0;
        $pdo->prepare("UPDATE smart_search SET is_active = ? WHERE id = 1")->execute([$newState]);
        $_SESSION['success'] = "Умный поиск выключен.";
        header("Location: admin_board.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['db_error'] = 'Ошибка при обновлении состояния умного поиска: ' . htmlspecialchars($e->getMessage());
    header("Location: logout.php");
    exit();
}

// Обработка увеличения/уменьшения коэффициента цены
try {
    if (isset($_POST['increment_price_coefficient']) || isset($_POST['decrement_price_coefficient'])) {
        $currentValue = $smartSearch['price_coefficient'];
        if (isset($_POST['increment_price_coefficient']) && $currentValue < 10) {
            $currentValue++;
            $pdo->prepare("UPDATE smart_search SET price_coefficient = ? WHERE id = 1")->execute([$currentValue]);
            $_SESSION['success'] = "Коэффициент цены увеличен.";
        } elseif (isset($_POST['decrement_price_coefficient']) && $currentValue > 0) {
            $currentValue--;
            $pdo->prepare("UPDATE smart_search SET price_coefficient = ? WHERE id = 1")->execute([$currentValue]);
            $_SESSION['success'] = "Коэффициент цены уменьшен.";
        }
        $smartSearch['price_coefficient'] = $currentValue;
    }
} catch (PDOException $e) {
    $_SESSION['db_error'] = 'Ошибка при обновлении коэффициента цены: ' . htmlspecialchars($e->getMessage());
    header("Location: logout.php");
    exit();
}

try {
    if (isset($_POST['increment_area_coefficient']) || isset($_POST['decrement_area_coefficient'])) {
        $currentValue = $smartSearch['area_coefficient'];
        if (isset($_POST['increment_area_coefficient']) && $currentValue < 10) {
            $currentValue++;
            $pdo->prepare("UPDATE smart_search SET area_coefficient = ? WHERE id = 1")->execute([$currentValue]);
            $_SESSION['success'] = "Коэффициент цены увеличен.";
        } elseif (isset($_POST['decrement_area_coefficient']) && $currentValue > 0) {
            $currentValue--;
            $pdo->prepare("UPDATE smart_search SET area_coefficient = ? WHERE id = 1")->execute([$currentValue]);
            $_SESSION['success'] = "Коэффициент цены уменьшен.";
        }
        $smartSearch['area_coefficient'] = $currentValue;
    }
} catch (PDOException $e) {
    $_SESSION['db_error'] = 'Ошибка при обновлении коэффициента цены: ' . htmlspecialchars($e->getMessage());
    header("Location: logout.php");
    exit();
}

// Обработка увеличения/уменьшения коэффициента избранного
try {
    if (isset($_POST['increment_favorite_coefficient']) || isset($_POST['decrement_favorite_coefficient'])) {
        $currentValue = $smartSearch['favorite_coefficient'];
        if (isset($_POST['increment_favorite_coefficient']) && $currentValue < 10) {
            $currentValue++;
            $pdo->prepare("UPDATE smart_search SET favorite_coefficient = ? WHERE id = 1")->execute([$currentValue]);
            $_SESSION['success'] = "Коэффициент избранного увеличен.";
        } elseif (isset($_POST['decrement_favorite_coefficient']) && $currentValue > 0) {
            $currentValue--;
            $pdo->prepare("UPDATE smart_search SET favorite_coefficient = ? WHERE id = 1")->execute([$currentValue]);
            $_SESSION['success'] = "Коэффициент избранного уменьшен.";
        }
        $smartSearch['favorite_coefficient'] = $currentValue;
    }
} catch (PDOException $e) {
    $_SESSION['db_error'] = 'Ошибка при обновлении коэффициента избранного: ' . htmlspecialchars($e->getMessage());
    header("Location: logout.php");
    exit();
}

// Обработка увеличения/уменьшения коэффициента заявок
try {
    if (isset($_POST['increment_application_coefficient']) || isset($_POST['decrement_application_coefficient'])) {
        $currentValue = $smartSearch['application_coefficient'];
        if (isset($_POST['increment_application_coefficient']) && $currentValue < 10) {
            $currentValue++;
            $pdo->prepare("UPDATE smart_search SET application_coefficient = ? WHERE id = 1")->execute([$currentValue]);
            $_SESSION['success'] = "Коэффициент заявок увеличен.";
        } elseif (isset($_POST['decrement_application_coefficient']) && $currentValue > 0) {
            $currentValue--;
            $pdo->prepare("UPDATE smart_search SET application_coefficient = ? WHERE id = 1")->execute([$currentValue]);
            $_SESSION['success'] = "Коэффициент заявок уменьшен.";
        }
        $smartSearch['application_coefficient'] = $currentValue;
    }
} catch (PDOException $e) {
    $_SESSION['db_error'] = 'Ошибка при обновлении коэффициента заявок: ' . htmlspecialchars($e->getMessage());
    header("Location: logout.php");
    exit();
}
}
?>

<?php require 'common/header.php'; ?>

<main class="admin-container"  style="padding: 20px;">
    <h2>Настройки умного поиска</h2>

    <?php if (isset($_SESSION['db_error'])): ?>
        <div class="notification error">
            <?= htmlspecialchars($_SESSION['db_error']) ?>
        </div>
        <?php unset($_SESSION['db_error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="notification success">
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <p >Умный поиск в данный момент <?= $smartSearch['is_active'] ? "включен" : "выключен" ?>.</p>

    <form method="post">
        <?php if ($smartSearch['is_active']): ?>
            <button type="submit" name="deactivate" value="0">Выключить умный поиск</button>
        <?php else: ?>
            <button type="submit" name="activate" value="1">Включить умный поиск</button>
        <?php endif; ?>
    </form>

    <h3>Коэффициенты</h3>
    <form method="post" class="admin-container">
        <div>
            <label>Коэффициент избранного:</label>
            <input type="text" name="favorite_coefficient" value="<?= $smartSearch['favorite_coefficient'] ?>" readonly>
            <button type="submit" name="increment_favorite_coefficient" value="1" formaction="" class="change-btn">+</button>
            <button type="submit" name="decrement_favorite_coefficient" value="1" formaction="" class="change-btn">-</button>
        </div>
        <div>
            <label>Коэффициент заявок:</label>
            <input type="text" name="application_coefficient" value="<?= $smartSearch['application_coefficient'] ?>" readonly>
            <button type="submit" name="increment_application_coefficient" value="1" formaction="" class="change-btn">+</button>
            <button type="submit" name="decrement_application_coefficient" value="1" formaction="" class="change-btn">-</button>
        </div>
        <div>
            <label>Коэффициент цены:</label>
            <input type="text" name="price_coefficient" value="<?= $smartSearch['price_coefficient'] ?>" readonly>
            <button type="submit" name="increment_price_coefficient" value="1" formaction="" class="change-btn">+</button>
            <button type="submit" name="decrement_price_coefficient" value="1" formaction="" class="change-btn">-</button>
        </div>
        <div>
            <label>Коэффициент площади:</label>
            <input type="text" name="area_coefficient" value="<?= $smartSearch['area_coefficient'] ?>" readonly>
            <button type="submit" name="increment_area_coefficient" value="1" formaction="" class="change-btn">+</button>
            <button type="submit" name="decrement_area_coefficient" value="1" formaction="" class="change-btn">-</button>
        </div>
    </form>
</main>
<style>

.admin-container{
    background-color: #f9f9f9;
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

form{
    background: #5FD1A5 !important;
    border:0 !important;
    box-shadow: none !important;
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
.notification {
    padding: 10px;
    margin-bottom: 20px;
}
.error {
    background-color: red;
    color: white;
}
.success {
    background-color: green;
    color: white;
}
.change-btn {
    margin: 0 5px;
}
</style>

<?php require 'common/footer.php'; ?>
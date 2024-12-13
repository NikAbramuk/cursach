<?php
session_start();
echo '<link rel="stylesheet" href="styles/login.css">';
require 'src/db/pdo.php';
require 'src/users/users.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        $pdo = getPDO();
        $user = checkUser($username, $password, $pdo);

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: apartments.php");
            exit();
        } else {
            $_SESSION['error'] = "Неверный логин или пароль.";
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'База данных недоступна. Пожалуйста, попробуйте позже.' . $e->getMessage();
    }
}
?>

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

<?php require 'common/header.php'; ?>

<main class="login-container">
    <h2>Вход</h2>

    <?php if (isset($error) && $error): ?>
        <div class="notification error">
            <span>
                <?= htmlspecialchars($error) ?>
            </span>
        </div>
        <?php unset($error); ?>
    <?php endif; ?>

    <form method="post" class="login-form">
        <label for="username" class="login-label">Логин:</label>
        <input type="text" name="username" class="login-input" required>
        
        <label for="password" class="login-label">Пароль:</label>
        <input type="password" name="password" class="login-input" required>
        
        <button type="submit" name="login" class="login-button">Войти</button>
    </form>
</main>

<?php require 'common/footer.php'; ?>

<?php
echo '<link rel="stylesheet" href="styles/register.css">';
session_start();
require 'common/header.php';
require 'src/db/pdo.php';
require 'src/users/users.php';

$error = "";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDO(); 
        list($errors, $userData) = validateUserData($_POST); 
        
        echo "<script>console.log(" . $postData['name'] . ");</script>";

        // Запись в файл на рабочий стол
        $desktopPath = '/home/kemal/Desktop/log.txt';
        file_put_contents($desktopPath, $postData['name'] . PHP_EOL, FILE_APPEND);

        if (!empty($errors)) {
            $error = implode(' ', $errors);
            $_SESSION['error'] = $error;
            header("Location: register.php");
        } elseif (isUserExists($pdo, $userData['username'])) {
            $error = "Пользователь с таким логином уже существует.";
            $_SESSION['error'] = $error;
            header("Location: register.php");
        } else {
            $userId = createUser(
                $pdo, $userData['name'], 
                $userData['username'], 
                $userData['password'],
                $userData['age'],
                $userData['gender'], 
                $userData['role']
            );  
            
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $userData['username'];
            $_SESSION['role'] = $userData['role'];

            header("Location: apartments.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'База данных недоступна. Пожалуйста, попробуйте позже.' . $e->getMessage();
        header("Location: register.php");
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

<main class="registration-container">
    <?php if (isset($error)): ?>
        <p class="error-message"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if (isset($error) && $error): ?>
        <div class="notification error">
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php unset($error); ?>
    <?php endif; ?>
    
    <form method="post" class="registration-form">
        <h2 class="registration-title">Регистрация</h2>
        
        <label for="name" class="registration-label">Имя:</label>
        <input type="text" name="name" class="registration-input" required>
        
        <label for="username" class="registration-label">Логин:</label>
        <input type="text" name="username" class="registration-input" required>
        
        <label for="password" class="registration-label">Пароль:</label>
        <input type="password" name="password" class="registration-input" required>
        
        <label for="age" class="registration-label">Возраст:</label>
        <input type="number" name="age" class="registration-input" required>
        
        <label for="gender" class="registration-label">Пол:</label>
        <select name="gender" class="registration-select" required>
            <option value="male">Мужской</option>
            <option value="female">Женский</option>
            <option value="other">Другой</option>
        </select>
        
        <label for="role" class="registration-label">Роль:</label>
        <select name="role" class="registration-select" required>
            <option value="landlord">Арендодатель</option>
            <option value="client">Клиент</option>
            <option value="admin">Администратор</option>
        </select>
        
        <button type="submit" class="registration-button">Зарегистрироваться</button>
    </form>
</main>

<?php
require 'common/footer.php';
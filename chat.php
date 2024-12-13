<?php
// chat.php
session_start();

$dsn = 'mysql:host=localhost;dbname=php_laba5;charset=utf8';
$username = 'root';
$password = '';

$isLandlord = $_SESSION['role'] === 'landlord';
$isClient = $_SESSION['role'] === 'client';
$isAdmin = $_SESSION['role'] === 'admin';

$userID = $_SESSION['user_id'];
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

if (!isset($_SESSION['user_id'] )) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['applicationID'])) {
    $_SESSION['db_error'] = 'Ошибка: ID заявки не указан.';
}
$applicationID = $_GET['applicationID'];


try {
    $stmt = $pdo->prepare("
        SELECT applications.*, 
               (SELECT name FROM users WHERE id = applications.userID) AS clientName,
               (SELECT name FROM users WHERE id = apartments.landlordID) AS landlordName
        FROM applications 
        JOIN apartments ON applications.apartmentID = apartments.id
        WHERE applications.id = ?
    ");
    $stmt->execute([$applicationID]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    // if (!$application) {
    //     $_SESSION['db_error'] = 'Ошибка: Заявка не найдена.' . $e->getMessage();
    // }
} catch (PDOException $e) {
    $_SESSION['db_error'] = 'Ошибка базы данных: ' . $e->getMessage();
}

try {
    $stmt = $pdo->prepare("
        SELECT comments.*, users.name 
        FROM comments 
        JOIN users ON comments.userID = users.id 
        WHERE applicationID = ?
    ");
    $stmt->execute([$applicationID]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['db_error'] = 'Ошибка базы данных: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $content = trim($_POST['comment']);
    $userID = $_SESSION['user_id']; 
    
    if (empty($content)) {
        $_SESSION['db_error'] = "Комментарий не может быть пустым.";
    } elseif (strlen($content) > 100) {
        $_SESSION['db_error'] = "Комментарий не должен превышать 100 символов.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO comments (applicationID, userID, content) VALUES (?, ?, ?)");
            $stmt->execute([$applicationID, $userID, $content]);
            header("Location: chat.php?applicationID=" . $applicationID);
            exit();
        } catch (PDOException $e) {
            $_SESSION['db_error'] = "Ошибка при отправке комментария: " . htmlspecialchars($e->getMessage());
        }
    }
}

?>

<?php require 'common/header.php'; ?>

<main style="margin: 20px;">
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
    <h1>Чат между <?php echo htmlspecialchars($application['clientName']); ?> и <?php echo htmlspecialchars($application['landlordName']); ?></h1>

<?php if (isset($error)): ?>
    <p><?php echo $error; ?></p>
<?php endif; ?>

<div class="comments">
    <?php foreach ($comments as $comment): ?>
        <div class="comment <?php echo ($comment['userID'] == $_SESSION['user_id']) ? 'my-comment' : 'other-comment'; ?>">
            <strong><?php echo htmlspecialchars($comment['name']); ?>:</strong>
            <p><?php echo htmlspecialchars($comment['content']); ?></p>
            <small><?php echo $comment['created_at']; ?></small>
        </div>
    <?php endforeach; ?>
</div>

<form method="post">
    <textarea name="comment" maxlength="100" required></textarea>
    <button type="submit">Отправить</button>
</form>
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

</style>
<style>
        .comments {
            max-width: 600px;
            margin: 20px auto;
            padding: 10px;
            border-radius: 5px;
            overflow-y: auto;
            height: 400px; /* Высота области для комментариев */
        }
        .comment {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px; 
            word-wrap: break-word;
            overflow-wrap: break-word; 
            max-width: 100%; 
        }
        .my-comment {
            background-color: #f0f0f0; /* Легкий серый фон */
            align-self: flex-end;
        }
        .other-comment {
            background-color: #d0e0ff; /* Светлый синий фон */
            align-self: flex-start;
        }
        textarea {
            width: 100%;
            height: 50px;
        }
    </style>
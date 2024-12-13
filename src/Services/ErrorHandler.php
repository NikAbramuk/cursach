<?php
namespace Services;

class ErrorHandler {
    public static function handleException(\Throwable $exception) {
        $_SESSION['error'] = $exception->getMessage();
        error_log($exception->getMessage());
        
        if (headers_sent()) {
            echo "Произошла ошибка. Пожалуйста, попробуйте позже.";
        } else {
            header('Location: /error.php');
        }
        exit();
    }
}

// Регистрация обработчика
set_exception_handler([ErrorHandler::class, 'handleException']); 
-- Создание базы данных
CREATE DATABASE IF NOT EXISTS php_laba5 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE php_laba5;

-- Создание таблицы пользователей
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(256) NOT NULL,
    username VARCHAR(256) UNIQUE NOT NULL,
    password VARCHAR(256) NOT NULL,
    age INT NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    role ENUM('admin', 'client', 'landlord') NOT NULL
);

-- Создание таблицы квартир
CREATE TABLE IF NOT EXISTS apartments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(256) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(256) NOT NULL,
    rooms INT NOT NULL,
    area DECIMAL(10, 2) NOT NULL,
    available BOOLEAN DEFAULT TRUE,
    landlordID INT,
    FOREIGN KEY (landlordID) REFERENCES users(id) ON DELETE CASCADE
);

-- Создание таблицы избранного
CREATE TABLE IF NOT EXISTS favorites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    userID INT NOT NULL,
    apartmentID INT NOT NULL,
    FOREIGN KEY (userID) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (apartmentID) REFERENCES apartments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (userID, apartmentID)
);

-- Создание таблицы заявок
CREATE TABLE IF NOT EXISTS applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    userID INT NOT NULL,
    apartmentID INT NOT NULL,
    status ENUM('in progress', 'accepted', 'rejected') DEFAULT 'in progress',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userID) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (apartmentID) REFERENCES apartments(id) ON DELETE CASCADE
);

-- Создание таблицы комментариев
CREATE TABLE IF NOT EXISTS comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    applicationID INT NOT NULL,
    userID INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applicationID) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (userID) REFERENCES users(id) ON DELETE CASCADE
);

-- Создание таблицы фотографий профиля
CREATE TABLE IF NOT EXISTS profile_pictures (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    image LONGBLOB NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Создание таблицы умного поиска
CREATE TABLE IF NOT EXISTS smart_search (
    id INT PRIMARY KEY AUTO_INCREMENT,
    is_active BOOLEAN DEFAULT FALSE,
    favorite_coefficient INT DEFAULT 0,
    application_coefficient INT DEFAULT 0,
    price_coefficient INT DEFAULT 0,
    area_coefficient INT DEFAULT 0
);

-- Вставка начальных данных для умного поиска
INSERT INTO smart_search (id, is_active, favorite_coefficient, application_coefficient, price_coefficient, area_coefficient) 
VALUES (1, FALSE, 0, 0, 0, 0);
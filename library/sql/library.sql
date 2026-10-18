CREATE DATABASE IF NOT EXISTS library
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE library;

CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(150) NOT NULL,
  role          ENUM('reader','admin') NOT NULL DEFAULT 'reader',
  card_number   VARCHAR(20) UNIQUE,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE authors (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE genres (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE books (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  title            VARCHAR(255) NOT NULL,
  author_id        INT NOT NULL,
  genre_id         INT NOT NULL,
  isbn             VARCHAR(20),
  description      TEXT,
  total_copies     INT NOT NULL DEFAULT 1,
  available_copies INT NOT NULL DEFAULT 1,
  CONSTRAINT fk_book_author FOREIGN KEY (author_id) REFERENCES authors(id),
  CONSTRAINT fk_book_genre  FOREIGN KEY (genre_id)  REFERENCES genres(id)
) ENGINE=InnoDB;

CREATE TABLE requests (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  book_id      INT NOT NULL,
  status       ENUM('pending','issued','rejected') NOT NULL DEFAULT 'pending',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  CONSTRAINT fk_req_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_req_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE loans (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  book_id         INT NOT NULL,
  request_id      INT NULL,
  issued_at       DATE NOT NULL,
  due_at          DATE NOT NULL,
  returned_at     DATE NULL,
  extension_days  INT NOT NULL DEFAULT 0,
  status          ENUM('active','returned') NOT NULL DEFAULT 'active',
  CONSTRAINT fk_loan_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_loan_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  INDEX idx_loans_active (status, due_at)
) ENGINE=InnoDB;
CREATE DATABASE IF NOT EXISTS quadbyte_lms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE quadbyte_lms;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS remember_tokens;
DROP TABLE IF EXISTS user_preferences;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS fines;
DROP TABLE IF EXISTS fine_rules;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS loan_transactions;
DROP TABLE IF EXISTS loan_requests;
DROP TABLE IF EXISTS book_authors;
DROP TABLE IF EXISTS book_copies;
DROP TABLE IF EXISTS books;
DROP TABLE IF EXISTS authors;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS publishers;
DROP TABLE IF EXISTS librarians;
DROP TABLE IF EXISTS members;
DROP TABLE IF EXISTS member_types;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  slug VARCHAR(50) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE member_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  slug VARCHAR(50) NOT NULL UNIQUE,
  loan_limit INT NOT NULL DEFAULT 5,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  phone VARCHAR(40) NULL,
  date_of_birth DATE NULL,
  avatar_url MEDIUMTEXT NULL,
  status ENUM('active','inactive','pending','rejected','suspended','deactivated') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  last_activity_at DATETIME NULL,
  approved_at DATETIME NULL,
  approved_by INT NULL,
  rejected_at DATETIME NULL,
  rejected_by INT NULL,
  suspended_at DATETIME NULL,
  suspended_by INT NULL,
  created_by INT NULL,
  updated_by INT NULL,
  deleted_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
  CONSTRAINT fk_users_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_users_rejected_by FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_users_suspended_by FOREIGN KEY (suspended_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_users_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_users_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_users_status (status),
  INDEX idx_users_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_preferences (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  transaction_alerts TINYINT(1) NOT NULL DEFAULT 1,
  due_reminders TINYINT(1) NOT NULL DEFAULT 1,
  overdue_alerts TINYINT(1) NOT NULL DEFAULT 1,
  fines_payment_notices TINYINT(1) NOT NULL DEFAULT 1,
  email_notifications TINYINT(1) NOT NULL DEFAULT 1,
  book_reminders TINYINT(1) NOT NULL DEFAULT 1,
  due_date_alerts TINYINT(1) NOT NULL DEFAULT 1,
  new_arrivals TINYINT(1) NOT NULL DEFAULT 0,
  recommendations TINYINT(1) NOT NULL DEFAULT 1,
  marketing_emails TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_preferences_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  member_type_id INT NOT NULL,
  member_number VARCHAR(50) NOT NULL UNIQUE,
  status ENUM('active','inactive','pending','rejected','suspended','deactivated') NOT NULL DEFAULT 'active',
  joined_at DATE NOT NULL,
  created_by INT NULL,
  updated_by INT NULL,
  deleted_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_members_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_members_type FOREIGN KEY (member_type_id) REFERENCES member_types(id),
  CONSTRAINT fk_members_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_members_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_members_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE librarians (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  employee_number VARCHAR(50) NULL UNIQUE,
  created_by INT NULL,
  updated_by INT NULL,
  deleted_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_librarians_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_librarians_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_librarians_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_librarians_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  created_by INT NULL,
  updated_by INT NULL,
  deleted_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_categories_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_categories_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_categories_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE publishers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  created_by INT NULL,
  updated_by INT NULL,
  deleted_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_publishers_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_publishers_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_publishers_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE authors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  created_by INT NULL,
  updated_by INT NULL,
  deleted_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_authors_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_authors_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_authors_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE books (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NULL,
  publisher_id INT NULL,
  title VARCHAR(255) NOT NULL,
  isbn VARCHAR(40) NULL,
  publication_year INT NULL,
  description TEXT NULL,
  rack_number VARCHAR(50) NULL,
  cover_url MEDIUMTEXT NULL,
  total_copies INT NOT NULL DEFAULT 1,
  late_fee_per_day DECIMAL(10,2) NOT NULL DEFAULT 20.00,
  price DECIMAL(10,2) NULL,
  replacement_value DECIMAL(10,2) NULL,
  created_by INT NULL,
  updated_by INT NULL,
  deleted_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_books_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_books_publisher FOREIGN KEY (publisher_id) REFERENCES publishers(id) ON DELETE SET NULL,
  CONSTRAINT fk_books_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_books_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_books_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_books_title (title),
  INDEX idx_books_isbn (isbn),
  INDEX idx_books_year (publication_year),
  INDEX idx_books_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE book_authors (
  book_id INT NOT NULL,
  author_id INT NOT NULL,
  PRIMARY KEY (book_id, author_id),
  CONSTRAINT fk_book_authors_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  CONSTRAINT fk_book_authors_author FOREIGN KEY (author_id) REFERENCES authors(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE book_copies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NOT NULL,
  copy_number VARCHAR(80) NOT NULL UNIQUE,
  barcode VARCHAR(80) NOT NULL UNIQUE,
  status ENUM('available','borrowed','reserved','lost','damaged','maintenance') NOT NULL DEFAULT 'available',
  condition_note VARCHAR(255) NULL,
  acquired_at DATE NULL,
  created_by INT NULL,
  updated_by INT NULL,
  deleted_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_book_copies_book FOREIGN KEY (book_id) REFERENCES books(id),
  CONSTRAINT fk_book_copies_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_book_copies_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_book_copies_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_book_copies_status (status),
  INDEX idx_book_copies_book_status (book_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loan_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  book_id INT NOT NULL,
  requested_due_date DATE NOT NULL,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  rejection_reason VARCHAR(255) NULL,
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_loan_requests_member FOREIGN KEY (member_id) REFERENCES members(id),
  CONSTRAINT fk_loan_requests_book FOREIGN KEY (book_id) REFERENCES books(id),
  CONSTRAINT fk_loan_requests_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_loan_requests_status (status),
  INDEX idx_loan_requests_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loan_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_request_id INT NULL,
  member_id INT NOT NULL,
  book_id INT NOT NULL,
  copy_id INT NOT NULL,
  issued_by INT NULL,
  borrowed_at DATETIME NOT NULL,
  due_date DATE NOT NULL,
  returned_at DATETIME NULL,
  status ENUM('borrowed','returned','lost','damaged') NOT NULL DEFAULT 'borrowed',
  renew_count INT NOT NULL DEFAULT 0,
  return_condition ENUM('good','damaged','lost') NULL,
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_loan_transactions_request FOREIGN KEY (loan_request_id) REFERENCES loan_requests(id) ON DELETE SET NULL,
  CONSTRAINT fk_loan_transactions_member FOREIGN KEY (member_id) REFERENCES members(id),
  CONSTRAINT fk_loan_transactions_book FOREIGN KEY (book_id) REFERENCES books(id),
  CONSTRAINT fk_loan_transactions_copy FOREIGN KEY (copy_id) REFERENCES book_copies(id),
  CONSTRAINT fk_loan_transactions_issued_by FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_loan_transactions_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_loan_transactions_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_loan_transactions_status (status),
  INDEX idx_loan_transactions_status_due (status, due_date),
  INDEX idx_loan_transactions_due_date (due_date),
  INDEX idx_loan_transactions_member (member_id),
  INDEX idx_loan_transactions_book (book_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  book_id INT NOT NULL,
  status ENUM('pending','active','ready_for_pickup','completed','cancelled','expired') NOT NULL DEFAULT 'active',
  queue_position INT NOT NULL DEFAULT 1,
  ready_at DATETIME NULL,
  fulfilled_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  expired_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_reservations_member FOREIGN KEY (member_id) REFERENCES members(id),
  CONSTRAINT fk_reservations_book FOREIGN KEY (book_id) REFERENCES books(id),
  INDEX idx_reservations_book_status (book_id, status),
  INDEX idx_reservations_member_status (member_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fine_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NULL,
  name VARCHAR(120) NOT NULL,
  fine_type ENUM('overdue','lost','damaged','manual') NOT NULL,
  amount_per_day DECIMAL(10,2) NULL,
  grace_days INT NOT NULL DEFAULT 0,
  default_amount DECIMAL(10,2) NULL,
  use_book_price TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT NULL,
  updated_by INT NULL,
  deleted_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_fine_rules_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  CONSTRAINT fk_fine_rules_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_fine_rules_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_fine_rules_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_fine_rules_type_active (fine_type, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  loan_transaction_id INT NULL,
  book_id INT NULL,
  fine_rule_id INT NULL,
  fine_type ENUM('overdue','lost','damaged','manual') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  status ENUM('unpaid','paid','waived') NOT NULL DEFAULT 'unpaid',
  assessed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at DATETIME NULL,
  paid_by INT NULL,
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_fines_member FOREIGN KEY (member_id) REFERENCES members(id),
  CONSTRAINT fk_fines_loan FOREIGN KEY (loan_transaction_id) REFERENCES loan_transactions(id) ON DELETE SET NULL,
  CONSTRAINT fk_fines_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE SET NULL,
  CONSTRAINT fk_fines_rule FOREIGN KEY (fine_rule_id) REFERENCES fine_rules(id) ON DELETE SET NULL,
  CONSTRAINT fk_fines_paid_by FOREIGN KEY (paid_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_fines_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_fines_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_fines_status (status),
  INDEX idx_fines_member_status (member_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fine_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  status ENUM('pending','paid','failed') DEFAULT 'pending',
  provider VARCHAR(50) DEFAULT 'paymongo',
  local_reference VARCHAR(64) NOT NULL,
  reference_id VARCHAR(255),
  checkout_url TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_fine FOREIGN KEY (fine_id) REFERENCES fines(id),
  UNIQUE KEY uq_payments_local_reference (local_reference),
  INDEX idx_payments_fine_status (fine_id, status),
  INDEX idx_payments_reference (reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  target_role ENUM('admin','member','both') NULL,
  loan_transaction_id INT NULL,
  related_entity_type VARCHAR(40) NULL,
  related_entity_id INT NULL,
  action_type VARCHAR(60) NULL,
  title VARCHAR(160) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('info','success','warning','overdue') NOT NULL DEFAULT 'info',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME NULL,
  sent_email_at DATETIME NULL,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_loan FOREIGN KEY (loan_transaction_id) REFERENCES loan_transactions(id) ON DELETE SET NULL,
  INDEX idx_notifications_user (user_id),
  INDEX idx_notifications_target_role (target_role),
  INDEX idx_notifications_related (related_entity_type, related_entity_id),
  INDEX idx_notifications_overdue_daily (loan_transaction_id, type, created_at),
  INDEX idx_notifications_read (is_read),
  INDEX idx_notifications_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE remember_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  selector CHAR(18) NOT NULL UNIQUE,
  validator_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_remember_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_remember_tokens_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id INT NULL,
  metadata JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_logs_entity (entity_type, entity_id),
  INDEX idx_audit_logs_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, slug) VALUES
  ('Admin', 'admin'),
  ('Member', 'member');

INSERT INTO member_types (name, slug, loan_limit) VALUES
  ('Student', 'student', 5),
  ('Staff', 'staff', 5);

INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, avatar_url, status)
VALUES
  ((SELECT id FROM roles WHERE slug = 'admin'), 'admin', 'admin@schodex.ph', '$2y$12$GDfFEMqX/83ANyt4cWcqjOS8R51sUR8JzZu2X1aGRXi0LxHLp5BSe', 'Dr.', 'Maria Santos', 'https://api.dicebear.com/7.x/personas/svg?seed=maria', 'active'),
  ((SELECT id FROM roles WHERE slug = 'member'), 'student1', 'student1@student.ph', '$2y$12$AfXzbMaefVESqHocKt64VOD5sa7YlP5DRC/BxXN9dlCHJs9wqMtfS', 'Juan', 'dela Cruz', 'https://api.dicebear.com/7.x/personas/svg?seed=student1', 'active'),
  ((SELECT id FROM roles WHERE slug = 'member'), 'staff1', 'staff1@school.ph', '$2y$12$ZHIZSR9PBXmOApAu3wMAkeglimVCfoFanlrvQgGf1X2XDW/3ar4XO', 'Ana', 'Reyes', 'https://api.dicebear.com/7.x/personas/svg?seed=staff1', 'active');

INSERT INTO librarians (user_id, employee_number)
VALUES ((SELECT id FROM users WHERE username = 'admin'), 'LIB-00001');

INSERT INTO members (user_id, member_type_id, member_number, status, joined_at)
VALUES
  ((SELECT id FROM users WHERE username = 'student1'), (SELECT id FROM member_types WHERE slug = 'student'), 'M-00002', 'active', CURDATE()),
  ((SELECT id FROM users WHERE username = 'staff1'), (SELECT id FROM member_types WHERE slug = 'staff'), 'M-00003', 'active', CURDATE());

INSERT INTO categories (name, slug) VALUES
  ('Fiction', 'fiction'),
  ('Dystopian', 'dystopian'),
  ('History', 'history'),
  ('Adventure', 'adventure'),
  ('Science Fiction', 'science-fiction'),
  ('Self-Help', 'self-help');

INSERT INTO publishers (name, slug) VALUES
  ('Scribner', 'scribner'),
  ('HarperCollins', 'harpercollins'),
  ('Signet Classic', 'signet-classic'),
  ('Harper', 'harper'),
  ('HarperOne', 'harperone'),
  ('Ace Books', 'ace-books'),
  ('Avery', 'avery'),
  ('Del Rey', 'del-rey');

INSERT INTO authors (name, slug) VALUES
  ('F. Scott Fitzgerald', 'f-scott-fitzgerald'),
  ('Harper Lee', 'harper-lee'),
  ('George Orwell', 'george-orwell'),
  ('Yuval Noah Harari', 'yuval-noah-harari'),
  ('Paulo Coelho', 'paulo-coelho'),
  ('Frank Herbert', 'frank-herbert'),
  ('James Clear', 'james-clear'),
  ('Douglas Adams', 'douglas-adams');

INSERT INTO books (category_id, publisher_id, title, isbn, publication_year, description, rack_number, cover_url, total_copies, late_fee_per_day, price, replacement_value)
VALUES
  ((SELECT id FROM categories WHERE slug='fiction'), (SELECT id FROM publishers WHERE slug='scribner'), 'The Great Gatsby', '978-0743273565', 1925, 'A story of wealth, love, and the American Dream set in the Roaring Twenties.', 'A-01', 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=300&q=80', 3, 20.00, 300.00, 300.00),
  ((SELECT id FROM categories WHERE slug='fiction'), (SELECT id FROM publishers WHERE slug='harpercollins'), 'To Kill a Mockingbird', '978-0061935466', 1960, 'A profound novel about racial injustice and moral growth in the American South.', 'A-02', 'https://images.unsplash.com/photo-1512820790803-83ca734da794?w=300&q=80', 2, 20.00, 350.00, 350.00),
  ((SELECT id FROM categories WHERE slug='dystopian'), (SELECT id FROM publishers WHERE slug='signet-classic'), '1984', '978-0451524935', 1949, 'A dystopian novel set in a totalitarian society ruled by Big Brother.', 'B-05', 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?w=300&q=80', 4, 15.00, 280.00, 280.00),
  ((SELECT id FROM categories WHERE slug='history'), (SELECT id FROM publishers WHERE slug='harper'), 'Sapiens', '978-0062316110', 2011, 'A brief history of humankind from the Stone Age to the present.', 'C-08', 'https://images.unsplash.com/photo-1497633762265-9d179a990aa6?w=300&q=80', 2, 25.00, 450.00, 450.00),
  ((SELECT id FROM categories WHERE slug='adventure'), (SELECT id FROM publishers WHERE slug='harperone'), 'The Alchemist', '978-0062315007', 1988, 'A philosophical novel about a young shepherd pursuing his destiny.', 'A-09', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=300&q=80', 3, 20.00, 300.00, 300.00),
  ((SELECT id FROM categories WHERE slug='science-fiction'), (SELECT id FROM publishers WHERE slug='ace-books'), 'Dune', '978-0441013593', 1965, 'An epic science fiction novel set on the desert planet Arrakis.', 'D-02', 'https://images.unsplash.com/photo-1519682337058-a94d519337bc?w=300&q=80', 2, 20.00, 400.00, 400.00),
  ((SELECT id FROM categories WHERE slug='self-help'), (SELECT id FROM publishers WHERE slug='avery'), 'Atomic Habits', '978-0735211292', 2018, 'A practical guide to building good habits and breaking bad ones.', 'E-01', 'https://images.unsplash.com/photo-1524578271613-d550eacf6090?w=300&q=80', 3, 20.00, 380.00, 380.00),
  ((SELECT id FROM categories WHERE slug='science-fiction'), (SELECT id FROM publishers WHERE slug='del-rey'), 'The Hitchhiker''s Guide to the Galaxy', '978-0345391803', 1979, 'A comedic science fiction adventure across the universe.', 'D-05', 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?w=300&q=80', 2, 15.00, 290.00, 290.00);

INSERT INTO book_authors (book_id, author_id)
SELECT b.id, a.id FROM books b INNER JOIN authors a ON a.name = 'F. Scott Fitzgerald' WHERE b.title = 'The Great Gatsby';
INSERT INTO book_authors (book_id, author_id)
SELECT b.id, a.id FROM books b INNER JOIN authors a ON a.name = 'Harper Lee' WHERE b.title = 'To Kill a Mockingbird';
INSERT INTO book_authors (book_id, author_id)
SELECT b.id, a.id FROM books b INNER JOIN authors a ON a.name = 'George Orwell' WHERE b.title = '1984';
INSERT INTO book_authors (book_id, author_id)
SELECT b.id, a.id FROM books b INNER JOIN authors a ON a.name = 'Yuval Noah Harari' WHERE b.title = 'Sapiens';
INSERT INTO book_authors (book_id, author_id)
SELECT b.id, a.id FROM books b INNER JOIN authors a ON a.name = 'Paulo Coelho' WHERE b.title = 'The Alchemist';
INSERT INTO book_authors (book_id, author_id)
SELECT b.id, a.id FROM books b INNER JOIN authors a ON a.name = 'Frank Herbert' WHERE b.title = 'Dune';
INSERT INTO book_authors (book_id, author_id)
SELECT b.id, a.id FROM books b INNER JOIN authors a ON a.name = 'James Clear' WHERE b.title = 'Atomic Habits';
INSERT INTO book_authors (book_id, author_id)
SELECT b.id, a.id FROM books b INNER JOIN authors a ON a.name = 'Douglas Adams' WHERE b.title = 'The Hitchhiker''s Guide to the Galaxy';

INSERT INTO book_copies (book_id, copy_number, barcode, status)
SELECT id, CONCAT('B', LPAD(id,5,'0'), '-C001'), CONCAT('B', LPAD(id,5,'0'), '-C001'), 'available' FROM books;
INSERT INTO book_copies (book_id, copy_number, barcode, status)
SELECT id, CONCAT('B', LPAD(id,5,'0'), '-C002'), CONCAT('B', LPAD(id,5,'0'), '-C002'), 'available' FROM books;
INSERT INTO book_copies (book_id, copy_number, barcode, status)
SELECT id, CONCAT('B', LPAD(id,5,'0'), '-C003'), CONCAT('B', LPAD(id,5,'0'), '-C003'), 'available' FROM books WHERE total_copies >= 3;
INSERT INTO book_copies (book_id, copy_number, barcode, status)
SELECT id, CONCAT('B', LPAD(id,5,'0'), '-C004'), CONCAT('B', LPAD(id,5,'0'), '-C004'), 'available' FROM books WHERE total_copies >= 4;

INSERT INTO fine_rules (name, fine_type, amount_per_day, grace_days, default_amount, use_book_price, is_active)
VALUES
  ('Default overdue fine', 'overdue', 20.00, 1, NULL, 0, 1),
  ('Lost book replacement', 'lost', NULL, 0, 500.00, 1, 1),
  ('Damaged book replacement', 'damaged', NULL, 0, 500.00, 1, 1);

INSERT INTO notifications (user_id, target_role, title, message, type)
VALUES
  (NULL, 'admin', 'Welcome to SchoDex', 'Database seed data is ready. You can add books, issue loans, and approve requests.', 'info'),
  ((SELECT id FROM users WHERE username = 'student1'), NULL, 'Welcome to SchoDex', 'Browse the catalog and submit a borrow request when you find a book you need.', 'info');

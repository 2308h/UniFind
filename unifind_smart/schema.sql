-- UniFind Smart: University Lost & Found System Schema
-- Tailored for Marwadi University Context

CREATE DATABASE IF NOT EXISTS unifind_smart;
USE unifind_smart;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS favorites;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS item_claims;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin', 'security') NOT NULL DEFAULT 'student',
    reward_points INT DEFAULT 0,
    badge VARCHAR(50) DEFAULT 'Novice Finder',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Item Reports Table
CREATE TABLE IF NOT EXISTS reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reference_number VARCHAR(24) UNIQUE NULL,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(60) NOT NULL,
    location VARCHAR(150) NOT NULL,
    item_date DATE NOT NULL,
    report_type ENUM('lost', 'found') NOT NULL,
    status ENUM('open', 'claimed', 'resolved') DEFAULT 'open',
    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'verified',
    image_path VARCHAR(255) NULL,
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    custody_location VARCHAR(150) NULL,
    custody_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Automatic Reference Generator Trigger
DROP TRIGGER IF EXISTS reports_assign_reference_before_insert;
DELIMITER //
CREATE TRIGGER reports_assign_reference_before_insert
BEFORE INSERT ON reports
FOR EACH ROW
BEGIN
    IF NEW.reference_number IS NULL OR NEW.reference_number = '' THEN
        SET NEW.reference_number = CONCAT('UF-', DATE_FORMAT(CURDATE(), '%Y'), '-', LPAD((SELECT IFNULL(MAX(id), 0) + 1 FROM reports), 6, '0'));
    END IF;
END//
DELIMITER ;

-- 4. Item Claims Table (Private Proof of Ownership)
CREATE TABLE IF NOT EXISTS item_claims (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_id INT NOT NULL,
    claimant_id INT NOT NULL,
    proof_of_ownership TEXT NOT NULL,
    proof_image VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected', 'handed_over') NOT NULL DEFAULT 'pending',
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    handover_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_claimant_report (report_id, claimant_id),
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (claimant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Favorites Table
CREATE TABLE IF NOT EXISTS favorites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    report_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_fav (user_id, report_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Activity Logs Table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Seed Users (Password for all: Password123#)
INSERT INTO users (username, email, password, role, reward_points, badge) VALUES
('admin_user', 'admin@unifind.edu', '$2y$10$qfheloiRcinYWlklZeCuDOURXtHozxtg0hvfpMIbwhguOob0VD/Xi', 'admin', 500, 'Master Admin'),
('security_officer', 'security@unifind.edu', '$2y$10$qfheloiRcinYWlklZeCuDOURXtHozxtg0hvfpMIbwhguOob0VD/Xi', 'security', 250, 'Security Guardian'),
('john_student', 'student@unifind.edu', '$2y$10$qfheloiRcinYWlklZeCuDOURXtHozxtg0hvfpMIbwhguOob0VD/Xi', 'student', 60, 'Helpful Scout'),
('priya_student', 'priya@unifind.edu', '$2y$10$qfheloiRcinYWlklZeCuDOURXtHozxtg0hvfpMIbwhguOob0VD/Xi', 'student', 120, 'Active Detective');

-- Default Seed Reports (Marwadi University Context)
INSERT INTO reports (reference_number, user_id, title, description, category, location, item_date, report_type, status, verification_status, custody_location) VALUES
('UF-2026-000001', 3, 'Blue Hydroflask Water Bottle', 'Blue 32oz Hydroflask water bottle with Marwadi University stickers on the back.', 'Bags & Accessories', 'Main Library 2nd Floor', CURDATE(), 'lost', 'open', 'verified', NULL),
('UF-2026-000002', 4, 'Silver Apple Watch Series 8', 'Found on a desk near the coffee counter in Student Union Cafe. White sport band.', 'Electronics', 'Student Union Cafe', CURDATE(), 'found', 'open', 'verified', 'Security Desk Locker 04'),
('UF-2026-000003', 3, 'Black Leather Laptop Backpack', 'Black SwissGear backpack containing notebooks and a charger. Handed to security desk.', 'Bags & Accessories', 'Science Building Room 204', CURDATE(), 'found', 'open', 'verified', 'Security Desk Locker 14'),
('UF-2026-000004', 4, 'Engineering Mathematics Textbook & Binder', 'Calculus textbook with blue ring binder containing lecture notes for MATH 201.', 'Books & Stationery', 'Engineering Quad Bench', CURDATE(), 'lost', 'open', 'verified', NULL),
('UF-2026-000005', 3, 'Sony Noise-Canceling Headphones', 'Found black Sony WH-1000XM4 headphones on a bench outside Block A.', 'Electronics', 'Block A Main Entrance', CURDATE(), 'found', 'open', 'pending', 'Security Office Safe'),
('UF-2026-000006', 4, 'Marwadi University Student ID Card', 'Student ID card belonging to Raj Patel found near the Canteen.', 'IDs & Wallets', 'Main Canteen', CURDATE(), 'found', 'open', 'pending', 'Security Counter');

-- Seed Sample Notifications
INSERT INTO notifications (user_id, title, message, is_read) VALUES
(3, 'Smart Match Found!', 'A found item "Black Leather Laptop Backpack" matches your recent lost report!', 0),
(4, 'Verification Pending', 'Your reported found item "Sony Headphones" is pending admin verification.', 0);

-- Seed Sample Activity Logs
INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES
(1, 'System Initialized', 'Database seed loaded successfully', '127.0.0.1'),
(3, 'Item Reported', 'Reported lost item: Blue Hydroflask Water Bottle', '127.0.0.1'),
(4, 'Item Reported', 'Reported found item: Silver Apple Watch Series 8', '127.0.0.1');

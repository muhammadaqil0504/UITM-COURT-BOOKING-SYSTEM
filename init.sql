CREATE TABLE IF NOT EXISTS USER (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ADMIN (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS COURT (
    court_id INT AUTO_INCREMENT PRIMARY KEY,
    court_name VARCHAR(100),
    court_type VARCHAR(50),
    location VARCHAR(100),
    admin_id INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES ADMIN(admin_id)
);

CREATE TABLE IF NOT EXISTS BOOKING (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    court_id INT,
    booking_date DATE,
    time_slot VARCHAR(50),
    status VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES USER(user_id),
    FOREIGN KEY (court_id) REFERENCES COURT(court_id)
);

-- Let's insert mock courts from your wireframe (Petanque, Futsal, Takraw, Volleyball)
INSERT INTO COURT (court_name, court_type, location) VALUES 
('Court A', 'Petanque', 'UiTM Kampus Kuala Terengganu'),
('Court B', 'Futsal', 'UiTM Kampus Kuala Terengganu'),
('Court C', 'Takraw', 'UiTM Kampus Kuala Terengganu'),
('Court D', 'Volleyball', 'UiTM Kampus Kuala Terengganu');

-- Insert default admin profile mapping matching new credentials
INSERT INTO ADMIN (name, email, password) 
VALUES ('System Administrator', 'admin@gmail.com', '123')
ON DUPLICATE KEY UPDATE password='123';
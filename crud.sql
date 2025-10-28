CREATE TABLE user_practice (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(15) NOT NULL UNIQUE,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    department VARCHAR(100) NOT NULL,
    team VARCHAR(100) NOT NULL,
    country_id INT DEFAULT NULL,
    state_id INT DEFAULT NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
);


CREATE TABLE countries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    country_name VARCHAR(100) NOT NULL
);


INSERT INTO countries (country_name) VALUES
('India'),
('United States'),
('Canada'),
('Australia');


CREATE TABLE states (
    id INT AUTO_INCREMENT PRIMARY KEY,
    state_name VARCHAR(100) NOT NULL,
    country_id INT NOT NULL,
    CONSTRAINT fk_country_state FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE
);


INSERT INTO states (state_name, country_id) VALUES
('Maharashtra', 1),
('Gujarat', 1),
('California', 2),
('New York', 2),
('Ontario', 3),
('Quebec', 3),
('New South Wales', 4),
('Victoria', 4);

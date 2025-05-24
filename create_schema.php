<?php
include('connection.php');

if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
}

// Testing only
$mysqli->query("DROP DATABASE IF EXISTS blood_donation");

// Create database
$mysqli->query("CREATE DATABASE blood_donation");
$mysqli->select_db("blood_donation");

echo "✅ Database created successfully.<br>";

// Create tables
$schema = "
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NULL,
    image_url VARCHAR(255),
    active TINYINT(1) NOT NULL DEFAULT 1,
    force_reset TINYINT(1) NOT NULL DEFAULT 0,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    dob DATE,
    gender ENUM('male', 'female') DEFAULT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') DEFAULT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    failed_attempts INT NOT NULL DEFAULT 0,
    last_failed_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    otp_expiry DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE user_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    telegram_chat_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE question_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE assessment_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_text TEXT NOT NULL,
    category_id INT NOT NULL,
    expected_answer VARCHAR(3),
    is_required BOOLEAN DEFAULT TRUE,
    order_no INT,
    FOREIGN KEY (category_id) REFERENCES question_categories(id) ON DELETE CASCADE
);

CREATE TABLE assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    is_pass TINYINT(1) NOT NULL DEFAULT 0,
    is_book_appointment TINYINT(1) NOT NULL DEFAULT 0,
    create_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE assessment_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    question_id INT NOT NULL,
    answer VARCHAR(3) NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (assessment_id) REFERENCES assessments(id),
    FOREIGN KEY (question_id) REFERENCES assessment_questions(id)
);

CREATE TABLE hospitals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20),
    telegram_chat_id VARCHAR(50),
    address TEXT NOT NULL,
    city VARCHAR(100),
    country VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE donation_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    hospital_id INT NOT NULL,
    assessment_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (assessment_id) REFERENCES assessments(id),
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
);

CREATE TABLE donation_appointment_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donation_appointment_id INT NOT NULL,
    status ENUM('Pending', 'Accepted', 'Completed', 'Rejected', 'Expired') DEFAULT 'Pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donation_appointment_id) REFERENCES donation_appointments(id)
);
";

if ($mysqli->multi_query($schema)) {
    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
    } while ($mysqli->next_result());
    echo "✅ Schema created successfully.<br>";
} else {
    die("❌ Error creating schema: " . $mysqli->error);
}

$init_data = "
INSERT INTO question_categories (name, description) VALUES
('General Health', 'Basic health check before donation'),
('Medical History', 'Questions about past and current medical conditions'),
('Infectious Diseases', 'Check for risk of disease transmission'),
('Travel History', 'Recent travel to areas with health risks'),
('Lifestyle Risk', 'Behavioral risk factors'),
('Donation History', 'History of previous donations'),
('Female-Specific', 'Special questions for female donors');

INSERT INTO assessment_questions (question_text, category_id, expected_answer, is_required, order_no) VALUES
-- 1. General Health Questions (category_id = 1)
('Are you feeling well today?', 1, 'Yes', TRUE, 1),
('Do you have any cold, flu, or fever symptoms?', 1, 'No', TRUE, 2),
('Have you recently taken any medications?', 1, 'No', TRUE, 3),
('Do you have any allergies?', 1, 'No', TRUE, 4),

-- 2. Medical History (category_id = 2)
('Have you ever had heart disease, cancer, epilepsy, diabetes, or other chronic illnesses?', 2, 'No', TRUE, 1),
('Have you had any recent surgeries or major dental procedures?', 2, 'No', TRUE, 2),
('Have you received any vaccines or injections in the last few weeks?', 2, 'No', TRUE, 3),
('Have you ever had a blood transfusion?', 2, 'No', TRUE, 4),
('Have you ever been diagnosed with a bleeding disorder?', 2, 'No', TRUE, 5),
('Have you ever been treated for cancer or tumors?', 2, 'No', TRUE, 6),
('Have you ever had a positive test for hepatitis B or C?', 2, 'No', TRUE, 7),
('Have you ever had a positive test for syphilis?', 2, 'No', TRUE, 8),
('Have you ever been diagnosed with a sexually transmitted disease (STD)?', 2, 'No', TRUE, 9),

-- 3. Infectious Diseases (category_id = 3)
('Have you ever tested positive for HIV/AIDS, hepatitis B or C, syphilis, or other blood-borne infections?', 3, 'No', TRUE, 1),
('Have you had jaundice or unexplained weight loss?', 3, 'No', TRUE, 2),
('Have you been in contact with someone who has a contagious disease in the last 3 months?', 3, 'No', TRUE, 3),
('Have you ever been diagnosed with malaria or other tropical diseases?', 3, 'No', TRUE, 4),
('Have you ever had a positive test for tuberculosis (TB)?', 3, 'No', TRUE, 5),

-- 4. Travel History (category_id = 4)
('Have you recently traveled to areas where malaria or other infectious diseases are common?', 4, 'No', TRUE, 1),
('Have you traveled outside the country in the last 3 months?', 4, 'No', TRUE, 2),
('Have you lived in or traveled to areas with high rates of HIV or other STDs?', 4, 'No', TRUE, 3),
('Have you been in contact with someone who has traveled to a high-risk area?', 4, 'No', TRUE, 4),

-- 5. Lifestyle and Risk Behavior (category_id = 5)
('Do you use or have you ever used intravenous drugs?', 5, 'No', TRUE, 1),
('Have you had unprotected sex with multiple partners or with someone who has HIV or other STDs?', 5, 'No', TRUE, 2),
('Have you ever received money or drugs in exchange for sex?', 5, 'No', TRUE, 3),
('Have you ever been in prison or jail?', 5, 'No', TRUE, 4),
('Have you ever had a tattoo or body piercing in the last 12 months?', 5, 'No', TRUE, 5),
('Have you ever had a blood transfusion or organ transplant?', 5, 'No', TRUE, 6),

-- 6. Previous Donation History (category_id = 6)
('Have you donated blood in the last 3 months (for men) or 4 months (for women)?', 6, 'No', TRUE, 1),
('Have you ever had a reaction to a previous blood donation?', 6, 'No', TRUE, 2),
('Have you ever been deferred from donating blood?', 6, 'No', TRUE, 3),
('Have you ever had a low hemoglobin level or anemia?', 6, 'No', TRUE, 4),
('Have you ever had a fainting spell or dizziness after donating blood?', 6, 'No', TRUE, 5),

-- 7. Female-Specific Questions (category_id = 7)
('Are you currently pregnant or have you recently given birth?', 7, 'No', TRUE, 1),
('Are you breastfeeding?', 7, 'No', TRUE, 2),
('Have you had a menstrual period in the last 3 days?', 7, 'No', TRUE, 3),
('Are you taking hormonal contraceptives or other medications that affect your menstrual cycle?', 7, 'No', TRUE, 4);

INSERT INTO hospitals (name, contact_number, telegram_chat_id, address, city, country, created_at) 
VALUES ('National Blood Transfusion Center Cambodia', '0961234567', '1236071046', 'GWV3+HR7, Yothapol Khemarak Phoumin Blvd (271), Phnom Penh', 'Phnom Penh', 'kh', '2025-05-21 21:24:47');

";

if ($mysqli->multi_query($init_data)) {
    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
    } while ($mysqli->next_result());
    echo "✅ Initial data inserted successfully.<br>";
} else {
    die("❌ Error inserting initial data: " . $mysqli->error);
}
// 1236071046 878514898
$users = [
    [
        'username' => 'admin',
        'password' => 'Admin@123',
        'first_name' => 'System',
        'last_name' => 'Admin',
        'dob' => '2000-01-01',
        'gender' => 'male',
        'blood_type' => 'A+',
        'role' => 'admin',
        'telegram_id' => '878514898'
    ],
    [
        'username' => 'chantria',
        'password' => 'Chantria@123',
        'first_name' => 'Chum',
        'last_name' => 'Ratanakchentria',
        'dob' => '2000-01-01',
        'gender' => 'female',
        'blood_type' => 'B+',
        'role' => 'staff',
        'telegram_id' => '878514898'
    ],
    [
        'username' => 'donor1',
        'password' => 'Donor1@123',
        'first_name' => 'First',
        'last_name' => 'Donor',
        'dob' => '1998-05-10',
        'gender' => 'male',
        'blood_type' => 'O+',
        'role' => 'donor',
        'telegram_id' => '878514898'
    ],
    [
        'username' => 'donor2',
        'password' => 'Donor2@123',
        'first_name' => 'Second',
        'last_name' => 'Donor',
        'dob' => '1999-07-12',
        'gender' => 'female',
        'blood_type' => 'A-',
        'role' => 'donor',
        'telegram_id' => '878514898'
    ],
    [
        'username' => 'nbtcc',
        'password' => 'Nbtcc@123',
        'first_name' => 'National Blood Transfusion Center Cambodia',
        'last_name' => '',
        'dob' => '1990-01-01',
        'gender' => 'other',
        'blood_type' => 'AB+',
        'role' => 'hospital',
        'telegram_id' => '1236071046'
    ],
];

$roles = ['admin', 'staff', 'donor', 'hospital'];
foreach ($roles as $role) {
    $stmt = $mysqli->prepare("INSERT IGNORE INTO roles (name) VALUES (?)");
    $stmt->bind_param("s", $role);
    $stmt->execute();
}

foreach ($users as $user) {
    $stmt = $mysqli->prepare("SELECT id FROM roles WHERE name = ?");
    $stmt->bind_param("s", $user['role']);
    $stmt->execute();
    $stmt->bind_result($role_id);
    $stmt->fetch();
    $stmt->close();

    $hashed_password = password_hash($user['password'], PASSWORD_BCRYPT);

    $stmt = $mysqli->prepare("INSERT INTO users (username, password, image_url, role_id) VALUES (?, ?, ?, ?)");
    $image_url = 'uploads/assets/default-user.png';
    $stmt->bind_param("sssi", $user['username'], $hashed_password, $image_url, $role_id);
    $stmt->execute();
    $user_id = $stmt->insert_id;
    $stmt->close();

    $stmt = $mysqli->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, dob, gender, blood_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $user_id, $user['first_name'], $user['last_name'], $user['dob'], $user['gender'], $user['blood_type']);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("INSERT INTO user_contacts (user_id, telegram_chat_id) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $user['telegram_id']);
    $stmt->execute();
    $stmt->close();
}

echo "✅ Default users created successfully.";

echo '<br><br><a href="auth/login.php" style="padding: 10px 20px; background-color: brown; text-align: center; color: white; text-decoration: none; border-radius: 5px;">Login</a>';

-- ===== XPRESSPOINT HR TRAINING DATABASE SCHEMA =====
-- Database name matches includes/database.example.php (xpresspo_db).
-- Create this database manually if auto-creation doesn't work.

CREATE DATABASE IF NOT EXISTS xpresspo_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE xpresspo_db;

-- ===== CONTACTS TABLE =====
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    company VARCHAR(255),
    phone VARCHAR(50),
    interest VARCHAR(100),
    message TEXT NOT NULL,
    status ENUM('new', 'contacted', 'qualified', 'blacklisted') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== USERS TABLE (ADMIN AUTHENTICATION) =====
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    display_name VARCHAR(255) NULL,
    avatar_path VARCHAR(500) NULL,
    role ENUM('admin', 'manager', 'trainer', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password: xpresspoint2024)
INSERT INTO users (username, password, email, role) VALUES
('admin', '$2y$10$K4qYAoRQFAOUVddAiM24vemorAhhGuVBvkmJ/YaAIQ8QiMX2CM8mq', 'admin@xpresspoint.com', 'admin')
ON DUPLICATE KEY UPDATE id=id;

-- ===== PROGRAMMES TABLE =====
CREATE TABLE IF NOT EXISTS programmes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    duration VARCHAR(100),
    price DECIMAL(10,2),
    tax_inclusive TINYINT(1) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    original_price DECIMAL(10,2) DEFAULT NULL,
    category VARCHAR(100),
    programme_type ENUM('webinar', 'training') NOT NULL DEFAULT 'training',
    max_participants INT DEFAULT 25,
    certification VARCHAR(255) DEFAULT 'HRD Corp Certified',
    programme_icon VARCHAR(100) DEFAULT 'fas fa-graduation-cap',
    programme_badge VARCHAR(100) DEFAULT 'Popular',
    location VARCHAR(100) DEFAULT 'Online',
    poster_image VARCHAR(500),
    poster_image_2 VARCHAR(500) DEFAULT NULL,
    banner_image VARCHAR(500) DEFAULT NULL,
    modules TEXT,
    schedule_type VARCHAR(100) DEFAULT 'single_day',
    start_date DATE,
    end_date DATE,
    start_time TIME,
    end_time TIME,
    requires_facility TINYINT(1) DEFAULT 0,
    facility_type VARCHAR(50) DEFAULT NULL,
    auto_create_facility_booking TINYINT(1) DEFAULT 0,
    facility_booking_id INT DEFAULT NULL,
    is_featured TINYINT(1) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    registration_status ENUM('open', 'closed', 'full') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== JOBS TABLE =====
CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    type VARCHAR(100),
    employment_type VARCHAR(100) DEFAULT NULL,
    description TEXT,
    requirements TEXT,
    company_name VARCHAR(255) DEFAULT 'Xpress Point Management',
    job_icon VARCHAR(100) DEFAULT 'fas fa-briefcase',
    job_badge VARCHAR(100) DEFAULT 'New',
    poster_image VARCHAR(500),
    start_time TIME,
    end_time TIME,
    salary_range VARCHAR(100),
    experience_level VARCHAR(100) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== APPLICATIONS TABLE =====
CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    ic_number VARCHAR(50),
    application_type VARCHAR(50) DEFAULT 'with_resume',
    resume_path VARCHAR(500),
    cover_letter_path VARCHAR(500),
    education_level VARCHAR(100) DEFAULT NULL,
    education_details TEXT DEFAULT NULL,
    reference1_name VARCHAR(255) DEFAULT NULL,
    reference1_position VARCHAR(255) DEFAULT NULL,
    reference1_company VARCHAR(255) DEFAULT NULL,
    reference1_phone VARCHAR(50) DEFAULT NULL,
    reference1_email VARCHAR(255) DEFAULT NULL,
    reference2_name VARCHAR(255) DEFAULT NULL,
    reference2_position VARCHAR(255) DEFAULT NULL,
    reference2_company VARCHAR(255) DEFAULT NULL,
    reference2_phone VARCHAR(50) DEFAULT NULL,
    reference2_email VARCHAR(255) DEFAULT NULL,
    reference3_name VARCHAR(255) DEFAULT NULL,
    reference3_position VARCHAR(255) DEFAULT NULL,
    reference3_company VARCHAR(255) DEFAULT NULL,
    reference3_phone VARCHAR(50) DEFAULT NULL,
    reference3_email VARCHAR(255) DEFAULT NULL,
    employment1_company VARCHAR(255) DEFAULT NULL,
    employment1_position VARCHAR(255) DEFAULT NULL,
    employment1_duration VARCHAR(255) DEFAULT NULL,
    employment1_description TEXT DEFAULT NULL,
    employment2_company VARCHAR(255) DEFAULT NULL,
    employment2_position VARCHAR(255) DEFAULT NULL,
    employment2_duration VARCHAR(255) DEFAULT NULL,
    employment2_description TEXT DEFAULT NULL,
    employment3_company VARCHAR(255) DEFAULT NULL,
    employment3_position VARCHAR(255) DEFAULT NULL,
    employment3_duration VARCHAR(255) DEFAULT NULL,
    employment3_description TEXT DEFAULT NULL,
    technical_skills TEXT DEFAULT NULL,
    soft_skills TEXT DEFAULT NULL,
    certifications TEXT DEFAULT NULL,
    languages TEXT DEFAULT NULL,
    expected_salary VARCHAR(100) DEFAULT NULL,
    availability_date DATE DEFAULT NULL,
    additional_notes TEXT DEFAULT NULL,
    interviewer_comment TEXT DEFAULT NULL,
    status ENUM('pending', 'reviewed', 'shortlisted', 'rejected', 'waiting for interview', 'hired', 'blacklisted') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== PROGRAMME REGISTRATIONS TABLE =====
CREATE TABLE IF NOT EXISTS programme_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    programme_id INT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    ic_number VARCHAR(50) DEFAULT NULL,
    company VARCHAR(255),
    position VARCHAR(255),
    billing_address TEXT DEFAULT NULL,
    billing_contact_no VARCHAR(50) DEFAULT NULL,
    vehicle_type VARCHAR(50) DEFAULT NULL,
    vehicle_type_other VARCHAR(100) DEFAULT NULL,
    plate_number VARCHAR(50) DEFAULT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    how_did_you_hear VARCHAR(100) DEFAULT NULL,
    how_did_you_hear_other VARCHAR(255) DEFAULT NULL,
    spicy_preference VARCHAR(50) DEFAULT NULL,
    allergies VARCHAR(100) DEFAULT NULL,
    allergies_other VARCHAR(255) DEFAULT NULL,
    dietary_preference VARCHAR(100) DEFAULT NULL,
    dietary_restrictions TEXT DEFAULT NULL,
    interested_programs VARCHAR(255) DEFAULT NULL,
    interested_programs_other VARCHAR(255) DEFAULT NULL,
    experience_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    preferred_schedule ENUM('weekday', 'weekend', 'evening', 'flexible') DEFAULT 'flexible',
    additional_notes TEXT,
    status ENUM('pending', 'confirmed', 'waitlisted', 'cancelled', 'blacklisted') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== PROGRAMME REGISTRATION QUESTIONS =====
CREATE TABLE IF NOT EXISTS programme_registration_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    programme_id INT NOT NULL,
    field_key VARCHAR(100) NOT NULL,
    field_label VARCHAR(255) NOT NULL,
    input_type ENUM('text', 'email', 'tel', 'textarea', 'select') NOT NULL DEFAULT 'text',
    field_options TEXT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_programme_field (programme_id, field_key),
    INDEX idx_programme_enabled (programme_id, is_enabled),
    CONSTRAINT fk_programme_registration_questions_programme
        FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== CLIENTS TABLE (TESTIMONIALS) =====
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    company VARCHAR(255),
    position VARCHAR(255),
    testimonial TEXT,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    is_featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== BOOKINGS TABLE =====
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(50),
    company VARCHAR(255),
    facility_type ENUM('conference_room', 'training_room', 'breakout_area') NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    attendees_count INT DEFAULT 1,
    purpose TEXT,
    special_requirements TEXT,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    confirmed_by INT,
    confirmed_at TIMESTAMP NULL,
    cancellation_reason TEXT,
    programme_id INT DEFAULT NULL,
    is_programme_booking TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== TRAINERS TABLE =====
CREATE TABLE IF NOT EXISTS trainers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    experience_years INT DEFAULT 0,
    image_path VARCHAR(500) DEFAULT NULL,
    description TEXT,
    specialties TEXT,
    credentials TEXT,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_trainers_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== BLACKLIST TABLE =====
CREATE TABLE IF NOT EXISTS blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL DEFAULT '',
    position_applied VARCHAR(255) NOT NULL DEFAULT '',
    phone_number VARCHAR(255) NOT NULL DEFAULT '',
    date VARCHAR(255) NOT NULL DEFAULT '',
    reason TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for better performance
CREATE INDEX idx_bookings_date ON bookings(booking_date);
CREATE INDEX idx_bookings_facility ON bookings(facility_type);
CREATE INDEX idx_bookings_status ON bookings(status);
CREATE INDEX idx_bookings_customer_email ON bookings(customer_email);

-- Insert sample testimonials
INSERT INTO clients (name, company, position, testimonial, rating, is_featured) VALUES
('Sarah Johnson', 'TechCorp Inc.', 'HR Director', 'XpressPoint\'s HR Leadership program transformed our approach to talent management. Our retention rates improved by 40% within the first year.', 5, 1),
('Michael Chen', 'Global Manufacturing', 'VP of Operations', 'The Employee Relations training helped us create a more inclusive workplace culture. Our employee satisfaction scores increased significantly.', 5, 1),
('Lisa Rodriguez', 'StartupXYZ', 'Chief People Officer', 'Their HR Analytics program gave us the tools to make data-driven decisions. We can now clearly demonstrate HR\'s impact on business outcomes.', 5, 1),
('David Thompson', 'Healthcare Plus', 'HR Manager', 'The compliance training was comprehensive and practical. It helped us avoid potential legal issues and improved our workplace safety record.', 4, 0),
('Emily Wilson', 'Financial Services Co.', 'Talent Acquisition Lead', 'The recruitment training provided us with innovative sourcing strategies that reduced our time-to-hire by 30%.', 5, 0);

-- ===== INDEXES FOR PERFORMANCE =====
CREATE INDEX idx_contacts_created_at ON contacts(created_at);
CREATE INDEX idx_contacts_email ON contacts(email);
CREATE INDEX idx_contacts_status ON contacts(status);
CREATE INDEX idx_jobs_active ON jobs(is_active);
CREATE INDEX idx_programmes_active ON programmes(is_active);
CREATE INDEX idx_programmes_owner ON programmes(owner_user_id);
CREATE INDEX idx_programmes_type ON programmes(programme_type);
CREATE INDEX idx_applications_status ON applications(status);
CREATE INDEX idx_programme_registrations_status ON programme_registrations(status);
CREATE INDEX idx_clients_featured ON clients(is_featured);

-- ===== VIEWS FOR COMMON QUERIES =====
CREATE OR REPLACE VIEW recent_contacts AS
SELECT * FROM contacts
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY created_at DESC;

CREATE OR REPLACE VIEW active_jobs AS
SELECT * FROM jobs
WHERE is_active = 1
ORDER BY created_at DESC;

CREATE OR REPLACE VIEW featured_testimonials AS
SELECT * FROM clients
WHERE is_featured = 1 AND rating >= 4
ORDER BY created_at DESC;

-- ===== GRANT PERMISSIONS (ADJUST AS NEEDED) =====
-- GRANT ALL PRIVILEGES ON xpresspo_db.* TO 'your_username'@'localhost';
-- FLUSH PRIVILEGES;

-- ===== DATABASE STATISTICS =====
SELECT 'contacts' AS table_name, COUNT(*) AS record_count FROM contacts
UNION ALL
SELECT 'users', COUNT(*) FROM users
UNION ALL
SELECT 'programmes', COUNT(*) FROM programmes
UNION ALL
SELECT 'jobs', COUNT(*) FROM jobs
UNION ALL
SELECT 'applications', COUNT(*) FROM applications
UNION ALL
SELECT 'programme_registrations', COUNT(*) FROM programme_registrations
UNION ALL
SELECT 'programme_registration_questions', COUNT(*) FROM programme_registration_questions
UNION ALL
SELECT 'clients', COUNT(*) FROM clients
UNION ALL
SELECT 'bookings', COUNT(*) FROM bookings
UNION ALL
SELECT 'trainers', COUNT(*) FROM trainers
UNION ALL
SELECT 'blacklist', COUNT(*) FROM blacklist;

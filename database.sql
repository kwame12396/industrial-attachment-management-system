-- database.sql
-- MySQL database schema for Industrial Attachment Management System (IAMS)
-- Updated with security enhancements, cascades, and constraints

CREATE DATABASE IF NOT EXISTS iams_db;
USE iams_db;

-- Users table for authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('coordinator', 'student', 'industrial_supervisor', 'university_supervisor') NOT NULL,
    related_id INT NULL,
    security_question VARCHAR(255) NULL,
    security_answer VARCHAR(255) NULL,
    is_locked TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Login attempts table (brute force protection)
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45),
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Students profile
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    program VARCHAR(100) NOT NULL,
    year_of_study INT NOT NULL,
    phone VARCHAR(20),
    preferred_location VARCHAR(100),
    preferred_project_type VARCHAR(100),
    skills TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_year CHECK (year_of_study BETWEEN 1 AND 6)
);

-- Organizations profile
CREATE TABLE organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    registration_number VARCHAR(50) UNIQUE,
    location VARCHAR(100) NOT NULL,
    website VARCHAR(200),
    contact_person VARCHAR(100),
    contact_email VARCHAR(100),
    contact_phone VARCHAR(20),
    required_skills TEXT,
    capacity INT DEFAULT 3,
    description TEXT,
    industry_type VARCHAR(100) DEFAULT 'Other',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Industrial supervisors (linked to organizations)
CREATE TABLE industrial_supervisors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    organization_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    position VARCHAR(100),
    phone VARCHAR(20),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

-- University supervisors profile
CREATE TABLE university_supervisors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    employee_id VARCHAR(30) UNIQUE,
    department VARCHAR(100),
    phone VARCHAR(20),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Student-Organization allocation
CREATE TABLE allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    organization_id INT NOT NULL,
    status ENUM('pending', 'confirmed', 'completed') DEFAULT 'pending',
    allocated_by INT,
    allocated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

-- Weekly logbooks
CREATE TABLE logbooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    week_number INT NOT NULL,
    week_start_date DATE,
    week_end_date DATE,
    activities TEXT NOT NULL,
    challenges TEXT,
    plans TEXT,
    supervisor_comments TEXT,
    commented_by VARCHAR(100) NULL,
    commented_at TIMESTAMP NULL,
    status ENUM('draft', 'submitted', 'reviewed') DEFAULT 'draft',
    submitted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_week (student_id, week_number)
);

-- Final attachment reports
CREATE TABLE final_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    original_filename VARCHAR(255),
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('submitted', 'reviewed', 'returned') DEFAULT 'submitted',
    supervisor_feedback TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Industrial supervisor performance reports
CREATE TABLE industrial_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    supervisor_id INT NOT NULL,
    organization_id INT NOT NULL,
    overall_rating INT CHECK (overall_rating BETWEEN 1 AND 10),
    attendance_rating INT CHECK (attendance_rating BETWEEN 1 AND 10),
    technical_skills_rating INT CHECK (technical_skills_rating BETWEEN 1 AND 10),
    communication_rating INT CHECK (communication_rating BETWEEN 1 AND 10),
    teamwork_rating INT CHECK (teamwork_rating BETWEEN 1 AND 10),
    comments TEXT,
    strengths TEXT,
    areas_for_improvement TEXT,
    recommendation ENUM('excellent', 'good', 'average', 'poor') DEFAULT 'good',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (supervisor_id) REFERENCES industrial_supervisors(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

-- University supervisor assessments (two visits)
CREATE TABLE university_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    supervisor_id INT NOT NULL,
    visit_number INT CHECK (visit_number IN (1, 2)),
    visit_date DATE,
    presentation_score INT CHECK (presentation_score BETWEEN 0 AND 20),
    project_knowledge_score INT CHECK (project_knowledge_score BETWEEN 0 AND 20),
    attitude_score INT CHECK (attitude_score BETWEEN 0 AND 10),
    overall_score INT GENERATED ALWAYS AS (presentation_score + project_knowledge_score + attitude_score) STORED,
    comments TEXT,
    recommendations TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (supervisor_id) REFERENCES university_supervisors(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_visit (student_id, visit_number)
);

-- Student ratings of companies (Two-Way Rating System)
CREATE TABLE IF NOT EXISTS student_company_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    organization_id INT NOT NULL,
    overall_rating INT CHECK (overall_rating BETWEEN 1 AND 10),
    mentorship_rating INT CHECK (mentorship_rating BETWEEN 1 AND 10),
    work_environment_rating INT CHECK (work_environment_rating BETWEEN 1 AND 10),
    learning_opportunities_rating INT CHECK (learning_opportunities_rating BETWEEN 1 AND 10),
    support_rating INT CHECK (support_rating BETWEEN 1 AND 10),
    would_recommend TINYINT(1) DEFAULT 1,
    comments TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_org (student_id, organization_id)
);

-- Notifications/Reminders
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('reminder', 'info', 'warning', 'success') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    due_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity audit log
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(200) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default coordinator (password: password)
INSERT INTO users (email, password, role, security_question, security_answer) VALUES
('coordinator@iams.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'coordinator',
 'What is your favourite colour?', 'blue');

-- University supervisor (password: password)
INSERT INTO users (email, password, role, security_question, security_answer) VALUES
('univ.supervisor@iams.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'university_supervisor',
 'What city were you born in?', 'gaborone');
INSERT INTO university_supervisors (user_id, full_name, employee_id, department) VALUES
(2, 'Dr. Sarah Johnson', 'UNIV001', 'Computer Science');

-- Sample organizations
INSERT INTO organizations (name, registration_number, location, contact_email, required_skills, capacity, industry_type) VALUES
('TechCorp Solutions', 'TC2024001', 'Gaborone', 'hr@techcorp.co.bw', 'Web Development, Python, JavaScript', 5, 'Information Technology'),
('DataAnalytics Ltd', 'DA2024002', 'Francistown', 'careers@dataanalytics.com', 'Data Science, SQL, Machine Learning', 3, 'Data & Analytics');

-- Industrial supervisor (password: password)
INSERT INTO users (email, password, role, security_question, security_answer) VALUES
('supervisor@techcorp.co.bw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'industrial_supervisor',
 'What was the name of your first pet?', 'rex');
INSERT INTO industrial_supervisors (user_id, organization_id, full_name, position) VALUES
(3, 1, 'John Molefe', 'Senior Developer');

-- Sample student (password: password)
INSERT INTO users (email, password, role, security_question, security_answer) VALUES
('student@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student',
 'What is your mother\'s maiden name?', 'smith');
INSERT INTO students (user_id, student_id, full_name, program, year_of_study, preferred_location, preferred_project_type) VALUES
(4, 'CS2021001', 'Thabo Nkosi', 'Computer Science', 3, 'Gaborone', 'Web Development');

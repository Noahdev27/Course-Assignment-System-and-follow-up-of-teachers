-- =====================================================================
-- Course Assignment System — database creation + test data
-- Import it in phpMyAdmin (Import tab) or from the command line:
--   mysql -u root < database/database.sql
-- Password of every test account: Passe1234
-- =====================================================================

SET NAMES utf8mb4;

DROP DATABASE IF EXISTS course_assignment;
CREATE DATABASE course_assignment CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE course_assignment;

-- ---------------------------------------------------------------------
-- Tables
-- ---------------------------------------------------------------------

CREATE TABLE departments (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    name     VARCHAR(100) NOT NULL,
    code     VARCHAR(10)  NOT NULL UNIQUE,
    head_id  INT NULL
) ENGINE=InnoDB;

CREATE TABLE users (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    last_name      VARCHAR(80)  NOT NULL,
    first_name     VARCHAR(80)  NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    role           ENUM('admin','head','teacher','accountant') NOT NULL DEFAULT 'teacher',
    department_id  INT NULL,
    academic_rank  VARCHAR(50) NULL,
    max_load       INT NOT NULL DEFAULT 0,
    phone          VARCHAR(30) NULL,
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE departments
    ADD CONSTRAINT fk_department_head FOREIGN KEY (head_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE academic_years (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    label       VARCHAR(9) NOT NULL UNIQUE,
    start_date  DATE NOT NULL,
    end_date    DATE NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE courses (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    code           VARCHAR(20)  NOT NULL UNIQUE,
    title          VARCHAR(150) NOT NULL,
    department_id  INT NOT NULL,
    level          VARCHAR(5)   NOT NULL,
    semester       TINYINT      NOT NULL,
    cm_hours       INT NOT NULL DEFAULT 0,
    td_hours       INT NOT NULL DEFAULT 0,
    tp_hours       INT NOT NULL DEFAULT 0,
    credits        INT NOT NULL DEFAULT 0,
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_course_department FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB;

CREATE TABLE assignments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    course_id    INT NOT NULL,
    teacher_id   INT NOT NULL,
    year_id      INT NOT NULL,
    type         ENUM('CM','TD','TP') NOT NULL,
    hours        DECIMAL(6,2) NOT NULL,
    status       ENUM('proposed','accepted','disputed','approved') NOT NULL DEFAULT 'approved',
    comment      VARCHAR(255) NULL,
    assigned_by  INT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_assignment UNIQUE (course_id, teacher_id, year_id, type),
    CONSTRAINT fk_assignment_course FOREIGN KEY (course_id) REFERENCES courses(id),
    CONSTRAINT fk_assignment_teacher FOREIGN KEY (teacher_id) REFERENCES users(id),
    CONSTRAINT fk_assignment_year FOREIGN KEY (year_id) REFERENCES academic_years(id),
    CONSTRAINT fk_assignment_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE hourly_rates (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    academic_rank  VARCHAR(50) NOT NULL,
    type           ENUM('CM','TD','TP') NOT NULL,
    rate_per_hour  INT NOT NULL DEFAULT 0,
    CONSTRAINT uq_rate UNIQUE (academic_rank, type)
) ENGINE=InnoDB;

CREATE TABLE payments (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id    INT NOT NULL,
    year_id       INT NOT NULL,
    amount        INT NOT NULL,
    payment_date  DATE NOT NULL,
    method        ENUM('cash','bank_transfer','mobile_money','cheque') NOT NULL,
    reference     VARCHAR(60) NULL,
    recorded_by   INT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_teacher FOREIGN KEY (teacher_id) REFERENCES users(id),
    CONSTRAINT fk_payment_year FOREIGN KEY (year_id) REFERENCES academic_years(id),
    CONSTRAINT fk_payment_recorded_by FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE class_sessions (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id     INT NOT NULL,
    session_date      DATE NOT NULL,
    start_time        TIME NOT NULL,
    end_time          TIME NOT NULL,
    duration          DECIMAL(4,2) NOT NULL,
    content           VARCHAR(255) NOT NULL,
    status            ENUM('declared','approved','rejected') NOT NULL DEFAULT 'declared',
    rejection_reason  VARCHAR(255) NULL,
    reviewed_by       INT NULL,
    payment_id        INT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_session_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id),
    CONSTRAINT fk_session_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_session_payment FOREIGN KEY (payment_id) REFERENCES payments(id)
) ENGINE=InnoDB;

CREATE TABLE preferences (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id  INT NOT NULL,
    course_id   INT NOT NULL,
    year_id     INT NOT NULL,
    priority    TINYINT NOT NULL,
    CONSTRAINT uq_preference UNIQUE (teacher_id, course_id, year_id),
    CONSTRAINT fk_preference_teacher FOREIGN KEY (teacher_id) REFERENCES users(id),
    CONSTRAINT fk_preference_course FOREIGN KEY (course_id) REFERENCES courses(id),
    CONSTRAINT fk_preference_year FOREIGN KEY (year_id) REFERENCES academic_years(id)
) ENGINE=InnoDB;

CREATE TABLE activity_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NULL,
    action      VARCHAR(60)  NOT NULL,
    details     VARCHAR(500) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Test data
-- ---------------------------------------------------------------------

INSERT INTO departments (id, name, code) VALUES
(1, 'Computer Science', 'CSC'),
(2, 'Mathematics', 'MTH'),
(3, 'Management', 'MGT');

-- Password of every account: Passe1234
SET @pwd = '$2y$10$oaDhiRuWChOtjHguiNxUtOTJAZAGDUbO67KV21kc7GiekOKM67sf.';

INSERT INTO users (id, last_name, first_name, email, password_hash, role, department_id, academic_rank, max_load, phone) VALUES
(1,  'Admin',     'System',      'admin@cas.local',      @pwd, 'admin',      NULL, NULL, 0, NULL),
(2,  'Ngo Bayiha','Mireille',    'accountant@cas.local', @pwd, 'accountant',  NULL, NULL, 0, '+237 690 00 00 02'),
(3,  'Tchoua',    'Jean-Paul',   'tchoua@cas.local',     @pwd, 'head',       1, 'Professor',            150, '+237 690 00 00 03'),
(4,  'Mbarga',    'Hélène',      'mbarga@cas.local',     @pwd, 'head',       2, 'Associate Professor', 160, '+237 690 00 00 04'),
(5,  'Fouda',     'Samuel',      'fouda@cas.local',      @pwd, 'head',       3, 'Associate Professor', 160, '+237 690 00 00 05'),
(6,  'Nkoulou',   'Patrice',     'nkoulou@cas.local',    @pwd, 'teacher', 1, 'Lecturer',       180, '+237 690 00 00 06'),
(7,  'Essomba',   'Carine',      'essomba@cas.local',    @pwd, 'teacher', 1, 'Assistant Lecturer',             200, '+237 690 00 00 07'),
(8,  'Kamga',     'Boris',       'kamga@cas.local',      @pwd, 'teacher', 1, 'Assistant Lecturer',             200, '+237 690 00 00 08'),
(9,  'Djoko',     'Arnaud',      'djoko@cas.local',      @pwd, 'teacher', 2, 'Lecturer',       180, '+237 690 00 00 09'),
(10, 'Abena',     'Laure',       'abena@cas.local',      @pwd, 'teacher', 2, 'Assistant Lecturer',             200, '+237 690 00 00 10'),
(11, 'Ndjock',    'Ivan',        'ndjock@cas.local',     @pwd, 'teacher', 2, 'Assistant Lecturer',             200, '+237 690 00 00 11'),
(12, 'Ewane',     'Sandrine',    'ewane@cas.local',      @pwd, 'teacher', 3, 'Lecturer',       180, '+237 690 00 00 12'),
(13, 'Tamba',     'Olivier',     'tamba@cas.local',      @pwd, 'teacher', 3, 'Assistant Lecturer',             200, '+237 690 00 00 13'),
(14, 'Moussa',    'Aïcha',       'moussa@cas.local',     @pwd, 'teacher', 3, 'Assistant Lecturer',             200, '+237 690 00 00 14'),
(15, 'Owona',     'Christian',   'owona@cas.local',      @pwd, 'teacher', 1, 'Lecturer',       180, '+237 690 00 00 15');

UPDATE departments SET head_id = 3 WHERE id = 1;
UPDATE departments SET head_id = 4 WHERE id = 2;
UPDATE departments SET head_id = 5 WHERE id = 3;

INSERT INTO academic_years (id, label, start_date, end_date, is_active) VALUES
(1, '2025-2026', '2025-09-01', '2026-07-31', 0),
(2, '2026-2027', '2026-09-01', '2027-07-31', 1);

INSERT INTO courses (id, code, title, department_id, level, semester, cm_hours, td_hours, tp_hours, credits) VALUES
(1,  'CSC101', 'Algorithms and Data Structures', 1, 'L1', 1, 30, 20, 10, 6),
(2,  'CSC102', 'Web Programming',                       1, 'L1', 2, 20, 10, 20, 5),
(3,  'CSC201', 'Relational Databases',         1, 'L2', 1, 25, 15, 15, 6),
(4,  'CSC202', 'Object-Oriented Programming',            1, 'L2', 2, 25, 15, 20, 6),
(5,  'CSC203', 'Computer Networks',                   1, 'L2', 1, 20, 15, 10, 5),
(6,  'CSC301', 'Software Engineering',                          1, 'L3', 1, 30, 20,  0, 5),
(7,  'CSC302', 'Operating Systems',                1, 'L3', 2, 25, 15, 10, 5),
(8,  'MTH101', 'Calculus I',                               2, 'L1', 1, 30, 30,  0, 6),
(9,  'MTH102', 'Linear Algebra',                        2, 'L1', 2, 30, 25,  0, 6),
(10, 'MTH201', 'Probability',                            2, 'L2', 1, 25, 20,  0, 5),
(11, 'MTH202', 'Descriptive Statistics',                 2, 'L2', 2, 20, 15, 10, 4),
(12, 'MTH301', 'Numerical Analysis',                       2, 'L3', 1, 25, 15, 15, 5),
(13, 'MTH302', 'Operations Research',                2, 'L3', 2, 25, 20,  0, 5),
(14, 'MGT101', 'Financial Accounting',                   3, 'L1', 1, 30, 20,  0, 6),
(15, 'MGT102', 'Principles of Economics',                       3, 'L1', 2, 30, 15,  0, 5),
(16, 'MGT201', 'Principles of Marketing',                   3, 'L2', 1, 25, 15,  0, 4),
(17, 'MGT202', 'Corporate Finance',                      3, 'L2', 2, 25, 20,  0, 5),
(18, 'MGT203', 'Business Law',                      3, 'L2', 1, 25, 10,  0, 4),
(19, 'MGT301', 'Organisational Management',            3, 'L3', 1, 25, 15,  0, 4),
(20, 'MGT302', 'Business Taxation',               3, 'L3', 2, 20, 15,  0, 4);

-- Hourly rates in FCFA per hour
INSERT INTO hourly_rates (academic_rank, type, rate_per_hour) VALUES
('Assistant Lecturer', 'CM', 6000),    ('Assistant Lecturer', 'TD', 4000),    ('Assistant Lecturer', 'TP', 3500),
('Lecturer', 'CM', 8000),       ('Lecturer', 'TD', 5000),       ('Lecturer', 'TP', 4500),
('Associate Professor', 'CM', 10000),('Associate Professor', 'TD', 6500), ('Associate Professor', 'TP', 5500),
('Professor', 'CM', 12000),           ('Professor', 'TD', 8000),            ('Professor', 'TP', 7000);

-- Assignments: past year (read-only), then active year
INSERT INTO assignments (id, course_id, teacher_id, year_id, type, hours, assigned_by) VALUES
(1,  2,  6, 1, 'CM', 20, 1),
(2,  2,  8, 1, 'TP', 20, 1),
(3,  1,  3, 2, 'CM', 30, 1),
(4,  1,  7, 2, 'TD', 20, 3),
(5,  1,  7, 2, 'TP', 10, 3),
(6,  3,  6, 2, 'CM', 25, 3),
(7,  3,  8, 2, 'TD', 15, 3),
(8,  3,  8, 2, 'TP', 15, 3),
(9,  5, 15, 2, 'CM', 20, 3),
(10, 5, 15, 2, 'TD', 15, 3),
(11, 8,  4, 2, 'CM', 30, 1),
(12, 8, 10, 2, 'TD', 30, 4),
(13, 10, 9, 2, 'CM', 25, 4),
(14, 10, 11, 2, 'TD', 20, 4),
(15, 14, 5, 2, 'CM', 30, 1),
(16, 14, 13, 2, 'TD', 20, 5),
(17, 16, 12, 2, 'CM', 25, 5),
(18, 18, 14, 2, 'CM', 25, 5);

-- Payments (amounts are recomputed below from the sessions they settle)
INSERT INTO payments (id, teacher_id, year_id, amount, payment_date, method, reference, recorded_by) VALUES
(1, 3,  2, 0, '2026-10-30', 'bank_transfer',     'VIR-2026-0012', 2),
(2, 7,  2, 0, '2026-10-30', 'mobile_money', 'OM-558214',     2),
(3, 6,  2, 0, '2026-10-31', 'bank_transfer',     'VIR-2026-0015', 2),
(4, 4,  2, 0, '2026-10-31', 'cash',      'REC-0041',      2),
(5, 9,  2, 0, '2026-11-03', 'mobile_money', 'MOMO-771203',   2),
(6, 13, 2, 0, '2026-11-03', 'cash',      'REC-0044',      2);

INSERT INTO class_sessions (assignment_id, session_date, start_time, end_time, duration, content, status, rejection_reason, reviewed_by, payment_id) VALUES
(3,  '2026-10-05', '08:00', '10:00', 2, 'Introduction to algorithms',            'approved',  NULL, 1, 1),
(3,  '2026-10-12', '08:00', '10:00', 2, 'Variables, types and assignment',            'approved',  NULL, 1, 1),
(3,  '2026-10-19', '08:00', '10:00', 2, 'Conditional statements',                 'approved',  NULL, 1, NULL),
(3,  '2026-10-26', '08:00', '10:00', 2, 'Loops',                                'declared', NULL, NULL, NULL),
(4,  '2026-10-07', '10:00', '12:00', 2, 'Tutorial 1: exercises on variables',          'approved',  NULL, 3, 2),
(4,  '2026-10-14', '10:00', '12:00', 2, 'Tutorial 2: conditional statements',           'approved',  NULL, 3, 2),
(4,  '2026-10-21', '10:00', '12:00', 2, 'Tutorial 3: loops',                          'approved',  NULL, 3, NULL),
(4,  '2026-10-28', '10:00', '12:00', 2, 'Tutorial 4: arrays',                         'declared', NULL, NULL, NULL),
(5,  '2026-10-09', '14:00', '17:00', 3, 'Lab 1: first programs in C',             'approved',  NULL, 3, NULL),
(5,  '2026-10-16', '14:00', '17:00', 3, 'Lab 2: working with arrays',             'rejected',  'No session was scheduled on this date: the room was closed.', 3, NULL),
(6,  '2026-10-06', '08:00', '11:00', 3, 'The relational model',                      'approved',  NULL, 3, 3),
(6,  '2026-10-13', '08:00', '11:00', 3, 'Relational algebra',                      'approved',  NULL, 3, 3),
(6,  '2026-10-20', '08:00', '11:00', 3, 'SQL: SELECT and joins',                  'approved',  NULL, 3, NULL),
(6,  '2026-10-27', '08:00', '11:00', 3, 'SQL: aggregate functions',              'declared', NULL, NULL, NULL),
(7,  '2026-10-08', '10:00', '12:00', 2, 'Tutorial: normalisation',                         'approved',  NULL, 3, NULL),
(7,  '2026-10-15', '10:00', '12:00', 2, 'Tutorial: SQL queries',                          'declared', NULL, NULL, NULL),
(9,  '2026-10-05', '13:00', '15:00', 2, 'The OSI model',                              'approved',  NULL, 3, NULL),
(9,  '2026-10-12', '13:00', '15:00', 2, 'The TCP/IP stack',                             'approved',  NULL, 3, NULL),
(11, '2026-10-05', '08:00', '11:00', 3, 'Sequences',                          'approved',  NULL, 1, 4),
(11, '2026-10-12', '08:00', '11:00', 3, 'Limits of functions',                       'approved',  NULL, 1, 4),
(11, '2026-10-19', '08:00', '11:00', 3, 'Continuity',                                 'approved',  NULL, 1, 4),
(12, '2026-10-07', '08:00', '11:00', 3, 'Tutorial: sequences',                     'approved',  NULL, 4, NULL),
(12, '2026-10-14', '08:00', '11:00', 3, 'Tutorial: limits',                               'approved',  NULL, 4, NULL),
(12, '2026-10-21', '08:00', '11:00', 3, 'Tutorial: continuity',                            'declared', NULL, NULL, NULL),
(13, '2026-10-06', '10:00', '12:00', 2, 'Counting',                               'approved',  NULL, 4, 5),
(13, '2026-10-13', '10:00', '12:00', 2, 'Probability spaces',                       'approved',  NULL, 4, NULL),
(15, '2026-10-05', '08:00', '11:00', 3, 'The balance sheet',                                   'approved',  NULL, 1, NULL),
(15, '2026-10-12', '08:00', '11:00', 3, 'The income statement',                      'approved',  NULL, 1, NULL),
(16, '2026-10-07', '13:00', '15:00', 2, 'Tutorial: building a balance sheet',                 'approved',  NULL, 5, 6),
(16, '2026-10-14', '13:00', '15:00', 2, 'Tutorial: journal entries',                  'approved',  NULL, 5, NULL),
(17, '2026-10-06', '10:00', '12:00', 2, 'Introduction to marketing',                  'declared', NULL, NULL, NULL),
(18, '2026-10-08', '15:00', '17:00', 2, 'The legal status of traders',                    'rejected',  'Time slot does not match the timetable.', 5, NULL);

-- Each payment amount = sum of (duration × hourly rate) of the sessions it settles
UPDATE payments p
SET p.amount = (
    SELECT COALESCE(SUM(s.duration * t.rate_per_hour), 0)
    FROM class_sessions s
    JOIN assignments a ON a.id = s.assignment_id
    JOIN users u ON u.id = a.teacher_id
    JOIN hourly_rates t ON t.academic_rank = u.academic_rank AND t.type = a.type
    WHERE s.payment_id = p.id
);

INSERT INTO preferences (teacher_id, course_id, year_id, priority) VALUES
(6, 4, 2, 1), (6, 6, 2, 2), (8, 2, 2, 1);

INSERT INTO activity_log (user_id, action, details, created_at) VALUES
(1, 'Academic year', 'Year 2026-2027 set as the active year', '2026-09-01 08:00:00'),
(3, 'Assignment', 'CSC201 TD (15 h) assigned to Kamga Boris', '2026-09-20 10:12:00'),
(2, 'Payment', 'Payment recorded for Tchoua Jean-Paul', '2026-10-30 15:40:00');

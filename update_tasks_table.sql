USE attendance_management;

-- Drop existing tasks table if it exists
DROP TABLE IF EXISTS tasks;

-- Create tasks table with all necessary columns
CREATE TABLE tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_number VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    assigned_to INT,
    assigned_by INT,
    due_date DATE,
    priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add missing columns to tasks table
ALTER TABLE tasks 
ADD COLUMN assigned_date DATE AFTER priority;

-- Update existing tasks with default values
UPDATE tasks 
SET task_number = CONCAT('TT-', DATE_FORMAT(created_at, '%d-%m-%Y'), '-', id),
    priority = 'medium',
    assigned_date = created_at
WHERE task_number IS NULL; 
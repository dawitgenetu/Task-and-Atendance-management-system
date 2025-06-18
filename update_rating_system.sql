USE attendance_management;

-- Drop existing task_ratings table if it exists
DROP TABLE IF EXISTS task_ratings;

-- Create task_ratings table with all necessary columns
CREATE TABLE task_ratings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    employee_id INT NOT NULL,
    manager_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    completed_on_time BOOLEAN DEFAULT TRUE,
    completion_time_rating DECIMAL(3,2) DEFAULT 0.00,
    attendance_rating DECIMAL(3,2) DEFAULT 0.00,
    overall_rating DECIMAL(3,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(id),
    FOREIGN KEY (manager_id) REFERENCES users(id)
);

-- Create or replace view for employee performance metrics
CREATE OR REPLACE VIEW employee_performance_metrics AS
SELECT 
    u.id as employee_id,
    u.first_name,
    u.last_name,
    COUNT(t.id) as total_tasks_assigned,
    SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as total_tasks_completed,
    SUM(CASE 
        WHEN t.status = 'completed' AND t.updated_at <= t.due_date THEN 1 
        ELSE 0 
    END) as tasks_completed_on_time,
    SUM(CASE 
        WHEN t.status = 'completed' AND t.updated_at > t.due_date THEN 1 
        ELSE 0 
    END) as tasks_completed_late,
    (
        SELECT COUNT(*) 
        FROM attendance a 
        WHERE a.employee_id = u.id 
        AND a.status = 'present'
    ) as present_days,
    (
        SELECT COUNT(*) 
        FROM attendance a 
        WHERE a.employee_id = u.id
    ) as total_working_days,
    COALESCE(AVG(tr.overall_rating), 0) as average_task_rating,
    COALESCE(AVG(tr.completion_time_rating), 0) as average_completion_time_rating,
    COALESCE(AVG(tr.attendance_rating), 0) as average_attendance_rating
FROM users u
LEFT JOIN tasks t ON u.id = t.assigned_to
LEFT JOIN task_ratings tr ON t.id = tr.task_id
WHERE u.role_id = (SELECT id FROM roles WHERE role_name = 'employee')
GROUP BY u.id, u.first_name, u.last_name; 
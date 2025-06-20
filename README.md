# Work & Attendance Management System

A complete PHP-based web application for managing employee attendance and tasks. Built with pure PHP and styled with Tailwind CSS.

## Features

- Role-based access control (Admin, Manager, Employee)
- Employee attendance tracking (clock in/out)
- Task management system
- User management
- Modern and responsive UI with Tailwind CSS

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (for autoloading)

## Installation

1. Clone the repository to your web server's document root:
   ```bash
   git clone <repository-url>
   cd attendance-management
   ```

2. Create a MySQL database and import the schema:
   ```bash
   mysql -u root -p < database.sql
   ```

3. Configure the database connection:
   - Open `config/database.php`
   - Update the database credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'your_username');
     define('DB_PASS', 'your_password');
     define('DB_NAME', 'attendance_management');
     ```

4. Set up the web server:
   - For Apache, ensure mod_rewrite is enabled
   - Point the document root to the project directory
   - Ensure the web server has write permissions for session handling

5. Access the application:
   - Open your web browser and navigate to the project URL
   - Default admin credentials:
     - Username: admin
     - Password: admin123

## Directory Structure

```
attendance-management/
├── config/
│   └── database.php
├── includes/
│   ├── header.php
│   ├── footer.php
│   └── session.php
├── database.sql
├── index.php
├── login.php
├── logout.php
├── dashboard.php
├── tasks.php
├── users.php
├── mark_attendance.php
├── view_task.php
└── unauthorized.php
```

## Security Features

- Password hashing using PHP's password_hash()
- Prepared statements for all database queries
- Session-based authentication
- Role-based access control
- Input validation and sanitization
- XSS protection through output escaping

## Usage

### Admin
- Manage all users
- View attendance reports
- Create and manage tasks
- Access all system features

### Manager
- Assign tasks to employees
- Track employee attendance
- Monitor task progress
- View team statistics

### Employee
- Mark daily attendance
- View assigned tasks
- Update task status
- View personal attendance history

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request#   T a s k - a n d - A t e n d a n c e - m a n a g e m e n t - s y s t e m  
 
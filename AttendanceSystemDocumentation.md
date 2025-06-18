# 📘 Attendance System Project Documentation

---

## 1. Introduction

### Overview
The Attendance System is a robust, web-based application designed to streamline employee attendance tracking, task management, and performance evaluation for organizations of all sizes. Built with PHP, MySQL, and Tailwind CSS, the system provides a secure, user-friendly interface for administrators, managers, and employees.

### Purpose & Goals
- **Automate** attendance and task workflows
- **Enhance** transparency and accountability
- **Provide** real-time insights and reporting
- **Support** role-based access and security

### Technologies Used
- **Backend:** PHP 7.4+
- **Database:** MySQL/MariaDB
- **Frontend:** HTML5, Tailwind CSS, JavaScript
- **Other:** Composer (optional), Apache/Nginx, XAMPP/Laragon (for local dev)

### Project Audience
- **Admins:** System setup, user management, reporting
- **Managers:** Task assignment, attendance review, performance evaluation
- **Employees:** Attendance marking, task acceptance, self-assessment

---

## 2. Features

### User Roles
- **Admin:** Full system access, user and report management
- **Manager:** Task assignment, attendance and performance review
- **Employee:** Attendance marking, task acceptance, dashboard view

### Core Features
- **Employee Attendance Tracking:**
  - Clock in/out with video/photo verification
  - Daily, weekly, and monthly attendance logs
- **Task Assignment & Workflow:**
  - Managers assign tasks to employees
  - Employees accept/reject tasks
  - Task status: Pending, In Progress, Completed, Cancelled
- **Employee Rating:**
  - Ratings based on task completion, punctuality, and attendance
  - Visual dashboard for performance trends
- **Report Generation:**
  - Attendance and task reports (CSV, PDF export)
  - Filter by date, employee, status
- **Authentication System:**
  - Secure login/logout
  - Password encryption
  - Session management
- **Notifications:**
  - Real-time alerts for tasks, attendance, and system events
  - Mark as read, delete, or bulk actions
- **Responsive UI:**
  - Mobile-friendly, accessible design

---

## 3. System Architecture

### High-Level Architecture Diagram
```
[Client Browser]
     |
     v
[Frontend (HTML, Tailwind CSS, JS)]
     |
     v
[PHP Controllers/Views]
     |
     v
[MySQL Database]
```
*For a visual diagram, place an image here: `/docs/architecture.png`*

### Backend Structure
- **MVC Pattern (Recommended):**
  - `models/` — Database models
  - `views/` — HTML/PHP templates
  - `controllers/` — Business logic
- **File Structure Example:**
```
includes/
  ├── header.php
  ├── footer.php
  ├── sidebar.php
  ├── session.php
config/
  └── database.php
public/
  ├── index.php
  ├── login.php
  ├── dashboard.php
  ├── attendance.php
  ├── mark_attendance.php
  └── ...
```

### Frontend Integration
- **Tailwind CSS** for utility-first, responsive design
- **JavaScript** for modals, AJAX, and interactivity

### Database Design
- **ERD (Entity Relationship Diagram):**
  - Place ERD image at `/docs/erd.png`
- **Main Tables:**
  - `users`, `roles`, `attendance`, `tasks`, `notifications`, `ratings`

---

## 4. Installation Guide

### Requirements
- PHP 7.4 or higher
- MySQL/MariaDB
- Apache/Nginx (XAMPP, Laragon, etc.)
- Composer (optional)

### Step-by-Step Setup
1. **Clone the Repository:**
   ```bash
   git clone https://github.com/your-org/attendance-system.git
   cd attendance-system
   ```
2. **Database Setup:**
   - Create a new MySQL database (e.g., `attendance_db`).
   - Import the provided SQL schema:
     ```sql
     -- In phpMyAdmin or MySQL CLI
     SOURCE /path/to/schema.sql;
     ```
   - Update `config/database.php` with your DB credentials.
3. **Environment Configuration:**
   - Set timezone and base URL in `includes/config.php`.
   - Ensure `uploads/` is writable:
     ```bash
     chmod -R 775 uploads/
     ```
4. **Run Locally:**
   - Start Apache/MySQL via XAMPP or similar.
   - Visit `http://localhost/attendance-system` in your browser.

---

## 5. Usage Guide

### Logging In
- Visit `/login.php` and enter credentials for Admin, Manager, or Employee.

### Recording Attendance
- Employees use `/mark_attendance.php` to clock in/out (with video/photo capture).
- Attendance status and history are visible on the dashboard.

### Task Assignment & Acceptance
- Managers assign tasks via `/tasks.php`.
- Employees accept/reject tasks from their dashboard.
- Task status updates in real time.

### Generating Reports
- Admins/Managers use `/reports.php` to generate and export attendance/task reports.

### Viewing Ratings & Dashboard
- Employees and managers can view performance ratings and analytics on the dashboard.

---

## 6. API Endpoints

> **Note:** If your project exposes RESTful APIs, document them here. Otherwise, skip this section.

| Endpoint                        | Method | Description                        | Sample Request/Response |
|----------------------------------|--------|------------------------------------|------------------------|
| `/api/login`                    | POST   | User authentication                | `{ "username": "...", "password": "..." }` |
| `/api/attendance`               | GET    | List attendance records            | `{ "user_id": 1 }`    |
| `/api/attendance/mark`          | POST   | Mark attendance (clock in/out)     | `{ "user_id": 1, "action": "clock_in" }` |
| `/api/tasks`                    | GET    | List tasks                         | `{ "assigned_to": 2 }`|
| `/api/tasks/assign`             | POST   | Assign a new task                  | `{ "title": "...", "assigned_to": 2 }` |
| `/api/notifications`            | GET    | List notifications                 | `{ "user_id": 1 }`    |

*Add more endpoints as needed. Include sample JSON for requests and responses.*

---

## 7. Database Schema

### Table: `users`
| Field         | Type         | Constraints         | Description           |
|---------------|--------------|--------------------|-----------------------|
| id            | INT          | PRIMARY KEY, AUTO  | User ID               |
| username      | VARCHAR(50)  | UNIQUE, NOT NULL   | Login username        |
| password      | VARCHAR(255) | NOT NULL           | Hashed password       |
| first_name    | VARCHAR(50)  | NOT NULL           |                      |
| last_name     | VARCHAR(50)  | NOT NULL           |                      |
| email         | VARCHAR(100) | UNIQUE, NOT NULL   |                      |
| role_id       | INT          | FK -> roles(id)    | User role             |

### Table: `roles`
| Field      | Type         | Constraints         | Description           |
|------------|--------------|--------------------|-----------------------|
| id         | INT          | PRIMARY KEY, AUTO  | Role ID               |
| role_name  | VARCHAR(20)  | UNIQUE, NOT NULL   | (admin, manager, employee) |

### Table: `attendance`
| Field         | Type         | Constraints         | Description           |
|---------------|--------------|--------------------|-----------------------|
| id            | INT          | PRIMARY KEY, AUTO  | Attendance ID         |
| employee_id   | INT          | FK -> users(id)    | Employee              |
| date          | DATE         | NOT NULL           | Attendance date       |
| clock_in      | DATETIME     |                    | Clock in time         |
| clock_out     | DATETIME     |                    | Clock out time        |
| status        | VARCHAR(20)  |                    | present/absent/late   |
| video_path    | VARCHAR(255) |                    | Video file path       |
| video_path_out| VARCHAR(255) |                    | Clock out video path  |

### Table: `tasks`
| Field         | Type         | Constraints         | Description           |
|---------------|--------------|--------------------|-----------------------|
| id            | INT          | PRIMARY KEY, AUTO  | Task ID               |
| title         | VARCHAR(100) | NOT NULL           | Task title            |
| description   | TEXT         |                    |                      |
| assigned_to   | INT          | FK -> users(id)    | Employee              |
| assigned_by   | INT          | FK -> users(id)    | Manager/Admin         |
| status        | VARCHAR(20)  |                    | pending/completed     |
| created_at    | DATETIME     |                    |                      |
| due_date      | DATE         |                    |                      |

### Table: `notifications`
| Field         | Type         | Constraints         | Description           |
|---------------|--------------|--------------------|-----------------------|
| id            | INT          | PRIMARY KEY, AUTO  | Notification ID       |
| user_id       | INT          | FK -> users(id)    | Recipient             |
| title         | VARCHAR(100) |                    |                      |
| message       | TEXT         |                    |                      |
| type          | VARCHAR(20)  |                    | info/warning/error    |
| is_read       | TINYINT(1)   | DEFAULT 0          |                      |
| created_at    | DATETIME     |                    |                      |

*Add more tables as needed (e.g., ratings, reports).* 

---

## 8. Admin, Manager & Employee Workflows

### Admin Workflow
1. Log in as Admin
2. Create Manager and Employee accounts
3. Monitor attendance and task reports
4. Generate and export reports

### Manager Workflow
1. Log in as Manager
2. Assign tasks to employees
3. Review attendance and task completion
4. Rate employee performance
5. Generate reports for their team

### Employee Workflow
1. Log in as Employee
2. Mark attendance (clock in/out)
3. View and accept/reject assigned tasks
4. Track personal performance and attendance

---

## 9. Security Practices

- **Authentication:**
  - Secure login with hashed passwords (bcrypt)
  - Session-based authentication
- **Password Encryption:**
  - All passwords stored as hashes
- **Session Handling:**
  - Regenerate session IDs on login
  - Session timeout and logout
- **Role-Based Access Control (RBAC):**
  - Restrict access to pages and actions based on user role
  - Validate permissions on every request
- **Input Validation:**
  - Sanitize all user inputs (server and client side)
- **Other:**
  - Use HTTPS in production
  - Regularly update dependencies

---

## 10. Future Improvements

- **Biometric or Facial Recognition:**
  - Integrate with webcam or mobile camera for advanced attendance verification
- **Mobile Version:**
  - Develop a mobile app or PWA for on-the-go access
- **Push/Email Notifications:**
  - Real-time alerts for important events
- **Advanced Analytics:**
  - More detailed dashboards and reporting
- **Leave Management:**
  - Add leave request and approval workflows
- **Multi-language Support:**
  - Internationalization for global teams
- **Audit Logs:**
  - Track all critical actions for compliance

---

*End of Documentation* 
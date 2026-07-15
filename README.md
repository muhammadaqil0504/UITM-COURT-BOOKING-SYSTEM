# UiTM Court Booking System

## Overview

The **UiTM Court Booking System** is a web-based application developed to simplify the process of reserving sports courts at Universiti Teknologi MARA (UiTM). The system enables students to register, log in, book available courts, manage their bookings, receive notifications, and update their account information. Administrators can efficiently manage courts, bookings, reports, time slots, and system settings through a dedicated administration dashboard.

---

# Features

## Student Module

- Register Account
- Login and Logout
- Dashboard
- Book Court
- View My Bookings
- Booking History
- Notifications
- Account Settings

## Administrator Module

- Admin Dashboard
- Booking Management
- Court Management
- Slot Management
- Reports and Analytics
- System Settings

---

# Technology Stack

| Layer | Technology | Description |
|-------|------------|-------------|
| Frontend | HTML5, CSS3, JavaScript, Bootstrap 5 | Develops a responsive and user-friendly web interface. |
| Backend | PHP 8.2 (PDO) | Handles business logic, booking management, authentication, and database communication. |
| Database | MySQL 8.0 | Stores user accounts, court information, bookings, reports, and system settings. |
| Authentication | PHP Session Authentication | Manages secure login, session handling, and role-based access control. |
| Version Control | Git & GitHub | Tracks source code changes and project development. |
| Deployment | Docker & Docker Compose | Provides a consistent and portable development and deployment environment. |

---

# Project Structure

```text
.
├── public/
│   ├── index.php
│   ├── login.php
│   ├── register.php
│   ├── dashboard.php
│   ├── book-court.php
│   ├── my-booking.php
│   ├── history.php
│   ├── notifications.php
│   ├── settings.php
│   ├── admin-dashboard.php
│   ├── admin-booking.php
│   ├── court-management.php
│   ├── slot-management.php
│   ├── reports.php
│   ├── settings-admin.php
│   ├── db.php
│   ├── *.css
│   └── images/
│
├── docker-compose.yml
├── Dockerfile
├── init.sql
├── README.md
```

---

# Prerequisites

Before running this project, ensure the following software is installed:

- Docker Desktop
- Docker Compose

Verify the installation using:

```bash
docker --version
docker compose version
```

---

# Installation Guide

## Step 1: Clone the Repository

```bash
git clone https://github.com/your-username/uitm-court-booking-system.git

cd uitm-court-booking-system
```

---

## Step 2: Build and Start the Containers

```bash
docker compose up --build -d
```

This command will:

- Build the PHP application container
- Create the MySQL database container
- Initialize the database using `init.sql`

---

## Step 3: Access the Application

Open your web browser and visit:

```
http://localhost:8081
```

---

# Environment Variables

The application uses the following database configuration:

```env
DB_HOST=db
DB_NAME=uitm_court_db
DB_USER=uitm_user
DB_PASSWORD=uitm_password
```

---

# Docker Commands

### Start the containers

```bash
docker compose up -d
```

### Stop the containers

```bash
docker compose down
```

### Rebuild the containers

```bash
docker compose up --build
```

### View running containers

```bash
docker ps
```

### View application logs

```bash
docker compose logs
```

---

# Default Administrator Account

| Username | Password |
|----------|----------|
| admin@gmail.com | 123 |

Students can create a new account using the **Register** page.

---

# Security Features

- PHP Session Authentication
- Password Hashing using `password_hash()`
- Password Verification using `password_verify()`
- Role-Based Access Control
- Prepared SQL Statements (PDO)
- Server-Side Input Validation

---

# Future Enhancements

- Email Notification Integration
- QR Code Check-in
- Online Payment Gateway
- Calendar Synchronization
- Mobile Optimization
- Multi-language Support

---

# Author

Developed as an academic project for the **Bachelor of Computer Science (Mobile Computing)**, Universiti Teknologi MARA (UiTM).

---

# License

This project is developed for educational purposes only.

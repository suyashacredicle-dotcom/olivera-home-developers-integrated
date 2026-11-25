# OLIVERA HOME DEVELOPERS – Backend & CRM Management System

A PHP and MySQL-based backend system developed for **Olivera Home Developers Pvt. Ltd.** to manage property enquiries, customers, sales leads, employees, and administrative operations.

The project provides a customer enquiry funnel integrated with a centralized CRM and a role-based administration panel.

## 🚀 Project Overview

The system is designed to help manage real estate business operations, capture customer enquiries from digital advertisements, organize sales leads, and monitor employee activities.

### Main Features

#### 1. Customer Property Enquiry System
- Two-step property enquiry form.
- Customer name, email address, and mobile number collection.
- Property budget selection.
- Preferred location selection.
- Plot size and customer address collection.
- Responsive enquiry interface.
- Company logo integration.
- No customer login or signup required for submitting enquiries.

#### 2. Advertisement Tracking
Supports tracking of enquiries received through:
- Facebook Ads
- Instagram Ads
- Google Ads
- YouTube
- Website visits
- Other advertising sources

Tracking parameters include:
- UTM Source
- UTM Medium
- UTM Campaign
- UTM Content
- UTM Term
- Facebook Click ID (`fbclid`)
- Google Click ID (`gclid`)
- Other advertising click identifiers

#### 3. CRM Lead Management
- Automatic CRM lead creation from property enquiries.
- Customer and enquiry record linking.
- Lead assignment and reassignment.
- Lead status management.
- Lead priority management.
- Customer information management.
- Lead notes and follow-ups.
- Lead activity history.
- Sales employee lead management.
- Lead search and filtering.
- CSV import/export functionality.

#### 4. Site Visit Management
- Site visit scheduling.
- Rescheduling and cancellation.
- Visit history.
- Calendar management.
- Employee-wise visit management.
- Site visit reports and analytics.

#### 5. Customer Management
- Customer records management.
- Customer search.
- Customer activation and deactivation.
- Customer information updates.
- Customer and CRM lead synchronization.

#### 6. Employee and Admin Management
- Role-based access control.
- Super Admin management.
- Admin management.
- Editor access.
- Sales Employee access.
- Employee account management.
- Lead assignment controls.
- Admin activity logs.

#### 7. Gallery Management
- Photo upload management.
- Public Website visibility.
- Employees Only visibility.
- Gallery editing and deletion.
- Separate public and employee gallery access.
- Protected employee-only images.

#### 8. Reports and Analytics
- CRM reports.
- Lead status reports.
- Employee performance reports.
- Site visit analytics.
- Advertisement source tracking.
- Campaign tracking information.

#### 9. Security Features
- Session-based authentication.
- Role and permission checks.
- CSRF protection.
- Password hashing for existing password-based accounts.
- Prepared SQL statements using PDO.
- Input validation and output escaping.
- Secure file upload validation.
- Protected employee-only gallery images.
- Database transactions for related record updates.

## 🛠️ Technology Stack

| Technology | Purpose |
|---|---|
| PHP | Backend development |
| MySQL | Database management |
| PDO | Database connectivity |
| HTML5 | Page structure |
| CSS3 | Styling and responsive layouts |
| JavaScript | Frontend interactions |
| Apache | Local web server |
| XAMPP | Local development environment |
| phpMyAdmin | Database administration |

## 📂 Project Structure

```text
mywebsite/
│
├── admin/
│   ├── actions/
│   ├── includes/
│   ├── dashboard.php
│   ├── login.php
│   ├── manage_leads.php
│   ├── manage_customers.php
│   ├── property_enquiries.php
│   ├── view_lead.php
│   └── ...
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── config/
│   └── database.php
│
├── includes/
│   └── ...
│
├── uploads/
│   └── gallery/
│
├── property-enquiry-start.php
├── property-enquiry.php
├── public-gallery.php
├── gallery_public_image.php
├── index.php
├── login.php
├── signup.php
└── README.md
```

*The structure above is a simplified overview; additional files and folders are present in the project.*

## 💻 Installation and Setup

### Requirements
- Windows, Linux, or macOS
- XAMPP or another PHP/MySQL environment
- PHP
- MySQL
- Apache

### Step 1: Clone the Repository

```bash
git clone YOUR_GITHUB_REPOSITORY_URL
```

### Step 2: Move the Project

For XAMPP, place the project inside:

```text
C:\xampp\htdocs\mywebsite
```

### Step 3: Start XAMPP

Start:
- Apache
- MySQL

### Step 4: Create the Database

Open:

```text
http://localhost/phpmyadmin/
```

Create a database named:

```text
mywebsite_db
```

Import the project's database SQL backup if available.

### Step 5: Configure Database Connection

Open:

```text
config/database.php
```

Configure the database hostname, database name, username, and password according to your local environment.

Do not commit production database credentials to GitHub.

### Step 6: Open the Website

Customer Enquiry Form:

```text
http://localhost/mywebsite/property-enquiry-start.php
```

Admin Login:

```text
http://localhost/mywebsite/admin/login.php
```

Admin Dashboard:

```text
http://localhost/mywebsite/admin/dashboard.php
```

phpMyAdmin:

```text
http://localhost/phpmyadmin/
```

## 🗄️ Database

The application uses MySQL tables for managing:
- Customers
- Property enquiries
- CRM leads
- Admin accounts
- Employee accounts
- Roles and permissions
- Gallery photos
- Follow-ups
- Site visits
- Activity logs
- Website settings

The exact database schema should be taken from the project's SQL database export.

## 🔐 Important Security Notes

Before deploying this application to a live server:

1. Configure secure production database credentials.
2. Enable HTTPS.
3. Keep configuration files and passwords out of public repositories.
4. Review file and folder permissions.
5. Configure secure session cookies.
6. Review access permissions for all admin pages.
7. Back up the database regularly.
8. Configure production error logging.
9. Review upload restrictions and server configuration.

## 📌 Project Status

The backend includes the customer enquiry funnel, CRM lead integration, customer management, role-based administration, gallery management, and reporting functionality.

Further frontend integration, deployment preparation, and comprehensive production verification may be required.

## 👨‍💻 Project

**Company:** Olivera Home Developers Pvt. Ltd.

**Project:** Real Estate Website Backend & CRM Management System

**Primary Technologies:** PHP, MySQL, HTML, CSS, JavaScript

---

*Developed to streamline property enquiries, customer management, sales operations, and real estate CRM workflows.*
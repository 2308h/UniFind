# UniFind Smart — Marwadi University Lost & Found System

UniFind Smart is a production-grade, procedural **PHP 8.2 & MySQLi** web application built specifically for **Marwadi University**. It features a strict **Dual-Dashboard Architecture** separating the **Student Portal** (Mobile-First visual card feed with gamification points and rewards) from the **Admin Portal** (Desktop executive dashboard with verification queues, Chart.js analytics, and CSV exports).

---

## 🏗️ Project Architecture & Blueprint

```text
unifind_smart/
├── config.php                 # Database connection, CSRF protection, auth guards, 2MB file uploader
├── schema.sql                 # Complete database schema & seed data (Marwadi University context)
├── style.css                  # CSS design system (.student-theme & .admin-theme)
├── header.php                 # Global HTML header partial
├── footer.php                 # Global HTML footer partial
├── index.php                  # Authentication Portal & Login Router
├── register.php               # Student/Staff registration with SEAMLESS AUTO-LOGIN
├── logout.php                 # Session destruction
├── README.md                  # System Documentation & Submission Guide
├── uploads/                   # Media directory for uploaded item photos
│
├── student/                   # 🎓 STUDENT PORTAL
│   ├── dashboard.php          # Visual Feed of Found/Lost items (Card layout)
│   ├── report_item.php        # Form with photo upload tool (Max 2MB)
│   ├── my_activity.php        # Track "My Reports", "My Claims", and "Saved Bookmarks"
│   ├── smart_matches.php      # Match alert engine comparing lost vs found items
│   ├── notifications.php      # In-app notification inbox
│   └── submit_claim.php       # Form to submit confidential proof of ownership
│
└── admin/                     # 🛡️ ADMIN PORTAL
    ├── dashboard.php          # Analytics charts & KPI stat cards
    ├── verification_queue.php # Moderation queue to approve/reject new item submissions
    ├── resolve_claims.php     # Review proof of ownership & process handovers (+50 finder points)
    ├── manage_users.php       # Student reward points, badge levels, and audit logs
    ├── analytics.php          # Chart.js 30-day trends and category distribution
    └── export_csv.php         # Downloadable CSV report exporter
```

---

## 🚀 Quick Setup Instructions

### 1. Database Setup

1. Start MySQL/MariaDB server (e.g. XAMPP / WAMP / Laragon).
2. Create or import the database using `schema.sql`:
   ```bash
   mysql -u root -p < schema.sql
   ```
   _The script automatically creates database `unifind_smart` and populates seed users & items._

### 2. Configure Database Connection (Optional)

If your MySQL credentials differ from default (`localhost`, `root`, no password), update constants in `config.php`:

```php
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'unifind_smart');
```

### 3. Run Application

Run PHP built-in web server from the project root:

```bash
php -S localhost:8000
```

Open **[http://localhost:8000/index.php](http://localhost:8000/index.php)** in your web browser.

---

## 🔑 Default Accounts

| Role         | Email                  | Password       | Administrative Key |
| :----------- | :--------------------- | :------------- | :----------------- |
| **Student**  | `student@unifind.edu`  | `Password123#` | N/A                |
| **Admin**    | `admin@unifind.edu`    | `Password123#` | `admin123`         |
| **Security** | `security@unifind.edu` | `Password123#` | `admin123`         |

---

## 🌟 Key Features Highlight

- **Seamless Auto-Login**: Upon registering an account on `register.php`, users are logged in immediately and taken straight to `student/dashboard.php`.
- **Finder Reward Points**: Students earn +10 points for reporting items and +50 points when an Admin completes a successful asset handover to the owner.
- **Verification Queue**: Found items require Admin approval before appearing publicly on the student community feed.
- **Chart.js Analytics**: Visual 30-day line trends and category distribution charts.
- **CSV Records Exporter**: Instant download of official campus records.
  Git workflow tested successfully.

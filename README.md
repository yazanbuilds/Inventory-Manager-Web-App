# Wholesale & Retail Inventory Manager (Web POS) 📦💼
> **Full-stack business inventory management and invoicing system built with PHP and SQLite.**

[![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-SQLite_/_PDO-003B57?logo=sqlite&logoColor=white)](https://www.sqlite.org/)
[![Architecture](https://img.shields.io/badge/Architecture-Modular_MVC-blue)](#)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

---

## 📌 Overview

**Wholesale & Retail Inventory Manager** is an intuitive, self-hosted web application designed to streamline inventory stock levels, vendor credit ledgers, sales tracking, and dynamic PDF invoicing for wholesale and retail trading businesses.

Built with lightweight PHP and SQLite via PDO, it requires zero heavy external database configurations—making it instantaneous to deploy on any standard web hosting environment or local server.

---

## ✨ Key Features

- **📊 Executive Dashboard:** Real-time visibility into total product counts, active vendors, recent invoices, 30-day net sales, and total outstanding vendor credit.
- **📦 Inventory & Stock Control:** Add, edit, and track product stock levels, cost prices, selling prices, categories, and SKU barcodes.
- **👥 Vendor Ledger & Credit Tracking:** Comprehensive vendor profiles, contact details, payment logs, and real-time ledger balance calculations.
- **🧾 Sales & Invoicing Module:** Instant invoice generation with itemized product lines, automatic totals, discounts, and payment status flags (Paid, Partial, Unpaid).
- **📄 Dynamic PDF Export:** Client-side and server-side PDF invoice generation for printing or digital receipts (invoice-pdf.php).
- **🔐 Secure Authentication:** Role-based session management with Bcrypt password hashing.

---

## 🏗️ Project Structure

``
├── api/                  # JSON API endpoints for AJAX queries
├── assets/               # CSS stylesheets, JavaScript utilities, branding
├── data/                 # SQLite database storage (AbayaWholesale.db)
├── includes/             # Core configurations, database helpers & ledger business logic
├── dashboard.php         # Main performance and metric overview
├── index.php             # Login portal & session checkpoint
├── invoices.php          # Invoice management and historical sales
├── invoice-pdf.php       # PDF generation and print template
├── payments.php          # Payment collection and vendor reconciliations
├── products.php          # Inventory catalog and stock adjustments
├── reports.php           # Financial reports and sales breakdowns
├── sales.php             # New sale checkout terminal
├── settings.php          # System and business profile preferences
└── vendors.php           # Vendor ledger management
``

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.0 or higher
- PDO SQLite extension enabled (php_pdo_sqlite)

### Quick Setup & Run (Built-in PHP Server)

1. **Clone the repository:**
   `ash
   git clone https://github.com/yazanbuilds/Inventory-Manager-Web-App.git
   cd Inventory-Manager-Web-App
   `

2. **Launch the local development server:**
   `ash
   php -S localhost:8000
   `

3. **Open in your browser:**
   `
   http://localhost:8000
   `

4. **Default Credentials:**
   - **Password:** dmin123

---

## 👨‍💻 Developed By

**Muhammad Yazan**  
*Full-Stack & Mobile Software Engineer*  
- **GitHub:** [@yazanbuilds](https://github.com/yazanbuilds)  
- **LinkedIn:** [linkedin.com/in/muhammad-yazan](https://www.linkedin.com/in/muhammad-yazan)  
- **Email:** [muhammad.yazan24@gmail.com](mailto:muhammad.yazan24@gmail.com)
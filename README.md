# LITEROS — Lite Restaurant Operating System

**Literos** is a full-featured, multi-branch **Restaurant Point of Sale (POS), Kitchen Display System (KDS), Floor & Table Manager, Menu Engineering Studio, and Hospitality ERP** built with **PHP 8+ (PDO)** and **MySQL**.

---

## ✨ Key Capabilities

1. **POS Register Terminal (`POS Terminal`)**
   - Fast category & Chef Favorites filtering, instant SKU/ingredient search (`/` hotkey), and course tags.
   - **Smart Dish Modifiers**: Upcharge parsing (e.g. `+$3`), allergen flags, cooking temperatures, and custom kitchen prep notes.
   - **Multi-Mode Billing**: `Dine-In`, `Takeaway`, and `Delivery` modes with automatic service charge toggling, percentage discounts (`10%`, `VIP 20%`), server tips, equal guest bill-splitting, and cash change calculation.
2. **Live Kitchen Display System (`Kitchen Display`)**
   - Real-time active order queue (`Pending`, `Preparing`, `Ready`) with elapsed minute timers, urgent ticket pulsing, item-level modifiers, and one-click **Mark Order Ready** / **Bump & Complete**.
3. **Interactive Dining Floor Plan (`Floor & Tables`)**
   - Visual table layout across **Main Hall**, **Patio Terrace**, **VIP Lounge**, and **Bar Counter** with live occupancy counts, active check totals, and one-click launch into the POS Register.
4. **Sales Ledger, Financial Analytics & Thermal Receipts (`Sales & Receipts`)**
   - Real-time Gross Revenue, Average Check Size, Tax & Gratuity totals, Payment Tender Mix (`Card`, `Cash`, `QR Pay`, `Split`), CSV export, and printable **80mm Thermal Receipts**.
5. **Menu & Recipe Cost Studio (`Menu Studio`)**
   - Create and edit dishes with automatic **Gross Profit Margin (`GP%`)** calculation, quick `+25` portion restocking, and instant `86'd / Sold Out` availability toggles.
6. **Sales Personnel & Commission Leaderboard (`Sales Personnel`)**
   - Manage Waiters, Cashiers, Managers, Chefs, and Admins across branches. Track order counts, total sales volume, and commission earnings, or switch active operators via **POS PIN Codes** (`1111`, `2222`, `3333`).
7. **Multi-Branch & Company HQ (`Branches` & `Company HQ`)**
   - Configure company branding, currency, default tax rate, dine-in service charge, receipt footer, and branch-level regional tax overrides.

---

## 🚀 Quick Start (XAMPP / MySQL)

1. Place this project in `c:\xampp\htdocs\literos`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open **`http://localhost/literos/`** in your browser.
   - **Zero-Config Auto-Installer**: On first load, [config/db.php](file:///c:/xampp/htdocs/literos/config/db.php) automatically creates the `literos` MySQL database and seeds all 9 tables from [database/schema.sql](file:///c:/xampp/htdocs/literos/database/schema.sql) (and even includes a seamless local PDO fallback if MySQL is temporarily offline).
4. **Demo Operator PINs**:
   - `1111` — **Elena Rostova** (Admin, SoHo Flagship)
   - `2222` — **Julian Sterling** (Cashier, SoHo Flagship)
   - `3333` — **Amara Okafor** (Waiter, SoHo Flagship)
   - `5555` — **Marcus Vance** (Manager, Williamsburg Grill)

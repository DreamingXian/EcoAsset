# EcoAsset – Collaborative Urban Asset & Damage Maintenance Platform

> **Course:** BMSE3004 / BMIS3004 Collaborative Development  
> **Architecture:** 3-Tier Architecture (Native Vanilla HTML5/CSS3/JavaScript + PHP 8.2 PSR-4 Object-Oriented MVC/DAO + Supabase PostgreSQL 17.6 & Cloud Storage)

---

## 📖 Project Overview

**EcoAsset** is a high-performance web platform designed to streamline and automate urban facility damage reporting, algorithmic hazard prioritization, proximity-based technician dispatching, and municipal lifecycle analytics. Built for the Visit Malaysia campaign and municipal governance (e.g., DBKL), EcoAsset eliminates maintenance delays by connecting citizen crowdsourcing directly with specialized maintenance field teams and administrative decision-makers.

---

## 🏗️ 3-Tier System Architecture

1. **Presentation Layer (Client GUI):**
   - **Public Damage Reporting Portal (PWA):** Mobile-first interactive Google Maps interface for citizens and tourists to browse damage pins, log live photo reports, and upvote hazards.
   - **Maintenance Crew Terminal:** Responsive mobile dashboard for field technicians featuring live 24h SLA countdown tickers, open maintenance pool self-claiming, external Google Maps turn-by-turn navigation, and on-site "After-Repair" photo proof submissions.
   - **Municipal Admin Portal:** Desktop-optimized operational control center featuring live priority queue inspection, manual & add-on crew assignments, dropped/invalid task dispute adjudication, user governance, and PDF export.
   - **Authentication Portal:** Secure role-based login, tourist self-registration, and token-based password recovery.

2. **Business Logic Layer (PHP 8.2 Application Server):**
   - **REST API Gateway & Custom Router (`api/index.php` & `router.php`):** Dispatches asynchronous JSON HTTP requests to modular PSR-4 controllers.
   - **JWT Authentication & RBAC Guard:** Issues signed JSON Web Tokens enforcing strict Role-Based Access Control across 3 system roles (`TOURIST`, `MAINTENANCE_CREW`, `ADMINISTRATOR`).
   - **Damage Reporting & EXIF Handler:** Validates live camera input, geospatial coordinates within 50m tolerance, and enforces 15-minute file decay timers.
   - **Prioritization Engine (Module 2):** Calculates dynamic multi-factor Priority Scores ($P_{\text{score}}$) combining hazard severity ($W_{\text{cat}}$), 200m cluster density ($D_{\text{zone}}$), logarithmic community upvotes ($U_{\text{count}}$), and reporter reputation trust multipliers.
   - **Proximity Dispatcher & Task Lifecycle Engine (Module 3):** Executes the spherical Haversine distance formula for instant single-report auto-dispatch ($\le 30\text{ km}$), manages scheduled batch dispatches (8:00 AM & 1:00 PM), tracks real-time 24h SLA windows, and handles dropped/invalid task dispute cycles.
   - **Analytics & PDF Generator (Module 4):** Computes regional KPIs, failure hotspot concentrations, resolution delays, and renders printable/downloadable analytics reports.

3. **Data Processing Layer (Persistence Storage):**
   - **Supabase PostgreSQL 17.6 Relational Database:** ACID-compliant storage with normalized tables (`users`, `tourist_profiles`, `maintenance_crews`, `damage_reports`, `report_upvotes`, `maintenance_tasks`, `system_audit_logs`).
   - **Supabase Cloud Object Storage (`ecoasset-media` bucket):** Cloud media repository for verified "Before" damage photos and "After-Repair" proof photos.

---

## 🧩 Core Modules Summary

| Module | Title | Key Features & Responsibilities |
| :--- | :--- | :--- |
| **Module 1** | **Public Crowdsourced Damage Reporting Portal** | HTML5 live camera capture (anti-gallery guard), 15-min decay timer, EXIF geospatial validation within 50m, Google Maps pin clustering, duplicate-protected upvoting, and invitation share links. |
| **Module 2** | **Algorithmic Prioritization Engine** | Real-time multi-factor $P_{\text{score}}$ calculations: $$P_{\text{score}} = \left[(W_{\text{cat}} \times 0.5) + (D_{\text{zone}} \times 0.3) + (\log_{10}(U_{\text{count}} + 1) \times 2.0)\right] \times \left(\frac{\text{Reputation}}{100.0}\right)$$ Dynamic priority queue re-sorting in PostgreSQL database upon report submissions and upvotes. |
| **Module 3** | **Proximity-Based Maintenance Dispatcher** | • **Instant Spatial Auto-Dispatch:** Assigns newly created reports to closest qualified technician ($\le 30$km) with 24h SLA.<br>• **Batch Auto-Dispatch:** Scheduled runs at 8:00 AM & 1:00 PM (or on-demand by admin).<br>• **Crew Terminal:** Live 1-second ticking SLA countdown, Open Pool claiming with transactional lock, Google Maps navigation, After-Repair photo submissions, task drops with penalty, and invalid report flagging.<br>• **Admin Task Control:** Manual & Add-On assignments with confirmation modals, dropped task rescheduling, pre-invalid flag dispute adjudication (approve flag with 20 rep penalty or reject with mandatory reason), and repair completion reviews. |
| **Module 4** | **Facility Lifecycle Analytics Dashboard** | High-level KPI ribbons (Total Reported, Active Queue, Avg Resolution Time, Available Crews), district failure hotspot heatmaps, resolution delay vs tourist footfall charts, crew workload summaries, and downloadable PDF report generation. |
| **Module 5** | **Authentication & User Management** | Secure login, tourist registration, signed JWT session tokens, active account status validation (blocking deactivated users), profile updates, token-based password reset, and administrative staff user directory (CRUD & Activate/Deactivate). |

---

## 🔑 Pre-Configured Demo Accounts (Password: `Pass1234!`)

All accounts are pre-seeded in the database and ready for instant testing:

| Role | Email Address | Password | Intended Portal / View |
| :--- | :--- | :--- | :--- |
| **Municipal Administrator** | `admin@ecoasset.my` *(or `admin@dbkl.gov.my`)* | `Pass1234!` | Admin Task Control Center & Analytics Dashboard |
| **Maintenance Crew (Structural)** | `crew@ecoasset.my` *(or `boonhuat@contractor.my`)* | `Pass1234!` | Mobile Crew Terminal (`/public/module3/index.html`) |
| **Maintenance Crew (Electrical)** | `selvam@contractor.my` | `Pass1234!` | Mobile Crew Terminal (`/public/module3/index.html`) |
| **Maintenance Crew (Sanitation)** | `kaiming@contractor.my` | `Pass1234!` | Mobile Crew Terminal (`/public/module3/index.html`) |
| **Tourist / Citizen** | `tourist@ecoasset.my` | `Pass1234!` | Public Interactive Map & Reporting Portal |

---

## 📂 Project Directory Structure

```text
EcoAsset/
├── .env                       # Pre-configured Supabase PostgreSQL & Storage configuration
├── .gitignore                 # Git ignore rules
├── database.sql               # Complete PostgreSQL schema definitions and seed data
├── router.php                 # PHP built-in development server router
├── setup_database.php         # Database setup & connectivity verification script
├── README.md                  # Main project documentation
├── SETUP_GUIDE.md             # Step-by-step setup and quickstart guide for evaluators
├── api/
│   └── index.php              # Central RESTful API Gateway & Routing Endpoint
├── public/                    # Frontend Client GUI (Tier 1)
│   ├── css/                   # Shared CSS styles
│   ├── js/                    # Shared client utilities (admin sidebar, auth helpers)
│   ├── module1/               # Module 1: Public Crowdsourced Reporting Portal
│   ├── module2/               # Module 2: Prioritization Engine Visual Queue
│   ├── module3/               # Module 3: Crew Terminal & Admin Task Control Center
│   │   ├── admin/             # Admin Task Control Dashboard
│   │   ├── index.html         # Crew Terminal UI
│   │   ├── terminal.js        # Crew Terminal logic & Real-time SLA ticker
│   │   └── profile.html       # Crew Profile screen
│   ├── module4/               # Module 4: Facility Lifecycle Analytics Dashboard
│   └── module5/               # Module 5: Authentication Portal (Login, Signup, Profile)
└── src/                       # Backend Business Logic (Tier 2)
    ├── autoload.php           # PSR-4 Modular Autoloader & .env Parser
    ├── config/                # Database PDO configuration
    ├── controller/            # MVC REST Controllers (Admin, Auth, Crew, Report, Task)
    ├── dao/                   # Data Access Objects (AnalyticsDAO, ReportDAO, TaskDAO, UserDAO)
    ├── model/                 # Entity models and ENUM definitions
    └── utils/                 # Utilities (JWTUtil, GeoUtil, StorageUtil, ResponseUtil)
```

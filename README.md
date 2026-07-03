# Industrial Attachment Management System (IAMS)

A role-based web platform that runs the entire student work-placement lifecycle:
matching students to organizations, tracking weekly logbooks, collecting supervisor
assessments, and giving coordinators live oversight — replacing the email-and-spreadsheet
workflow universities typically use.

## The four roles

| Role | What they can do |
|---|---|
| **Coordinator** | Match students to organizations, manage allocations, track submissions, send reminders, view ratings insights, generate reports |
| **Student** | View allocation, submit weekly logbooks and final reports, rate the host company, receive notifications |
| **Industrial supervisor** (host company) | Review assigned students, verify logbooks, submit progress reports |
| **University supervisor** | Record site-visit assessments (presentation, project knowledge, attitude) per visit |

## Features

- **Student–organization matching** with allocation management
- **Digital logbooks** — weekly submissions with supervisor verification
- **Assessment workflows** — structured scoring across multiple site visits
- **Notifications** — per-user, with read tracking and due dates
- **Ratings & insights** — students rate host companies; coordinators see trends
- **Login hardening** — attempt tracking with lockout, common-password blocklist,
  hashed passwords (`password_hash`), student-ID format validation

## Tech stack

| Layer | Tech |
|---|---|
| Backend | PHP 8 (mysqli) |
| Database | MySQL / MariaDB |
| Frontend | HTML, CSS (Bootstrap-styled alerts), vanilla JavaScript |
| Extras | Python launcher script (`launch_iams.py`) for local development |

## Getting started

1. Run a local PHP + MySQL stack (XAMPP/WAMP/LAMP, PHP 8.0+).
2. Create the database and import `database.sql`.
3. Adjust the constants at the top of `config/database.php`
   (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, MySQL port, `BASE_URL`).
4. Serve the project root with Apache and open `index.php` —
   or use `python launch_iams.py` to start everything in one step.

## Project structure

```
├── config/
│   ├── database.php        # DB connection + shared helpers (auth, notifications, logging)
│   └── icons.php
├── modules/
│   ├── coordinator/        # matching, allocations, reports, reminders, insights
│   ├── student/            # dashboard, logbooks, final report, ratings
│   ├── industrial_supervisor/
│   └── university_supervisor/
├── assets/                 # css, images, uploads (git-ignored at runtime)
├── database.sql            # schema
└── index.php               # login / role router
```

---

Built by **Kwame Boateng** ([@kwame12396](https://github.com/kwame12396)) — AI automation
& full-stack development.

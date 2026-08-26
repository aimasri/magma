# FussyBaby to Urban Sugar: Deployment & Scaling Roadmap

This document serves as the master blueprint and historical record for all infrastructure, deployment, and scaling decisions made for the FussyBaby and Urban Sugar platforms. It outlines the precise path from a single-vendor beta environment to a massive multi-tenant SaaS.

---

## 🏗️ Strategic Business Architecture

Moving forward, the architectural boundary between our brands is strictly defined:
- **Urban Sugar (The Core):** Urban Sugar is the core multi-tenant application, backend, and primary brand. All development focuses on the Urban Sugar core.
- **Fussy Baby (The Bespoke Client):** Fussy Baby transitions to becoming a bespoke, hosted front-end domain. It acts as a specialized client that seamlessly interacts with and consumes the Urban Sugar backend services.

---

## 🛑 Core Architectural Decisions (The "Why")

1. **Why Debian 12?**
   - **Decision:** The server OS will perfectly match the local development environment (Debian 12).
   - **Reason:** Guarantees "Dev/Prod Parity" so code that works locally will run flawlessly on the server.
2. **Why No cPanel?**
   - **Decision:** We use a tailored HestiaCP installation via SSH.
   - **Reason:** cPanel is bloated, expensive (£18/mo), and consumes ~1GB of RAM. A surgical HestiaCP install (Nginx, PHP, Postgres) keeps the server lightning fast and free.
3. **Why No Built-in Email Server?**
   - **Decision:** Server mail modules (Exim/ClamAV) are disabled. We use Resend/Mailgun for transactional app emails and Zoho/Google Workspace for the business inbox.
   - **Reason:** Anti-virus software consumes massive RAM, and self-hosted VPS IPs are instantly flagged as Spam by Gmail.
4. **Why Cloudflare R2 for Backups & Media?**
   - **Decision:** User uploads (images) and DB dumps are stored in Cloudflare R2 / AWS S3, not on the VPS hard drive.
   - **Reason:** Prevents the 25GB server SSD from filling up (which would crash the database) and ensures data is safe even if the server is destroyed.

---

## 🚀 Phase 1: FussyBaby Launch (Single-Vendor Beta) [COMPLETED]
*The goal is a highly-optimized, zero-maintenance, low-cost production environment for a single operating bakery.*

### 1. Infrastructure (Krystal Cloud VPS) [x]
- **Plan:** K1 Basic VPS (£10/month)
- **Specs:** 1 CPU Core, 1GB RAM, 25GB SSD
- **Network:** Krystal standard DNS (No Cloudflare CDN/WAF needed yet).

### 2. Server Provisioning [x]
- **OS:** Debian 12
- **Control Panel:** HestiaCP (Installed via CLI with Nginx, PHP-FPM 8.5, PostgreSQL. **Disabled:** Apache, MySQL, Mail, Anti-virus, DNS).
- **Security:** UFW Firewall (Ports 80, 443, 22 only). Password logins disabled (SSH Keys only).
- **Web Server Routing:** Custom `magma` Nginx template created in HestiaCP to properly route Front Controller traffic through `www/index.php`.

### 3. Backend Preparation & Daemons [x]
- **Environment Secrets:** Passwords (DB, Redis) securely stored in a `.env` file on the server (never in Git).
- **Supervisor (Daemons):**
  - Configured to keep `php bin/worker.php` (Redis consumer) alive 24/7.
  - Configured to keep `php bin/outbox_publisher.php` (CQRS Postgres poller) alive 24/7.
- **Cron Jobs (Scheduled Tasks):**
  - Daily 2:00 AM: `php bin/cleanup_tokens.php` (Database garbage collection).
  - Daily 3:00 AM: `pg_dump` automated database backup.

### 4. The Deployment Pipeline (CI/CD) [x]
- **GitHub Actions:** A `.github/workflows/deploy.yml` workflow automatically deploys to production on every push to the `main` branch.
- **Rsync:** Pushes code strictly to the `public_html` directory via an automated deploy SSH key.
- **Migrations:** Automatically executes `php bin/migrate.php` over SSH after code sync to keep the database schema safely updated.
- **Cache:** Automatically compiles Magma routes for OPcache speed.

---

## 🌍 Phase 2: Urban Sugar Beta (Multi-Tenant Launch)
*Transitioning to a multi-tenant SaaS supporting ~30+ concurrent vendors.*

### 1. Vertical Scaling (The 1-Click Upgrade)
- **Action:** Upgrade the Krystal VPS from K1 to **K3 or K4** (2-4 Cores, 4-8GB RAM).
- **Reason:** 1GB RAM cannot safely run Nginx, PostgreSQL, Redis, and dozens of concurrent PHP workers. The K3/K4 tier provides the "sweet spot" RAM buffer.

### 2. Security Upgrade (The Shield)
- **Action:** Point the domain DNS to **Cloudflare (Free Tier)**.
- **Reason:** Provides an Enterprise Web Application Firewall (WAF) to block malicious bots and DDoS attacks from hitting the server.

### 3. Staging Environment
- **Action:** Create a `staging.urbansugar.com` environment on the server.
- **Reason:** We can no longer push code straight to live. All code is tested on the staging clone to ensure zero downtime for paying customers.

---

## 🏢 Phase 3: Urban Sugar Enterprise (Massive Scale)
*Scaling to support hundreds of concurrent vendors.*

### 1. Database Separation (The Split)
- **Action:** Purchase a second K3 VPS (or Managed Database service) exclusively for PostgreSQL and Redis.
- **Reason:** Isolating the Web Server from the Database ensures that heavy UI traffic never starves the database of CPU/RAM, and vice versa. They communicate via an un-hackable Private Network cable.

### 2. Block Storage Expansion
- **Action:** Attach Katapult Block Storage to the Database server.
- **Reason:** If the database grows to 100GB, we pay £5/mo for storage rather than £100/mo for a massive CPU/RAM tier we don't need.

### 3. Horizontal Scaling (Load Balancing)
- **Action:** Spin up a second Web Server (App Server B) and put a Load Balancer in front of both.
- **Reason:** The Load Balancer splits traffic 50/50 between the servers, allowing the platform to scale infinitely as new vendors sign up.

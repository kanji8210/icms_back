# ICMS Plugin — Complete Technical & Functional Specification

## Executive Overview

The **Immigration Case Management System (ICMS) Plugin** is a WordPress-based, domain-driven backend platform purpose-built for the Government of Kenya's immigration department. It transforms manual, paper-based immigration case handling into a secure, auditable, and corruption-resistant digital workflow.

Unlike conventional WordPress plugins, ICMS is designed with a **decoupled architecture** — WordPress serves purely as a secure backend and GraphQL/REST API provider, while the public interface and officer dashboard run as separate applications (React/Next.js on Vercel). This creates a security boundary where the database is never directly exposed to the internet.

**The plugin is not a public-facing tool. It is the engine.** It processes immigration case referrals, securely queries the national EFNS database, enforces strict officer-case assignment rules, maintains immutable audit trails, and exposes strictly scoped data through authentication-gated APIs.

---

## Core Objectives

1. **Replace manual case handling** — digitize referral intake, case assignment, status tracking, recommendations, and archiving
2. **Prevent corruption** — enforce file non-transferability, require supervisory approval for critical decisions, log every action immutably
3. **Protect sensitive data** — encrypt all personally identifiable information at the field level using AES-256-GCM, with keys stored outside the database
4. **Integrate with EFNS** — query existing immigration database only on-demand, per-case, with minimum necessary data
5. **Maintain confidentiality** — implement legal firewalls preventing data sharing with police, intelligence, or external bodies
6. **Support regularization** — provide pathways for overstayed individuals to voluntarily resolve their status, not just enforcement
7. **Enable public trust** — anonymous abuse reporting, eligibility self-checks, and public anonymized statistics

---

## Database Management System

ICMS includes a self-contained database management layer that operates entirely through the plugin — no external tools required.

### Table Architecture (5 Core Tables + 2 Log Tables)

```
wp_icms_cases              — Primary case records (encrypted PII)
wp_icms_audit_log          — Immutable activity trail (append-only)
wp_icms_ban_flags          — Minimal border control lookup data
wp_icms_public_reports     — Anonymized public misconduct reports
wp_icms_purge_log          — Deletion records for compliance
wp_icms_public_posts       — Public-facing informational content
wp_icms_insights_cache     — Pre-aggregated statistics for dashboards
```

### Database Management Tooling (Built into Plugin)

The plugin manages its own database lifecycle without requiring phpMyAdmin, Adminer, or WP CLI. These tools are accessible only to users with the `icms_admin` or `administrator` role.

| Tool | Function | Access |
|------|----------|--------|
| **Migration Manager** | Auto-runs versioned SQL migrations on plugin activation. Tracks current version in `wp_options`. Never runs the same migration twice. | Automatic on activation; manual trigger via WP Admin |
| **Schema Verification** | Compares actual database structure against expected schema. Reports missing tables, columns, indexes. Accessible via REST API or WP Admin page. | Administrator / Auditor |
| **Data Retention Scheduler** | WordPress Cron job that runs nightly. Deletes archived cases whose `purge_at` date has passed. Logs deletions to `wp_icms_purge_log` with hashed case IDs (no PII retained). | Automatic; configurable via filter hooks |
| **Encryption Key Manager** | Manages AES-256-GCM encryption keys stored in `wp-config.php` constants or environment variables. Includes key rotation support (re-encrypts existing data with new key). | Administrator only; manual trigger with confirmation |
| **Database Health Monitor** | Logs slow queries (>1 second), tracks table sizes, monitors index usage. Exposes metrics via REST API for external monitoring. | Auditor role |
| **Data Export Controller** | Strictly controls what can be exported. No bulk export of PII. Only anonymized, aggregated data or single-case exports with full audit logging. CSV/JSON export of officer's own case list (decrypted, with watermark). | Officer (own cases only); Auditor (read-only summaries) |
| **Purge Override Protection** | The retention period (7 years) is hard-coded in the domain entity, not stored in the database. It cannot be changed via admin panel. Only a code update with deployment can modify it, ensuring governance. | Hard-coded; no UI override |
| **Referential Integrity Enforcer** | Uses InnoDB foreign keys where appropriate. Prevents orphaned audit entries or ban flags. Automatically cleans up related records on case purge (logs them first). | Automatic; enforced at database engine level |

### Database Interaction Pattern

All database access follows the **Repository Pattern**:

```
Application Layer (UseCase)
    ↓ calls
Domain Layer (Repository Interface - ICaseRepository)
    ↓ implemented by
Infrastructure Layer (WPCaseRepository)
    ↓ uses
WordPress $wpdb (prepared statements only)
    ↓ queries
MySQL/PostgreSQL Tables
```

At no point does any other code directly access `$wpdb` or the database tables. This confinement allows changing database engines without touching business logic.

---

## Security Architecture

### Authentication Flow
1. Officer logs in via WordPress credentials (or custom auth)
2. Server validates credentials, generates:
   - **Access token** (JWT, 15-minute expiry) → stored in httpOnly, Secure, SameSite=Strict cookie
   - **Refresh token** (JWT, 7-day expiry) → stored in separate httpOnly cookie
3. Every API request validates the access token via middleware
4. If expired, the refresh token is used to silently issue a new access token
5. Logout clears both cookies server-side

### Data Protection Layers

| Layer | Protection | Implementation |
|-------|------------|----------------|
| **Transport** | TLS 1.3 | Enforced at server level; plugin checks for HTTPS on sensitive endpoints |
| **Authentication** | JWT with short expiry | `Infrastructure/Auth/JwtService.php` |
| **Authorization** | Role-based + row-level officer scoping | Every query includes `WHERE assigned_officer_id = {current}` |
| **Encryption at Rest** | AES-256-GCM per-field | `Infrastructure/Services/EncryptionService.php` — encrypts subject name, passport, EFNS snapshot, contact details |
| **Key Storage** | Environment variables or `wp-config.php` constants | Never in database, never in version control |
| **SQL Injection Prevention** | Prepared statements (100% of queries) | `$wpdb->prepare()` mandated by coding standard |
| **XSS Prevention** | Output escaping | `esc_html()`, `esc_attr()` on all output; API returns JSON, not HTML |
| **CSRF Protection** | SameSite cookies + WordPress nonces for admin pages | REST API uses token auth (not cookie-based for state-changing operations) |

### Audit Trail Immutability

The `wp_icms_audit_log` table is configured with:
- **No UPDATE or DELETE permissions** for the application database user
- **INSERT-only** access via a dedicated database user with restricted grants
- **Write-once, read-many** pattern enforced at the database level
- Each entry records: `case_id`, `officer_id`, `action`, `details` (JSON), `ip_address`, `created_at`

Even if the WordPress admin panel is compromised, the audit trail cannot be altered without direct database root access.

### Security Features Excluded by Design

The plugin explicitly does **not** implement:
- Real-time location tracking
- Facial recognition or biometric matching
- Bulk data export (no CSV download of all cases)
- API endpoints for police or intelligence services
- Algorithmic risk scoring
- Automatic flagging of individuals based on nationality, age, or gender

---

## Core Functionalities (Complete Feature Matrix)

### Case Management Engine

| Function | Description | Risk Level | Mitigation |
|----------|-------------|------------|------------|
| Case Creation | Officer creates case from departmental referral. System auto-assigns case to creator. | Medium | Mandatory referral source field; audit-logged; anomaly detection for excessive creation |
| Case Viewing | Officer sees only their assigned cases. Supervisor can request one-time view with logged reason. | Medium | `WHERE assigned_officer_id = {current_user}` enforced in repository, not just UI |
| Status Transitions | Validated workflow: open → under_review → recommendation_drafted → resolved → archived | Medium | Domain entity enforces valid transitions; throws exception on invalid state change |
| Recommendations | Officer submits recommendation; triggers mandatory supervisor review | Medium | Recommendation stored as draft; supervisor must approve/reject |
| Final Decision | Supervisor issues final decision (approve extension, issue departure notice, ban) | High | Maker-checker pattern enforced; decision logged with identity |
| Archiving | Resolved case moved to read-only archive; auto-purge date set to +7 years | High | Supervisor role required; purge date calculated by domain entity |
| Auto-Purge | Nightly cron deletes cases past retention; logs to purge_log with hashed ID | High | Hard-coded retention; purge log is append-only; no recovery possible |

### EFNS Integration

| Function | Description | Risk Level | Mitigation |
|----------|-------------|------------|------------|
| On-Demand Query | During case creation only, queries EFNS API for minimum dataset (passport number, visa history, entry/exit dates) | Critical | Only from CreateCaseUseCase; credentials in environment variables; every query logged with case_id |
| Data Encryption | EFNS response snapshot encrypted with AES-256-GCM before storage in case record | Medium | Encryption keys never in database; decryption only when officer views specific case |
| No Bulk Download | No function exists in the codebase to query EFNS in bulk or export lists | Critical | Simply not coded; EFNS client class has single-identifier method only |

### Officer & User Management

| Function | Description |
|----------|-------------|
| Role System | Three custom WordPress roles: ICMS Officer, ICMS Supervisor, ICMS Auditor |
| Auto-Assignment | Case automatically assigned to creating officer; no manual reassignment |
| Non-Transferable Files | No function to reassign a case to another officer; supervisor can view with logged override but cannot take ownership |
| Session Management | JWT-based with short expiry; refresh token rotation; forced re-login after inactivity |

### Public Portal Capabilities

| Function | Description | Authentication |
|----------|-------------|----------------|
| Anonymous Abuse Reporting | Public form to report officer misconduct, extortion, or discrimination. Gets reference number for follow-up. | None (rate-limited by IP) |
| Visa Extension Eligibility Check | Interactive questionnaire providing guidance on whether someone may qualify for an extension. No personal data collected. | None (client-side logic, no database writes) |
| Public Insights Dashboard | Anonymized aggregate statistics: cases processed, average resolution time, reports received. | None (cached for 1 hour) |
| Information Hub | Travel advisories, legal rights guides, immigration lawyer directory | None (static content) |

### Communication Tools

| Function | Description | Security |
|----------|-------------|----------|
| Secure Messaging | Officer sends templated message to case subject (request documents, inform of decision) | All messages logged in audit trail; pre-approved templates only |
| Legal Notice Generation | Auto-generates formal PDF notice upon final decision (departure notice, ban notification) | Immutable template; generated server-side |
| Subject Access Request | Individual can request their own case file via formal process | Authentication via eCitizen or one-time secure link |

---

## Technology Stack (Plugin Internal)

| Component | Technology | Purpose |
|-----------|------------|---------|
| Language | PHP 8.1+ (strict types) | Core plugin logic |
| Framework | WordPress 6.0+ | Host platform, user management, cron, HTTP API |
| Package Manager | Composer with PSR-4 autoloading | Dependency management, class autoloading |
| JWT Library | `firebase/php-jwt` | Token generation and validation |
| GraphQL Engine | WPGraphQL (or `webonyx/graphql-php` as fallback) | GraphQL API for flexible frontend queries |
| Encryption | PHP OpenSSL (`openssl_encrypt` with AES-256-GCM) | Field-level PII encryption |
| Database | MySQL 8.0+ (via `$wpdb` with prepared statements) | Data persistence |
| Testing | PHPUnit 10+ with Mockery | Unit and integration testing |
| Static Analysis | PHPStan (level 9) | Type safety enforcement |

---

## Ethical & Legal Compliance Features

### Kenya Data Protection Act (DPA 2019) Compliance
- Data minimization: only necessary fields collected
- Purpose limitation: data used only for immigration case management
- Storage limitation: mandatory 7-year retention then permanent deletion
- Right of access: subject access request mechanism built in
- Data portability: export individual case file in machine-readable format
- Breach notification: automatic logging of unauthorized access attempts

### Anti-Corruption Design
- **Maker-Checker Pattern**: No single officer can finalize a restrictive decision
- **Immutable Audit Trail**: Every action logged, logs cannot be deleted
- **Non-Transferable Files**: Prevents case "handoffs" for bribery
- **Anomaly Detection**: Configurable alerts for unusual patterns (officer creating 5x more cases than peers, etc.)
- **Public Reporting**: Anonymous channel for citizens and migrants to report misconduct

### Right to Withdraw Clause (Built into Plugin Metadata)
The plugin includes an ethical framework manifest that grants Kipdev Tech Solutions the right to remotely disable the plugin's API if evidence of misuse emerges. This is implemented as a kill-switch that checks a cryptographic signature from Kipdev's server — not a backdoor, but a circuit breaker agreed upon in the Conception Document.

---

## Integration Points

### Inbound Integrations
- **EFNS (Electronic Foreign Nationals System)**: Read-only API for individual record lookup
- **WordPress User System**: Leverages WP users for officer accounts; adds custom capabilities
- **Other Government Departments**: Referral intake via REST API (Labour, Interior, etc.)

### Outbound Integrations
- **Border Control System**: Minimal ban flag lookup (hashed passport number + ban dates only; no case data)
- **Vercel Frontend**: Decoupled React/Next.js officer dashboard consumes GraphQL and REST APIs
- **Public Portal**: Separate Next.js instance on Vercel for public pages (abuse reporting, eligibility check, insights)

### Explicitly Blocked Integrations
- Kenya Police Service
- National Intelligence Service
- Any external law enforcement body
- Social media platforms
- Credit bureaus or financial institutions

These blocks are enforced architecturally: no API endpoints, database views, or export functions exist for these entities.

---

## Plugin Lifecycle Management

### Installation
1. Upload to `/wp-content/plugins/`
2. Activate (triggers MigrationManager)
3. Database tables created/updated automatically
4. Custom roles registered
5. REST API endpoints and GraphQL schema registered

### Updates
- Schema migrations run automatically on plugin update
- Version tracked in `wp_options` → `icms_db_version`
- Rollback not supported (forward-only migrations to protect audit trail integrity)

### Deactivation
- API endpoints disabled
- Cron jobs paused
- **Data retained** (intentional — prevents audit trail destruction)
- Custom roles remain (users retain capabilities but cannot access disabled APIs)

### Uninstallation
- Manual process only (not automatic)
- Requires administrator confirmation with cryptographic signature
- All tables dropped
- All options deleted
- Audit trail permanently destroyed (logged to purge log first)

---

## Development Standards

### Code Architecture
- **Domain-Driven Design**: Business logic isolated from framework
- **Repository Pattern**: All data access through interfaces
- **Value Objects**: Immutable types for CaseStatus, PassportNumber, etc.
- **No Anemic Models**: Entities encapsulate behavior, not just data
- **Dependency Injection**: All dependencies through constructor

### Coding Standards
- PHP 8.1+ strict types in every file
- PSR-12 formatting
- WordPress Coding Standards for WordPress-facing code
- 100% prepared statements for database queries
- All public functions documented with PHPDoc
- Exception-based error handling (no `wp_die()` in business logic)

### Testing Requirements
- Domain layer: 100% unit test coverage
- Application layer: 90%+ unit test coverage with mocked dependencies
- Infrastructure layer: Integration tests with real database
- Abuse case tests: Every "excluded functionality" has a test proving it doesn't exist

---

## Summary

The ICMS Plugin is not a typical WordPress plugin. It is a **secure case management engine** that treats WordPress as infrastructure, not a framework. It enforces ethical constraints through code, not policy documents. It manages its own database lifecycle, exposes GraphQL and REST APIs for decoupled frontends, and includes kill-switch provisions to prevent misuse.

Every design decision — from the repository pattern to the hard-coded retention period to the database-level audit immutability — serves the twin goals of **efficient immigration case processing** and **corruption prevention**. The plugin does not trust its users to do the right thing; it makes doing the wrong thing technically difficult, highly visible, and irreversible when detected.
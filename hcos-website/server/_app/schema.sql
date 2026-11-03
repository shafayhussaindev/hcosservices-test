-- HCOS website — MySQL schema
-- Applied by `php _app/cli.php migrate`. Every statement is idempotent
-- (CREATE TABLE IF NOT EXISTS) so the command is safe to re-run.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS events (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug              VARCHAR(120) NOT NULL UNIQUE,
  title             VARCHAR(200) NOT NULL,
  teaser            TEXT NULL,
  intro             TEXT NULL,
  hero_image        VARCHAR(255) NULL,
  hero_alt          VARCHAR(255) NULL,
  status            ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  is_upcoming       TINYINT(1) NOT NULL DEFAULT 0,
  registration_open TINYINT(1) NOT NULL DEFAULT 0,
  attendee_uploads  TINYINT(1) NOT NULL DEFAULT 1,
  sort_order        INT NOT NULL DEFAULT 0,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_events_status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The "Event details" box: free label/value rows (Date, Venue, Email ...).
CREATE TABLE IF NOT EXISTS event_details (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_id   INT UNSIGNED NOT NULL,
  label      VARCHAR(80) NOT NULL,
  value      VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_details_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  INDEX idx_details_event (event_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Speakers, panelists, guests. Every field is editable in the admin so a
-- misspelt name or wrong designation is a two-second fix.
CREATE TABLE IF NOT EXISTS event_people (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_id     INT UNSIGNED NOT NULL,
  role_group   VARCHAR(80) NOT NULL DEFAULT 'Speakers',
  name         VARCHAR(160) NOT NULL,
  designation  VARCHAR(200) NULL,
  organization VARCHAR(200) NULL,
  photo        VARCHAR(255) NULL,
  sort_order   INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_people_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  INDEX idx_people_event (event_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sponsors and partners, grouped by tier label ("Platinum Sponsor", "In Collaboration with").
CREATE TABLE IF NOT EXISTS event_orgs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_id    INT UNSIGNED NOT NULL,
  kind        ENUM('sponsor','partner') NOT NULL DEFAULT 'sponsor',
  group_label VARCHAR(120) NULL,
  name        VARCHAR(200) NOT NULL,
  logo        VARCHAR(255) NULL,
  url         VARCHAR(255) NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_orgs_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  INDEX idx_orgs_event (event_id, kind, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Brochure / catalogue, agenda pages, posters, and approved attendee documents.
CREATE TABLE IF NOT EXISTS event_materials (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_id   INT UNSIGNED NOT NULL,
  kind       ENUM('brochure','agenda','poster','document') NOT NULL DEFAULT 'agenda',
  title      VARCHAR(200) NULL,
  file       VARCHAR(255) NOT NULL,
  mime       VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_materials_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  INDEX idx_materials_event (event_id, kind, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photo gallery. Only approved photos ever land here (attendee uploads are
-- copied in when an admin approves them).
CREATE TABLE IF NOT EXISTS event_gallery (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_id        INT UNSIGNED NOT NULL,
  file            VARCHAR(255) NOT NULL,
  caption         VARCHAR(255) NULL,
  sort_order      INT NOT NULL DEFAULT 0,
  registration_id INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gallery_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  INDEX idx_gallery_event (event_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registrations (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_id     INT UNSIGNED NOT NULL,
  name         VARCHAR(160) NOT NULL,
  organization VARCHAR(200) NULL,
  designation  VARCHAR(200) NULL,
  email        VARCHAR(190) NOT NULL,
  phone        VARCHAR(40) NULL,
  linkedin_url VARCHAR(512) NULL,
  gender       VARCHAR(32) NULL,
  price_option VARCHAR(32) NULL,
  headshot     VARCHAR(255) NULL,
  status       ENUM('new','confirmed','attended','cancelled') NOT NULL DEFAULT 'new',
  upload_token CHAR(64) NOT NULL UNIQUE,
  ip           VARCHAR(45) NULL,
  user_agent   VARCHAR(255) NULL,
  note         VARCHAR(255) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reg_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  INDEX idx_reg_event (event_id, created_at),
  INDEX idx_reg_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos and documents attendees share through their private link. Nothing
-- here is public until an admin approves it.
CREATE TABLE IF NOT EXISTS registration_uploads (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  registration_id INT UNSIGNED NOT NULL,
  event_id        INT UNSIGNED NOT NULL,
  kind            ENUM('photo','document') NOT NULL,
  file            VARCHAR(255) NOT NULL,
  original_name   VARCHAR(255) NOT NULL,
  mime            VARCHAR(100) NOT NULL,
  size            INT UNSIGNED NOT NULL,
  status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at     DATETIME NULL,
  reviewed_by     INT UNSIGNED NULL,
  CONSTRAINT fk_upload_reg FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
  CONSTRAINT fk_upload_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  INDEX idx_uploads_status (status, created_at),
  INDEX idx_uploads_reg (registration_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(160) NULL,
  email      VARCHAR(190) NOT NULL,
  phone      VARCHAR(40) NULL,
  message    TEXT NOT NULL,
  ip         VARCHAR(45) NULL,
  status     ENUM('new','read','replied') NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_contact_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60) NOT NULL UNIQUE,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NULL,
  password_hash VARCHAR(255) NOT NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every login attempt, for throttling brute force per IP and per username.
CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(60) NULL,
  ip           VARCHAR(45) NOT NULL,
  succeeded    TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attempts_ip (ip, attempted_at),
  INDEX idx_attempts_user (username, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who changed what in the admin. Append-only.
CREATE TABLE IF NOT EXISTS audit_log (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  admin_id   INT UNSIGNED NULL,
  action     VARCHAR(60) NOT NULL,
  entity     VARCHAR(40) NULL,
  entity_id  INT UNSIGNED NULL,
  details    TEXT NULL,
  ip         VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed-window throttle for the public endpoints (contact, register, upload).
CREATE TABLE IF NOT EXISTS rate_limits (
  rl_key       VARCHAR(120) NOT NULL PRIMARY KEY,
  hits         INT UNSIGNED NOT NULL DEFAULT 0,
  window_start DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Footer visitor counter. One row.
CREATE TABLE IF NOT EXISTS visits (
  id    TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  count BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

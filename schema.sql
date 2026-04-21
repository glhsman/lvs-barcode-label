-- Drinkport-Barcode Python – MariaDB-Schema
-- Einmalig ausführen:  python db_setup.py
-- Voraussetzung: Datenbank 'drinkport_barcode' existiert bereits
-- Empfohlen: ALTER DATABASE drinkport_barcode CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Standorte ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS locations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    logo_data   LONGTEXT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Projekte ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS projects (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    location_id INT NOT NULL,
    name        VARCHAR(255) NOT NULL,
    description TEXT,
    csv_filename VARCHAR(255) NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    INDEX idx_name (name),
    INDEX idx_location (location_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Tabellenfelder (Spalten) pro Projekt ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS project_fields (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT NOT NULL,
    name        VARCHAR(100) NOT NULL,
    position    INT NOT NULL DEFAULT 0,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── Etikettenformat pro Projekt ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS label_formats (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    project_id       INT NOT NULL UNIQUE,
    template_id      INT NULL,
    manufacturer     VARCHAR(100),
    product_name     VARCHAR(100),
    width_mm         FLOAT NOT NULL DEFAULT 100.0,
    height_mm        FLOAT NOT NULL DEFAULT 50.0,
    margin_top_mm    FLOAT NOT NULL DEFAULT 2.0,
    margin_bottom_mm FLOAT NOT NULL DEFAULT 2.0,
    margin_left_mm   FLOAT NOT NULL DEFAULT 2.0,
    margin_right_mm  FLOAT NOT NULL DEFAULT 2.0,
    `cols`           INT   NOT NULL DEFAULT 1,
    `rows`           INT   NOT NULL DEFAULT 1,
    col_gap_mm       FLOAT NOT NULL DEFAULT 0.0,
    row_gap_mm       FLOAT NOT NULL DEFAULT 0.0,
    show_calibration_border TINYINT(1) DEFAULT 0,
    print_scale      FLOAT NOT NULL DEFAULT 100.0,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Etikettenobjekte (Text, Barcode, Grafik, Formen) ─────────────────────────
CREATE TABLE IF NOT EXISTS label_objects (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT NOT NULL,
    type        ENUM('text','barcode','image','rect','ellipse','line') NOT NULL,
    x_mm        FLOAT NOT NULL DEFAULT 0.0,
    y_mm        FLOAT NOT NULL DEFAULT 0.0,
    width_mm    FLOAT NOT NULL DEFAULT 20.0,
    height_mm   FLOAT NOT NULL DEFAULT 10.0,
    rotation    FLOAT NOT NULL DEFAULT 0.0,
    z_order     INT   NOT NULL DEFAULT 0,
    properties  JSON,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── Globale Etiketten-Vorlagen (nur Formate) ──────────────────────────────────
CREATE TABLE IF NOT EXISTS global_label_templates (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(255) NOT NULL,
    manufacturer     VARCHAR(100),
    product_name     VARCHAR(100),
    width_mm         FLOAT NOT NULL DEFAULT 100.0,
    height_mm        FLOAT NOT NULL DEFAULT 50.0,
    margin_top_mm    FLOAT NOT NULL DEFAULT 2.0,
    margin_bottom_mm FLOAT NOT NULL DEFAULT 2.0,
    margin_left_mm   FLOAT NOT NULL DEFAULT 2.0,
    margin_right_mm  FLOAT NOT NULL DEFAULT 2.0,
    `cols`           INT   NOT NULL DEFAULT 1,
    `rows`           INT   NOT NULL DEFAULT 1,
    col_gap_mm       FLOAT NOT NULL DEFAULT 0.0,
    row_gap_mm       FLOAT NOT NULL DEFAULT 0.0,
    UNIQUE INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Migration für bestehende Datenbanken (Drinkport-Barcode)
-- Führt fehlende Spalten zur Tabelle 'label_formats' hinzu und implementiert Standortverwaltung

-- CSV-Dateiname am Projekt speichern
ALTER TABLE projects ADD COLUMN IF NOT EXISTS csv_filename VARCHAR(255) NULL AFTER description;

-- Spalten für Kalibrierung und Skalierung (falls noch nicht vorhanden)
ALTER TABLE label_formats ADD COLUMN IF NOT EXISTS show_calibration_border TINYINT(1) DEFAULT 0;
ALTER TABLE label_formats ADD COLUMN IF NOT EXISTS print_scale FLOAT NOT NULL DEFAULT 100.0;
ALTER TABLE label_formats ADD COLUMN IF NOT EXISTS media_type ENUM('sheet','roll') NOT NULL DEFAULT 'sheet';
ALTER TABLE global_label_templates ADD COLUMN IF NOT EXISTS media_type ENUM('sheet','roll') NOT NULL DEFAULT 'sheet';

-- Standort-Tabelle erstellen
CREATE TABLE IF NOT EXISTS locations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    logo_data   LONGTEXT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Falls Tabelle schon existiert, logo_data hinzufügen
ALTER TABLE locations ADD COLUMN IF NOT EXISTS logo_data LONGTEXT NULL AFTER name;

-- Einen Standard-Standort anlegen, falls keiner existiert
INSERT INTO locations (id, name)
SELECT 1, 'Hauptstandort' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM locations LIMIT 1);

-- Projekte um location_id erweitern
ALTER TABLE projects ADD COLUMN IF NOT EXISTS location_id INT AFTER id;

-- Bestehende Projekte dem Hauptstandort zuweisen
UPDATE projects SET location_id = 1 WHERE location_id IS NULL;

-- Pflichtfeld und Fremdschlüssel für location_id setzen
ALTER TABLE projects MODIFY COLUMN location_id INT NOT NULL;
ALTER TABLE projects ADD CONSTRAINT fk_projects_location FOREIGN KEY IF NOT EXISTS (location_id) REFERENCES locations(id) ON DELETE CASCADE;
ALTER TABLE projects ADD INDEX IF NOT EXISTS idx_location (location_id);

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
    media_type       ENUM('sheet','roll') NOT NULL DEFAULT 'sheet',
    UNIQUE INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- media_type nochmals per ALTER sicherstellen (falls Tabelle schon ohne die Spalte existierte)
ALTER TABLE global_label_templates ADD COLUMN IF NOT EXISTS media_type ENUM('sheet','roll') NOT NULL DEFAULT 'sheet';

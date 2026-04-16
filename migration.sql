-- Migration für bestehende Datenbanken (Drinkport-Barcode)
-- Führt fehlende Spalten zur Tabelle 'label_formats' hinzu

-- Fügt Spalte für den Kalibrierungsrahmen hinzu
ALTER TABLE label_formats ADD COLUMN IF NOT EXISTS show_calibration_border TINYINT(1) DEFAULT 0;

-- Fügt Spalte für die Druck-Skalierung hinzu
ALTER TABLE label_formats ADD COLUMN IF NOT EXISTS print_scale FLOAT NOT NULL DEFAULT 100.0;

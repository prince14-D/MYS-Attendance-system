-- Adds the document_dispatches table for existing databases that were created
-- before this table was added to schema.sql. Safe to re-run (idempotent).
USE mys_attendance;

CREATE TABLE IF NOT EXISTS document_dispatches (
    dispatch_id VARCHAR(60) PRIMARY KEY,
    document_type VARCHAR(100) NOT NULL DEFAULT 'Letter',
    subject VARCHAR(255) NOT NULL DEFAULT '',
    recipient_name VARCHAR(150) NOT NULL DEFAULT '',
    recipient_agency VARCHAR(150) NOT NULL DEFAULT '',
    dispatched_by VARCHAR(150) NOT NULL DEFAULT '',
    dispatched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes TEXT NULL,
    original_name VARCHAR(255) NOT NULL DEFAULT '',
    filename VARCHAR(255) NOT NULL DEFAULT '',
    mime_type VARCHAR(120) NOT NULL DEFAULT '',
    size INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_document_dispatches_dispatched_at (dispatched_at)
) ENGINE=InnoDB;

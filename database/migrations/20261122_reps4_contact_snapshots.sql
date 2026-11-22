-- REPS 4.0 Phase 1: requester and secondary contact snapshots.
-- Run once against the v3 schema. Existing tickets are left intact; their historical
-- contact details cannot be reconstructed reliably from the current users table.

ALTER TABLE tickets
    ADD COLUMN primary_contact_name VARCHAR(255) DEFAULT NULL AFTER contact_person,
    ADD COLUMN primary_contact_phone VARCHAR(20) DEFAULT NULL AFTER primary_contact_name,
    ADD COLUMN primary_contact_email VARCHAR(255) DEFAULT NULL AFTER primary_contact_phone,
    ADD COLUMN secondary_contact_name VARCHAR(255) DEFAULT NULL AFTER primary_contact_email,
    ADD COLUMN secondary_contact_phone VARCHAR(20) DEFAULT NULL AFTER secondary_contact_name,
    ADD COLUMN secondary_contact_email VARCHAR(255) DEFAULT NULL AFTER secondary_contact_phone;

CREATE TABLE ticket_contact_history (
    id INT NOT NULL AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    contact_type ENUM('primary', 'secondary') NOT NULL,
    contact_name VARCHAR(255) DEFAULT NULL,
    contact_phone VARCHAR(20) DEFAULT NULL,
    contact_email VARCHAR(255) DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ticket_contact_history (ticket_id, recorded_at, id),
    CONSTRAINT fk_ticket_contact_history_ticket
        FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_contact_history_user
        FOREIGN KEY (recorded_by) REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

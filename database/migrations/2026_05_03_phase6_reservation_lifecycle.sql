-- Phase 6 reservation lifecycle statuses and timestamps.
-- Run once against existing databases before using ready/fulfill/expire actions.

ALTER TABLE reservations
  MODIFY status ENUM('pending','active','ready_for_pickup','completed','cancelled','fulfilled','expired') NOT NULL DEFAULT 'active';

ALTER TABLE reservations
  ADD COLUMN ready_at DATETIME NULL AFTER queue_position,
  ADD COLUMN fulfilled_at DATETIME NULL AFTER ready_at,
  ADD COLUMN cancelled_at DATETIME NULL AFTER fulfilled_at,
  ADD COLUMN expired_at DATETIME NULL AFTER cancelled_at;

UPDATE reservations
SET status = 'completed'
WHERE status = 'fulfilled';

ALTER TABLE reservations
  MODIFY status ENUM('pending','active','ready_for_pickup','completed','cancelled','expired') NOT NULL DEFAULT 'active';

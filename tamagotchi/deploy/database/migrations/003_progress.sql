-- ============================================================
--  Migration 003 — Progression par thème (déblocage à la Duolingo)
--  Mémorise combien de bonnes réponses l'enfant a eues par thème.
-- ============================================================
CREATE TABLE IF NOT EXISTS topic_progress (
    pet_id  INT UNSIGNED NOT NULL,
    topic   VARCHAR(20)  NOT NULL,
    correct INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (pet_id, topic),
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

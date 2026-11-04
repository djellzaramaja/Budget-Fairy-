-- Run this ONCE in phpMyAdmin (SQL tab, with the budgetfairy database selected)
-- if you already imported schema.sql before. Nothing else changes.
CREATE TABLE IF NOT EXISTS recurring_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type ENUM('income','expense') NOT NULL,
  amount DECIMAL(12,2) NOT NULL CHECK (amount > 0),
  description VARCHAR(120) NOT NULL,
  category VARCHAR(60) NOT NULL,
  day_of_month TINYINT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  last_generated DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rec_user (user_id),
  CONSTRAINT fk_rec_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

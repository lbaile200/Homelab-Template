CREATE DATABASE IF NOT EXISTS `circle_jerks`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `circle_jerks`;

CREATE TABLE IF NOT EXISTS muscle_groups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  muscle_group_name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_muscle_group_name (muscle_group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS eligible_days (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  eligible_day_name VARCHAR(40) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_eligible_day_name (eligible_day_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS focuses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  focus_name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_focus_name (focus_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exercises (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  exercise_name VARCHAR(150) NOT NULL,
  difficulty ENUM('Beginner', 'Intermediate', 'Advanced') NOT NULL,
  estimated_minutes SMALLINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exercise_muscle_groups (
  exercise_id INT UNSIGNED NOT NULL,
  muscle_group_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (exercise_id, muscle_group_id),
  KEY idx_emg_group (muscle_group_id),
  CONSTRAINT fk_emg_exercise
    FOREIGN KEY (exercise_id)
    REFERENCES exercises (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_emg_group
    FOREIGN KEY (muscle_group_id)
    REFERENCES muscle_groups (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exercise_eligible_days (
  exercise_id INT UNSIGNED NOT NULL,
  eligible_day_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (exercise_id, eligible_day_id),
  KEY idx_ex_eligible_day (eligible_day_id),
  CONSTRAINT fk_ex_eligible_exercise
    FOREIGN KEY (exercise_id)
    REFERENCES exercises (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_ex_eligible_day
    FOREIGN KEY (eligible_day_id)
    REFERENCES eligible_days (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exercise_focuses (
  exercise_id INT UNSIGNED NOT NULL,
  focus_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (exercise_id, focus_id),
  KEY idx_ex_focus (focus_id),
  CONSTRAINT fk_ex_focus_exercise
    FOREIGN KEY (exercise_id)
    REFERENCES exercises (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_ex_focus_focus
    FOREIGN KEY (focus_id)
    REFERENCES focuses (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Схема базы «Тарелка»
CREATE DATABASE IF NOT EXISTS tarelka DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tarelka;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS error_log, audit_log, visits, notifications, orders, course_lessons, courses,
  lesson_requests, slots, chefs, favorites, recipe_ratings, recipe_likes, recipe_steps,
  recipe_ingredients, recipes, ingredients, news, pages, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  login VARCHAR(32) NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL UNIQUE,
  pass_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL,
  role ENUM('user','chef','editor','admin') NOT NULL DEFAULT 'user',
  status ENUM('active','blocked') NOT NULL DEFAULT 'active',
  bio VARCHAR(300) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(60) NOT NULL UNIQUE,
  title VARCHAR(160) NOT NULL,
  body TEXT NOT NULL,
  seo_title VARCHAR(160) NOT NULL DEFAULT '',
  seo_desc VARCHAR(300) NOT NULL DEFAULT '',
  published TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE news (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  title VARCHAR(160) NOT NULL,
  body TEXT NOT NULL,
  published TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ingredients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  kind ENUM('pantry','basic','fresh','meat','dairy','grain','spice','fruit') NOT NULL DEFAULT 'basic',
  unit VARCHAR(20) NOT NULL DEFAULT 'г'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recipes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) NOT NULL UNIQUE,
  title VARCHAR(160) NOT NULL,
  category ENUM('breakfast','bakery','soup','salad','main','pasta','dessert','drink') NOT NULL,
  minutes SMALLINT UNSIGNED NOT NULL,
  servings TINYINT UNSIGNED NOT NULL DEFAULT 2,
  difficulty ENUM('легко','средне','сложно') NOT NULL DEFAULT 'легко',
  kcal SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  description VARCHAR(300) NOT NULL DEFAULT '',
  tip VARCHAR(300) NOT NULL DEFAULT '',
  author_id INT NULL,
  status ENUM('pending','published','rejected') NOT NULL DEFAULT 'pending',
  reject_note VARCHAR(300) NOT NULL DEFAULT '',
  rating_avg DECIMAL(3,2) NOT NULL DEFAULT 0,
  rating_count INT UNSIGNED NOT NULL DEFAULT 0,
  likes_count INT UNSIGNED NOT NULL DEFAULT 0,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  published_at DATETIME NULL,
  CONSTRAINT fk_recipe_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
  KEY idx_status_cat (status, category),
  KEY idx_rating (rating_avg)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recipe_ingredients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  recipe_id INT NOT NULL,
  ingredient_id INT NOT NULL,
  qty DECIMAL(8,2) NULL,
  unit VARCHAR(20) NOT NULL DEFAULT '',
  is_optional TINYINT(1) NOT NULL DEFAULT 0,
  sort TINYINT UNSIGNED NOT NULL DEFAULT 1,
  CONSTRAINT fk_ri_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
  CONSTRAINT fk_ri_ing FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
  KEY idx_ri (recipe_id, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recipe_steps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  recipe_id INT NOT NULL,
  n TINYINT UNSIGNED NOT NULL,
  body VARCHAR(400) NOT NULL,
  minutes SMALLINT UNSIGNED NULL,
  CONSTRAINT fk_rs_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
  KEY idx_rs (recipe_id, n)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recipe_likes (
  user_id INT NOT NULL,
  recipe_id INT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, recipe_id),
  CONSTRAINT fk_like_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_like_r FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recipe_ratings (
  user_id INT NOT NULL,
  recipe_id INT NOT NULL,
  score TINYINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, recipe_id),
  CONSTRAINT fk_rate_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_rate_r FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE favorites (
  user_id INT NOT NULL,
  recipe_id INT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, recipe_id),
  CONSTRAINT fk_fav_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fav_r FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE chefs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  title VARCHAR(120) NOT NULL,
  bio VARCHAR(400) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE slots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chef_id INT NOT NULL,
  topic VARCHAR(160) NOT NULL,
  starts_at DATETIME NOT NULL,
  duration SMALLINT UNSIGNED NOT NULL DEFAULT 90,
  seats SMALLINT UNSIGNED NOT NULL DEFAULT 12,
  price INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  CONSTRAINT fk_slot_chef FOREIGN KEY (chef_id) REFERENCES chefs(id) ON DELETE CASCADE,
  KEY idx_start (starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lesson_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  slot_id INT NOT NULL,
  comment VARCHAR(300) NOT NULL DEFAULT '',
  status ENUM('new','confirmed','declined') NOT NULL DEFAULT 'new',
  reply VARCHAR(400) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  replied_at DATETIME NULL,
  CONSTRAINT fk_req_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_req_s FOREIGN KEY (slot_id) REFERENCES slots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  title VARCHAR(160) NOT NULL,
  level ENUM('начальный','средний','продвинутый') NOT NULL DEFAULT 'начальный',
  lessons_count TINYINT UNSIGNED NOT NULL DEFAULT 6,
  hours TINYINT UNSIGNED NOT NULL DEFAULT 4,
  price INT UNSIGNED NOT NULL,
  summary VARCHAR(300) NOT NULL,
  chef_id INT NULL,
  published TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_course_chef FOREIGN KEY (chef_id) REFERENCES chefs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE course_lessons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  n TINYINT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  body TEXT NOT NULL,
  CONSTRAINT fk_cl_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  kind ENUM('course','lesson') NOT NULL,
  item_id INT NOT NULL,
  amount INT UNSIGNED NOT NULL,
  status ENUM('new','paid','cancelled') NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL,
  paid_at DATETIME NULL,
  CONSTRAINT fk_order_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_user_kind (user_id, kind, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  body VARCHAR(300) NOT NULL,
  url VARCHAR(160) NOT NULL DEFAULT '',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_note_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE visits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  day DATE NOT NULL,
  path VARCHAR(60) NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_day_path (day, path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(60) NOT NULL,
  detail VARCHAR(300) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE error_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message VARCHAR(500) NOT NULL,
  context VARCHAR(200) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
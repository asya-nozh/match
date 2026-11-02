-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Хост: MySQL-8.4:3306
-- Время создания: Июн 25 2026 г., 09:45
-- Версия сервера: 8.4.6
-- Версия PHP: 8.4.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `match_project`
--

-- --------------------------------------------------------

--
-- Структура таблицы `action_log`
--

CREATE TABLE `action_log` (
  `id` bigint UNSIGNED NOT NULL,
  `actor_user_id` bigint UNSIGNED NOT NULL,
  `action_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint UNSIGNED DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `action_log`
--


-- --------------------------------------------------------

--
-- Структура таблицы `appeals`
--

CREATE TABLE `appeals` (
  `id` bigint UNSIGNED NOT NULL,
  `decision_id` bigint UNSIGNED NOT NULL,
  `appellant_id` bigint UNSIGNED NOT NULL,
  `appeal_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('new','in_review','accepted','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `reviewer_id` bigint UNSIGNED DEFAULT NULL,
  `review_comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `appeals`
--


-- --------------------------------------------------------

--
-- Структура таблицы `chats`
--

CREATE TABLE `chats` (
  `id` bigint UNSIGNED NOT NULL,
  `match_id` bigint UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_message_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `chats`
--


-- --------------------------------------------------------

--
-- Структура таблицы `cities`
--

CREATE TABLE `cities` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `region` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RU',
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `cities`
--

INSERT INTO `cities` (`id`, `name`, `region`, `country_code`, `is_active`) VALUES
(1, 'Москва', 'Москва', 'RU', 1),
(2, 'Санкт-Петербург', 'Санкт-Петербург', 'RU', 1),
(3, 'Казань', 'Республика Татарстан', 'RU', 1),
(4, 'Екатеринбург', 'Свердловская область', 'RU', 1),
(5, 'Новосибирск', 'Новосибирская область', 'RU', 1);

-- --------------------------------------------------------

--
-- Структура таблицы `interests`
--

CREATE TABLE `interests` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `interests`
--

INSERT INTO `interests` (`id`, `name`, `category`, `is_active`) VALUES
(1, 'Музыка', 'Тестовые интересы', 1),
(2, 'Кино и сериалы', 'Тестовые интересы', 1),
(3, 'Литература', 'Творчество', 1),
(4, 'Прогулки', 'Тестовые интересы', 1),
(5, 'Путешествия', 'Тестовые интересы', 1),
(6, 'Спорт', 'Тестовые интересы', 1),
(7, 'Настольные игры', 'Тестовые интересы', 1),
(8, 'Видеоигры', 'Тестовые интересы', 1),
(9, 'Фотография', 'Тестовые интересы', 1),
(10, 'Искусство', 'Тестовые интересы', 1),
(11, 'Театр', 'Тестовые интересы', 1),
(12, 'Музеи', 'Тестовые интересы', 1),
(13, 'Технологии', 'Тестовые интересы', 1),
(14, 'Изучение языков', 'Тестовые интересы', 1),
(15, 'Кулинария', 'Тестовые интересы', 1),
(16, 'K-Pop', 'Музыка', 1),
(17, 'Книги', 'Тестовые интересы', 1),
(18, 'Разговоры', 'Тестовые интересы', 1),
(19, 'Кафе', 'Тестовые интересы', 1),
(20, 'Домашний отдых', 'Досуг', 1),
(21, 'Активный отдых', 'Тестовые интересы', 1),
(22, 'Походы', 'Активность', 1),
(23, 'Совместный досуг', 'Тестовые интересы', 1),
(25, 'Аниме', 'Тестовые интересы', 1),
(26, 'Манга и комиксы', 'Творчество', 1),
(27, 'Новые знакомства', 'Общение', 1),
(28, 'Еда и напитки', 'Тестовые интересы', 1),
(29, 'Сладости', 'Досуг', 1),
(30, 'Города и архитектура', 'Досуг', 1),
(31, 'Природа', 'Тестовые интересы', 1),
(32, 'Животные', 'Тестовые интересы', 1),
(33, 'Растения', 'Образ жизни', 1),
(34, 'Фитнес', 'Активность', 1),
(35, 'Йога', 'Активность', 1),
(36, 'Бег', 'Активность', 1),
(37, 'Велосипед', 'Активность', 1),
(38, 'Выставки', 'Культура', 1),
(39, 'Рисование', 'Творчество', 1),
(40, 'Рукоделие', 'Тестовые интересы', 1),
(41, 'Дизайн', 'Творчество', 1),
(42, 'Творчество', 'Творчество', 1),
(43, 'Программирование', 'Тестовые интересы', 1),
(44, 'Учёба', 'Образование', 1),
(45, 'Наука', 'Образование', 1),
(46, 'История', 'Образование', 1),
(47, 'Психология', 'Тестовые интересы', 1),
(48, 'Мода', 'Образ жизни', 1),
(49, 'Красота', 'Образ жизни', 1),
(50, 'Танцы', 'Тестовые интересы', 1),
(51, 'Уютные вечера', 'Досуг', 1),
(52, 'Спокойное общение', 'Тестовые интересы', 1),
(53, 'Волонтёрство', 'Образ жизни', 1),
(54, 'Шумные компании', 'Антиинтерес', 1),
(55, 'Спешка', 'Антиинтерес', 1),
(56, 'Грубость', 'Антиинтерес', 1),
(79, 'Домашний уют', 'Тестовые интересы', 1),
(80, 'Дорамы', 'Тестовые интересы', 1),
(81, 'Концерты', 'Тестовые интересы', 1),
(82, 'Кофе', 'Тестовые интересы', 1);

-- --------------------------------------------------------

--
-- Структура таблицы `likes`
--

CREATE TABLE `likes` (
  `id` bigint UNSIGNED NOT NULL,
  `sender_id` bigint UNSIGNED NOT NULL,
  `receiver_id` bigint UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `viewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `likes`
--


-- --------------------------------------------------------

--
-- Структура таблицы `matches`
--

CREATE TABLE `matches` (
  `id` bigint UNSIGNED NOT NULL,
  `user1_id` bigint UNSIGNED NOT NULL,
  `user2_id` bigint UNSIGNED NOT NULL,
  `status` enum('active','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `matches`
--


-- --------------------------------------------------------

--
-- Структура таблицы `messages`
--

CREATE TABLE `messages` (
  `id` bigint UNSIGNED NOT NULL,
  `chat_id` bigint UNSIGNED NOT NULL,
  `sender_id` bigint UNSIGNED NOT NULL,
  `message_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` datetime DEFAULT NULL,
  `edited_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `messages`
--


-- --------------------------------------------------------

--
-- Структура таблицы `moderation_decisions`
--

CREATE TABLE `moderation_decisions` (
  `id` bigint UNSIGNED NOT NULL,
  `report_id` bigint UNSIGNED NOT NULL,
  `moderator_id` bigint UNSIGNED NOT NULL,
  `decision_type` enum('no_violation','warning','temporary_restriction','permanent_block') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `moderation_decisions`
--


-- --------------------------------------------------------

--
-- Структура таблицы `places`
--

CREATE TABLE `places` (
  `id` bigint UNSIGNED NOT NULL,
  `category_id` int UNSIGNED NOT NULL,
  `city_id` int UNSIGNED NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','hidden') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by_user_id` bigint UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `places`
--


-- --------------------------------------------------------

--
-- Структура таблицы `place_categories`
--

CREATE TABLE `place_categories` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `place_categories`
--

INSERT INTO `place_categories` (`id`, `name`, `is_active`) VALUES
(1, 'Кафе', 1),
(2, 'Парк', 1),
(3, 'Музей', 1),
(4, 'Кинотеатр', 1),
(5, 'Коворкинг', 1),
(6, 'Спортивный объект', 1),
(7, 'Театр', 1),
(8, 'Выставочное пространство', 1);

-- --------------------------------------------------------

--
-- Структура таблицы `reports`
--

CREATE TABLE `reports` (
  `id` bigint UNSIGNED NOT NULL,
  `author_id` bigint UNSIGNED NOT NULL,
  `reported_user_id` bigint UNSIGNED NOT NULL,
  `message_id` bigint UNSIGNED DEFAULT NULL,
  `reason_id` int UNSIGNED NOT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('new','in_review','resolved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `assigned_moderator_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `taken_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `reports`
--


-- --------------------------------------------------------

--
-- Структура таблицы `report_reasons`
--

CREATE TABLE `report_reasons` (
  `id` int UNSIGNED NOT NULL,
  `target_type` enum('profile','message') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `report_reasons`
--

INSERT INTO `report_reasons` (`id`, `target_type`, `name`, `description`, `is_active`) VALUES
(1, 'profile', 'Ложная информация', 'В профиле указаны недостоверные или вводящие в заблуждение сведения.', 1),
(2, 'profile', 'Недопустимое изображение', 'Аватар или другие материалы профиля нарушают правила веб-приложения.', 1),
(3, 'profile', 'Мошенничество', 'Пользователь предпринимает действия, направленные на обман других участников.', 1),
(4, 'profile', 'Выдача себя за другого человека', 'Профиль использует чужие данные или изображения.', 1),
(5, 'message', 'Оскорбление или угрозы', 'Сообщение содержит оскорбления, унижение или угрозы.', 1),
(6, 'message', 'Нежелательные сообщения', 'Пользователь отправляет повторяющиеся или навязчивые сообщения.', 1),
(7, 'message', 'Недопустимое содержимое', 'Сообщение содержит материалы, нарушающие правила веб-приложения.', 1),
(8, 'message', 'Мошенничество', 'Сообщение связано с обманом или попыткой получить личные данные.', 1),
(9, 'profile', 'Оскорбительное поведение', 'Пользователь ведёт себя грубо, оскорбляет других участников или нарушает нормы уважительного общения.', 1),
(10, 'message', 'Оскорбительное поведение', 'Сообщения пользователя содержат грубое, унизительное или оскорбительное поведение.', 1);

-- --------------------------------------------------------

--
-- Структура таблицы `roles`
--

CREATE TABLE `roles` (
  `id` tinyint UNSIGNED NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `roles`
--

INSERT INTO `roles` (`id`, `name`, `slug`) VALUES
(1, 'Участник', 'participant'),
(2, 'Модератор', 'moderator'),
(3, 'Администратор', 'admin');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `role_id` tinyint UNSIGNED NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `email_code_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_code_expires_at` datetime DEFAULT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','blocked','deleted') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `failed_login_attempts` smallint UNSIGNED NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_active_at` datetime DEFAULT NULL,
  `privacy_accepted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--


-- --------------------------------------------------------

--
-- Структура таблицы `user_ai_interests`
--

CREATE TABLE `user_ai_interests` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `interest_id` int UNSIGNED DEFAULT NULL,
  `type` enum('interest','anti_interest') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'interest',
  `topic` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `user_ai_interests`
--


-- --------------------------------------------------------

--
-- Структура таблицы `user_blocks`
--

CREATE TABLE `user_blocks` (
  `id` bigint UNSIGNED NOT NULL,
  `blocker_id` bigint UNSIGNED NOT NULL,
  `blocked_id` bigint UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `user_blocks`
--


-- --------------------------------------------------------

--
-- Структура таблицы `user_interests`
--

CREATE TABLE `user_interests` (
  `user_id` bigint UNSIGNED NOT NULL,
  `interest_id` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `user_interests`
--


-- --------------------------------------------------------

--
-- Структура таблицы `user_profiles`
--

CREATE TABLE `user_profiles` (
  `user_id` bigint UNSIGNED NOT NULL,
  `display_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `birth_date` date NOT NULL,
  `gender` enum('female','male','other','not_specified') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_specified',
  `city_id` int UNSIGNED DEFAULT NULL,
  `communication_goal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'communication',
  `about` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `interests_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `avatar_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `completed_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `user_profiles`
--


-- --------------------------------------------------------

--
-- Структура таблицы `user_restrictions`
--

CREATE TABLE `user_restrictions` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `decision_id` bigint UNSIGNED NOT NULL,
  `restriction_type` enum('chat_ban','profile_hidden','temporary_block','permanent_block') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `starts_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ends_at` datetime DEFAULT NULL,
  `status` enum('active','expired','lifted') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by_user_id` bigint UNSIGNED NOT NULL,
  `lifted_by_user_id` bigint UNSIGNED DEFAULT NULL,
  `lifted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `user_restrictions`
--


--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `action_log`
--
ALTER TABLE `action_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_action_log_actor_created` (`actor_user_id`,`created_at`),
  ADD KEY `idx_action_log_type` (`action_type`),
  ADD KEY `idx_action_log_entity` (`entity_type`,`entity_id`);

--
-- Индексы таблицы `appeals`
--
ALTER TABLE `appeals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_appeals_decision_appellant` (`decision_id`,`appellant_id`),
  ADD KEY `idx_appeals_status_created` (`status`,`created_at`),
  ADD KEY `idx_appeals_reviewer_id` (`reviewer_id`),
  ADD KEY `fk_appeals_appellant` (`appellant_id`);

--
-- Индексы таблицы `chats`
--
ALTER TABLE `chats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_chats_match_id` (`match_id`),
  ADD KEY `idx_chats_last_message_at` (`last_message_at`);

--
-- Индексы таблицы `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cities_name_region_country` (`name`,`region`,`country_code`),
  ADD KEY `idx_cities_active` (`is_active`);

--
-- Индексы таблицы `interests`
--
ALTER TABLE `interests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_interests_name` (`name`),
  ADD KEY `idx_interests_category` (`category`),
  ADD KEY `idx_interests_active` (`is_active`);

--
-- Индексы таблицы `likes`
--
ALTER TABLE `likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_likes_sender_receiver` (`sender_id`,`receiver_id`),
  ADD KEY `idx_likes_receiver_id` (`receiver_id`),
  ADD KEY `idx_likes_created_at` (`created_at`),
  ADD KEY `idx_likes_receiver_viewed` (`receiver_id`,`viewed_at`);

--
-- Индексы таблицы `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_matches_user_pair` (`user1_id`,`user2_id`),
  ADD KEY `idx_matches_user2_id` (`user2_id`),
  ADD KEY `idx_matches_status` (`status`);

--
-- Индексы таблицы `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_messages_chat_sent` (`chat_id`,`sent_at`),
  ADD KEY `idx_messages_sender_id` (`sender_id`),
  ADD KEY `idx_messages_deleted_at` (`deleted_at`);

--
-- Индексы таблицы `moderation_decisions`
--
ALTER TABLE `moderation_decisions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_decisions_report_id` (`report_id`),
  ADD KEY `idx_decisions_moderator_id` (`moderator_id`),
  ADD KEY `idx_decisions_type` (`decision_type`),
  ADD KEY `idx_decisions_created_at` (`created_at`);

--
-- Индексы таблицы `places`
--
ALTER TABLE `places`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_places_city_category_status` (`city_id`,`category_id`,`status`),
  ADD KEY `idx_places_created_by` (`created_by_user_id`),
  ADD KEY `fk_places_category` (`category_id`);

--
-- Индексы таблицы `place_categories`
--
ALTER TABLE `place_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_place_categories_name` (`name`),
  ADD KEY `idx_place_categories_active` (`is_active`);

--
-- Индексы таблицы `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reports_author_id` (`author_id`),
  ADD KEY `idx_reports_reported_user_id` (`reported_user_id`),
  ADD KEY `idx_reports_message_id` (`message_id`),
  ADD KEY `idx_reports_reason_id` (`reason_id`),
  ADD KEY `idx_reports_status_created` (`status`,`created_at`),
  ADD KEY `idx_reports_assigned_moderator_id` (`assigned_moderator_id`);

--
-- Индексы таблицы `report_reasons`
--
ALTER TABLE `report_reasons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_report_reasons_target_name` (`target_type`,`name`),
  ADD KEY `idx_report_reasons_active` (`is_active`);

--
-- Индексы таблицы `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_name` (`name`),
  ADD UNIQUE KEY `uq_roles_slug` (`slug`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role_id` (`role_id`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_last_active_at` (`last_active_at`);

--
-- Индексы таблицы `user_ai_interests`
--
ALTER TABLE `user_ai_interests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_ai_interests_user_type` (`user_id`,`type`),
  ADD KEY `idx_user_ai_interests_interest` (`interest_id`);

--
-- Индексы таблицы `user_blocks`
--
ALTER TABLE `user_blocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_blocks_blocker_blocked` (`blocker_id`,`blocked_id`),
  ADD KEY `idx_blocks_blocked_id` (`blocked_id`);

--
-- Индексы таблицы `user_interests`
--
ALTER TABLE `user_interests`
  ADD PRIMARY KEY (`user_id`,`interest_id`),
  ADD KEY `idx_user_interests_interest_id` (`interest_id`);

--
-- Индексы таблицы `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_profiles_city_id` (`city_id`),
  ADD KEY `idx_profiles_birth_date` (`birth_date`),
  ADD KEY `idx_profiles_goal` (`communication_goal`),
  ADD KEY `idx_profiles_visible` (`is_visible`);

--
-- Индексы таблицы `user_restrictions`
--
ALTER TABLE `user_restrictions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_restrictions_user_status` (`user_id`,`status`),
  ADD KEY `idx_restrictions_decision_id` (`decision_id`),
  ADD KEY `idx_restrictions_ends_at` (`ends_at`),
  ADD KEY `idx_restrictions_created_by` (`created_by_user_id`),
  ADD KEY `idx_restrictions_lifted_by` (`lifted_by_user_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `action_log`
--
ALTER TABLE `action_log`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT для таблицы `appeals`
--
ALTER TABLE `appeals`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `chats`
--
ALTER TABLE `chats`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT для таблицы `cities`
--
ALTER TABLE `cities`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `interests`
--
ALTER TABLE `interests`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=208;

--
-- AUTO_INCREMENT для таблицы `likes`
--
ALTER TABLE `likes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=233;

--
-- AUTO_INCREMENT для таблицы `matches`
--
ALTER TABLE `matches`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT для таблицы `messages`
--
ALTER TABLE `messages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=206;

--
-- AUTO_INCREMENT для таблицы `moderation_decisions`
--
ALTER TABLE `moderation_decisions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `places`
--
ALTER TABLE `places`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT для таблицы `place_categories`
--
ALTER TABLE `place_categories`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `reports`
--
ALTER TABLE `reports`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблицы `report_reasons`
--
ALTER TABLE `report_reasons`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `roles`
--
ALTER TABLE `roles`
  MODIFY `id` tinyint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT для таблицы `user_ai_interests`
--
ALTER TABLE `user_ai_interests`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT для таблицы `user_blocks`
--
ALTER TABLE `user_blocks`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `user_restrictions`
--
ALTER TABLE `user_restrictions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `action_log`
--
ALTER TABLE `action_log`
  ADD CONSTRAINT `fk_action_log_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `appeals`
--
ALTER TABLE `appeals`
  ADD CONSTRAINT `fk_appeals_appellant` FOREIGN KEY (`appellant_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appeals_decision` FOREIGN KEY (`decision_id`) REFERENCES `moderation_decisions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appeals_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `chats`
--
ALTER TABLE `chats`
  ADD CONSTRAINT `fk_chats_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `likes`
--
ALTER TABLE `likes`
  ADD CONSTRAINT `fk_likes_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_likes_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `matches`
--
ALTER TABLE `matches`
  ADD CONSTRAINT `fk_matches_user1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_matches_user2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_chat` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `moderation_decisions`
--
ALTER TABLE `moderation_decisions`
  ADD CONSTRAINT `fk_decisions_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_decisions_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `places`
--
ALTER TABLE `places`
  ADD CONSTRAINT `fk_places_category` FOREIGN KEY (`category_id`) REFERENCES `place_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_places_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_places_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `fk_reports_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reports_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reports_moderator` FOREIGN KEY (`assigned_moderator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reports_reason` FOREIGN KEY (`reason_id`) REFERENCES `report_reasons` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reports_reported_user` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `user_ai_interests`
--
ALTER TABLE `user_ai_interests`
  ADD CONSTRAINT `fk_user_ai_interests_interest` FOREIGN KEY (`interest_id`) REFERENCES `interests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_ai_interests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `user_blocks`
--
ALTER TABLE `user_blocks`
  ADD CONSTRAINT `fk_blocks_blocked` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_blocks_blocker` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `user_interests`
--
ALTER TABLE `user_interests`
  ADD CONSTRAINT `fk_user_interests_interest` FOREIGN KEY (`interest_id`) REFERENCES `interests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_interests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `fk_profiles_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `user_restrictions`
--
ALTER TABLE `user_restrictions`
  ADD CONSTRAINT `fk_restrictions_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_restrictions_decision` FOREIGN KEY (`decision_id`) REFERENCES `moderation_decisions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_restrictions_lifted_by` FOREIGN KEY (`lifted_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_restrictions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

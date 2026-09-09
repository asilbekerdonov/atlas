# Atlas 🌍

[![Symfony](https://img.shields.io/badge/Symfony-6.4%20%2F%208.1-black?style=flat-square&logo=symfony)](https://symfony.com/)
[![PHP](https://img.shields.io/badge/PHP-8.4%2B-777BB4?style=flat-square&logo=php)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?style=flat-square&logo=postgresql)](https://www.postgresql.org/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat-square&logo=bootstrap)](https://getbootstrap.com/)
[![Cloudinary](https://img.shields.io/badge/Storage-Cloudinary-3448C5?style=flat-square&logo=cloudinary)](https://cloudinary.com/)
[![Yandex Maps](https://img.shields.io/badge/Maps-Yandex_Maps_API-red?style=flat-square&logo=yandex)](https://yandex.ru/dev/maps/)
[![License](https://img.shields.io/badge/License-Proprietary-gray?style=flat-square)](#)

> **Atlas** — современная масштабируемая веб-платформа для соискателей (кандидатов) и рекрутеров, обеспечивающая полный цикл взаимодействия: от интерактивного заполнения профиля и гибкого конструктора резюме (CV) до умного подбора вакансий и прямого обсуждения.

---

## 📑 Содержание

- [О проекте](#-о-проекте)
- [Стек технологий](#-стек-технологий)
- [Основные функции](#-основные-функции)
  - [1. Профиль кандидата](#1-профиль-кандидата)
  - [2. Управление проектами](#2-управление-проектами)
  - [3. Резюме (CV) и отклики](#3-резюме-cv-и-отклики)
  - [4. Медиа и Cloudinary (Direct Upload)](#4-медиа-и-cloudinary-direct-upload)
  - [5. Безопасность и права доступа](#5-безопасность-и-права-доступа)
- [Архитектура и структура кода](#-архитектура-и-структура-кода)
- [Потоки данных (Data Flows)](#-потоки-данных-data-flows)
  - [Загрузка аватара через Presigned URLs](#загрузка-аватара-через-presigned-urls)
  - [Выбор локации через Яндекс.Карты](#выбор-локации-через-яндекскарты)
- [Установка и запуск](#-установка-и-запуск)
  - [Предварительные требования](#предварительные-требования)
  - [Пошаговая инструкция](#пошаговая-инструкция)
- [Конфигурация окружения (.env)](#-конфигурация-окружения-env)
- [Тестовые пользователи (Fixtures)](#-тестовые-пользователи-fixtures)
- [Команды Makefile и консоли](#-команды-makefile-и-консоли)
- [Тестирование](#-тестирование)

---

## 🎯 О проекте

Платформа **Atlas** решает проблему фрагментированного процесса найма, предоставляя соискателям удобные инструменты для демонстрации своих навыков, портфолио проектов и резюме, а рекрутерам — расширенную систему подбора кандидатов по гибким шаблонам атрибутов и правилам доступа (Access Rules).

Ключевые инженерные преимущества платформы:
- **Zero-Proxy Media Architecture**: бинарные файлы изображений загружаются клиентом напрямую в Cloudinary CDN с криптографической подписью бэкенда, не нагружая веб-сервер.
- **Интерактивное автосохранение**: все изменения профиля и проектов сохраняются в фоновом режиме по дебаунс-триггерам.
- **Гео-привязка кандидатов**: точный выбор места жительства с использованием интерактивной Яндекс.Карты и серверного геокодирования.
- **Многоуровневая система безопасности**: строгая RBAC-модель, двухуровневый Rate Limiter и жесткая валидация DTO на уровне контроллеров.

---

## 🛠 Стек технологий

### Backend
- **Язык:** PHP 8.4+
- **Фреймворк:** Symfony 6.4 LTS / Symfony 8.1 components
- **ORM / База данных:** Doctrine ORM 3.x, Doctrine Migrations, PostgreSQL 16 (с поддержкой полнотекстового поиска `tsvector`)
- **Асинхронность и очереди:** Symfony Messenger, Symfony Mercure Hub (real-time обновления)
- **Безопасность:** Symfony Security Bundle, Symfony RateLimiter
- **Авторизация:** KnpU OAuth2 Client Bundle (Google OAuth, GitHub OAuth)

### Frontend
- **Шаблонизатор:** Twig, Twig Extra
- **UI Framework:** Bootstrap 5.3, Bootstrap Icons
- **Клиентский стек:** Vanilla JavaScript (ES6+), Symfony UX Stimulus & Turbo
- **Обработка изображений:** Cropper.js (обрезка, масштабирование, поворот аватара)
- **Интерактивные элементы:** Tagify (тегирование стека технологий)

### Интеграции и внешние сервисы
- **Cloudinary:** Облачное хранилище медиа-файлов, CDN и оптимизация изображений
- **Яндекс.Карты API:** JavaScript API v2.1 для фронтенда + Geocoder HTTP API для бэкенда
- **OAuth 2.0:** Google Cloud Console, GitHub Developer Apps

---

## 🌟 Основные функции

### 1. Профиль кандидата
- **Автозаполнение и автосохранение (Autosave):** Изменения полей профиля (имя, фамилия, локация, статус) автоматически отправляются на сервер с дебаунсом через `/api/profile/autosave`.
- **Интерактивный выбор локации:**
  - Интеграция с Яндекс.Картами: открытие карты в модальном окне с динамической подгрузкой API.
  - Возможность перемещения маркера по карте, клика для установки точки и автоматического обратного геокодирования адреса.
- **Динамическая библиотека атрибутов:**
  - Категории: Soft Skills, Hard Skills, Certifications, Personal Info, Domain Knowledge.
  - Поддержка различных типов данных: булевы значения, числа, даты, выбор из списка (`ONE_OF_MANY`), свободный текст.
  - Поиск и добавление атрибутов с автодополнением в реальном времени.

### 2. Управление проектами
- **Полный CRUD:** Создание, редактирование, просмотр и удаление проектов из портфолио.
- **Теги стека технологий:** Удобное добавление и автодополнение тегов (например, `PHP`, `PostgreSQL`, `Docker`) с помощью Tagify.
- **Автосохранение описания и деталей проекта.**

### 3. Резюме (CV) и отклики
- **Многократные резюме:** Кандидат может формировать несколько версий резюме под разные специализации.
- **Привязка к позициям (Вакансии):** Связывание резюме с конкретными вакансиями рекрутеров.
- **Жизненный цикл резюме:** Статусы `Draft` (черновик) и `Published` (опубликовано).
- **Социальные взаимодействия:** Система лайков (`CvLike`) с оптимизированным счетчиком и защитой от повторных отметок.

### 4. Медиа и Cloudinary (Direct Upload)
- **Архитектура Presigned URLs:** Бэкенд генерирует временную цифровую подпись (`sha1(params + api_secret)`). Клиент загружает файл напрямую в CDN Cloudinary, минуя сервер приложения.
- **Встроенный редактор аватаров:** Инструмент на базе Cropper.js с поддержкой:
  - Пропорционального кадрирования (1:1);
  - Вращения на 90 градусов;
  - Зеркального отражения по горизонтали/вертикали;
  - Масштабирования (Zoom in / Zoom out).
- **Оптимизация:** Автоматическая конвертация и сжатие на стороне CDN.

### 5. Безопасность и права доступа
- **Ролевая модель (RBAC):**
  - `ROLE_CANDIDATE`: управление своим профилем, проектами и резюме.
  - `ROLE_RECRUITER`: управление вакансиями (Positions), шаблонами атрибутов и правилами доступа.
  - `ROLE_ADMIN`: полный доступ ко всем ресурсам и админ-панели управления пользователями.
- **Rate Limiting:** Двухуровневое ограничение запросов на получение подписанных ссылок для загрузки медиа (`10 запросов в час` на уровне пользователя и IP-адреса).
- **Строгая валидация файлов:**
  - Максимальный размер: **5 МБ**;
  - Разрешенные MIME-типы: `image/jpeg`, `image/png`, `image/webp`.
- **Защита данных:** CSRF-токены, валидация DTO через `Symfony\Component\Validator`, хэширование паролей алгоритмом `auto` (bcrypt / argon2id).

---

## 📂 Архитектура и структура кода

Проект следует принципам чистой слоистой архитектуры (**Layered Architecture / SOLID**), разделяя ответственность между слоями контроллеров, прикладных сервисов, DTO и репозиториев:

```text
atlas/
├── assets/                          # Фронтенд-ресурсы
│   ├── app.js                       # Главная точка входа скриптов
│   ├── controllers/                 # Stimulus контроллеры (Turbo, CSRF)
│   ├── js/
│   │   └── profile.js               # Логика профиля: Autosave, Cropper, Yandex Maps
│   └── styles/                      # Стили проекта (CSS / SCSS)
├── bin/
│   └── console                      # Symfony Console CLI
├── config/                          # Конфигурация Symfony и пакетов
│   ├── packages/                    # Конфиги: security, rate_limiter, doctrine, oauth
│   ├── routes.yaml                  # Маршрутизация
│   └── services.yaml                # DI контейнер и сервисы
├── migrations/                      # Миграции базы данных Doctrine
├── src/                             # Исходный код бэкенда (PHP)
│   ├── Controller/                  # Тонкие HTTP-контроллеры
│   │   ├── AdminController.php             # Панель администратора
│   │   ├── AttributeLibraryController.php  # API поиска и библиотеки атрибутов
│   │   ├── CandidateProfileController.php  # Страница и действия профиля
│   │   ├── CvController.php                # Управление резюме
│   │   ├── DiscussionController.php        # Внутренние обсуждения и чаты
│   │   ├── MediaController.php             # Эндпоинт presign для Cloudinary
│   │   ├── OAuthController.php             # Авторизация через Google/GitHub
│   │   ├── PositionController.php          # Вакансии рекрутеров
│   │   └── ProjectController.php           # CRUD проектов кандидата
│   ├── DTO/                         # Data Transfer Objects
│   │   ├── Request/                        # Типизированные входные запросы с валидацией
│   │   │   ├── MediaPresignRequestDTO.php
│   │   │   ├── ProfileAutosaveRequestDTO.php
│   │   │   ├── ProjectCreateRequestDTO.php
│   │   │   └── ...
│   │   └── PresignedUploadDTO.php          # Выходные структуры данных
│   ├── DataFixtures/                # Тестовые данные (AppFixtures, Categories)
│   ├── Entity/                      # Сущности Doctrine ORM
│   │   ├── Attribute.php                   # Характеристики / навыки
│   │   ├── CandidateProfile.php            # Профиль соискателя
│   │   ├── Cv.php                          # Резюме
│   │   ├── CvLike.php                      # Лайки к резюме
│   │   ├── Position.php                    # Позиция / вакансия
│   │   ├── Project.php                     # Проект кандидата
│   │   ├── Tag.php                         # Теги технологий
│   │   └── User.php                        # Пользователь системы
│   ├── Enum/                        # Перечисления (UserRole, AttributeDataType, etc.)
│   ├── Repository/                  # Doctrine репозитории с оптимизированными запросами
│   ├── Security/                    # Аутентификаторы, UserChecker, OAuth-провайдеры
│   └── Service/                     # Слой бизнес-логики (Application Services)
│       ├── AccessRule/                     # Оценка правил соответствия вакансии
│       ├── Attribute/                      # Обработка и парсинг атрибутов
│       ├── Cv/                             # Логика генерации и управления CV
│       ├── Media/                          # PresignedUploadService (подпись Cloudinary)
│       ├── Position/                       # Сервисы вакансий
│       ├── Profile/                        # Сервис автосохранения профиля
│       └── Social/                         # Сервис лайков (CvLikeService)
├── templates/                       # Шаблоны Twig (UI компоненты и страницы)
├── tests/                           # Набор тестов (Unit, Functional, Integration)
├── compose.yaml                     # Docker Compose (PostgreSQL 16, Mercure Hub)
├── Makefile                         # Шорткаты для миграций и сброса БД
└── composer.json                    # Зависимости проекта
```

---

## 🔄 Потоки данных (Data Flows)

### Загрузка аватара через Presigned URLs

Сервер приложения никогда не принимает на себя тяжелые бинарные данные аватаров. Загрузка происходит по следующей схеме:

```
  [Браузер кандидата]              [Atlas Backend]               [Cloudinary CDN]
           |                              |                              |
  1. Выбор файла + Cropper.js             |                              |
           |                              |                              |
  2. POST /api/media/presign ------------>|                              |
     (filename, mimeType, sizeBytes)      | (Rate Limiting check: 10/hr) |
                                          | (MIME & Size validation)     |
                                          | (Генерация SHA-1 подписи)    |
  3. JSON {url, fields, signature} <------|                              |
           |                                                             |
  4. Прямой POST multipart/form-data ----------------------------------->|
     (файл + подпись + api_key)                                          |
           |                                                             |
  5. JSON response {secure_url, public_id} <-----------------------------|
           |                              |
  6. POST /api/profile/autosave --------->|
     (avatarUrl: secure_url)              | (Сохранение URL в БД)
  7. OK (200) <---------------------------|
```

### Выбор локации через Яндекс.Карты

1. Пользователь открывает модальное окно выбора локации в профиле.
2. Скрипт `profile.js` лениво (lazy-load) загружает скрипт Яндекс.Карт по ключу `YANDEX_MAPS_JS_API_KEY`.
3. На карте инициализируется маркер (Placemark) с поддержкой drag-and-drop.
4. При перемещении маркера вызывается геокодирование координаты, адрес отображается в поле ввода.
5. При нажатии «Сохранить» координаты и текстовое наименование локации передаются в эндпоинт автосохранения профиля.

---

## 🚀 Установка и запуск

### Предварительные требования

Перед началом убедитесь, что на вашей машине установлены:
- **PHP** версии 8.4 или выше (с расширениями `pdo_pgsql`, `ctype`, `iconv`, `curl`, `intl`)
- **Composer** 2.x
- **Docker** и **Docker Compose**
- **Symfony CLI** (рекомендуется для локального запуска)
- **Node.js** и **npm** (для управления фронтенд-зависимостями)

### Пошаговая инструкция

#### 1. Клонирование репозитория
```bash
git clone https://github.com/your-username/atlas.git
cd atlas
```

#### 2. Установка PHP и JS зависимостей
```bash
composer install
npm install
php bin/console importmap:install
```

#### 3. Настройка переменных окружения
Создайте локальный файл `.env.local` на основе основного `.env`:
```bash
cp .env .env.local
```
Отредактируйте параметры подключения к БД, ключи Cloudinary и Яндекс.Карт (см. раздел [Конфигурация окружения](#-конфигурация-окружения-env)).

#### 4. Запуск инфраструктуры в Docker
Запустите контейнеры базы данных PostgreSQL 16 и Mercure Hub:
```bash
docker compose up -d
```

#### 5. Инициализация базы данных и фикстур
Для применения миграций и загрузки начальных данных воспользуйтесь Makefile:
```bash
make db-reset
```
*Эта команда удалит существующую БД (если была), создаст новую схему, применит миграции и заполнит систему демо-данными.*

Или вручную через консоль:
```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
```

#### 6. Запуск веб-сервера
Используя Symfony CLI:
```bash
symfony server:start -d
```
Или встроенный PHP-сервер:
```bash
php -S 127.0.0.1:8000 -t public
```

Приложение доступно по адресу: **http://127.0.0.1:8000**

---

## ⚙️ Конфигурация окружения (.env)

Основные переменные, настраиваемые в `.env.local`:

| Переменная | Описание | Пример значения |
|---|---|---|
| `APP_ENV` | Окружение приложения (`dev`, `prod`, `test`) | `dev` |
| `APP_SECRET` | Секретный ключ приложения Symfony | `c2691b...` |
| `DATABASE_URL` | Строка подключения к PostgreSQL | `postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8` |
| `CLOUDINARY_CLOUD_NAME` | Имя облака Cloudinary | `my-cloud-name` |
| `CLOUDINARY_API_KEY` | Публичный API-ключ Cloudinary | `123456789012345` |
| `CLOUDINARY_API_SECRET` | Приватный секрет Cloudinary (для подписи) | `abcdefghijklmnopqrstuvwxyz` |
| `CLOUDINARY_UPLOAD_URL` | URL для прямой загрузки в Cloudinary | `https://api.cloudinary.com/v1_1/my-cloud-name/image/upload` |
| `YANDEX_MAPS_JS_API_KEY` | JS API ключ Яндекс.Карт (для браузера) | `your-yandex-js-api-key` |
| `YANDEX_GEOCODER_API_KEY`| Ключ API Геокодера Яндекс (для бэкенда) | `your-yandex-geocoder-api-key` |
| `GOOGLE_CLIENT_ID` | OAuth Client ID Google | `xxxx.apps.googleusercontent.com` |
| `GOOGLE_CLIENT_SECRET` | OAuth Client Secret Google | `GOCSPX-xxxx` |
| `OAUTH_GITHUB_CLIENT_ID` | OAuth Client ID GitHub | `github-client-id` |
| `OAUTH_GITHUB_CLIENT_SECRET` | OAuth Client Secret GitHub | `github-client-secret` |
| `MERCURE_URL` | URL хаба Mercure для публикации | `http://127.0.0.1:3000/.well-known/mercure` |

---

## 👥 Тестовые пользователи (Fixtures)

После выполнения `make db-reset` в системе создаются предустановленные аккаунты для проверки всех ролей:

| Email | Пароль | Роль | Описание |
|---|---|---|---|
| `admin@platform.local` | `password123` | `ROLE_ADMIN` | Администратор платформы (полный доступ) |
| `recruiter@acme.com` | `password123` | `ROLE_RECRUITER` | Рекрутер компании Acme Corp |
| `candidate.anna@mail.com`| `password123` | `ROLE_CANDIDATE` | Кандидат с заполненным профилем и проектами |
| `candidate.john@mail.com`| `password123` | `ROLE_CANDIDATE` | Новый кандидат |

---

## ⌨️ Команды Makefile и консоли

В репозитории предусмотрен `Makefile` для быстрой работы с базой данных:

```bash
make db-reset      # Полный сброс БД: drop -> create -> migrate -> fixtures
make db-migrate    # Выполнение непримененных миграций
make db-fixtures   # Повторная загрузка тестовых фикстур
```

Полезные команды Symfony CLI:

```bash
php bin/console cache:clear                   # Очистка кэша
php bin/console debug:router                  # Список всех маршрутов
php bin/console debug:autowiring limiter      # Проверка конфигурации Rate Limiter
php bin/console messenger:consume async       # Запуск обработчика асинхронных очередей
```

---

## 🧪 Тестирование

Проект покрыт автоматическими тестами с использованием **PHPUnit**:
- **Unit-тесты:** проверка логики сервисов (PresignedUploadService, AccessRuleEvaluator, AttributeValueParser);
- **Functional / Integration тесты:** тестирование контроллеров, аутентификации, rate limiting и DTO-валидации.

Запуск тестового набора:
```bash
php bin/phpunit
```

Запуск конкретной группы тестов:
```bash
php bin/phpunit tests/Unit
php bin/phpunit tests/Functional
```

---

## 📄 Лицензия

Проект является проприетарным программным обеспечением. Все права защищены.

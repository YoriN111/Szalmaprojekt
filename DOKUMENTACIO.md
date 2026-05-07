# Szalma Ételrendelő API – Teljes Projektdokumentáció

> Iskolai projekt dokumentáció | PHP REST API | XAMPP + MySQL

---

## Tartalomjegyzék

1. [Projekt áttekintése](#1-projekt-áttekintése)
2. [Szükséges szoftverek](#2-szükséges-szoftverek)
3. [XAMPP telepítése és konfigurálása](#3-xampp-telepítése-és-konfigurálása)
4. [Projekt beállítása](#4-projekt-beállítása)
5. [Adatbázis létrehozása](#5-adatbázis-létrehozása)
6. [Környezeti változók konfigurálása](#6-környezeti-változók-konfigurálása)
7. [Composer függőségek telepítése](#7-composer-függőségek-telepítése)
8. [Faker – adatbázis feltöltése tesztadatokkal](#8-faker--adatbázis-feltöltése-tesztadatokkal)
9. [API dokumentáció (Swagger UI)](#9-api-dokumentáció-swagger-ui)
10. [Postman collection](#10-postman-collection)
11. [API végpontok részletes leírása](#11-api-végpontok-részletes-leírása)
12. [Autentikáció és jogosultságkezelés](#12-autentikáció-és-jogosultságkezelés)
13. [Email funkciók](#13-email-funkciók)
14. [Biztonsági megvalósítások (OWASP Top 10)](#14-biztonsági-megvalósítások-owasp-top-10)
15. [Projektstruktúra magyarázata](#15-projektstruktúra-magyarázata)
16. [Éles szerverre telepítés (Deployment)](#16-éles-szerverre-telepítés-deployment)
17. [Hibakeresés és hibaelhárítás](#17-hibakeresés-és-hibaelhárítás)
18. [HTTP státuszkódok összefoglalója](#18-http-státuszkódok-összefoglalója)

---

## 1. Projekt áttekintése

Ez egy **PHP alapú REST API** egy ételrendelő platformhoz. Nincs framework (pl. Laravel, Symfony) – minden kód saját implementáció, hogy az alapok jól láthatók legyenek.

### Mit tud a rendszer?

| Funkció | Leírás |
|---------|--------|
| Regisztráció + email hitelesítés | Új felhasználó regisztrál, kap egy megerősítő emailt |
| Bejelentkezés JWT-vel | Token alapú autentikáció, bejelentkezési értesítő email |
| Jelszó visszaállítás | Email alapú token, 1 óráig érvényes |
| Éttermek kezelése | CRUD – létrehozás, listázás, módosítás, törlés |
| Menü kezelése | Étteremhez tartozó ételek kezelése |
| Rendelések | Leadás, státusz frissítés, lemondás |
| Képfeltöltés | JPG/PNG/WebP/GIF, max 5 MB |
| Hozzáférési napló | Minden kérés logolva (IP, eszköz, böngésző, ország) |
| Rate limiting | Brute force védelem bejelentkezésnél |
| Swagger dokumentáció | Interaktív API leírás |

### Felhasználói szerepek

| Szerep | Mit tehet |
|--------|-----------|
| `customer` | Regisztrál, bejelentkezik, rendel, lemondja saját rendelését |
| `admin` | Éttermet és menüt kezel, felhasználókat lát/töröl |
| `driver` | Aktív rendeléseket lát, státuszt frissít |

---

## 2. Szükséges szoftverek

A következő programokat kell telepíteni a fejlesztői gépre:

### XAMPP
- **Letöltés:** https://www.apachefriends.org/
- **Verzió:** 8.2+ (PHP 8.2 szükséges)
- Tartalmazza: Apache, MySQL (MariaDB), PHP, phpMyAdmin

### Composer
- **Letöltés:** https://getcomposer.org/download/
- PHP csomagkezelő – a függőségek telepítéséhez szükséges
- Telepítés után ellenőrzés: `composer --version`

### Git (opcionális, ajánlott)
- **Letöltés:** https://git-scm.com/
- Verziókövetéshez

### Postman
- **Letöltés:** https://www.postman.com/downloads/
- API teszteléshez és a collection exportálásához

### Visual Studio Code (ajánlott szerkesztő)
- **Letöltés:** https://code.visualstudio.com/
- Ajánlott bővítmények: PHP Intelephense, REST Client

---

## 3. XAMPP telepítése és konfigurálása

### 3.1 Telepítés

1. Töltsd le a XAMPP telepítőt a hivatalos oldalról
2. Futtasd telepítőként (`xampp-windows-x64-8.x.x-installer.exe`)
3. Telepítési mappa: `C:\xampp` (ne változtasd meg)
4. Komponensek: **Apache**, **MySQL**, **PHP** – mind szükséges

### 3.2 XAMPP Control Panel indítása

1. Nyisd meg: `C:\xampp\xampp-control.exe`
2. Kattints **Start** az **Apache** és **MySQL** soroknál
3. Ha sikeresen elindult, zöld hátteret kapsz

> **Probléma:** Ha az Apache nem indul el, valószínűleg a 80-as port foglalt.
> Megoldás: Módosítsd az Apache portját 8080-ra:
> XAMPP Control Panel → Apache → Config → `httpd.conf`
> Keresd meg: `Listen 80` → változtasd: `Listen 8080`

### 3.3 Ellenőrzés

- Böngészőben: `http://localhost` → XAMPP üdvözlőoldal jelenik meg
- phpMyAdmin: `http://localhost/phpmyadmin`

### 3.4 mod_rewrite engedélyezése

Az API URL-routing működéséhez szükséges. Ellenőrzés:

1. Nyisd meg: `C:\xampp\apache\conf\httpd.conf`
2. Keresd meg ezt a sort és vedd ki a `#` jelet az elejéről:
   ```
   #LoadModule rewrite_module modules/mod_rewrite.so
   ```
   → legyen:
   ```
   LoadModule rewrite_module modules/mod_rewrite.so
   ```
3. Keresd meg az `AllowOverride None` sorokat, és változtasd `AllowOverride All`-ra a `htdocs` mappánál
4. Mentés után indítsd újra az Apache-ot a XAMPP Control Panelben

---

## 4. Projekt beállítása

### 4.1 Projekt elhelyezése

A projektet a XAMPP `htdocs` mappájába kell másolni:

```
C:\xampp\htdocs\Szalmaprojekt\
```

Ha Git-tel töltöd le:
```bash
cd C:\xampp\htdocs
git clone <repo-url> Szalmaprojekt
```

### 4.2 Mappastruktúra

```
Szalmaprojekt/
├── src/                        # Forráskód
│   ├── Controllers/            # HTTP kérések kezelői
│   │   ├── AuthController.php  # Regisztráció, bejelentkezés, jelszó visszaállítás
│   │   ├── RestaurantController.php
│   │   ├── MenuController.php
│   │   ├── OrderController.php
│   │   ├── UserController.php
│   │   └── UploadController.php
│   ├── Models/                 # Adatbázis műveletek
│   │   ├── User.php
│   │   ├── Restaurant.php
│   │   ├── MenuItem.php
│   │   └── Order.php
│   ├── Middleware/
│   │   └── AuthMiddleware.php  # JWT token ellenőrzés
│   ├── Services/
│   │   ├── Mailer.php          # Email küldés (PHPMailer)
│   │   ├── DeviceDetector.php  # Eszközfelismerés (Mobile Detect)
│   │   ├── IpLookup.php        # IP geolokáció
│   │   └── RateLimiter.php     # Brute force védelem
│   ├── Auth.php                # JWT encode/decode
│   ├── Database.php            # PDO singleton
│   ├── OpenApi.php             # Swagger sémák és globális info
│   ├── Response.php            # JSON válasz formázó
│   └── Router.php              # URL routing
├── database/
│   ├── migrations.sql          # Adatbázis tábla definíciók
│   └── seeder.php              # Tesztadatok feltöltése
├── docs/
│   └── index.html              # Swagger UI oldal
├── uploads/                    # Feltöltött képek (automatikusan jön létre)
├── vendor/                     # Composer csomagok (ne szerkeszd!)
├── .env                        # Környezeti változók (NE commitold!)
├── .env.example                # .env sablon (ezt commitold)
├── .htaccess                   # Apache URL routing szabályok
├── composer.json               # PHP függőségek listája
├── composer.lock               # Pontos verzió zárolás
└── index.php                   # Belépési pont – minden kérés ide fut
```

---

## 5. Adatbázis létrehozása

### 5.1 phpMyAdmin megnyitása

Böngészőben: `http://localhost/phpmyadmin`

Alapértelmezett belépés:
- Felhasználónév: `root`
- Jelszó: *(üres)*

### 5.2 SQL futtatása

1. Kattints a **SQL** fülre a felső menüben
2. Másold be a `database/migrations.sql` fájl teljes tartalmát
3. Kattints **Végrehajtás** (Go)

Ez létrehozza az `szalmaprojekt` adatbázist és az összes táblát.

### 5.3 Táblák magyarázata

| Tábla | Leírás |
|-------|--------|
| `users` | Felhasználók – email hitelesítéssel, jelszó-visszaállítási tokenekkel |
| `restaurants` | Éttermek – admin tulajdonoshoz kötve |
| `menu_items` | Étlap tételek – étteremhez kötve |
| `orders` | Rendelések – customer, restaurant, driver összekapcsolva |
| `order_items` | Rendelés sorok – menü tétel + mennyiség + egységár |
| `access_logs` | Hozzáférési napló – minden kérés logolva |
| `rate_limits` | Brute force védelem – bejelentkezési kísérletek nyomon követése |

### 5.4 Users tábla extra mezők (új projekteknél nincs gond, meglévőnél futtatni kell)

Ha az adatbázis már létezik és hiányoznak az új oszlopok:

```sql
ALTER TABLE users
  ADD COLUMN email_verified_at      TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN email_verify_token     VARCHAR(64) NULL DEFAULT NULL,
  ADD COLUMN reset_token            VARCHAR(64) NULL DEFAULT NULL,
  ADD COLUMN reset_token_expires_at TIMESTAMP NULL DEFAULT NULL;
```

Ha a `rate_limits` tábla hiányzik:

```sql
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip VARCHAR(45) NOT NULL,
    action VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_action (ip, action, created_at)
);
```

---

## 6. Környezeti változók konfigurálása

### 6.1 .env fájl létrehozása

Másold le a `.env.example` fájlt:

```bash
copy .env.example .env
```

### 6.2 .env fájl kitöltése

```env
# Alkalmazás URL-je (XAMPP helyi fejlesztésnél)
APP_URL=http://localhost/Szalmaprojekt

# Adatbázis kapcsolat
DB_HOST=localhost
DB_NAME=szalmaprojekt
DB_USER=root
DB_PASS=

# JWT beállítások
JWT_SECRET=valami-hosszu-veletlenszeru-string-ide-minimum-32-karakter
JWT_EXPIRY=3600

# SMTP email (Mailtrap ajánlott fejlesztéshez)
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=a_te_mailtrap_felhasznaloneved
MAIL_PASSWORD=a_te_mailtrap_jelszo
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@szalmaprojekt.hu
MAIL_FROM_NAME=Szalma
```

### 6.3 JWT_SECRET generálása

A JWT titkosítási kulcsnak véletlenszerűnek és hosszúnak kell lennie. Generálás:

```php
<?php echo bin2hex(random_bytes(32)); ?>
```

Vagy online: https://generate-random.org/api-key-generator

### 6.4 Mailtrap beállítása (email tesztelés)

A Mailtrap egy ingyenes email tesztelő szolgáltatás – a valódi emailek nem mennek ki, hanem egy webes postafiókban jelennek meg.

1. Regisztrálj: https://mailtrap.io/
2. Hozz létre egy Inbox-ot
3. Kattints az Inbox nevére → SMTP Settings fül
4. Másold ki a Host, Port, Username, Password értékeket a `.env` fájlba

---

## 7. Composer függőségek telepítése

### 7.1 Telepítés

Nyiss egy parancssort (`cmd`) a projekt mappájában:

```bash
cd C:\xampp\htdocs\Szalmaprojekt
composer install
```

Ez letölti az összes szükséges csomagot a `vendor/` mappába.

### 7.2 Telepített csomagok

| Csomag | Verzió | Mire való |
|--------|--------|-----------|
| `firebase/php-jwt` | ^6.0 | JWT token generálás és ellenőrzés |
| `mobiledetect/mobiledetectlib` | ^3.0 | Eszközfelismerés (mobil/tablet/asztali) |
| `fakerphp/faker` | ^1.0 | Tesztadat generálás |
| `vlucas/phpdotenv` | ^5.0 | `.env` fájl betöltése |
| `phpmailer/phpmailer` | ^6.0 | Email küldés SMTP-n keresztül |
| `zircote/swagger-php` | ^6.0 | OpenAPI dokumentáció generálás |
| `phpunit/phpunit` | ^11.0 | Egység tesztek (dev függőség) |

### 7.3 Autoload frissítése

Ha új PHP osztályt adsz a `src/` mappához:

```bash
composer dump-autoload
```

---

## 8. Faker – adatbázis feltöltése tesztadatokkal

### 8.1 Seeder futtatása

```bash
cd C:\xampp\htdocs\Szalmaprojekt
php database/seeder.php
```

### 8.2 Mit tölt fel?

- **3 admin** felhasználó: `admin1@example.com` ... `admin3@example.com`
- **10 customer** felhasználó: `customer1@example.com` ... `customer10@example.com`
- **2 driver** felhasználó: `driver1@example.com`, `driver2@example.com`
- **5 étterem** véletlenszerű névvel és adatokkal
- **20 menü tétel** (éttermenként 4)
- **15 rendelés** különböző státuszokkal

**Minden teszt felhasználó jelszava:** `password`

> **Fontos:** A seeder törli az összes meglévő adatot futtatás előtt (`TRUNCATE TABLE`)!

### 8.3 Manuális regisztráció tesztelése

A seeder által létrehozott felhasználók email hitelesítés nélkül jönnek létre (`email_verified_at = NOW()`). Ha manuálisan regisztrálsz a `/api/auth/register` végponton, Mailtrap-ban kell megerősíteni az emailt.

---

## 9. API dokumentáció (Swagger UI)

### 9.1 Swagger UI megnyitása

A projekt futása közben böngészőben:

```
http://localhost/Szalmaprojekt/docs/
```

Ez egy interaktív oldal, ahol minden API végpont kipróbálható.

### 9.2 JWT token beállítása Swaggerben

1. Nyisd meg a Swagger UI-t
2. Kattints az **Authorize** gombra (jobb felső sarok, lakat ikon)
3. A `bearerAuth` mezőbe írd be a tokened: `Bearer <a_te_tokened>`
4. Kattints **Authorize** → **Close**
5. Mostantól a védett végpontok is elérhetők

### 9.3 Swagger JSON elérése

A nyers OpenAPI JSON specifikáció:

```
http://localhost/Szalmaprojekt/api/swagger.json
```

Ezt Postmanbe is importálhatod.

---

## 10. Postman Collection

### 10.1 Importálás Swagger-ből

1. Nyisd meg a Postmant
2. **Import** gomb → **Link** fül
3. URL: `http://localhost/Szalmaprojekt/api/swagger.json`
4. Kattints **Continue** → **Import**
5. Postman automatikusan létrehozza az összes endpoint-ot

### 10.2 Environment beállítása Postmanben

1. **Environments** → **+** (új)
2. Név: `Szalma Local`
3. Változók:

| Változó | Érték |
|---------|-------|
| `base_url` | `http://localhost/Szalmaprojekt` |
| `token` | *(bejelentkezés után töltsd ki)* |

4. Minden request URL-jében használd: `{{base_url}}/api/...`
5. Authorization fülön: Bearer Token → `{{token}}`

### 10.3 Collection exportálása

1. A collection neve mellett kattints a **...** menüre
2. **Export** → **Collection v2.1**
3. Mentsd a projekt mappájába: `postman_collection.json`
4. Commitold a repóba

---

## 11. API végpontok részletes leírása

### Alapválasz formátum

Minden végpont JSON választ ad vissza:

```json
{
  "status": 200,
  "message": "OK",
  "data": { ... }
}
```

Hiba esetén:

```json
{
  "status": 422,
  "message": "Field 'email' is required",
  "data": null
}
```

---

### Auth végpontok

#### POST `/api/auth/register`
Új felhasználó regisztrálása.

**Request body:**
```json
{
  "name": "Kiss János",
  "email": "kiss.janos@example.com",
  "password": "titkosjelszo",
  "role": "customer"
}
```

**Válasz (201):**
```json
{
  "status": 201,
  "message": "Created",
  "data": {
    "user": { "id": 1, "name": "Kiss János", ... },
    "message": "Check your email to verify your account."
  }
}
```

**Folyamat:**
1. Validáció (kötelező mezők, email formátum, min. 6 karakter jelszó)
2. Ellenőrzi, hogy az email már regisztrált-e
3. Létrehozza a felhasználót
4. Generál egy email hitelesítési tokent
5. Elküld egy megerősítő emailt
6. Visszaadja a felhasználó adatait

---

#### POST `/api/auth/login`
Bejelentkezés, JWT token visszaadása.

**Request body:**
```json
{
  "email": "kiss.janos@example.com",
  "password": "titkosjelszo"
}
```

**Válasz (200):**
```json
{
  "status": 200,
  "message": "OK",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR...",
    "user": {
      "id": 1,
      "name": "Kiss János",
      "email": "kiss.janos@example.com",
      "role": "customer"
    }
  }
}
```

**Folyamat:**
1. Rate limit ellenőrzés (max 5 kísérlet / 15 perc IP-nként)
2. Felhasználó keresése email alapján
3. Jelszó ellenőrzés (bcrypt)
4. Email hitelesítettség ellenőrzés
5. JWT token generálás (1 óráig érvényes)
6. Bejelentkezési értesítő email küldése
7. Rate limit törlése sikeres bejelentkezés esetén

---

#### GET `/api/auth/verify-email?token=<token>`
Email cím hitelesítése a regisztrációs emailben kapott linkkel.

**Válasz (200):**
```json
{
  "status": 200,
  "message": "OK",
  "data": { "message": "Email verified. You can now log in." }
}
```

---

#### POST `/api/auth/forgot-password`
Jelszó visszaállítási link kérése.

**Request body:**
```json
{ "email": "kiss.janos@example.com" }
```

**Válasz (200)** – mindig ugyanaz, nem árulja el, hogy az email létezik-e:
```json
{
  "status": 200,
  "message": "OK",
  "data": { "message": "If that email is registered, a reset link has been sent." }
}
```

---

#### POST `/api/auth/reset-password`
Jelszó megváltoztatása a tokennel.

**Request body:**
```json
{
  "token": "a_tokenben_kapott_token",
  "password": "ujjelszo123"
}
```

---

### Éttermek végpontjai

| Metódus | URL | Jogosultság | Leírás |
|---------|-----|-------------|--------|
| GET | `/api/restaurants` | Nyilvános | Összes étterem |
| GET | `/api/restaurants/{id}` | Nyilvános | Egy étterem |
| POST | `/api/restaurants` | Admin | Étterem létrehozása |
| PUT | `/api/restaurants/{id}` | Admin (tulajdonos) | Étterem módosítása |
| DELETE | `/api/restaurants/{id}` | Admin (tulajdonos) | Étterem törlése (204) |

**Étterem létrehozása (POST body):**
```json
{
  "name": "Pizzéria Bella",
  "address": "Budapest, Váci út 1.",
  "phone": "+36 1 234 5678",
  "description": "Autentikus olasz pizzák",
  "cuisine": "Olasz",
  "opening_hours": "10:00 - 22:00"
}
```

---

### Menü végpontjai

| Metódus | URL | Jogosultság | Leírás |
|---------|-----|-------------|--------|
| GET | `/api/restaurants/{id}/menu` | Nyilvános | Étterem menüje |
| POST | `/api/restaurants/{id}/menu` | Admin (tulajdonos) | Étel hozzáadása |
| PUT | `/api/restaurants/{id}/menu/{mid}` | Admin (tulajdonos) | Étel módosítása |
| DELETE | `/api/restaurants/{id}/menu/{mid}` | Admin (tulajdonos) | Étel törlése (204) |

**Étel hozzáadása (POST body):**
```json
{
  "name": "Margherita Pizza",
  "description": "Paradicsom, mozzarella, bazsalikom",
  "price": 2490.00,
  "available": true
}
```

---

### Rendelések végpontjai

| Metódus | URL | Jogosultság | Leírás |
|---------|-----|-------------|--------|
| GET | `/api/orders` | Bejelentkezett | Rendelések (szerep szerint szűrve) |
| GET | `/api/orders/{id}` | Bejelentkezett | Egy rendelés részletei |
| POST | `/api/orders` | Customer | Rendelés leadása |
| PUT | `/api/orders/{id}/status` | Admin/Driver | Státusz frissítése |
| DELETE | `/api/orders/{id}` | Customer (sajátja) | Rendelés lemondása (204) |

**Rendelés leadása (POST body):**
```json
{
  "restaurant_id": 1,
  "items": [
    { "menu_item_id": 3, "quantity": 2 },
    { "menu_item_id": 5, "quantity": 1 }
  ]
}
```

**Státusz értékek:**
- `pending` – Várakozik
- `preparing` – Elkészítés alatt
- `out_for_delivery` – Kiszállítás alatt
- `delivered` – Kézbesítve
- `cancelled` – Lemondva

---

### Felhasználók végpontjai

| Metódus | URL | Jogosultság | Leírás |
|---------|-----|-------------|--------|
| GET | `/api/users` | Admin | Összes felhasználó |
| GET | `/api/users/{id}` | Saját profil / Admin | Egy felhasználó |
| PUT | `/api/users/{id}` | Saját profil / Admin | Módosítás |
| DELETE | `/api/users/{id}` | Admin | Törlés (204) |

---

### Képfeltöltés

| Metódus | URL | Jogosultság | Leírás |
|---------|-----|-------------|--------|
| POST | `/api/upload` | Admin | Kép feltöltése |

**Postmanben:**
- Body: `form-data`
- Kulcs: `image`, Típus: `File`
- Max méret: 5 MB
- Formátumok: JPG, PNG, WebP, GIF

---

## 12. Autentikáció és jogosultságkezelés

### JWT token használata

Minden védett végpontnál az `Authorization` fejlécet kell küldeni:

```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

### JWT tartalom

A token dekódolva (https://jwt.io/):

```json
{
  "sub": 1,
  "role": "customer",
  "exp": 1714920000
}
```

- `sub` – felhasználó ID-je
- `role` – szerepkör (`customer`, `admin`, `driver`)
- `exp` – lejárat (Unix timestamp, alapértelmezett 1 óra)

### Token lejárata

A `JWT_EXPIRY` értéke másodpercben: `3600` = 1 óra.

Ha a token lejár, új bejelentkezés szükséges.

---

## 13. Email funkciók

### 13.1 Regisztrációs email hitelesítés

Regisztráció után:
1. A rendszer generál egy 64 karakteres véletlenszerű tokent
2. Elmenti az adatbázisba (`email_verify_token` mező)
3. Emailt küld a hitelesítési linkkel
4. A felhasználó nem tud bejelentkezni, amíg nem hitelesíti az emailjét

### 13.2 Bejelentkezési értesítő

Sikeres bejelentkezés után biztonsági emailt küld:
- Bejelentkezés ideje
- IP cím
- Eszköz típusa
- Böngésző
- Operációs rendszer

### 13.3 Jelszó visszaállítás

1. Felhasználó kéri: `POST /api/auth/forgot-password`
2. Rendszer generál tokent, elmenti az adatbázisba, 1 óráig érvényes
3. Emailt küld a visszaállítási linkkel
4. Felhasználó új jelszót ad meg: `POST /api/auth/reset-password`
5. Token törlődik beváltás után

---

## 14. Biztonsági megvalósítások (OWASP Top 10)

### A01 – Hozzáférés-vezérlés (Broken Access Control)

- Minden módosító végpont JWT ellenőrzéssel van védve
- Admin végpontok csak `admin` szerepű felhasználóknak
- `GET /api/users/{id}` – csak saját profil vagy admin
- `PUT /api/users/{id}` – csak saját profil vagy admin
- Étterem módosítás/törlés csak a tulajdonos adminnak

### A02 – Kriptográfiai hibák (Cryptographic Failures)

- Jelszavak: `password_hash()` bcrypt algoritmussal tárolva
- JWT: HS256 algoritmus, legalább 32 karakteres titkos kulcs
- Email/jelszó-visszaállítás tokenek: `bin2hex(random_bytes(32))` – kriptográfiai véletlen

### A03 – SQL Injection

- Minden adatbázis lekérdezés PDO prepared statement-tel fut
- Felhasználói adatok soha nem kerülnek közvetlenül SQL-be

### A04 – Tervezési hibák (Insecure Design)

- Email hitelesítés regisztráció után
- Jelszó-visszaállítás tokenek időkorlátos (1 óra)
- Szerepkör alapú hozzáférés-vezérlés (RBAC)
- Nem árulja el, hogy egy email regisztrált-e

### A05 – Biztonsági konfiguráció (Security Misconfiguration)

HTTP biztonsági fejlécek minden válasznál:
```
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
Content-Security-Policy: default-src 'none'
```
- PHP verzió nem látható (`X-Powered-By` eltávolítva)
- Feltöltött fájlok mappájában `.htaccess` tiltja a PHP futtatást

### A07 – Autentikációs hibák (Identification and Authentication Failures)

- Rate limiting bejelentkezésnél: max 5 kísérlet / 15 perc IP-nként
- Rossz jelszó és nem hitelesített email is rate limit-et triggerel
- Jelszó-visszaállítás is rate limitelt

### A09 – Naplózás és monitorozás (Security Logging and Monitoring Failures)

- Minden HTTP kérés naplózva az `access_logs` táblában
- Naplózott adatok: IP, eszköz, böngésző, OS, ország, város, ISP
- Sikertelen bejelentkezési kísérletek a `rate_limits` táblában

### A10 – SSRF (Server-Side Request Forgery)

- IP validáció `filter_var(FILTER_VALIDATE_IP)` a külső lekérdezés előtt
- Privát IP tartományok szűrve (`127.x`, `10.x`, `192.168.x`, stb.)

---

## 15. Projektstruktúra magyarázata

### index.php – Belépési pont

Minden HTTP kérés ide fut be. Feladatai sorban:
1. Autoload betöltés (Composer)
2. `.env` fájl betöltése
3. HTTP fejlécek beállítása (CORS + biztonsági)
4. Hozzáférési napló írása
5. URL feldolgozása (XAMPP alap path levágása)
6. Router létrehozása és route-ok regisztrálása
7. Kérés dispatch-elése

### Router.php – URL feldolgozás

- Dinamikus route-okat kezel: `{id}`, `{mid}` helyőrzőkkel
- HTTP metódus alapján is szűr (GET, POST, PUT, DELETE)
- Ha nincs egyező route: 404 választ küld

### Database.php – Adatbázis kapcsolat

Singleton minta – az egész kérés alatt egyetlen PDO kapcsolat létezik.
Automatikusan betölti a kapcsolati adatokat a `.env`-ből.

### Response.php – Válasz formázás

- `Response::json($data, $status, $message)` – JSON válasz küldése
- `Response::error($message, $status)` – hibaválasz
- `Response::noContent()` – 204 státusz, üres válasz (DELETE-hez)

### AuthMiddleware.php – JWT hitelesítés

- Kinyeri a Bearer tokent az `Authorization` fejlécből
- Dekódolja és ellenőrzi a JWT tokent
- Opcionálisan ellenőrzi a szerepkört
- Hiba esetén 401 vagy 403 választ küld és megáll

---

## 16. Éles szerverre telepítés (Deployment)

> **Figyelem:** Éles szerverre telepítés előtt kötelező az alábbi változtatások elvégzése!

### 16.1 Szükséges módosítások

#### .env fájl

```env
# Éles URL – a te domainedet írd be
APP_URL=https://www.szalmaprojekt.hu

# Adatbázis – a hosting provider adatait írd be
DB_HOST=localhost
DB_NAME=szalma_prod
DB_USER=szalma_db_user
DB_PASS=erős_adatbázis_jelszó_ide

# JWT – hosszú, véletlenszerű string!
JWT_SECRET=minimum_64_karakter_hosszu_veletlen_string_ide_soha_ne_oszd_meg

# Email – éles SMTP (pl. Mailgun, SendGrid, Gmail SMTP)
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@szalmaprojekt.hu
MAIL_PASSWORD=mailgun_api_kulcs
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@szalmaprojekt.hu
MAIL_FROM_NAME=Szalma Ételrendelő
```

#### index.php – CORS beállítás

Éles szerveren ne engedj minden origint! Változtasd:

```php
// Fejlesztés:
header('Access-Control-Allow-Origin: *');

// Éles (csak a te frontended URL-je):
header('Access-Control-Allow-Origin: https://www.szalmaprojekt.hu');
```

### 16.2 Szerver követelmények

| Követelmény | Minimum |
|-------------|---------|
| PHP | 8.2+ |
| MySQL | 5.7+ vagy MariaDB 10.4+ |
| Apache | mod_rewrite engedélyezve |
| PHP kiterjesztések | `pdo_mysql`, `mbstring`, `fileinfo`, `openssl` |

### 16.3 Fájlfeltöltés éles szerveren

1. Töltsd fel az összes fájlt FTP-n keresztül (FileZilla ajánlott)
   - **NE töltsd fel** a `vendor/` mappát – futtasd `composer install --no-dev` a szerveren
   - **NE töltsd fel** a `.env` fájlt – hozd létre közvetlenül a szerveren
2. Állítsd be a mappajogosultságokat:
   ```bash
   chmod 755 uploads/
   chmod 644 .env
   ```

### 16.4 Composer éles szerveren

```bash
composer install --no-dev --optimize-autoloader
```

- `--no-dev` – fejlesztői csomagok (PHPUnit) kihagyása
- `--optimize-autoloader` – gyorsabb osztálybetöltés

### 16.5 .htaccess ellenőrzése éles szerveren

Ha az URL rewriting nem működik, az Apache konfig fájlban engedélyezni kell:

```apache
<Directory /var/www/html/szalmaprojekt>
    AllowOverride All
</Directory>
```

### 16.6 Tilos éles szerveren

- Seeder futtatása (`php database/seeder.php`) – törli az összes adatot!
- Debug módban hagyni a PHP-t (`display_errors = Off`)
- Gyenge JWT titkos kulcs használata
- Root adatbázis felhasználó használata (hozz létre külön DB usert)

### 16.7 Éles szerver biztonsági checklist

- [ ] `.env` fájl nem elérhető böngészőből (`.htaccess` védi)
- [ ] `vendor/` mappa nem böngészhető
- [ ] `uploads/` mappában PHP futtatás tiltva
- [ ] HTTPS engedélyezve (SSL tanúsítvány)
- [ ] Adatbázis jelszó erős és egyedi
- [ ] JWT titkos kulcs min. 64 karakter, véletlenszerű
- [ ] CORS csak a saját frontend domain-re engedélyezett
- [ ] PHP hibaüzenetek kikapcsolva (`display_errors = Off`)
- [ ] Rendszeres adatbázis backup

---

## 17. Hibakeresés és hibaelhárítás

### Frequently Asked Questions

#### "Class not found" hiba
```
composer dump-autoload
```

#### "SQLSTATE: could not find driver"
A `pdo_mysql` PHP kiterjesztés nincs engedélyezve. XAMPP-ban:
1. Nyisd meg: `C:\xampp\php\php.ini`
2. Keresd meg: `;extension=pdo_mysql`
3. Vedd ki a pontosvesszőt: `extension=pdo_mysql`
4. Indítsd újra az Apache-ot

#### "Access denied for user 'root'"
Ellenőrizd a `.env` fájlban a `DB_USER` és `DB_PASS` értékeket.
XAMPP alapértelmezetten: `DB_USER=root`, `DB_PASS=` (üres)

#### "404 Not Found" minden végpontnál
A mod_rewrite nincs engedélyezve. Lásd a 3.4 fejezetet.

#### Token lejárt hiba (401)
A JWT token 1 óra után lejár. Jelentkezz be újra a friss token megszerzéséhez.

#### Email nem érkezik meg
1. Ellenőrizd a Mailtrap postafiókot
2. Ellenőrizd a `.env` SMTP beállításait
3. Ellenőrizd, hogy a `phpmailer/phpmailer` csomag telepítve van-e: `composer show phpmailer/phpmailer`

#### "Too many login attempts" (429)
Brute force védelem aktívált. 15 percet kell várni, vagy az adatbázisból törölni kell a rate_limits bejegyzéseket:
```sql
DELETE FROM rate_limits WHERE ip = 'a_te_ip_cimed' AND action = 'login';
```

#### Swagger UI üres / nem tölt be
1. Ellenőrizd: `http://localhost/Szalmaprojekt/api/swagger.json` – ad-e vissza JSON-t?
2. Ha PHP hibát ad: `composer require zircote/swagger-php`
3. Ha "file not found": Composer autoload frissítése szükséges

### PHP hibák megjelenítése fejlesztés közben

Fejlesztés alatt hasznos ha a PHP hibák megjelennek. Az `index.php` elejéhez adható:

```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

> **Éles szerveren soha ne legyen benne!**

---

## 18. HTTP státuszkódok összefoglalója

| Kód | Neve | Mikor használjuk |
|-----|------|-----------------|
| **200** | OK | Sikeres GET, PUT kérés |
| **201** | Created | Sikeres POST (létrehozás) |
| **204** | No Content | Sikeres DELETE (nincs válasz body) |
| **400** | Bad Request | Érvénytelen kérés (pl. token nem váltható be) |
| **401** | Unauthorized | Hiányzó vagy érvénytelen JWT token |
| **403** | Forbidden | Nincs jogosultság (rossz szerepkör, nem saját adat) |
| **404** | Not Found | Az erőforrás nem létezik |
| **409** | Conflict | Ütközés (pl. email már regisztrált) |
| **422** | Unprocessable Entity | Validációs hiba (hiányzó mező, rossz formátum) |
| **429** | Too Many Requests | Rate limit elérve |
| **500** | Internal Server Error | Szerver oldali hiba |

---

## Gyors indítási útmutató (Összefoglaló)

```bash
# 1. XAMPP: Apache és MySQL elindítása

# 2. Adatbázis létrehozása phpMyAdmin-ban (database/migrations.sql futtatása)

# 3. .env fájl létrehozása
copy .env.example .env
# → szerkeszd a .env fájlt

# 4. Composer csomagok telepítése
cd C:\xampp\htdocs\Szalmaprojekt
composer install

# 5. Tesztadatok betöltése
php database/seeder.php

# 6. API tesztelése
# Böngésző: http://localhost/Szalmaprojekt/docs/
# Vagy Postman: import http://localhost/Szalmaprojekt/api/swagger.json

# 7. Bejelentkezés tesztelése
# POST http://localhost/Szalmaprojekt/api/auth/login
# Body: { "email": "admin1@example.com", "password": "password" }
```

---

*Dokumentáció utoljára frissítve: 2026-05-05*

# Food Delivery System — Design Spec
**Date:** 2026-04-28  
**Tech:** PHP (vanilla, no framework) + MySQL + Composer  
**Project:** Ételkiszállítási rendszer (Electronic Business Security course project)

---

## 1. Overview

Multi-restaurant food delivery REST API. Three user roles: **customer**, **admin**, **driver**.

- Customers browse restaurants/menus and place orders
- Admins manage their restaurant, menu items, and order statuses
- Drivers update order status (out for delivery → delivered)

No email notifications. JWT authentication. All data in MySQL.

---

## 2. Project Structure

```
Szalmaprojekt/
├── index.php                          # Front controller
├── .htaccess                          # Route all requests to index.php
├── composer.json
├── .env                               # DB credentials, JWT secret
├── src/
│   ├── Router.php                     # URI + HTTP method matcher
│   ├── Response.php                   # JSON response helper
│   ├── Auth.php                       # JWT encode/decode
│   ├── Middleware/
│   │   └── AuthMiddleware.php         # JWT verification + role check
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── RestaurantController.php
│   │   ├── MenuController.php
│   │   ├── OrderController.php
│   │   └── UserController.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Restaurant.php
│   │   ├── MenuItem.php
│   │   └── Order.php
│   └── Services/
│       ├── DeviceDetector.php         # MobileDetect wrapper
│       └── IpLookup.php               # ip-api.com integration
├── database/
│   ├── migrations.sql
│   └── seeder.php                     # Faker seeder
└── docs/
    └── postman_collection.json
```

---

## 3. Database Schema

```sql
users (
  id INT PK AUTO_INCREMENT,
  name VARCHAR(100),
  email VARCHAR(150) UNIQUE,
  password_hash VARCHAR(255),
  role ENUM('customer','admin','driver'),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)

restaurants (
  id INT PK AUTO_INCREMENT,
  name VARCHAR(150),
  address VARCHAR(255),
  phone VARCHAR(30),
  admin_id INT FK -> users.id,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)

menu_items (
  id INT PK AUTO_INCREMENT,
  restaurant_id INT FK -> restaurants.id,
  name VARCHAR(150),
  description TEXT,
  price DECIMAL(8,2),
  available TINYINT(1) DEFAULT 1
)

orders (
  id INT PK AUTO_INCREMENT,
  customer_id INT FK -> users.id,
  restaurant_id INT FK -> restaurants.id,
  driver_id INT NULL FK -> users.id,
  status ENUM('pending','preparing','out_for_delivery','delivered','cancelled'),
  total_price DECIMAL(10,2),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)

order_items (
  id INT PK AUTO_INCREMENT,
  order_id INT FK -> orders.id,
  menu_item_id INT FK -> menu_items.id,
  quantity INT,
  unit_price DECIMAL(8,2)
)

access_logs (
  id INT PK AUTO_INCREMENT,
  user_id INT NULL,
  ip_address VARCHAR(45),
  device_type VARCHAR(20),
  browser VARCHAR(100),
  os VARCHAR(100),
  ip_country VARCHAR(100),
  ip_city VARCHAR(100),
  ip_isp VARCHAR(150),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

---

## 4. API Endpoints

All responses follow this format:
```json
{ "status": 200, "message": "OK", "data": {} }
```

### Auth
| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| POST | /api/auth/register | No | Register new user |
| POST | /api/auth/login | No | Login, returns JWT |

### Restaurants
| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| GET | /api/restaurants | No | List all restaurants |
| GET | /api/restaurants/{id} | No | Get one restaurant |
| POST | /api/restaurants | admin | Create restaurant |
| PUT | /api/restaurants/{id} | admin | Update restaurant |
| DELETE | /api/restaurants/{id} | admin | Delete restaurant |

### Menu Items
| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| GET | /api/restaurants/{id}/menu | No | List menu |
| POST | /api/restaurants/{id}/menu | admin | Add menu item |
| PUT | /api/restaurants/{id}/menu/{mid} | admin | Update item |
| DELETE | /api/restaurants/{id}/menu/{mid} | admin | Delete item |

### Orders
| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| GET | /api/orders | customer/admin | List orders |
| GET | /api/orders/{id} | customer/admin/driver | Get order detail |
| POST | /api/orders | customer | Place order |
| PUT | /api/orders/{id}/status | admin/driver | Update status |
| DELETE | /api/orders/{id} | customer | Cancel (pending only) |

### Users
| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| GET | /api/users | admin | List all users |
| GET | /api/users/{id} | any | Get user profile |
| PUT | /api/users/{id} | same user/admin | Update profile |
| DELETE | /api/users/{id} | admin | Delete user |

---

## 5. Authentication

- JWT via `firebase/php-jwt`
- Token expiry: 1 hour
- Header: `Authorization: Bearer <token>`
- `AuthMiddleware` decodes token, injects user object into request context
- Role stored in JWT payload: `{ "sub": 1, "role": "customer", "exp": ... }`

---

## 6. Security (OWASP Top 10)

| Threat | Mitigation |
|--------|-----------|
| Broken Auth | JWT expiry, bcrypt (`password_hash`) |
| SQL Injection | PDO prepared statements everywhere, no string concatenation in queries |
| XSS | `htmlspecialchars()` on all output |
| Broken Access Control | AuthMiddleware role check per route |
| Security Misconfiguration | `.env` for secrets, `.htaccess` blocks direct PHP access |
| Sensitive Data Exposure | Passwords never returned in API responses |
| Mass Assignment | Explicit field whitelist in each controller |

---

## 7. Device Detection & IP Logging

- `DeviceDetector.php` wraps `mobiledetect/mobiledetectlib`
- `IpLookup.php` calls `http://ip-api.com/json/{ip}` (free, no API key)
- Both run on every request, result stored in `access_logs`
- Detects: device type (mobile/tablet/desktop), browser, OS, country, city, ISP

---

## 8. Composer Dependencies

```json
{
  "require": {
    "firebase/php-jwt": "^6.0",
    "mobiledetect/mobiledetectlib": "^3.0",
    "fakerphp/faker": "^1.0",
    "vlucas/phpdotenv": "^5.0"
  }
}
```

---

## 9. Database Seeder

`database/seeder.php` using Faker creates:
- 3 admin users
- 10 customer users
- 2 driver users
- 5 restaurants (one per admin, 2 share)
- 4 menu items per restaurant (20 total)
- 15 orders with order_items

---

## 10. Postman Collection

`docs/postman_collection.json` contains all endpoints with:
- Example request bodies
- Saved responses for each call
- Environment variables: `base_url`, `token`

---

## 11. Out of Scope

- Email (PHPMailer skipped per decision)
- OAuth 2.0 (optional in requirements, not implemented)
- Frontend UI (API only)
- Payment processing

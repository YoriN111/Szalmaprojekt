# Szalmaprojekt – Projekt bemutató

> Ez a dokumentum bemutatja a projekt összes funkcióját, úgy magyarázva, mintha még soha nem hallottál volna programozásról.

---

## Mi ez a projekt?

A **Szalmaprojekt** egy ételfutár-rendszer „agya" – egy úgynevezett **REST API** (Application Programming Interface, magyarul: alkalmazásprogramozási felület).

Képzeld el úgy: ez a projekt olyan, mint egy éttermi pult mögött álló alkalmazott. Nem ő az étterem díszítése (nincs benne gomb, szín, weboldal), hanem ő az, aki fogadja a rendeléseket, ellenőrzi a személyazonosságot, kezeli az adatokat, és visszaadja az eredményt. Egy mobilalkalmazás vagy weboldal majd „megkéri" ezt a programot, hogy adjon adatot vagy végezzen el valamit.

**Technológia:** PHP (programnyelv) + MySQL (adatbázis) + Composer (csomagkezelő)

---

## Szerepkörök – Ki mit tehet?

A rendszerben háromféle felhasználó létezik:

| Szerepkör | Magyar neve | Mit tehet? |
|-----------|-------------|-----------|
| `customer` | Vásárló | Böngészi az éttermeket, rendel, lemondja saját rendelését |
| `admin` | Admin/Étterem-tulajdonos | Éttermet kezel, étlapot szerkeszt, képet tölt fel, felhasználókat lát |
| `driver` | Futár | Látja az aktív rendeléseket, frissíti azok állapotát |

> Regisztrációkor csak `customer` vagy `driver` szerepkör választható. Az `admin` szerepkört csak kézzel, adatbázisban lehet beállítani – ez szándékos biztonsági döntés.

---

## 1. Felhasználókezelés és Bejelentkezés (Auth)

### Regisztráció
**Mi történik, amikor valaki regisztrál?**

1. Megadja a nevét, email-címét, jelszavát (és opcionálisan: szerepkörét)
2. A rendszer ellenőrzi, hogy az email-cím formailag helyes-e
3. Ellenőrzi, hogy a jelszó legalább 6 karakter-e
4. Ellenőrzi, hogy az email-cím még nincs-e regisztrálva
5. A jelszót **bcrypt** algoritmussal titkosítja – az adatbázisban sosem a valódi jelszó tárolódik, csak egy visszafejthetetlen „lenyomat"
6. Elküld egy **megerősítő emailt** egy egyedi linkkel
7. Addig nem lehet bejelentkezni, amíg a link nincs megnyomva

### Email-cím megerősítés
A regisztrációs emailben lévő linkre kattintva a rendszer „megjelöli" a fiókot mint megerősítettet. Megerősítés nélkül a bejelentkezés megtagadva.

### Bejelentkezés
**Mi történik login után?**

1. A rendszer ellenőrzi, hogy az email-jelszó pár helyes-e
2. Ha az email még nincs megerősítve: megtagadja a belépést
3. Ha sikeres a belépés: küld egy **JWT tokent** (erről lentebb)
4. **Biztonsági email értesítést** küld a felhasználónak: mikor lépett be, milyen IP-ről, milyen eszközről, milyen böngészőből és operációs rendszerből
5. Ha valaki **5-ször egymás után rosszul adja meg a jelszavát** 15 percen belül: a rendszer blokkolja azt az IP-címet (rate limiting – erről lentebb)

### JWT Token – mi ez?
Képzeld el, mint egy **belépőkártya**. Bejelentkezéskor kapsz egyet. Minden további kérésnél ezt kell „felmutatni" – így a rendszer tudja, ki vagy és milyen jogosultságod van. A kártya 1 óra után lejár (ez beállítható a `.env` fájlban). Senki nem tudja hamisítani, mert egy titkos kulccsal van aláírva.

### Elfelejtett jelszó
1. Megadsz egy email-címet
2. A rendszer küld egy **jelszóvisszaállító linket** – de **mindig ugyanazt az üzenetet adja vissza**, akár létezik az email, akár nem. Ez azért fontos, hogy ne lehessen kideríteni, ki van regisztrálva.
3. A linkben lévő token **1 óráig érvényes**, utána lejár
4. A linken keresztül megadható az új jelszó

---

## 2. Éttermek kezelése

### Nyilvános funkciók (bejelentkezés nélkül is elérhető)
- **Összes étterem listázása** – visszaadja az összes regisztrált éttermet
- **Egy étterem adatainak lekérése** – ID alapján megmutatja az étterem nevét, címét, telefonszámát

### Admin funkciók (csak bejelentkezett admin számára)
- **Étterem létrehozása** – az admin megadja a nevet, címet, telefonszámot; a rendszer automatikusan hozzárendeli az adminhoz mint tulajdonoshoz
- **Étterem módosítása** – csak saját éttermet módosíthat, mást nem
- **Étterem törlése** – csak saját éttermet törölhet; törléssel az összes hozzá tartozó menüpont és rendelés is törlődik (adatbázis kaszkád)

---

## 3. Étlap kezelése (Menüpontok)

### Nyilvános funkciók
- **Étterem étlapjának listázása** – megmutatja az étterem összes menüpontját (neve, leírása, ára, elérhetősége)

### Admin funkciók (csak saját étteremhez)
- **Menüpont hozzáadása** – nevet, leírást, árat és elérhetőségi státuszt lehet megadni
- **Menüpont módosítása** – pl. árat vagy nevet változtatni
- **Menüpont törlése** – eltávolítja az étlapról
- **`available` mező** – egy menüpont „ideiglenesen nem elérhető"-re állítható anélkül, hogy törölni kellene (pl. elfogyott az alapanyag); ilyenkor rendelni sem lehet belőle

---

## 4. Rendelések

### Rendelés leadása (csak vásárló)
1. A vásárló megadja: melyik étteremből rendel, és milyen menüpontokból mennyit
2. A rendszer **minden menüpontnál ellenőrzi**, hogy az elérhető-e – ha bármelyik nem elérhető, az egész rendelés megtagadva
3. Az **árat a szerver számítja ki** a menüpontok árából – a kliens nem manipulálhatja az összeget
4. Az egész rendelés **adatbázis-tranzakcióban** történik: vagy minden bekerül, vagy semmi. Ez megakadályozza, hogy „félkész" rendelés maradjon az adatbázisban, ha valami hiba történik közben.
5. A rendelés `pending` (függőben lévő) állapotból indul

### Rendelés állapotok
Egy rendelés ezeken az állapotokon mehet végig:

```
pending → preparing → out_for_delivery → delivered
                 ↘
              cancelled
```

| Állapot | Magyar | Ki állíthatja be? |
|---------|--------|-------------------|
| `pending` | Függőben | – (automatikus rendeléskor) |
| `preparing` | Készítés alatt | Admin, Futár |
| `out_for_delivery` | Úton | Admin, Futár |
| `delivered` | Kiszállítva | Admin, Futár |
| `cancelled` | Lemondva | Admin, Futár; vagy Vásárló (csak `pending` állapotban) |

### Rendelések megtekintése
A rendszer **szerepkör szerint szűr**:
- **Vásárló**: csak a saját rendeléseit látja
- **Futár**: csak a `preparing` és `out_for_delivery` állapotú rendeléseket látja – plusz megkapja az étterem pontos címét, telefonszámát és a vásárló email-címét (mert szüksége van rá a kiszállításhoz)
- **Admin**: mindent lát

### Rendelés lemondása (vásárló)
Vásárló csak `pending` állapotú, saját rendelését mondhatja le. Ha már elkezdték készíteni (`preparing`), nem lehet visszavonni.

---

## 5. Felhasználók kezelése

- **Admin listázza az összes felhasználót** – látja a neveket, emaileket, szerepköröket
- **Saját profil megtekintése** – mindenki láthatja saját adatait; más adatait nem (kivéve admin)
- **Saját profil módosítása** – nevet, emailt, jelszót lehet változtatni; más profilját nem (kivéve admin)
- **Felhasználó törlése** – csak admin teheti; törléssel az összes rendelése is törlődik

> A jelszó sosem kerül vissza válaszban – az adatbázis-lekérdezések kizárják a `password_hash` mezőt a visszaadott adatokból.

---

## 6. Képfeltöltés

Csak admin tölthet fel képet (pl. étel fotó, étterem fotó).

**Ellenőrzések feltöltéskor:**
- Csak képfájl fogadható el: JPG, PNG, WebP, GIF
- Maximum méret: **5 MB**
- A rendszer **ténylegesen megvizsgálja a fájl tartalmát** (nem csak a kiterjesztést) – ha valaki átnevez egy `.php` fájlt `.jpg`-re, azt is kiszűri
- A mentett fájl neve **véletlenszerű 32 karakteres kód** (pl. `a3f7c2d1...jpg`) – nem lehet kitalálni más képek nevét
- Az `uploads/` mappába automatikusan egy **`.htaccess` fájl kerül**, ami megakadályozza, hogy valaki PHP-t futtasson abból a mappából – ez fontos biztonsági intézkedés

---

## 7. Biztonsági funkciók (összefoglalva)

| Funkció | Mit véd? |
|---------|---------|
| **JWT token** | Azonosítja a felhasználót; hamisíthatatlan |
| **bcrypt jelszóhash** | Ha kiszivárog az adatbázis, a jelszavak nem olvashatók |
| **Email-megerősítés** | Megakadályozza, hogy valaki más email-jével regisztráljon |
| **Rate limiting** | 5 sikertelen bejelentkezés/15 perc után blokkolja az IP-t |
| **Jelszó-visszaállítás anonimitása** | Nem árulja el, melyik email van regisztrálva |
| **Reset token lejárat** | A jelszóvisszaállító link 1 óra után érvénytelen |
| **XSS védelem** | A felhasználó neve és emailje szanitizálva tárolódik |
| **Biztonsági HTTP fejlécek** | Véd iframe-betöltés, MIME-sniffing és más támadások ellen |
| **Upload MIME ellenőrzés** | Megakadályozza álcázott PHP-fájlok feltöltését |
| **Upload .htaccess** | PHP-futtatás tiltva az uploads/ mappában |
| **Szerepkör-alapú jogosultság** | Mindenki csak azt teheti, amire jogosult |
| **Tulajdonos-ellenőrzés** | Admin csak saját étteremét módosíthatja |

---

## 8. Hozzáférési napló (Access Log)

**Minden egyes kérésnél** a rendszer eltárolja:

- Ki küldte (felhasználó ID, ha be van jelentkezve)
- Honnan jött (IP-cím)
- Milyen eszközről (mobil / tablet / desktop)
- Milyen böngészőből (Chrome, Firefox, Edge stb.)
- Milyen operációs rendszerből (Windows, Android, iOS stb.)
- Melyik országból, városból és internet-szolgáltatótól

Az IP-cím geolokációhoz a projekt a **ip-api.com** ingyenes API-ját használja (3 másodperces timeout – ha nem érhető el, csendben továbblép, nem akad meg a kérés).

---

## 9. Automatikus API dokumentáció (Swagger)

A projektben minden végpont le van dokumentálva **PHP attribútumokkal** – ezek speciális megjegyzések a kód felett, amelyek leírják, mit vár a végpont és mit ad vissza.

Ebből a rendszer automatikusan generál egy **Swagger/OpenAPI JSON fájlt**, amely elérhető itt:

```
http://localhost/Szalmaprojekt/api/swagger.json
```

Ezt be lehet tölteni a https://editor.swagger.io oldalra, ahol vizuálisan, gombokkal is kipróbálhatók az API végpontok.

---

## 10. Technikai felépítés

### Mappa- és fájlszerkezet

```
Szalmaprojekt/
├── index.php              ← Belépési pont – minden kérés ide érkezik
├── .env                   ← Titkos beállítások (jelszavak, kulcsok)
├── .htaccess              ← URL átírás (minden kérés → index.php)
├── composer.json          ← Csomagok listája
├── database/
│   └── migrations.sql     ← Adatbázis táblák létrehozó SQL fájl
├── src/
│   ├── Auth.php           ← JWT token kezelés
│   ├── Database.php       ← Adatbázis kapcsolat (singleton)
│   ├── Response.php       ← Egységes JSON válaszformátum
│   ├── Router.php         ← URL routing (melyik URL melyik kódot hívja)
│   ├── Controllers/       ← Kéréskezelők (mit csinál az API)
│   ├── Models/            ← Adatbázis modellek (hogyan tárolja az adatokat)
│   ├── Middleware/        ← Auth ellenőrzés (jogosult-e a kérés?)
│   └── Services/          ← Külső szolgáltatások (email, IP lookup, eszközfelismerés)
├── uploads/               ← Feltöltött képek helye
└── vendor/                ← Composer csomagok (ne módosítsd kézzel)
```

### Külső csomagok (Composer)

| Csomag | Mire való? |
|--------|-----------|
| `firebase/php-jwt` | JWT token generálás és ellenőrzés |
| `vlucas/phpdotenv` | `.env` fájl beolvasása |
| `phpmailer/phpmailer` | Email küldés SMTP-n keresztül |
| `mobiledetect/mobiledetectlib` | Eszköztípus felismerése (mobil/tablet/desktop) |
| `fakerphp/faker` | Teszt adatok generálása |
| `zircote/swagger-php` | Swagger dokumentáció automatikus generálása |
| `phpunit/phpunit` | Automatizált tesztelés |

### Hogyan dolgozza fel a rendszer a kéréseket?

1. Böngésző/app küld egy kérést (pl. `GET /api/restaurants`)
2. Apache (XAMPP) fogadja, a `.htaccess` átirányítja → `index.php`
3. `index.php` betölti a `.env` fájlt és beállítja a fejléceket
4. Naplózza a kérést az `access_logs` táblába
5. A `Router` megtalálja a megfelelő `Controller` metódust
6. A `Controller` elvégzi az ellenőrzéseket és meghívja a `Model`-t
7. A `Model` kommunikál az adatbázissal
8. A `Response` visszaküldi a JSON választ

---

## Az összes API végpont egy táblában

### Bejelentkezés / Regisztráció
| Metódus | URL | Mit csinál | Kell-e bejelentkezés? |
|---------|-----|-----------|----------------------|
| POST | `/api/auth/register` | Regisztrál | Nem |
| POST | `/api/auth/login` | Bejelentkezik, tokent ad | Nem |
| GET | `/api/auth/verify-email?token=...` | Megerősíti az emailt | Nem |
| POST | `/api/auth/forgot-password` | Jelszóvisszaállító emailt küld | Nem |
| POST | `/api/auth/reset-password` | Új jelszót állít be | Nem |

### Éttermek
| Metódus | URL | Mit csinál | Kell-e bejelentkezés? |
|---------|-----|-----------|----------------------|
| GET | `/api/restaurants` | Listáz | Nem |
| GET | `/api/restaurants/{id}` | Egy étterem adatai | Nem |
| POST | `/api/restaurants` | Létrehoz | Admin |
| PUT | `/api/restaurants/{id}` | Módosít (csak sajátot) | Admin |
| DELETE | `/api/restaurants/{id}` | Töröl (csak sajátot) | Admin |

### Étlap
| Metódus | URL | Mit csinál | Kell-e bejelentkezés? |
|---------|-----|-----------|----------------------|
| GET | `/api/restaurants/{id}/menu` | Étlap listázás | Nem |
| POST | `/api/restaurants/{id}/menu` | Menüpont hozzáadása | Admin (saját étterem) |
| PUT | `/api/restaurants/{id}/menu/{mid}` | Menüpont módosítása | Admin (saját étterem) |
| DELETE | `/api/restaurants/{id}/menu/{mid}` | Menüpont törlése | Admin (saját étterem) |

### Rendelések
| Metódus | URL | Mit csinál | Kell-e bejelentkezés? |
|---------|-----|-----------|----------------------|
| GET | `/api/orders` | Lista (szerepkör szerint szűrt) | Igen |
| GET | `/api/orders/{id}` | Egy rendelés | Igen |
| POST | `/api/orders` | Rendelés leadása | Vásárló |
| PUT | `/api/orders/{id}/status` | Állapot frissítése | Admin / Futár |
| DELETE | `/api/orders/{id}` | Lemondás (csak pending) | Vásárló (saját) |

### Felhasználók
| Metódus | URL | Mit csinál | Kell-e bejelentkezés? |
|---------|-----|-----------|----------------------|
| GET | `/api/users` | Összes felhasználó | Admin |
| GET | `/api/users/{id}` | Egy felhasználó | Saját / Admin |
| PUT | `/api/users/{id}` | Módosítás | Saját / Admin |
| DELETE | `/api/users/{id}` | Törlés | Admin |

### Egyéb
| Metódus | URL | Mit csinál | Kell-e bejelentkezés? |
|---------|-----|-----------|----------------------|
| POST | `/api/upload` | Képfeltöltés | Admin |
| GET | `/api/swagger.json` | API dokumentáció | Nem |

---

*Dokumentáció írva: 2026-05-07*

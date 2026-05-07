# Szalmaprojekt – Telepítési és beállítási útmutató

> **Kinek szól ez az útmutató?**
> Olyanoknak, akik még sosem programoztak, és most először szeretnék elindítani ezt a projektet a saját számítógépükön, vagy feltölteni egy webszerverre.

---

## Mi ez a projekt?

Ez egy **ételfutár API** – olyan program, amivel étteremrendelést lehet kezelni (felhasználók, éttermek, menük, rendelések). Nem weboldal, amit böngészőben nézegetünk, hanem egy „háttérprogram", amit más alkalmazások (például egy mobilalkalmazás) hívnak meg.

---

## Szükséges programok (helyi gépen)

Mielőtt bármit csinálsz, ezeket kell telepíteni:

| Program | Mire való? | Letöltési link |
|---------|-----------|----------------|
| **XAMPP** | PHP és MySQL egyszerre fut benne | https://www.apachefriends.org |
| **Composer** | PHP csomagkezelő (mint egy appstore PHP-hoz) | https://getcomposer.org |

---

## 1. lépés – A projekt mappájának elhelyezése

A projekt mappájának itt kell lennie:

```
C:\xampp\htdocs\Szalmaprojekt\
```

Ha máshol van, másold oda. A `htdocs` mappa az a hely, ahol az XAMPP keresi a weboldalakat.

---

## 2. lépés – Composer csomagok telepítése

A projekthez külső kódok (csomagok) is szükségesek. Ezeket egyszer le kell tölteni.

1. Nyisd meg a **Windows PowerShell**-t vagy a **Parancssort** (CMD)
2. Navigálj a projekt mappájába:
   ```
   cd C:\xampp\htdocs\Szalmaprojekt
   ```
3. Futtasd ezt a parancsot:
   ```
   composer install
   ```

> Ez letölti az összes szükséges csomagot a `vendor` mappába. Internetre van szükség. Várj, amíg lefut.

Ha már van `vendor` mappa és az tele van fájlokkal, ezt a lépést **kihagyhatod** – a csomagok már telepítve vannak.

---

## 3. lépés – A `.env` fájl beállítása

A `.env` fájl tartalmazza az **érzékeny adatokat** (adatbázis jelszó, email fiók stb.). Ez a fájl nem kerül fel GitHubra biztonsági okokból.

### Így csináld:

1. A projekt mappájában találsz egy `.env.example` nevű fájlt – ez egy minta
2. Másold le, és nevezd el `.env`-re:
   ```
   copy .env.example .env
   ```
3. Nyisd meg a `.env` fájlt egy szövegszerkesztővel (pl. Notepad, VS Code)
4. Töltsd ki az adatokat (lásd lent)

### A `.env` fájl tartalma és magyarázata:

```env
APP_URL=http://localhost/Szalmaprojekt

DB_HOST=localhost
DB_NAME=szalmaprojekt
DB_USER=root
DB_PASS=

JWT_SECRET=change-me-to-a-long-random-string
JWT_EXPIRY=3600

MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=your_mailtrap_user
MAIL_PASSWORD=your_mailtrap_pass
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@szalmaprojekt.hu
MAIL_FROM_NAME=Szalma
```

| Sor | Mit jelent? | Helyi gépen mit írj? |
|-----|------------|----------------------|
| `APP_URL` | Az oldal teljes webcíme | `http://localhost/Szalmaprojekt` |
| `DB_HOST` | Az adatbázis szerver helye | `localhost` |
| `DB_NAME` | Az adatbázis neve | `szalmaprojekt` |
| `DB_USER` | Adatbázis felhasználónév | `root` |
| `DB_PASS` | Adatbázis jelszó | *(marad üres, ha XAMPP alapbeállítás)* |
| `JWT_SECRET` | Titkos kulcs a bejelentkezéshez | Írj ide **legalább 32 véletlen karaktert** |
| `JWT_EXPIRY` | Token lejárat másodpercben | `3600` = 1 óra |
| `MAIL_*` | Email küldés beállításai | Mailtrap.io fiókhoz szükséges (ld. lent) |

> **JWT_SECRET**: Ez olyan, mint egy jelszó a jelszavakhoz. Minél véletlenszerűbb, annál jobb. Pl.: `aX7kQm2pR9vBnLwYdTzE5sUcFhJo3iG8`

---

## 4. lépés – Az adatbázis létrehozása

Az adatbázis olyan, mint egy nagy Excel-tábla, ahol az adatok tárolódnak. Létre kell hozni, és be kell tölteni a táblaszerkezetet.

### Lépések:

1. Indítsd el az **XAMPP Control Panel**-t
2. Kattints a **Start** gombra az **Apache** és **MySQL** sorok mellett
3. Nyisd meg a böngészőben: `http://localhost/phpmyadmin`
4. A bal oldali sávban kattints az **Importálás** (Import) fülre – de előbb olvasd el a következő pontot

### Az SQL fájl importálása:

1. A phpMyAdmin felső menüjében kattints az **„Import"** gombra
2. Kattints a **„Fájl kiválasztása"** gombra
3. Keresd meg és válaszd ki: `C:\xampp\htdocs\Szalmaprojekt\database\migrations.sql`
4. Görgess le és kattints a **„Importálás"** (Go) gombra

Ez létrehozza:
- `szalmaprojekt` nevű adatbázist
- Benne a következő táblákat: `users`, `restaurants`, `menu_items`, `orders`, `order_items`, `access_logs`, `rate_limits`

> Ha hibát kapsz, hogy az adatbázis már létezik, az nem baj – a fájl tartalmaz `IF NOT EXISTS` feltételt, vagyis csak akkor hoz létre valamit, ha még nem létezik.

---

## 5. lépés – Az `uploads` mappa létrehozása

A program képfeltöltést is kezel. Ehhez szükséges egy mappa:

```
C:\xampp\htdocs\Szalmaprojekt\uploads\
```

Ha nincs ott, hozd létre kézzel (jobb klikk → Új mappa).

---

## 6. lépés – Tesztelés

Nyisd meg a böngészőt, és írd be:

```
http://localhost/Szalmaprojekt/api/restaurants
```

Ha valami ilyesmit látsz: `{"data":[],"message":"OK"}` vagy hasonló JSON szöveget – **minden működik!**

Ha hibaüzenetet látsz, ellenőrizd:
- Fut-e az Apache és MySQL az XAMPP-ban?
- Létezik-e a `.env` fájl (nem csak `.env.example`)?
- Be van-e importálva az adatbázis?

---

## Email küldés beállítása (Mailtrap)

A projekt email-t tud küldeni (pl. regisztrációkor megerősítő levél). Fejlesztés közben **Mailtrap.io**-t ajánlunk – ez egy teszt emailbox, nem küld ki valódi emaileket.

1. Regisztrálj a https://mailtrap.io oldalon (ingyenes)
2. Hozz létre egy „Inbox"-ot
3. Kattints az inboxra, majd válaszd a **SMTP** fület
4. Másold be az adatokat a `.env` fájlba:
   ```env
   MAIL_HOST=smtp.mailtrap.io
   MAIL_PORT=587
   MAIL_USERNAME=<amit Mailtrap mutat>
   MAIL_PASSWORD=<amit Mailtrap mutat>
   ```

---

## API dokumentáció megtekintése

A projekt automatikusan generál egy Swagger dokumentációt (ez leírja, milyen kéréseket lehet küldeni az API-nak):

```
http://localhost/Szalmaprojekt/api/swagger.json
```

Ezt a JSON-t be lehet tölteni a https://editor.swagger.io oldalra a vizuális megjelenítéshez.

---

---

# Szerver feltöltés (éles üzemeltetés)

> Ez akkor kell, ha nem csak a saját gépeden, hanem az interneten is elérhető szeretnéd tenni a projektet.

---

## Mi változik szerveren?

| Beállítás | Helyi gép | Szerver |
|-----------|-----------|---------|
| `APP_URL` | `http://localhost/Szalmaprojekt` | `https://sajatdomained.hu` |
| `DB_HOST` | `localhost` | általában `localhost` marad, de tárhely-cég megmondja |
| `DB_USER` | `root` | a tárhelyen létrehozott adatbázis felhasználó neve |
| `DB_PASS` | *(üres)* | a tárhelyen beállított jelszó |
| `JWT_SECRET` | bármi | **erős, véletlenszerű, legalább 32 karakter** |
| `MAIL_*` | Mailtrap teszt adatok | valódi SMTP szerver adatai (pl. Gmail, SendGrid) |

---

## Lépések a szerverre feltöltéshez

### 1. A fájlok feltöltése

Töltsd fel az összes fájlt a szerverre FTP-vel (pl. FileZilla program segítségével), **kivéve** ezeket:
- `vendor/` mappa – ezt ne töltsd fel, nagyon sok fájl van benne
- `.env` fájl – ezt se töltsd fel, érzékeny adatokat tartalmaz

> A `vendor` mappát a szerveren újra kell generálni a `composer install` paranccsal (lásd lent).

### 2. `.env` fájl létrehozása a szerveren

A legtöbb tárhely biztosít egy fájlkezelőt (File Manager) a vezérlőpultban (cPanel, Plesk stb.).

1. Nyisd meg a fájlkezelőt
2. Navigálj a projekt mappájába
3. Hozz létre egy `.env` nevű fájlt
4. Írd bele a szerveres adatokat (lásd a táblázatot feljebb)

### 3. Composer futtatása a szerveren

Ha a tárhelyhez SSH hozzáférés van (terminál a szerveren):

```bash
cd /var/www/html/szalmaprojekt   # (vagy ahol a projekt van)
composer install --no-dev --optimize-autoloader
```

A `--no-dev` azt jelenti: ne telepítse a fejlesztői csomagokat (pl. tesztelő eszközök) – ezek szerveren nem kellenek.
Az `--optimize-autoloader` gyorsabbá teszi a betöltést.

Ha nincs SSH, kérdezd meg a tárhely-céget, hogy tudnak-e Composer-t futtatni.

### 4. Adatbázis importálása a szerveren

1. Nyisd meg a tárhely vezérlőpultját (általában cPanel)
2. Keresd meg a **phpMyAdmin** opciót
3. Hozz létre egy új adatbázist (pl. `szalmaprojekt` névvel)
4. Hozz létre egy adatbázis-felhasználót, és add hozzá az adatbázishoz
5. Kattints az adatbázisra, majd az **Import** fülre
6. Töltsd fel a `database/migrations.sql` fájlt

### 5. `uploads` mappa jogosultság

A szerveren az `uploads` mappának írhatónak kell lennie:

```bash
chmod 755 uploads/
```

Vagy a fájlkezelőben jobb klikk → Jogosultságok → 755.

### 6. `.htaccess` ellenőrzése

A projekt gyökerében lévő `.htaccess` fájl kezeli az URL átírásokat. Ez szükséges az API működéséhez.

Ha az Apache szerveren nincs engedélyezve a mod_rewrite modul, kérd meg a tárhely-céget, hogy engedélyezzék.

Ha a projekt **közvetlenül a domain gyökerében** van (pl. `https://sajatdomained.hu/`), és nem almappában, akkor a `index.php`-ban ez a sor is jól fog működni:

```php
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
```

Ez automatikusan kitalálja, hogy almappában vagy gyökérben fut-e a projekt.

---

## Biztonsági teendők szerver előtt

- [ ] `JWT_SECRET` legyen legalább 32 véletlenszerű karakter – **soha ne hagyd a `change-me-...` értéket!**
- [ ] `DB_PASS` legyen erős jelszó
- [ ] A `.env` fájl **ne legyen nyilvánosan elérhető** a böngészőből (a `.htaccess` ezt alapból blokkolja)
- [ ] Valódi email SMTP adatok beállítva (ne Mailtrap)
- [ ] HTTPS legyen engedélyezve a szerveren (SSL tanúsítvány – a legtöbb tárhely ingyen adja Let's Encrypt-tel)

---

## Gyors összefoglaló táblázat

| Mit kell csinálni? | Helyi gép | Szerver |
|--------------------|-----------|---------|
| Fájlok elhelyezése | `C:\xampp\htdocs\Szalmaprojekt\` | FTP-vel feltöltés |
| `.env` fájl | Másolás `.env.example`-ból, kitöltés | Fájlkezelőben létrehozás, kitöltés |
| Csomagok telepítése | `composer install` | `composer install --no-dev` |
| Adatbázis | phpMyAdmin import (`migrations.sql`) | cPanel phpMyAdmin import |
| `uploads` mappa | Létrehozás kézzel | Létrehozás + `chmod 755` |
| Email | Mailtrap teszt adatok | Valódi SMTP |
| URL | `http://localhost/Szalmaprojekt` | `https://sajatdomained.hu` |

---

*Dokumentáció írva: 2026-05-07*

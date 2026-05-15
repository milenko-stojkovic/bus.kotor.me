# Testiranje uz MySQL (opciono)

Podrazumijevano, PHPUnit koristi **SQLite in-memory** (`phpunit.xml`) — brzo i bez lokalnog servera.

Za validaciju bližu **produkciji** (ENUM/`ALTER … MODIFY`, MySQL JSON, itd.) možeš pokrenuti isti suite protiv posebne MySQL baze.

## Pravilo imena baze

**`DB_DATABASE` mora završavati na `_test`** (npr. `bus_kotor_test`). Takvo ime smanjuje rizik da slučajno pokreneš migracije ili `RefreshDatabase` nad dev ili produkcijskom bazom.

## Rizik: RefreshDatabase

Testovi koji koriste **`Illuminate\Foundation\Testing\RefreshDatabase`** u **svakom** pokretanju **brišu sve tabele** u konekciji koju PHPUnit koristi, zatim ponovo izvrše migracije.

Ako je `DB_DATABASE` pogrešno podešen (npr. ista baza kao u `.env` za razvoj), **gubiš sve podatke** u toj bazi.

Uvijek:

1. Kreiraj **odvojenu** bazu čije ime završava na `_test`.
2. Prije pokretanja testova, provjeri koji driver i baza su aktivni (npr. `echo $env:DB_DATABASE` u PowerShell-u).

## Šta je u repou

| Fajl | Namjena |
|------|---------|
| `phpunit.xml` | Default: SQLite `:memory:` — **ne mijenjaj** za svakodnevni rad ako želiš brze testove. |
| `phpunit.mysql.xml` | Kopija konfiguracije sa `DB_CONNECTION=mysql` i placeholder vrijednostima (`bus_kotor_test`, `root`, prazna lozinka). |
| `.env.testing.mysql.example` | Primjer varijabli za MySQL test okruženje (bez pravih tajni). |

## Laragon / MySQL — siguran redoslijed

1. **Pokreni MySQL** (Laragon → Start All ili samo MySQL).

2. **Kreiraj bazu** (jednokratno), npr. u HeidiSQL / MySQL shell:

   ```sql
   CREATE DATABASE bus_kotor_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Ne** postavljaj `DB_DATABASE=bus_kotor_test` u glavnom `.env` ako tamo radiš razvoj sa pravim podacima — koristi samo env varijable u terminalu za PHPUnit ili zaseban lokalni fajl koji **nije** u git-u.

4. **Pokreni PHPUnit** sa MySQL konfiguracijom.

### PowerShell (Laragon PHP)

Iz korena repozitorija, eksplicitno postavi bazu i (po potrebi) kredencijale:

```powershell
Set-Location C:\laragon\www\bus.kotor.me
# Na Windowsu obično i: mysql.exe u PATH (v. sekciju ispod), inače migrate:fresh / schema load može pasti.
$env:DB_DATABASE = 'bus_kotor_test'
$env:DB_USERNAME = 'root'
$env:DB_PASSWORD = ''
.\vendor\bin\phpunit -c phpunit.mysql.xml
```

Ako `phpunit` nije u PATH-u, putanja može biti `C:\laragon\bin\php\php-8.x\php.exe vendor\bin\phpunit -c phpunit.mysql.xml` (prilagodi verziju PHP-a).

### Laravel `artisan test`

`artisan test` podrazumijeva koristi `phpunit.xml`. Za MySQL koristi **`phpunit` direktno** sa `-c phpunit.mysql.xml`, ili privremeno exportuj `DB_*` varijable pa pokreni — provjeri u svojoj Laravel/PHPUnit verziji koji način prima env (često je pouzdanije `vendor\bin\phpunit -c phpunit.mysql.xml`).

### Windows / Laragon: `mysql.exe` u PATH

Kada je u PHPUnit konfiguraciji **MySQL** driver, Laravel tokom **`migrate:fresh`** (npr. preko **`RefreshDatabase`**) može interno pozvati **MySQL klijent** (`mysql`) za **dump / učitavanje šeme** (schema state). Terminal mora moći da pokrene **`mysql.exe`** — ako nije u **`PATH`**, Windows javlja:

```text
'mysql' is not recognized as an internal or external command
```

**Gdje je `mysql.exe` (Laragon):** tipično ispod `C:\laragon\bin\mysql\`, u `bin` podfolderu instalacije (npr. `mysql-8.4.3-winx64\bin`). Provjeri Explorerom ili u PowerShell-u:

```powershell
Get-ChildItem C:\laragon\bin\mysql -Recurse -Filter mysql.exe | Select-Object -First 1 -ExpandProperty FullName
```

Primjer direktorijuma (verzija foldera se menja):

```text
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin
```

**Privremeno (samo ovaj PowerShell prozor)** — dodaj taj `bin` **ispred** postojećeg `PATH`:

```powershell
$env:PATH = "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin;$env:PATH"
```

**Brza provera:**

```powershell
mysql --version
```

Trajno dodavanje sistema `PATH` nije dokumentovano u repou — po potrebi koristi Windows postavke okruženja ili Laragon dokumentaciju.

## Verified local MySQL run (Laragon)

Na ovom repou jednokratno je lokalno na Laragonu provjereno **puno** pokretanje PHPUnit suite-a sa **`phpunit.mysql.xml`**:

- **Rezultat:** **435** testova, **1771** asercija — svi prolaze.
- **PHP:** Laragon **8.3.30**.
- **MySQL server:** **8.4.3** (verzija klijenta prati Laragon instalaciju).
- **Preduslov:** **`mysql.exe` mora biti u `PATH`** (vidi gornju sekciju); bez toga schema dump/load tokom migracija može pasti.

Tokom uvođenja MySQL testiranja uočeni su i **ispravljeni** tipični **SQLite/MySQL drift** slučajevi (detalji u git istoriji / `project-done.md`):

- **Ime stranog ključa:** migracija je pretpostavljala Laravel default **`vehicles_user_id_foreign`**, dok `create_vehicles_table` koristi eksplicitno ime **`fk_vehicles_user`** — na MySQL-u `dropForeign` nije pronalazio FK dok se logika ne uskladi sa stvarnim imenom.
- **`control_access` na `admins`:** migracija je učinjena **idempotentnom** (`Schema::hasColumn` u `up`/`down`) da se izbegne greška duplog dodavanja kolone ako već postoji u bazi.
- **Test fixture-i:** unos **`temp_data.status = 'failed'`** — vrijednost **nije** u produkcijskom `ENUM`; ispravno terminalno stanje u bazi je **`canceled`** (npr. `TempData::STATUS_CANCELED`).

**Uloga drivera:** SQLite in-memory (`phpunit.xml`) ostaje odličan za **brze lokalne** iteracije. **Suite sa `phpunit.mysql.xml` tretira se kao autoritativna pre-release provjera** ponašanja šeme kao u produkciji (`ENUM`, imena FK, grane `ALTER` u migracijama).

## SQLite vs MySQL i `temp_data.status`

SQLite (`phpunit.xml`) i dalje se **koristi za brze** lokalne iteracije; za paritet sa produkcijom vidi **[Verified local MySQL run (Laragon)](#verified-local-mysql-run-laragon)**.

Migracija `2026_04_29_220000_sqlite_relax_temp_data_status_check.php` na SQLite **uklanja** stari CHECK na `temp_data.status` i koristi širi `VARCHAR`, tako da vrijednosti poput `canceled` odgovaraju produkcijskom modelu u testovima na SQLite.

Testovi koji su ranije preskakani zbog SQLite šeme (**`PaymentCallbackSuccessAfterCanceledAdminEmailTest`**, **`AdminFiscalizationAlertPersistsDbTest`**) ponovo su pokretljivi na SQLite nakon te relaksacije; skipovi su uklonjeni uz potvrdu prolaza.

## Dalje (CI)

U CI-u dodaj poseban job koji kreira praznu `*_test` bazu i pokrene `phpunit -c phpunit.mysql.xml` — van opsega ovog minimalnog dokumenta.

## Poveznice

- Konvencije okruženja: **[project-conventions.md](./project-conventions.md)** — sekcija 3 (Laragon / artisan, MySQL suite).
- Indeks dokumenata: **[project-status-next-steps.md](./project-status-next-steps.md)**.
- Primjer DB varijabli u repou: **`.env.example`**, **`.env.testing.mysql.example`**.

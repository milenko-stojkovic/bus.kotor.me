# External file archive (MEGA)

**Poslednje ažuriranje:** 2026-05-15

Privatni fajlovi ispod `storage/app/private/` (npr. `limo_plate_uploads`, `limo_pickup_evidence`, `limo_pickup_photos` (tabela + zastarjeli prefiks putanje), **`limo_incidents/`** (fotografije incidenta), **`free-reservation-requests/`** (prilozi FZBR)) mogu se **arhivirati na MEGA** nakon što više nisu potrebni na produkcijskom disku. **Kredencijali za MEGA idu samo u `.env`** i koriste se **isključivo server-side** (PHP poziva Node skriptu); **nikakav browser** ne vidi lozinku.

---

## Izvor istine u bazi

Tabela **`external_file_archives`** drži:

- vezu na izvor (`source_table`, `source_id`, `source_column`)
- generisano ime fajla na MEGA (`generated_file_name` — **nije** originalni naziv korisnika)
- `mega_node_id` / `mega_path` kada je upload uspio
- `original_local_path` — relativna putanja na `local` (private) disku prije brisanja (i dalje cilj za preview/restore, čak i kad je na MEGA arhiviran **derivat**)
- **`archived_derivative`** (bool), **`derivative_source_path`**, **`derivative_options`** (JSON) — samo za **Limo plate upload** arhivu: na MEGA ide optimizovani JPEG, metadata opisuje izrez/resize (vidi Artisan ispod)
- `status`: `pending` | `uploaded` | `failed`
- `archived_at`, `local_deleted_at`
- **`preview_restored_at`**, **`preview_expires_at`** — vremenski ograničen admin **privremeni** re-download sa MEGA (Limo pickup/incident, FZBR prilozi u admin pregledu); vidi ispod.

---

## Pravilo brisanja lokalnog fajla

**Lokalni fajl se briše samo ako** su **upload na MEGA** i **ažuriranje reda u bazi** na `uploaded` uspješno završeni.** Ako upload ili DB update padne, fajl ostaje na disku.

---

## Generisano ime (bez korisničkog imena)

Format (ASCII, bez razmaka):

`{context_type}__{source_table}_{source_id}__{source_column}__{uuid}.{ext}`

Ekstenzija se uzima iz lokalne putanje (lower-case), osim kod **Limo plate derivata** gdje se na MEGA forsira **`.jpg`** (`ArchiveFilenameGenerator` + `extensionOverride`). `uuid` osigurava jedinstvenost.

---

## Konfiguracija

`config/external_archive.php` — **`preview_ttl_minutes`** iz **`EXTERNAL_ARCHIVE_PREVIEW_TTL_MINUTES`** (default **60**): trajanje privremenog preview fajla na disku (od trenutka re-downloada).

`config/services.php` → `services.mega`:

- `email` ← `MEGA_EMAIL`
- `password` ← `MEGA_PASSWORD`
- `base_folder` ← `MEGA_BASE_FOLDER` (default `bus.kotor`)
- `node_binary` ← `MEGA_NODE_BINARY` (opciono, inače `node`)
- `user_agent` ← `MEGA_USER_AGENT` (default **`BusKotorArchive/1.0`**) — eksplicitni **User-Agent** za megajs `Storage` (MEGA koristi ga za identifikaciju klijenta; preporučeno za server-side login).

Svi fajlovi idu **direktno u bazni folder** na MEGA — **bez podfoldera**.

---

## Servisi

- `App\Services\ExternalArchive\MegaArchiveService` — implementacija `MegaArchiveClient` (Node `scripts/mega-archive.js`, paket `megajs`). Login koristi fiksni **`userAgent`** iz `MEGA_USER_AGENT` / `services.mega.user_agent` (default `BusKotorArchive/1.0`). **Download:** megajs `File.prototype.download(opts)` vraća **Readable** stream; callback (ako se koristi) dobija **`(err, Buffer)`**, ne stream — skripta koristi `const rs = file.download({}); rs.pipe(writeStream)`.
- `App\Services\ExternalArchive\ExternalFileArchiveService::archiveLocalPrivateFile(...)` — pending red → upload → `uploaded` → brisanje **originalnog** lokalnog fajla na `original_local_path`. Opcioni argument **`ArchiveDerivativeUpload`**: upload sa privremenog JPEG-a (`LimoPlateArchiveDerivativeBuilder`), metadata na redu; privremeni derivat se briše nakon Node poziva.
- **`App\Services\Limo\LimoPlateArchiveDerivativeBuilder`** — samo za arhivu (ne dira OCR niti upload tok): korisnički izrez `plate_crop_*_bp` + **~12,5%** margine, inače cijela slika; max duža strana **1600 px**; **JPEG Q80**; bez grayscale. Zahtijeva PHP **GD**.
- **`App\Services\ExternalArchive\MegaDiagnoseService`** + komanda **`files:mega-diagnose`** — login + listing root + postojanje `MEGA_BASE_FOLDER` bez uploada (vidi Artisan).
- `ExternalFileArchiveService::restoreFromMega(...)` — trajni restore: preuzimanje na `original_local_path`, **`local_deleted_at = null`**.
- **`ExternalFileArchiveService::restoreFromMegaForPreview(...)`** — preuzimanje na istu putanju za **admin preview**; **`local_deleted_at` ostaje postavljen** (izvor istine za „kanonski” primjerak ostaje MEGA); postavljaju se **`preview_restored_at`** i **`preview_expires_at`** (TTL).
- **`ExternalFileArchiveService::ensureLocalPreviewForSource(...)`** — ako fajl postoji lokalno, vraća putanju; ako ne postoji i postoji odgovarajući **`uploaded`** MEGA red sa **`local_deleted_at`**, poziva `restoreFromMegaForPreview`. Putanja u arhivi mora se poklapati sa proslijeđenom relativnom putanjom.

### Admin — pregled slike tablice (Limo pickup)

- Ruta: **`GET /admin/limo/pickups/{limoPickupEvent}/plate-photo-preview`** (`admin.limo.pickups.plate-photo-preview`), samo za **`source = plate`**; fajl samo ispod prefiksa **`limo_pickup_evidence/`** (produkcija, vidi `LimoPlatePickupService`) ili **`limo_pickup_photos/`** (legacy); zabranjen `..` i ostale proizvoljne putanje; nema javnog MEGA linka.

### Admin — pregled slika incidenta (Limo)

- Rute: **`GET /admin/limo/incidents/{limoIncident}/plate-photo-preview`** (`admin.limo.incidents.plate-photo-preview`) i **`GET /admin/limo/incidents/{limoIncident}/branding-photo-preview`** (`admin.limo.incidents.branding-photo-preview`) — `LimoIncidentPhotoPreviewController`; dozvoljene su samo putanje ispod prefiksa **`limo_incidents/`** (vidi `LimoIncidentService`, helper `App\Support\LimoIncidentEvidencePreviewPath`).
- Arhiva: **`source_table = limo_incidents`**, **`source_id`** = ID reda, **`source_column`** = `plate_photo_path` ili `branding_photo_path`; **`original_local_path`** mora se tačno poklapati sa vrijednošću u tom polju (isti uslov kao kod pickup preview-a).

### Admin — pregled FZBR priloga (terminalni zahtjevi)

- Ruta: **`GET /admin/besplatne-rezervacije/fzbr/attachments/{freeReservationRequestAttachment}/preview`** (`panel_admin.fzbr-attachments.preview`) — `FzbrAttachmentPreviewController` + `ServeFzbrAttachmentFile`; samo kada roditeljski zahtjev ima **`status` ∈ {`fulfilled`, `rejected`}**; dozvoljene su samo putanje ispod prefiksa **`free-reservation-requests/`** (`App\Support\FzbrAttachmentPreviewPath`).
- Arhiva: **`source_table = free_reservation_request_attachments`**, **`source_column = stored_path`**, **`context_type = fzbr_attachment`** (kao kod `files:archive-private --source=fzbr`).
- Postojeća ruta za aktivne zahtjeve (**`GET /admin/besplatne-rezervacije/zahtjevi/{request}/attachments/{attachment}/preview`**) koristi isti servis za serviranje (MEGA privremeni restore + ista validacija putanje).

### Privremeni MEGA restore i čišćenje (zajednički princip)

- Ako je lokalni fajl obrisan poslije arhive, prvi zahtjev ga **privremeno** vraća na disk; čišćenje: **`php artisan files:cleanup-preview-cache`** (zakazano dnevno **00:15** `Europe/Podgorica` u `routes/console.php`) briše istekle preview fajlove gdje je **`local_deleted_at` još uvijek postavljen** i **`preview_expires_at <= sada`**; red u **`external_file_archives`** ostaje **`uploaded`**, MEGA se ne dira.

Log kanal `payments` (bez binarnog sadržaja): `external_archive_upload_started`, **`external_archive_derivative_prepared`** (`original_bytes`, `archive_bytes`, `reduction_percent`, `derivative_options`), `external_archive_upload_succeeded` (ista polja veličine kad je derivat), `external_archive_upload_failed`, `external_archive_local_deleted`, **`external_archive_preview_restored`**, **`external_archive_preview_cache_cleanup`**, `external_archive_preview_restore_failed`.

---

## Artisan

- `php artisan files:archive-private --source=all|fzbr|limo --dry-run --limit=100`  
  - **FZBR:** samo kada je zahtjev u terminalnom statusu (`fulfilled`, `rejected` — vidi `FreeReservationRequest`). PDF/dokumenti se arhiviraju **bez** slike derivata.  
  - **Limo plate:** `consumed_at` nije `NULL`. Prije uploada na MEGA gradi se **JPEG derivat** (korisnički izrez + ~12,5% margine ako postoji `plate_crop_*_bp`, inače cijela slika; max duža strana 1600 px, kvalitet 80). Na MEGA ide derivat; `original_local_path` i dalje pokazuje na originalnu privatnu putanju. U `external_file_archives`: `archived_derivative`, `derivative_source_path`, `derivative_options` (JSON). Log `payments`: `original_bytes`, `archive_bytes`, `reduction_percent`. Original na disku ostaje do uspješnog uploada + DB update.  
  - **Limo pickup foto:** roditeljski `limo_pickup_events.invoice_email_sent_at` nije `NULL` (konzervativno: poslije slanja računa emailom).  
  - **`limo_incidents`:** još **nije** uključeno (TODO — politika zadržavanja dokaza / email).
- `php artisan files:restore-private {archive_id}` — trajni restore sa MEGA na originalnu privatnu putanju (`local_deleted_at` se briše).
- **`php artisan files:mega-diagnose`** — provjera login-a i postojanja baznog foldera na MEGA (Node akcija `diagnose` u `scripts/mega-archive.js`; **ne** kreira bazni folder). Izlaz: maskirani email, **User-Agent** (`MEGA_USER_AGENT` / config), JSON polja (`login_ok`, `folder_found`, `root_children_sample`, …); lozinka se **nikad** ne ispisuje. Korisno kad browser login radi, a megajs vraća npr. `ENOENT (-9)`.
- **`php artisan files:cleanup-preview-cache`** — briše **istekle** privremene preview fajlove (vidi gore); ne dira redove gdje je **`local_deleted_at` null** (lokalno zadržani fajlovi).

Na Windows-u iz korena repa: `.\laragon-artisan.cmd files:archive-private --dry-run`, `.\laragon-artisan.cmd files:mega-diagnose` (vidi `docs/project-conventions.md` §3).

### Migracije (redoslijed)

1. `2026_05_14_120000_create_external_file_archives_table.php`
2. `2026_05_15_100000_add_preview_columns_to_external_file_archives_table.php`
3. `2026_05_15_120000_add_derivative_columns_to_external_file_archives_table.php`

---

## Šta još nije

- **Politika brisanja** fajlova na MEGA nije definisana.
- **Derivat arhive** za **Limo incident** branding / plate fotografije — nije uključen (samo puni fajl kad/ako incident uđe u `files:archive-private`).
- **UI** za generički pregled / trajni restore arhive (bez Limo admin preview linka) nije implementiran — i dalje postoji **`files:restore-private`** po ID arhive.

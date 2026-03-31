# Handoff za novi Cursor chat

**Svrha:** U novom tabu nema istorije ovog četa. Ovaj fajl daje **tekst koji možeš zalepiti** kao prvu poruku, plus uputstvo kako držati **TODO / DONE** u dokumentaciji.

---

## 1) Tekst za prvu poruku u novom chatu (kopiraj–nalepi)

Prilagodi putanju ako radni folder nije isti.

```
Radiš na Laravel 12 (PHP 8.3) projektu bus.kotor.me (rezervacije autobusa, plaćanje, fiskalizacija).

Obavezno prvo pročitaj (redosled):
- docs/handoff-new-chat.md (ovaj fajl — kontekst)
- docs/project-todo.md (šta je otvoreno)
- docs/project-done.md (šta je već urađeno — ne ponavljaj)
- docs/project-conventions.md (pravila + §0: dokumentacija kao izvor istine, ne zabune)

Kratki indeks svih docs: docs/project-status-next-steps.md

Pravilo ažuriranja: kad nešto završiš, premesti stavku iz docs/project-todo.md u docs/project-done.md (sa datumom). Ne ostavljaj zastarele TODO stavke.
Ako promena dira domen (payment, fiskal, auth), ažuriraj i odgovarajući tematski fajl u docs/ (v. docs/project-status-next-steps.md i §3 u handoff-new-chat.md).

Trenutni zadatak: [OVDE NAPIŠI ŠTA RADIŠ]
```

Zameni poslednji red konkretnim zadatkom (npr. „implementirati Bankart inquiry“).

---

## 2) Kako održavati TODO i DONE

| Akcija | Gde |
|--------|-----|
| Novi otvoreni posao | Dodaj stavku u `docs/project-todo.md` (prioritet / blok). |
| Završen posao | **Izbriši** iz `project-todo.md`, dodaj u `docs/project-done.md` sa datumom `YYYY-MM-DD` i jednom rečenicom šta je urađeno. |
| Veći milestone | U `project-done.md` možeš dodati podnaslov po mesecu ili temi. |
| Konvencije / pravila | Ažuriraj `docs/project-conventions.md`, ne širi chat. |
| Istorija odluke u istom doc-u („bilo → sada“) | Dozvoljeno po **`project-conventions.md` § 0.1** — uvek jasno označiti šta je **važeće**. |

**Zašto odvojeno od chat-a:** istorija četa se gubi; trajni opis treba da živi u `docs/`.

**Princip:** dokumentacija mora biti **izvor istine, ne zabune** — v. **`docs/project-conventions.md` § 0**. Fajlovi u `docs/` važe kao istina samo dok su **usklađeni sa kodom**; kad odkriješ kontradikciju, popravi doc ili kod, ne ostavljaj oba.

---

## 3) Dublja dokumentacija u `docs/` (domeni)

Tematski fajlovi (payment-states, callback, concurrency, cron, auth/guest, admin spec, itd.) **mora** da prate kod — v. `project-conventions.md` § 0 i § 8. Pri zatvaranju stavke iz `project-todo.md` proveri da li treba ažurirati pogođeni tematski `.md`. Indeks: `docs/project-status-next-steps.md` → „Ostala dokumentacija“.

---

## 4) Gde su detalji (bez dupliranja u chat)

| Tema | Dokument |
|------|----------|
| QA fake plaćanje / fiskal | `docs/fake-payment-and-fiscal-qa-checklist.md` |
| Konvencije (prevod, mail, queue) | `docs/project-conventions.md` |
| Usklađenost fake vs real API | `docs/fake-vs-real-contract-parity.md` |
| Zakazani zadaci | `docs/scheduled-tasks-overview.md` |
| Payment / temp_data tok | `docs/payment-states.md`, `docs/workflow-placanje-temp-data.md` |
| Admin | `docs/admin-panel.md` |

---

Poslednje ažuriranje ovog fajla: 2026-03-31 (dodata napomena o održavanju tematskih docs)

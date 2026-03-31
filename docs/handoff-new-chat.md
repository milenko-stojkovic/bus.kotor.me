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
- docs/project-conventions.md (pravila: ui_translations, mail, locale, queue, rute)

Kratki indeks svih docs: docs/project-status-next-steps.md

Pravilo ažuriranja: kad nešto završiš, premesti stavku iz docs/project-todo.md u docs/project-done.md (sa datumom). Ne ostavljaj zastarele TODO stavke.

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

**Zašto odvojeno od chat-a:** istorija četa se gubi; izvor istine su fajlovi u `docs/`.

---

## 3) Gde su detalji (bez dupliranja u chat)

| Tema | Dokument |
|------|----------|
| QA fake plaćanje / fiskal | `docs/fake-payment-and-fiscal-qa-checklist.md` |
| Usklađenost fake vs real API | `docs/fake-vs-real-contract-parity.md` |
| Zakazani zadaci | `docs/scheduled-tasks-overview.md` |
| Payment / temp_data tok | `docs/payment-states.md`, `docs/workflow-placanje-temp-data.md` |
| Admin | `docs/admin-panel.md` |

---

Poslednje ažuriranje ovog fajla: 2026-03-31

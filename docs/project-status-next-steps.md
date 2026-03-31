# Project status — indeks

**Poslednje ažuriranje:** 2026-03-31

Ovaj fajl je **kratak smerač**. Detalji su podeljeni da bi novi chat mogao da učita samo ono što treba.

| Dokument | Svrha |
|----------|--------|
| **[handoff-new-chat.md](./handoff-new-chat.md)** | Tekst za **kopiranje u prvu poruku** novog Cursor chata + kako održavati liste |
| **[project-todo.md](./project-todo.md)** | Šta je **otvoreno** (čekiraj / briši kad završiš) |
| **[project-done.md](./project-done.md)** | Šta je **urađeno** (dodaj datum kad premestiš iz TODO) |
| **[project-conventions.md](./project-conventions.md)** | Pravila: prevodi, mail, queue, rute, parking |

**Radni tok:** završi zadatak → stavku iz `project-todo.md` prebaci u `project-done.md` (sa datumom) → ukloni iz TODO.

---

## Brzi test (lokalno)

- `.env`: `BANK_DRIVER=fake`, `FISCALIZATION_DRIVER=fake`, **`QUEUE_CONNECTION=sync`** (ili `queue:work`).
- Detalji: [fake-payment-and-fiscal-qa-checklist.md](./fake-payment-and-fiscal-qa-checklist.md).

---

## Real Bankart E2E

Smislen test sa pravim callback-om obično zahteva **hostovan** domen i bankin mod/simulaciju — v. kratku napomenu u [handoff-new-chat.md](./handoff-new-chat.md) i stavke u [project-todo.md](./project-todo.md).

---

## Ostala dokumentacija (dublje u teme)

- [fake-vs-real-contract-parity.md](./fake-vs-real-contract-parity.md)
- [scheduled-tasks-overview.md](./scheduled-tasks-overview.md)
- [payment-states.md](./payment-states.md)
- [workflow-placanje-temp-data.md](./workflow-placanje-temp-data.md)
- [admin-panel.md](./admin-panel.md)

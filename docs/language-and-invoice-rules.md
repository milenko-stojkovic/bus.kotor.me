# Language & invoice rules

## 1. UI language

- **Authenticated users:** use **users.lang** (column: `lang`, values: `en`, `cg`). SetLocale middleware applies it.
- **Guests:** detect from browser **Accept-Language** header. Mapping:
  - **cg, hr, sr, bs** → **cg** (Crnogorski)
  - all others → **en**
- **Guest can manually change UI language:** GET **/locale/{locale}** (e.g. `/locale/cg`, `/locale/en`) stores choice in **session** and redirects back. SetLocale middleware uses `session('locale')` when set.

## 2. Email language

- **Authenticated users:** **users.lang** (same as UI).
- **Guests:** use **reservation.preferred_locale** (set at checkout from session or detected Accept-Language).
- Emails (subject, body) are **localized** (cg / en). SendInvoiceEmailJob sets `app()->setLocale()` to the recipient’s locale before building content so `__()` returns the correct translations.

## 3. Invoice (PDF) & fiscalization

- **Always in Montenegrin (cg).** Legal requirement (local government issuer).
- **Never** generate invoice in en or any other language.
- GenerateInvoicePdfJob uses **hardcoded Montenegrin strings** for PDF content and sets `app()->setLocale('cg')` inside the job so any future use of locale is cg. Do not rely on app locale from request/callback for invoice content.

## 4. Bank callback

- **API routes only** (POST /api/payments/callback). Machine-to-machine.
- **Do NOT** rely on cookies, session, or browser language.
- Identify reservation **only** by **merchant_transaction_id** (or internal reference). Language context is **irrelevant** for invoice generation (invoice is always cg).
- Callback is **stateless technical confirmation** only.

## 5. Custom fields in bank request

- **Do not** rely on the bank returning language or UI context.
- Treat callback payload as **stateless**; do not use it to choose invoice language. Invoice is always cg.

---

## Implementation summary

| Context        | Source of locale                    | Used for              |
|----------------|-------------------------------------|------------------------|
| UI (auth)      | users.lang                          | app locale             |
| UI (guest)     | session('locale') or Accept-Language| app locale             |
| Email (auth)   | users.lang                          | SendInvoiceEmailJob    |
| Email (guest)  | reservation.preferred_locale        | SendInvoiceEmailJob    |
| Invoice PDF    | always **cg**                       | GenerateInvoicePdfJob  |
| Bank callback  | N/A (no locale)                    | Identify by merchant_transaction_id only |

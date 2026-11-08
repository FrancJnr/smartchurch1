# Church CMIS — Laravel application overlay

This ZIP contains **application source files**, not the Laravel framework or `vendor/` dependencies. It targets a fresh Laravel 13 project with PHP 8.3+ and MySQL. Existing eKanisa numbers are entered manually and must be unique.

## Local development setup

This workspace now includes Laravel 13 and installed Composer/npm dependencies. Local development uses SQLite (`database/database.sqlite`) and the Africa/Nairobi timezone. The migrations and `GivingTypeSeeder` have been run.

Start the web server with `php artisan serve --host=127.0.0.1 --port=8000` and the queue worker in another terminal with `php artisan queue:work --sleep=3 --tries=1`. Open http://127.0.0.1:8000/login. A local staff account uses `admin@example.test`; its generated password was provided during setup.

Run checks with `php artisan test` and build frontend assets with `npm run build`. SMS needs the Advanta credentials described below.

## Original overlay installation instructions
1. Install PHP 8.3+, Composer, MySQL and Node.js (if adding frontend assets).
2. Create a fresh project: `composer create-project laravel/laravel church-cmis "^13.0"`
3. Copy the folders from this ZIP **into the Laravel project root**, merging folders. This replaces `routes/web.php` but does not replace the default Laravel `User` model or default migrations.
4. Create a MySQL database and dedicated database user. Copy `.env.example` to `.env`, then configure `DB_*`, `APP_URL`, `APP_TIMEZONE=Africa/Nairobi`, and `QUEUE_CONNECTION=database`.
5. Run `php artisan key:generate`, `php artisan migrate`, and `php artisan db:seed --class=GivingTypeSeeder`.
6. Create the first staff user via `php artisan tinker`:
   `\App\Models\User::create(['name'=>'Admin','email'=>'admin@example.org','password'=>\Illuminate\Support\Facades\Hash::make('CHANGE-THIS-STRONG-PASSWORD')]);`
   **Replace email and password before executing.** Disable open registration; this starter has login only.
7. Start `php artisan serve` and separately `php artisan queue:work`.

## SMS configuration
`app/Services/SmsGateway.php` is a **generic integration adapter**, not a confirmed Africa's Talking or other provider implementation. Configure `services.church_sms` in `config/services.php`:
```
'church_sms' => [
  'url' => env('SMS_API_URL'),
  'key' => env('SMS_API_KEY'),
  'sender' => env('SMS_SENDER_ID', 'CHURCH'),
],
```
Add `SMS_API_URL`, `SMS_API_KEY`, `SMS_SENDER_ID` to `.env`. Adapt request fields and success parsing to the selected provider before enabling live SMS. Until configured, SMS jobs will fail safely and the financial records will remain saved. A gateway acceptance is **not** proof of handset delivery. SMS retry is deliberately disabled until the provider supports idempotency or a reconciliation workflow.

## Important before production
- Add role-based authorization (admin, treasurer, secretary, auditor), granular report access, audit logs, MFA, secure backups and retention policies.
- Implement duplicate submission protection (idempotency keys) and reconciliation of M-Pesa/bank payments.
- Protect member personal data, restrict SMS access and comply with applicable privacy laws.
- Use provider delivery callbacks for accurate sent/delivered status; avoid retrying ambiguous requests without idempotency.
- CSV output should escape cells beginning with spreadsheet formula characters before opening in Excel.
- Add tests for duplicate eKanisa numbers, financial validation, access control, SMS failures, and report totals.
- This is an MVP starter, **not production-certified** and has not been integration-tested against a running Laravel installation.

## Advanta Bulk SMS adapter (included)

This edition uses **Advanta QuickSMS**, not the old generic gateway. Add these to the Laravel project's `.env` (keep the values secret):

```dotenv
ADVANTA_SMS_URL=https://quicksms.advantasms.com/api/services/sendsms/
ADVANTA_API_KEY=your_advanta_api_key
ADVANTA_PARTNER_ID=your_advanta_partner_id
ADVANTA_SENDER_ID=YOUR_APPROVED_SENDER_ID
QUEUE_CONNECTION=database
```

- Get API Key and Partner ID from the Advanta account's API credentials section. Register/obtain an approved Sender ID (shortcode) before going live.
- Run `php artisan config:clear` after changing `.env` and `php artisan queue:work` under a process manager in production.
- Each giving produces one `sms_logs` row. The job sends a JSON POST to `https://quicksms.advantasms.com/api/services/sendsms/`, normalizes Kenyan mobile numbers, checks Advanta's `responses[0]['respose-code'] == 200`, and stores `messageid`.
- `accepted` means **Advanta accepted the message**, not confirmed handset delivery. For verified delivery, build a separate status lookup against `/api/services/getdlr/` using the stored `provider_reference` and the provider's documented DLR response format.
- An unrecognized response or network timeout sets `needs_review` and **does not auto-retry**, preventing accidental duplicate thank-you messages. Explicit rejection sets `failed`.
- Existing financial records remain saved even if SMS transmission fails.
- Basic fake-HTTP tests are included at `tests/Unit/AdvantaSmsGatewayTest.php`; run `php artisan test --filter=AdvantaSmsGatewayTest` within the fresh Laravel project. Live provider testing needs valid credentials and credits.
- Do not send sensitive personal details in SMS. Obtain appropriate member notification consent and comply with applicable privacy requirements.

Official reference: https://www.advantasms.com/bulksms-api

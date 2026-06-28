# VerifyHomes

VerifyHomes is a role-based property platform for safer rentals, inspections, payments, purchases, and occupancy management.

It gives tenants, landlords, and admin/staff users separate workspaces for the full property workflow: verified listings, inspection requests, payment checkout, occupancy tracking, complaints, move-out requests, notifications, and operational review.

## Quick Start

```bash
git clone https://github.com/AbVikk/verifyhomes.git
cd verifyhomes
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

In a second terminal, run:

```bash
npm run dev
```

## Overview

VerifyHomes is built around trust in property discovery. Tenants can browse verified listings, request inspections, complete rent or purchase payments, and track stays or purchases after payment. Landlords can manage profiles, documents, listings, occupants, and paid property money. Admin and staff users manage reviews, inspections, payments, purchases, occupancy operations, complaints, reminders, and audit logs.

The system connects role-based dashboards with listing verification, inspection workflows, terms acceptance, payment processing, occupancy records, purchase records, notifications, and search.

## Core User Roles

### Tenant

- Browse verified property listings.
- Save listings.
- Request inspections after accepting the inspection terms.
- Track inspection status and payment status.
- Pay rent when inspection is complete and eligible.
- Pay for house or land purchases on sale listings.
- View payments, purchase receipts, notifications, and active stays.
- Submit move-out requests and complaints for active occupancies.

### Landlord

- Manage landlord profile and verification documents.
- Create and manage rent, sale, lease, house, and land listings.
- Track listing review status and inspection coordination.
- View occupants tied to their own properties.
- View only verified paid property money tied to their listings.
- Receive landlord-relevant rent reminders and payment notifications.

Landlords do not see tenant inspection payment details in their payment workspace.

### Admin / Staff

- Review landlord documents and property listings.
- Manage inspection requests, schedules, statuses, and outcomes.
- View full operational payment lifecycles.
- Monitor purchases, occupancy, complaints, move-outs, overdue rent, and reminders.
- Use admin search, notification center, and audit logs.

## Main Features

### Authentication and Workspaces

- Laravel authentication with role-based routing.
- Separate tenant, landlord, and admin/staff dashboards.
- Role-aware navigation and shell layouts.
- Profile/settings pages for each role.

### Listings and Property Management

- Landlords can create and edit property listings.
- Admin can review and approve listings before public visibility.
- Listings support images, documents, location, inventory, pricing, and status history.
- Listing intents include rent, sale, and lease.
- Land listings support land size, size unit, land-specific fields, and sale quantity where applicable.

### Inspection Workflow

- Tenants request inspections from public property detail pages.
- Inspection requests are tenant-owned.
- Admin/staff manage scheduling, status changes, and outcomes.
- Landlords can coordinate access for their own listings without seeing inspection payment details.

### Terms Acceptance

- Inspection and listing terms use a timed modal acceptance flow.
- Users must open the modal and wait before accepting.
- Timing is enforced on both frontend and backend.
- The duration is configured with `TERMS_GATE_SECONDS`.

### Payments

- Inspection booking payment.
- Rent payment after completed inspection and eligible outcome.
- House purchase payment for sale listings.
- Land purchase payment with quantity support for multi-unit land listings.
- Paystack support when keys are configured.
- Stub gateway support for local and test flows.

### Purchases and Occupancy

- Successful rent payment creates or updates occupancy records and listing availability.
- Successful house or land purchase creates a durable purchase record.
- Tenant purchase receipts are available after successful purchase.
- Occupancy pages show rent due dates, overdue status, move-out actions, and complaint actions.
- Landlords can view occupants tied to their properties.
- Admin can approve/reject move-out requests and manage complaints.

### Search, Notifications, and Reminders

- Admin search supports properties, tenants, landlords, and payments.
- Landlord search is scoped to landlord-owned records and landlord-visible payments.
- Role-specific notification centers show operational updates.
- Automated rent reminders are generated for active rent occupancies.
- Reminder stages include 60 days, 30 days, 7 days, due today, 7 days overdue, and 30 days overdue.
- Purchased properties and moved-out occupancies are excluded from rent reminders.

### Admin Review and Audit

- Admin workspaces cover documents, landlords, tenants, properties, inspections, payments, purchases, occupancy, notifications, search, and audit.
- Status history and audit records support operational traceability.

## Tech Stack

- PHP 8.2+
- Laravel 12
- Livewire 4
- Laravel Breeze
- Spatie Laravel Permission
- MySQL or another Laravel-supported database
- Vite
- Tailwind CSS
- Alpine.js
- PHPUnit 11
- Paystack payment adapter plus local stub gateway

## Installation and Local Setup

Clone the repository:

```bash
git clone https://github.com/AbVikk/verifyhomes.git
cd verifyhomes
```

Install dependencies:

```bash
composer install
npm install
```

Create and configure the environment file:

```bash
cp .env.example .env
php artisan key:generate
```

Update `.env` with your database settings:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=verifyhomes
DB_USERNAME=root
DB_PASSWORD=
```

Run migrations and seeders:

```bash
php artisan migrate
php artisan db:seed
```

Create the storage symlink:

```bash
php artisan storage:link
```

Start the app:

```bash
php artisan serve
```

Start frontend assets:

```bash
npm run dev
```

Build production assets:

```bash
npm run build
```

## Test Instructions

Run the full test suite:

```bash
php artisan test
```

Or run PHPUnit directly:

```bash
vendor/bin/phpunit
```

Run the feature suite:

```bash
vendor/bin/phpunit --testsuite Feature
```

Useful focused tests:

```bash
vendor/bin/phpunit --testsuite Feature --filter PaymentFoundationTest
vendor/bin/phpunit --testsuite Feature --filter PurchaseWorkflowTest
vendor/bin/phpunit --testsuite Feature --filter OccupancyWorkflowTest
vendor/bin/phpunit --testsuite Feature --filter NotificationCenterTest
vendor/bin/phpunit --testsuite Feature --filter SearchExperienceTest
vendor/bin/phpunit --testsuite Feature --filter TermsModalFlowTest
```

Frontend terms-gate tests are present under `tests/Frontend`. The current `package.json` does not define a default `npm test` script, so run those tests with the JavaScript test runner configured for your local environment if frontend test tooling is added or maintained.

## Scheduler and Rent Reminders

Rent reminders are generated by:

```bash
php artisan rent-reminders:generate
```

The command is scheduled in `routes/console.php` to run daily at `06:00` and uses `withoutOverlapping()`.

For production, configure Laravel's scheduler on the server so this runs every minute:

```bash
php artisan schedule:run
```

Reminder runs are logged with the number of notifications created.

## Payment Notes

VerifyHomes uses a gateway manager:

- `paystack` is used when Paystack keys are configured.
- `stub` is used when no external provider is configured or when tests/local flows need a fake gateway.

Important payment environment variables:

```env
PAYMENT_PROVIDER=paystack
PAYSTACK_PUBLIC_KEY=
PAYSTACK_SECRET_KEY=
PAYSTACK_BASE_URL=https://api.paystack.co
PAYSTACK_VERIFY_SSL=true
PAYMENT_WEBHOOK_SECRET=
INSPECTION_BOOKING_FEE_AMOUNT=5000
PLATFORM_FEE_PERCENTAGE=10
RENT_PLATFORM_FEE_PERCENTAGE=20
```

Payment callbacks return through the tenant payment callback route. Provider webhooks are handled through the webhook endpoint. For local development, keep SSL verification enabled unless your local certificate setup requires the explicit `PAYSTACK_VERIFY_SSL` override.

## Search and Notification Notes

Search is role-aware:

- Admin/staff can search across operational records.
- Landlords can search only landlord-scoped records.
- Landlord payment search only returns paid landlord-relevant property money.

Notifications are stored in `user_notifications` and shown in role-specific notification centers. They support payment confirmations, complaints, move-out requests, rent reminders, and operational updates.

## Project Status

VerifyHomes is a working Laravel/Livewire product with connected tenant, landlord, and admin workflows. It includes inspection, payment, purchase, occupancy, notification, search, reminder, review, and audit flows.

The project has focused test coverage for the main workflows. Before production use, confirm environment setup, scheduler setup, payment provider configuration, webhook handling, and a full regression test run.

## Optional Future Improvements

- Add a dedicated tenant purchase/receipt index page.
- Add stronger database-level guarantees for reminder dedupe and payment effect idempotency.
- Improve admin filters for large occupancy, payment, and notification queues.
- Consolidate repeated notification and workflow helper logic.
- Add more end-to-end browser testing for payment and inspection flows.

## License

License information has not yet been added.

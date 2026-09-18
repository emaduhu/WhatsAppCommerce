# WhatsApp Commerce

Production-oriented multi-tenant Laravel 12 frontend and API for SMEs selling through WhatsApp.

## Included

- Laravel 12 / PHP 8.2+ frontend and API
- Browser console for merchant registration, login, dashboard metrics and product management
- Laravel Sanctum authentication
- Multi-tenant merchants and merchant membership authorization
- Product/service catalog and stock
- AI sales-agent checkout flow for WhatsApp messages such as `Nataka Samsung A55 blue mbili`
- Persistent carts and cart items before checkout
- Customers, conversations and message history
- Orders, order items and payment state
- WhatsApp Business Platform / Cloud API integration
- Meta Embedded Signup as the primary merchant WhatsApp connection flow
- GET webhook verification + POST `X-Hub-Signature-256` validation
- Idempotency on WhatsApp message IDs
- Asynchronous inbound processing and outbound sends with database queues on cPanel
- WhatsApp delivery/read/failure status updates
- 24-hour customer-service-window protection for free-form outbound text
- Approved template-message endpoint outside the service window
- Human handoff (`agent`, `human`, `mtu`, `msaada`)
- Catalog-aware sales replies, delivery capture, mobile-money checkout and payment confirmation
- Generic payment gateway adapter + signed payment webhook
- MySQL + cPanel cron defaults, with optional Redis/Docker/Nginx/Supervisor assets for later migration


## Current production modules

This repository now includes the MVP production backbone for **WhatsApp Commerce for SMEs**:

- merchant registration and Sanctum authentication, including `GET /api/auth/me`
- tenant isolation middleware on all `/api/merchants/{merchant}` routes
- categories, products, variants and inventory adjustment APIs
- inventory movement audit trail through `InventoryService`
- WhatsApp Embedded Signup onboarding APIs
- idempotent WhatsApp webhook event storage in `webhook_events`
- deterministic English/Swahili commerce agent with human handoff
- carts, checkout, orders and payment initiation
- idempotent signed payment callbacks stored in `payment_transactions`
- server-side WhatsApp 24-hour messaging policy enforcement
- merchant dashboard metrics, recent orders/conversations, payment exceptions and low stock
- `GET /api/health` for non-secret health checks

Additional documentation:

- [`ARCHITECTURE.md`](ARCHITECTURE.md)
- [`WHATSAPP_SETUP.md`](WHATSAPP_SETUP.md)
- [`PAYMENT_SETUP.md`](PAYMENT_SETUP.md)
- [`AI_AGENT.md`](AI_AGENT.md)
- [`CPANEL_INSTALL.md`](CPANEL_INSTALL.md)

## Important production design decision

WhatsApp access tokens are stored with Laravel's `encrypted` Eloquent cast. Keep `APP_KEY` in a secret manager and back it up securely. Rotating or losing `APP_KEY` without a compatible previous key will make stored tokens unreadable.

## 1. Install locally

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
php artisan queue:work database --tries=8 --timeout=60
```

For production Docker deployment:

```bash
cp .env.example .env
# edit all secrets and URLs

docker compose build
docker compose up -d

docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
```

Do not generate a new `APP_KEY` on every deployment. Generate once and store it securely.

## 2. WhatsApp / Meta setup

Create/configure a platform-owned Meta app with WhatsApp Business Platform and Embedded Signup. The platform needs:

- Meta App ID
- Meta App Secret
- Embedded Signup configuration ID
- a private webhook verify token of your own choosing

Individual SMEs do not paste WABA IDs, Phone Number IDs or long-lived access tokens into this app. Each merchant connects their own WhatsApp Business account and number from the dashboard through Embedded Signup. Laravel exchanges the returned authorization code, reads the authorized phone numbers, subscribes the WABA to webhooks, and stores the merchant token encrypted.

Set:

```env
WHATSAPP_GRAPH_VERSION=v26.0
WHATSAPP_APP_ID=<meta-app-id>
WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID=<embedded-signup-configuration-id>
WHATSAPP_EMBEDDED_SIGNUP_CALLBACK_URL=https://wc.vigourtech.net
WHATSAPP_WEBHOOK_VERIFY_TOKEN=<strong-random-value>
WHATSAPP_APP_SECRET=<meta-app-secret>
WHATSAPP_ENFORCE_SIGNATURE=true
```

The API version is intentionally configurable. Confirm Meta's currently supported WhatsApp Graph API version before each production upgrade.

Configure the Meta webhook callback URL:

```text
https://wc.vigourtech.net/api/webhooks/whatsapp
```

Use your `WHATSAPP_WEBHOOK_VERIFY_TOKEN` as the verify token and subscribe the WhatsApp Business Account to at least the `messages` webhook field.

The same endpoint handles:

- `GET` verification challenge
- `POST` message/status webhooks

Production POST requests are checked against `X-Hub-Signature-256` using the Meta App Secret.

## 3. Register the first merchant

```http
POST /api/auth/register
Content-Type: application/json

{
  "name": "Owner Name",
  "email": "owner@example.com",
  "password": "StrongPassword123",
  "password_confirmation": "StrongPassword123",
  "business_name": "Neema Cosmetics"
}
```

The response returns a Sanctum bearer token and merchant.

## 4. Merchant WhatsApp self-onboarding

The dashboard includes a **WhatsApp Connection** panel. A merchant logs in, chooses their merchant profile, enters their WhatsApp number, optionally enters a 6-digit registration PIN, and clicks **Connect with Meta**. Meta Embedded Signup opens in a popup so the merchant can authorize the business, WABA and phone number.

After Meta returns the authorization code, Laravel automatically:

1. exchanges the code for the business integration system-user token,
2. reads the authorized WABA and phone-number list,
3. matches the entered WhatsApp number and stores its Phone Number ID,
4. configures the Meta app webhook subscription for `https://wc.vigourtech.net/api/webhooks/whatsapp`,
5. subscribes the WABA to `messages` webhooks,
6. registers the phone number on Cloud API when requested, using the merchant PIN or an auto-generated 6-digit PIN,
7. stores the WhatsApp account and encrypted token against the merchant.

The backend endpoints are:

```text
GET  /api/merchants/{merchant}/whatsapp/onboarding/config
POST /api/merchants/{merchant}/whatsapp/onboarding/complete
```

Meta still requires the platform owner to create the Meta app and Embedded Signup configuration first. Laravel cannot create those without access to the platform owner's Meta developer account.

Manual connection remains available for admin/operator migrations only:

```http
POST /api/merchants/{merchant}/whatsapp/accounts
Authorization: Bearer <token>
Content-Type: application/json

{
  "waba_id": "123456789",
  "phone_number_id": "987654321",
  "display_phone_number": "+2557XXXXXXXX",
  "verified_name": "Neema Cosmetics",
  "access_token": "<production-system-user-token>"
}
```

## 5. Product API

```text
GET    /api/merchants/{merchant}/categories
POST   /api/merchants/{merchant}/categories
GET    /api/merchants/{merchant}/products
POST   /api/merchants/{merchant}/products
GET    /api/merchants/{merchant}/products/{product}
PATCH  /api/merchants/{merchant}/products/{product}
DELETE /api/merchants/{merchant}/products/{product}
POST   /api/merchants/{merchant}/products/{product}/variants
PATCH  /api/merchants/{merchant}/products/{product}/variants/{variant}
POST   /api/merchants/{merchant}/products/{product}/inventory/adjust
```

Example create payload:

```json
{
  "sku": "PERF-ASAD-100",
  "name": "Lattafa Asad 100ml",
  "description": "Men's fragrance",
  "type": "PRODUCT",
  "price": 45000,
  "stock_quantity": 20,
  "track_stock": true,
  "is_active": true
}
```

## 6. Orders

```text
GET   /api/merchants/{merchant}/orders
POST  /api/merchants/{merchant}/orders
GET   /api/merchants/{merchant}/orders/{order}
PATCH /api/merchants/{merchant}/orders/{order}/status
```

Creating an order locks product rows during stock validation and decrements tracked stock atomically.

## 7. WhatsApp sales agent

Inbound customer messages are processed by `SalesAgent`. For example:

```text
Nataka Samsung A55 blue mbili
```

The agent:

1. extracts the requested product and quantity,
2. searches the active merchant catalog,
3. checks tracked inventory,
4. creates or updates a cart,
5. asks for the delivery location,
6. asks for a mobile-money number or CASH,
7. creates the order and payment record,
8. initiates the configured payment provider, and
9. sends WhatsApp status/confirmation messages.

If `PAYMENT_BASE_URL` is not configured, the order is still created and the customer is told that a staff member will complete payment. Configure the provider settings before treating mobile-money initiation as live.

## 8. Sending WhatsApp messages

Within the active customer-service window:

```http
POST /api/merchants/{merchant}/whatsapp/messages/text

{
  "account_id": 1,
  "customer_id": 10,
  "body": "Asante. Order yako imepokelewa."
}
```

The request queues the send. Free-form text is rejected by the queue job if the latest inbound customer message is older than 24 hours.

For an approved WhatsApp template:

```http
POST /api/merchants/{merchant}/whatsapp/messages/template

{
  "account_id": 1,
  "customer_id": 10,
  "name": "order_update",
  "language": "sw",
  "components": []
}
```

Template names, categories, languages and components must match templates approved in WhatsApp Manager.

## 9. Payment provider

This repository intentionally isolates payment-provider-specific logic in `GenericPaymentGateway`. Configure:

```env
PAYMENT_PROVIDER=your-provider
PAYMENT_BASE_URL=https://provider.example/api
PAYMENT_API_KEY=...
PAYMENT_WEBHOOK_SECRET=...
PAYMENT_CALLBACK_URL=https://wc.vigourtech.net/api/webhooks/payments/generic
```

Expected generic initiation contract:

```http
POST {PAYMENT_BASE_URL}/payments
Authorization: Bearer ...

{
  "reference": "<local-payment-id>",
  "amount": 95000,
  "currency": "TZS",
  "msisdn": "2557XXXXXXXX",
  "callback_url": "..."
}
```

Expected webhook contract:

```json
{
  "id": "provider-reference",
  "status": "PAID"
}
```

Webhook signature: lowercase hex HMAC-SHA256 of the raw body in `X-Signature`.

**Adapt this gateway to the exact provider contract before going live.** Do not accept unsigned callbacks.

## 10. Production checklist

1. Put TLS in front of the service and redirect HTTP to HTTPS.
2. Set `APP_ENV=production`, `APP_DEBUG=false`.
3. Store `APP_KEY`, DB password, Meta App Secret, encrypted merchant WhatsApp tokens and payment secrets in a secret manager or encrypted database backup workflow.
4. Use Meta Embedded Signup in production mode so merchant Business Integration System User tokens are issued through Meta's authorization flow.
5. Keep `WHATSAPP_ENFORCE_SIGNATURE=true`.
6. Restrict `CORS_ALLOWED_ORIGINS` to your real dashboard origins.
7. Run `php artisan migrate --force` during controlled releases.
8. Run `php artisan optimize` after deployment.
9. Keep the cPanel database queue cron active and monitor failed queue jobs.
10. Add centralized logs/metrics/alerts for webhook failures, queue lag, WhatsApp 4xx/5xx and payment callback failures.
11. Back up MySQL and test restores.
12. Configure database connection limits and API rate limits.
13. Add your payment provider's exact replay protection/idempotency key rules.
14. Implement customer consent and approved templates for marketing communications.
15. Add retention/deletion rules for customer PII and message payloads.
16. Before scale, replace the deterministic parser with a tool-calling LLM layer that can only use authoritative catalog/cart/order/payment functions.

## 11. Architecture

```text
Customer WhatsApp
       |
       v
Meta WhatsApp Cloud API
       |
       | webhook
       v
Apache/cPanel -> Laravel API -> Database Queue -> Webhook Processor
                                  |                |
                                  |                +-> Customers / Conversations / Messages
                                  |                +-> Sales agent / carts / checkout
                                  |                +-> Human handoff
                                  |
                                  +-> Outbound WhatsApp Jobs -> Graph API

Dashboard / Mobile App -> Sanctum -> Merchant-scoped APIs
                                      |
                                      +-> Products / stock
                                      +-> Orders
                                      +-> Payments
                                      +-> Embedded Signup / WhatsApp sends/templates

MySQL = authoritative commerce data
cPanel cron + database queue = background processing
```

## 12. Health and release checks

```text
GET /api/health
```

A cPanel-safe release check is available for operators:

```bash
php scripts/cpanel_release_check.php
```

It verifies core environment values and database reachability without printing secrets.

## 13. What still must be configured for a real launch

No repository can contain your production Meta or payment credentials. Before launch you must supply the real platform Meta App ID, Meta App Secret, Embedded Signup configuration ID, approved templates, DNS/TLS domain, payment aggregator credentials and provider-specific payload mapping. Each merchant supplies their own WhatsApp Business authorization through Embedded Signup.

The included sales-agent parser is deliberately deterministic. It demonstrates the full inbound -> catalog lookup -> cart -> checkout -> queued outbound path without allowing an LLM to invent price/stock. An LLM can be added later behind a strict tool layer.

---

## cPanel / MySQL deployment

This package has been updated to support ordinary cPanel hosting with **MySQL as the default database**, **database queues**, and **file cache/session storage**. Redis remains optional.

See [`CPANEL_INSTALL.md`](CPANEL_INSTALL.md) for the complete installation procedure, queue cron configuration, recommended document-root layout, security settings, and scaling notes.

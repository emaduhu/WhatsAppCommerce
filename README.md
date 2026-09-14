# WhatsApp Commerce API

Production-oriented multi-tenant Laravel 13 backend for SMEs selling through WhatsApp.

## Included

- Laravel 13 / PHP 8.4 API
- Laravel Sanctum authentication
- Multi-tenant merchants and merchant membership authorization
- Product/service catalog and stock
- Customers, conversations and message history
- Orders, order items and payment state
- WhatsApp Business Platform / Cloud API integration
- GET webhook verification + POST `X-Hub-Signature-256` validation
- Idempotency on WhatsApp message IDs
- Asynchronous inbound processing and outbound sends with Redis queues
- WhatsApp delivery/read/failure status updates
- 24-hour customer-service-window protection for free-form outbound text
- Approved template-message endpoint outside the service window
- Human handoff (`agent`, `human`, `mtu`, `msaada`)
- Basic catalog auto-reply engine (replace/extend with your preferred LLM orchestration)
- Generic payment gateway adapter + signed payment webhook
- PostgreSQL + Redis + Docker + Nginx + Supervisor

## Important production design decision

WhatsApp access tokens are stored with Laravel's `encrypted` Eloquent cast. Keep `APP_KEY` in a secret manager and back it up securely. Rotating or losing `APP_KEY` without a compatible previous key will make stored tokens unreadable.

## 1. Install locally

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
php artisan queue:work redis --tries=8 --timeout=60
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

Create/configure a Meta app with WhatsApp Business Platform and obtain:

- WhatsApp Business Account ID (`waba_id`)
- Phone Number ID
- production System User access token with required WhatsApp permissions
- Meta App Secret
- a private webhook verify token of your own choosing

Set:

```env
WHATSAPP_GRAPH_VERSION=v26.0
WHATSAPP_WEBHOOK_VERIFY_TOKEN=<strong-random-value>
WHATSAPP_APP_SECRET=<meta-app-secret>
WHATSAPP_ENFORCE_SIGNATURE=true
```

The API version is intentionally configurable. Confirm Meta's currently supported WhatsApp Graph API version before each production upgrade.

Configure the Meta webhook callback URL:

```text
https://api.yourdomain.tld/api/webhooks/whatsapp
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

## 4. Connect a WhatsApp number

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
GET    /api/merchants/{merchant}/products
POST   /api/merchants/{merchant}/products
PATCH  /api/merchants/{merchant}/products/{product}
DELETE /api/merchants/{merchant}/products/{product}
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

## 7. Sending WhatsApp messages

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

## 8. Payment provider

This repository intentionally isolates payment-provider-specific logic in `GenericPaymentGateway`. Configure:

```env
PAYMENT_PROVIDER=your-provider
PAYMENT_BASE_URL=https://provider.example/api
PAYMENT_API_KEY=...
PAYMENT_WEBHOOK_SECRET=...
PAYMENT_CALLBACK_URL=https://api.yourdomain.tld/api/webhooks/payments/generic
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

## 9. Production checklist

1. Put TLS in front of the service and redirect HTTP to HTTPS.
2. Set `APP_ENV=production`, `APP_DEBUG=false`.
3. Store `APP_KEY`, DB password, Meta App Secret, WhatsApp tokens and payment secrets in a secret manager.
4. Use a permanent/System User token appropriate for production rather than a temporary getting-started token.
5. Keep `WHATSAPP_ENFORCE_SIGNATURE=true`.
6. Restrict `CORS_ALLOWED_ORIGINS` to your real dashboard origins.
7. Run `php artisan migrate --force` during controlled releases.
8. Run `php artisan optimize` after deployment.
9. Keep Redis persistent/managed and monitor failed queue jobs.
10. Add centralized logs/metrics/alerts for webhook failures, queue lag, WhatsApp 4xx/5xx and payment callback failures.
11. Back up PostgreSQL and test restores.
12. Configure database connection limits and reverse-proxy/API rate limits.
13. Add your payment provider's exact replay protection/idempotency key rules.
14. Implement customer consent and approved templates for marketing communications.
15. Add retention/deletion rules for customer PII and message payloads.
16. Before scale, replace the simple keyword catalog responder with a tool-calling AI layer that can only read authoritative catalog/order functions.

## 10. Architecture

```text
Customer WhatsApp
       |
       v
Meta WhatsApp Cloud API
       |
       | webhook
       v
Nginx -> Laravel API -> Redis Queue -> Webhook Processor
                         |                |
                         |                +-> Customers / Conversations / Messages
                         |                +-> Catalog intent
                         |                +-> Human handoff
                         |
                         +-> Outbound WhatsApp Jobs -> Graph API

Dashboard / Mobile App -> Sanctum -> Merchant-scoped APIs
                                      |
                                      +-> Products / stock
                                      +-> Orders
                                      +-> Payments
                                      +-> WhatsApp sends/templates

PostgreSQL = authoritative commerce data
Redis      = queues/cache
```

## 11. What still must be configured for a real launch

No repository can contain your production Meta or payment credentials. Before launch you must supply the real WhatsApp Business Account/Phone Number IDs, approved templates, System User token, App Secret, DNS/TLS domain, payment aggregator credentials and provider-specific payload mapping.

The included catalog auto-reply is deliberately deterministic. It demonstrates the full inbound -> DB lookup -> queued outbound path without allowing an LLM to invent price/stock. An LLM can be added later behind a strict tool layer.

---

## cPanel / MySQL deployment

This package has been updated to support ordinary cPanel hosting with **MySQL as the default database**, **database queues**, and **file cache/session storage**. Redis remains optional.

See [`CPANEL_INSTALL.md`](CPANEL_INSTALL.md) for the complete installation procedure, queue cron configuration, recommended document-root layout, security settings, and scaling notes.

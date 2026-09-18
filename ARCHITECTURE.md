# Architecture

WhatsApp Commerce for SMEs is a modular Laravel monolith. Laravel and MySQL are the source of truth for merchants, products, inventory, carts, orders, payments and conversations. WhatsApp and AI are interfaces into controlled backend services; they are never authoritative for price, stock, order or payment state.

The platform is designed for many independent SMEs. Each merchant owns their WhatsApp Business Account and phone number, then connects it to the platform through Meta Embedded Signup. The platform owner maintains the Meta app, App Secret, Embedded Signup configuration and webhook endpoint; merchants authorize their own WABA/phone-number access through Meta. Manual WABA/Phone Number ID and token entry is an operator fallback for migrations.

Runtime flow:

```text
Customer WhatsApp -> Meta Cloud API -> Laravel webhook -> signature verification -> webhook_events -> queue/job -> inbound processor -> conversation state machine -> catalog/cart/order/payment services -> WhatsApp response
```

Core modules currently implemented:

- Auth and merchant membership with Sanctum.
- Tenant isolation middleware on every merchant-scoped route.
- WhatsApp Embedded Signup and merchant WhatsApp accounts with encrypted access tokens.
- Webhook signature verification and idempotent webhook event storage.
- Customers, conversations, messages and human handoff.
- Categories, products, variants, carts and orders.
- Inventory movements through `InventoryService`.
- Generic payment gateway abstraction and idempotent callback records.
- cPanel-friendly file cache, file sessions and database queues.

Phase-2 modules such as campaigns, abandoned carts, richer analytics and WebSockets should be added after the customer-message-to-payment transaction is stable with real providers.

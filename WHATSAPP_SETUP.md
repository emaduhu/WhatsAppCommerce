# WhatsApp Setup

The production webhook URL is:

```text
https://wc.vigourtech.net/api/webhooks/whatsapp
```

Set these server-side environment variables. Never place these values in Angular or other frontend code.

```env
WHATSAPP_GRAPH_BASE_URL=https://graph.facebook.com
WHATSAPP_GRAPH_VERSION=v26.0
WHATSAPP_APP_ID=
WHATSAPP_APP_SECRET=
WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID=
WHATSAPP_EMBEDDED_SIGNUP_CALLBACK_URL=https://wc.vigourtech.net
WHATSAPP_WEBHOOK_VERIFY_TOKEN=
WHATSAPP_ENFORCE_SIGNATURE=true
```

The platform owner configures the Meta app, App Secret, Embedded Signup configuration ID and webhook verification token. Merchants should not be asked to generate system-user tokens or paste WABA and Phone Number IDs.

Merchant self-onboarding uses Meta Embedded Signup. The merchant enters a WhatsApp number in the dashboard, completes Meta authorization, and Laravel exchanges the returned code for the business integration token. Laravel then reads the authorized WABA phone-number list, stores the matched Phone Number ID, configures the app webhook subscription, subscribes the WABA to messages, and optionally registers the phone with a six-digit PIN.

Meta still requires the platform owner to create the Meta app and Embedded Signup configuration first. Laravel cannot create those from only a phone number.

Production verification checklist:

1. Meta verifies `GET /api/webhooks/whatsapp` using `WHATSAPP_WEBHOOK_VERIFY_TOKEN`.
2. A signed WhatsApp webhook POST returns 200.
3. An invalid signature returns 401.
4. Incoming customer text creates a customer, conversation and inbound message.
5. Outbound text is sent only within the 24-hour customer-service window unless a template is used.

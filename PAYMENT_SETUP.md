# Payment Setup

Payments are provider-agnostic behind `PaymentGatewayInterface`. The current adapter is `GenericPaymentGateway`; replace or extend it for the exact aggregator contract used for M-Pesa, Mixx by Yas, Airtel Money, Halopesa, card or bank channels.

Required environment values:

```env
PAYMENT_PROVIDER=generic
PAYMENT_BASE_URL=
PAYMENT_API_KEY=
PAYMENT_WEBHOOK_SECRET=
PAYMENT_CALLBACK_URL=https://wc.vigourtech.net/api/webhooks/payments/generic
```

The app must never mark a payment as paid from payment initiation alone. A payment becomes `PAID` only after a signed provider callback or authoritative provider query verifies:

- provider reference,
- amount,
- currency,
- order/payment match,
- callback signature.

Callbacks are stored in `payment_transactions` with provider/event idempotency so repeated callbacks do not double-confirm orders.

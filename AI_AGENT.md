# AI Agent

The current production-safe agent is deterministic and lives in `App\Services\WhatsApp\SalesAgent`. It understands common English/Swahili product requests, searches the merchant's catalog, checks inventory, creates carts, collects delivery details, creates orders and starts payments.

Rules enforced by Laravel:

- Product prices come from MySQL.
- Stock comes from MySQL and inventory movements.
- Orders and payments are created by Laravel services.
- Payment success only comes from verified backend callbacks.
- Human handoff switches conversation mode to `HUMAN` and stops automatic AI replies.

Future LLM integration should expose only controlled tools such as `search_products`, `check_stock`, `add_to_cart`, `checkout`, `get_payment_status`, and `request_human_agent`. The LLM must not receive direct database credentials, arbitrary SQL access, or secret tokens.

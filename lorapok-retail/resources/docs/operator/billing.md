# Billing

## The states

- **Trial** — evaluating, full access, no invoice yet.
- **Active** — paid and current.
- **Past due** — an invoice is past its due date. **Still selling.**
- **Suspended** — access withdrawn. The only state that stops a shop.
- **Cancelled** — ended. Terminal; coming back means a new subscription.

## Taking payment

Shops pay by bKash, Nagad or bank transfer. Stripe does not operate in
Bangladesh, so this is not a stopgap — it is how payment works here.

A shop records that it has paid and gives you a reference. That creates a
**pending** attempt; it does not mark the invoice paid. You confirm the money
arrived, and then it does. Someone saying they paid is not the money arriving.

## Invoices

Numbered sequentially and never deleted. A mistaken invoice is **voided**,
which leaves it visible with its reason. A paid invoice cannot be voided —
refund it, so both entries stand.

## What to do about a late shop

Talk to them first. Suspension is a last step, not an automatic one, and it
costs you the revenue as well as them.

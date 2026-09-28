# Shops

## Provisioning

Creating a shop builds it a database of its own and puts it on its own
subdomain. It is not a row in a shared table — one shop cannot read another's
sales even if something goes wrong in the application.

The slug becomes the web address and **cannot be changed afterwards**, because
the shop will have printed it, bookmarked it and told customers. Check it
with them before creating.

Reserved slugs are refused. The shops share an apex with every other Lorapok
product, so handing out `mail` or `cursor` would take a live site off the air.

## Suspending

Suspension blocks the whole shop — nobody can sell. Use it for non-payment
after the conversation has already happened, not as a first reminder. A shop
that cannot ring up sales cannot earn the money to pay you.

Being **past due** does not suspend anything on its own. That is a deliberate
separation: lateness is a billing state, suspension is a decision someone
makes.

## Deleting

Deleting a shop drops its entire database. It asks for the slug back as
confirmation because there is no undo and no backup inside the application.
The audit entry survives — the record of the deletion outlives the thing
deleted.

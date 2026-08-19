# Mini Ticketing API
 
An event ticketing REST API built with **Laravel 13**, **MongoDB**, and **Laravel Passport**.
 
This is a rebuild of an earlier project that used MySQL and Sanctum. The domain is deliberately unchanged — events, ticket types, orders — so the interesting differences are architectural: what changes when you swap a relational database for a document one, and session-style tokens for OAuth2.
 
---
 
## Stack
 
| | |
|---|---|
| Framework | Laravel 13 |
| Database | MongoDB 8 (single-node replica set) |
| Auth | Laravel Passport 13 — OAuth2 with scopes |
| Docs | Swagger / OpenAPI 3.0 via l5-swagger |
| Tests | PHPUnit — 58 tests |
 
---
 
## Setup
 
```bash
git clone https://github.com/hessalabdullatif/mini-ticketing-api-mongo.git
cd mini-ticketing-api-mongo
composer install
cp .env.example .env
php artisan key:generate
```
 
MongoDB must run as a **replica set**, not standalone — transactions require it. In `/opt/homebrew/etc/mongod.conf`:
 
```yaml
replication:
  replSetName: rs0
```
 
Then, once ever:
 
```bash
brew services restart mongodb-community@8.0
mongosh --eval "rs.initiate({_id:'rs0', members:[{_id:0, host:'localhost:27017'}]})"
```
 
Configure `.env`:
 
```env
DB_CONNECTION=mongodb
MONGODB_URI=mongodb://localhost:27017/?replicaSet=rs0
MONGODB_DATABASE=mini_ticketing
 
QUEUE_CONNECTION=mongodb
MAIL_MAILER=log
PAYMENT_GATEWAY=visa
```
 
Passport needs keys and a personal access client:
 
```bash
php artisan passport:keys
php artisan passport:client --personal
```
 
Run it:
 
```bash
php artisan serve          # terminal 1
php artisan queue:work     # terminal 2
```
 
API docs at `/api/documentation`.
 
---
 
## Endpoints
 
| Method | Path | Auth | Notes |
|---|---|---|---|
| `POST` | `/api/register` | — | Always creates a `user`; the role is never taken from the request |
| `POST` | `/api/login` | — | Returns a token whose scopes depend on the role |
| `POST` | `/api/logout` | token | Revokes only the current token |
| `GET` | `/api/events` | — | `?city=` filter, paginated |
| `GET` | `/api/events/{id}` | — | Includes ticket types |
| `POST` | `/api/events` | `events:create` | Admin only |
| `GET` | `/api/orders` | token | Only your own orders |
| `POST` | `/api/orders` | token | Total computed server-side |
| `PATCH` | `/api/events/{id}` | `events:manage` | Partial update |
| `DELETE` | `/api/events/{id}` | `events:manage` | Blocked if the event has orders |
| `POST` | `/api/events/{id}/tickets` | `events:manage` | Create a ticket type |
| `PATCH` | `/api/tickets/{id}` | `events:manage` | Update price or stock |
| `DELETE` | `/api/tickets/{id}` | `events:manage` | Blocked if it has orders |
| `POST` | `/api/orders/{id}/refund` | token | Returns stock atomically |
---
 
## The layers, and why
 
A request travels through several layers, each responsible for one thing. When something is rejected, the status code tells you which layer did it.
 
```
Route + auth:api          who are you?              401
Route + CheckToken        are you allowed?          403
     ↓
Form Request              is the input valid?       422
     ↓
Controller                thin — receives, delegates
     ↓
Service                   business rules            422 (no stock, event unavailable)
     ↓
PaymentGateway            an abstraction
     ↓
Models                    the data
     ↓
Resource                  shapes the JSON response
```
 
### Form Requests — the barrier
 
Validation runs **before** the controller. If it fails, Laravel returns `422` and the method body never executes — so inside the controller the data is guaranteed valid.
 
What they *reject* matters more than what they accept. `StoreOrderRequest` has no `total`, no `status`, no `user_id`. If it did, a client could send `{"quantity": 2, "total": 1}` and buy two 500 SAR tickets for one riyal.
 
**The rule: the client sends intent, the server computes the outcome.**
 
`$request->validated()` returns only fields that passed the rules, so injected extras are discarded before reaching the service.
 
### Services — where the thinking lives
 
`OrderService` holds the actual rules of buying a ticket: is the event on sale, has it already happened, is there stock, what does it cost, charge the gateway, save the order.
 
None of that belongs in a controller. It's business logic, and business logic should live in one place — callable from HTTP today, from a scheduled command or CLI tool tomorrow.
 
### Custom exceptions — the way to say no
 
`InsufficientTicketsException`, `EventNotOnSaleException`, `EventHasPassedException`, `PaymentFailedException`.
 
A service returning `false` forces every caller to remember to check it — forget once and you've sold tickets you don't have. An exception can't be ignored.
 
Each knows how to render itself as an HTTP response, so the service throws a **domain concept** and never learns what a status code is. That keeps it testable without HTTP.
 
### The PaymentGateway interface
 
`OrderService` calls `$this->gateway->charge($total)`. It never sees a concrete class name and cannot know whether that's Visa, Mada, or Apple Pay.
 
Adding a third provider means writing a class that implements the contract and adding one line to the container binding. **`OrderService` is untouched. So is the controller. So are the routes.**
 
That's the Dependency Inversion Principle — high-level code depends on an abstraction, not on low-level details.
 
It only works because of constructor injection. If the service wrote `new FakeVisa()` inside `create()`, it would be permanently welded to Visa, and untestable without a real payment provider.
 
### API Resources — the output contract
 
Without them, returning `$event` serialises the raw model: every field, Mongo's `ObjectId` structure, internal timestamps. Your API's shape becomes whatever your database happens to hold.
 
**This matters more on Mongo than SQL.** In MySQL the schema constrained what could appear. Mongo has no schema — any field ever written to a document shows up. A Resource is the only guarantee about output shape, exactly as `$fillable` is the only guarantee about input.
 
### Queued jobs
 
The confirmation email is a queued job, so `POST /api/orders` returns in ~40ms rather than waiting for a mail server. And if the mail server is down, the order still succeeds — the purchase doesn't depend on the notification.
 
It's dispatched **after** the transaction commits. Inside it, a rollback would leave a job referencing an order that never existed.
 
---
 
## Data design
 
MySQL is designed around **relationships**. MongoDB is designed around **access patterns** — the question isn't "how do these relate?" but "what do I always read together?"
 
### Ticket types are referenced, not embedded
 
Embedding looks right at first: ticket types are bounded, meaningless without their event, always displayed alongside it.
 
**But `quantity_available` changes on every purchase.** Embedded, every order would modify the event's own document — and Mongo locks per document, so a hundred simultaneous buyers would queue behind each other. Cross-event queries would also need `$unwind` rather than a plain `where`.
 
**The rule: embed what's read together and changes rarely.** Inventory counters are the exact opposite.
 
### Orders store frozen copies
 
An order carries `event_name`, `event_date`, `ticket_type`, and `unit_price` — duplicated from their sources.
 
**Performance** is the smaller reason: "my orders" is the hottest endpoint, and the copies mean one read instead of ten lookups.
 
**Historical accuracy** is the real one. If VIP goes from 500 to 600 next month, an order holding only `ticket_id` would show 600 on a receipt for a purchase made at 500. **An order is a historical record, not a live view.**
 
### `meta` is the clearest argument for Mongo
 
A concert has an artist and venue. A match has two teams. A conference has speakers.
 
In MySQL: many mostly-`NULL` columns, or a key/value table plus a `JOIN`, or a `JSON` column you can't query into.
 
Here it's a real object, and `Event::where('meta.artist', 'Mohammed Abdu')` works — querying inside a field declared in no schema anywhere.
 
---
 
## What changed because of MongoDB
 
Four things broke in ways worth documenting, because the pattern is the same each time: **a package written for SQL doesn't automatically work on Mongo.**
 
### Passport needed five custom models
 
Passport's models assume the primary key is `id` and an integer. Mongo's is `_id` and an `ObjectId`.
 
The fix is five short classes in `app/Models/Passport/`, each extending its Passport counterpart with the `DocumentModel` trait, then registered via `Passport::useTokenModel()` and friends in `AppServiceProvider`.
 
This is Laravel's own documented "Overriding Default Models" feature, not a workaround.
 
Note that MongoDB's documentation lists `PersonalAccessClient` — removed in Passport 13 and replaced by `DeviceCode`. **The installed source is more reliable than the documentation:**
 
```bash
grep -n "public static function use" vendor/laravel/passport/src/Passport.php
```
 
### The `array` cast is harmful
 
It exists because MySQL has no array type, so Laravel serialises to a JSON string. Mongo stores arrays natively — casting turns them into strings, forfeiting querying, indexing, and `$unwind`.
 
**Cast types Mongo doesn't understand natively (dates, booleans). Don't cast what it does (arrays, objects).**
 
### The database queue driver doesn't work
 
Laravel's `database` driver builds `SELECT ... FOR UPDATE` — a row lock Mongo has no equivalent for. Jobs were written to the collection but the worker never saw them.
 
The Mongo package ships its own driver built on `findOneAndUpdate`, achieving the same guarantee atomically.
 
### `RefreshDatabase` doesn't work either
 
It wraps each test in a SQL transaction and rolls back. `tests/TestCase.php` drops the collections manually instead.
 
---
 
## Testing
 
```bash
php artisan test
```
 
58 tests across five files.

**`OrderServiceTest`** (unit) — `calculateTotal` in isolation, no database or HTTP. Includes float drift: `19.99 * 3` is `59.970000000000006` in raw PHP, and `round()` is what keeps stored totals honest.
 
**`OrderTest`** — stock decrement, financial integrity, security, boundaries, auth scoping.
 
**`EventTest`** — paused, cancelled and past events; admin vs regular user.
 
**`AuthTest`** — registration, duplicate email, hashing, login, and decoding the JWT to prove scopes travel inside the token.
 
**`AdminTest`** — event updates and deletion, ticket type management, and that a price change leaves existing orders untouched.

### Two real bugs the tests caught
 
Both were invisible through manual testing.
 
**`payment_gateway` and `payment_reference` were missing from `Order`'s `$fillable`.** Laravel silently discarded them on every order ever placed. `$fillable` protects against mass assignment by ignoring unlisted fields — no error, no warning. Every payment record was being lost.
 
**The `status` validation rule wasn't in `StoreEventRequest`.** `status: "watermelon"` was accepted and stored. Since `isOnSale()` compares against `STATUS_ACTIVE` exactly, such an event would never sell a single ticket, with no meaningful error to explain why.
 
Mongo accepts any shape. Validation is the only guard, which makes it worth testing that the guard is actually there.
 
---
 
## Concurrency
 
Two people buying the last ticket simultaneously is the scenario that costs real money when it fails.
 
**Protection is in place on two levels.** `decrement()` maps to Mongo's atomic `$inc`, so the database performs the subtraction rather than PHP reading, subtracting, and writing back. And the transaction ensures the stock decrement and the order insert either both happen or neither does.
 
**Proving it needs a load test, not a feature test.** PHPUnit runs sequentially in one process — there's no parallelism to exercise. Spawning processes with `exec()` produces a test that passes or fails depending on system timing, and a test that fails randomly is worse than no test, because you learn to ignore it.
 
Flagged as identified, not covered. The right tool is `k6` or similar, in a different phase of the project.
 
---
 
## Admin management

Admins hold a token carrying `events:create` and `events:manage`, and can:

- create, update and delete events
- create, update and delete ticket types

**Deletion is deliberately restricted.** An event or ticket type that has orders cannot be deleted — doing so would orphan those orders and erase a financial record. Cancelling the event is the correct action instead: it preserves the record, stops new sales, and lets existing orders be refunded.

**Changing a ticket price never affects existing orders.** They store the price paid at purchase time, so a receipt from last month still shows what was actually charged.

---

## Refunds

`POST /api/orders/{id}/refund` marks an order refunded and returns its tickets to stock.

Both writes happen inside a transaction. Marking the order without returning stock would lose those tickets permanently; returning stock without marking the order would allow an unlimited refund loop.

Scoped to the authenticated user — someone else's order returns `404` rather than `403`, so the API never confirms an order exists to someone who shouldn't see it.

---

## Known gaps

**Load testing.** Concurrency protection is in the code, but proving it needs a tool that fires many parallel requests. See the Concurrency section above.

**Pending orders.** Every order is currently marked paid immediately, so there are no pending orders to expire. Separating payment from order creation would be the prerequisite for a scheduled cleanup command.
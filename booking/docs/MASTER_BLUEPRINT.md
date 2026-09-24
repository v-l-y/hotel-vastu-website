# MASTER BLUEPRINT v1.0 — BOOKING SYSTEM SCOPE FREEZE

**Product:** Hotel Vastu Booking / PMS / Restaurant  
**Version:** 1.0  
**Scope status:** FROZEN  
**Freeze date:** 24 September 2026  
**Application:** `booking/` Laravel application  
**Production boundary:** `booking.hotelvastu.com`

---

## 1. Purpose

This document is the single source of truth for **Hotel Vastu Booking System v1.0**.

v1.0 is considered a complete, end-to-end hotel booking, front-desk, billing and restaurant operations baseline. Future reviews of v1.0 must verify this frozen contract. They must not invent new product requirements, new modules, or new business rules merely because another review is performed.

A v1.0 review may identify only:

- a defect against this blueprint;
- a security or authorization weakness;
- a data-integrity or concurrency defect;
- a regression;
- a missing implementation that this blueprint explicitly requires;
- an operational/deployment misconfiguration.

Anything else is an enhancement and belongs to a later version. It does **not** reopen v1.0.

---

## 2. v1.0 product goal

v1.0 provides one coherent flow from a guest searching for a room through final checkout and hotel reporting:

```text
Static hotel website
    ↓
Booking search
    ↓
Availability + price
    ↓
Temporary inventory hold
    ↓
Guest details
    ↓
Confirmed reservation
    ↓
Optional pre-arrival payment
    ↓
Front desk / modification / cancellation / no-show
    ↓
Check-in + physical room assignment
    ↓
Stay operations + restaurant / room service
    ↓
Guest folio
    ↓
Settlement
    ↓
Checkout
    ↓
Invoice
    ↓
Refund / credit-note lifecycle when applicable
    ↓
Operational and financial reports
```

The objective is a reliable direct-booking/PMS core for one hotel, not a universal hospitality platform.

---

## 3. Deployment boundary

The two applications remain deliberately separate:

- **`hotelvastu.com`** — static, SEO-first public hotel website.
- **`booking.hotelvastu.com`** — Laravel booking/PMS/restaurant application.

The public website may pass non-sensitive stay-prefill values to the booking application. Guest identity/contact data is collected inside the booking application.

This separation is part of v1.0 and must not be collapsed during a v1.0 review.

---

## 4. Canonical v1.0 guest booking flow

### 4.1 Search

The guest provides:

- check-in date;
- check-out date;
- room type;
- rate plan;
- room quantity;
- adults;
- children.

Rules:

- check-in cannot be before today;
- check-out must be after check-in;
- only active room types and rate plans are selectable;
- requested occupancy must fit configured room-type capacity.

### 4.2 Availability

Availability is room-type/date-range based.

Saleable inventory is reduced by:

- confirmed reservations;
- checked-in reservations;
- valid pending reservations where applicable;
- active temporary holds;
- active room blocks;
- rooms that are not saleable.

For a multi-night search, availability is based on the **peak committed demand for any individual stay night**, not the sum of sequential reservations across different nights.

Checkout day does not consume the following guest's check-in inventory.

Inventory-changing operations use database transactions and the canonical room-type row lock so concurrent requests cannot oversell inventory.

### 4.3 Temporary hold

Before guest confirmation, selected inventory is protected by an expiring reservation hold.

v1.0 rules:

- hold duration is the implemented short booking window;
- expired holds do not consume inventory;
- a hold converts at most once;
- concurrent hold creation uses inventory locking;
- confirmation must reject an expired or already-converted hold.

### 4.4 Pricing

Pricing is calculated per stay night.

The price source is:

1. the applicable non-overlapping dated room rate for the room type + rate plan; otherwise
2. the room type base rate.

Rate-plan minimum/maximum stay rules apply to the total stay length.

No rate, tax, room count, room number, or policy may be invented by application seed/demo logic.

### 4.5 Taxes

Effective tax rules are evaluated per stay night.

The confirmed reservation stores immutable per-night snapshots of:

- stay date;
- room type;
- rate plan;
- quantity;
- unit rate;
- line total;
- tax rate;
- tax amount;
- gross total.

Later tax/rate configuration changes must not rewrite historical confirmed pricing.

### 4.6 Guest details and confirmation

Confirmation records the guest and creates:

- a unique booking number;
- an opaque public confirmation token;
- confirmed reservation-room records;
- immutable reservation night-rate snapshots.

Public booking pages must never depend on a predictable internal database ID as the guest-facing identifier.

---

## 5. Reservation lifecycle

v1.0 supports:

- confirmed reservation;
- pre-arrival modification;
- cancellation;
- no-show;
- checked-in;
- checked-out.

### 5.1 Modification

A confirmed pre-arrival reservation may be modified only after:

- validating the new dates/occupancy;
- rechecking inventory;
- repricing;
- regenerating the new immutable night snapshots;
- invalidating any no-longer-current live gateway order.

Existing payments are reconciled against the new reservation total.

Payment state is:

- `unpaid`;
- `partially_paid`;
- `paid`;
- `overpaid` when captured/net payment exceeds the current reservation total.

An overpayment must remain visible until reconciled/refunded; it must not be silently represented as merely paid.

### 5.2 Cancellation and no-show

Cancellation is limited to the lifecycle states supported by the service.

No-show cannot be applied before the reservation check-in date.

These transitions must release future booking inventory as defined by the availability service.

---

## 6. Payments

v1.0 supports verified hotel payment recording through:

- cash;
- UPI;
- card;
- bank transfer;
- configured online gateway.

Core invariants:

- a payment targets exactly one reservation, folio, or restaurant order;
- manual payment cannot exceed the current outstanding balance;
- direct restaurant payment is allowed only for a served non-room-service order;
- room-service settlement belongs to the guest folio;
- payment posting and refund accounting are idempotent;
- refunds cannot exceed the refundable balance;
- succeeded payments/refunds are the accounting source of truth.

### 6.1 Razorpay

When configured, v1.0 supports:

- server-created Razorpay orders;
- checkout signature verification;
- provider-side captured-payment verification;
- signed webhook verification;
- captured-payment reconciliation;
- provider-backed refunds;
- pending / processed / failed refund lifecycle;
- idempotent webhook processing.

Concurrency rules:

- online order creation is serialized with the reservation row;
- payment capture uses the canonical lock order;
- duplicate checkout requests reuse the valid live gateway order where appropriate;
- a stale provider order that was genuinely captured is ledgered rather than discarded;
- any resulting overpayment is explicitly visible for reconciliation.

Razorpay remains hidden when live credentials are not configured.

---

## 7. Front desk

v1.0 front desk supports:

- new desk / walk-in confirmed booking creation with live availability and pricing validation;
- reservation list/work queue;
- reservation modification;
- cancellation;
- no-show;
- check-in;
- room transfer;
- stay extension;
- checkout;
- housekeeping state changes.

### 7.1 Check-in

Check-in requires:

- reservation status `confirmed`;
- pricing status `priced`;
- check-in within the reserved stay window;
- exact physical-room count matching reserved quantities;
- physical room type matching the reserved room type;
- room status active;
- room housekeeping state ready for guests;
- no active conflicting stay assignment;
- no overlapping active room block.

Check-in creates:

- stay;
- physical room assignments;
- stay guest links;
- guest folio;
- initial room charge.

Pre-arrival reservation payments are transferred to the folio accounting context.

### 7.2 Room transfer

A room transfer:

- requires an active checked-in stay;
- keeps the reserved room type;
- requires the target room to be active and ready;
- rejects an occupied or blocked target room;
- releases the old assignment;
- marks the released room dirty.

### 7.3 Stay extension

A stay extension:

- requires an active stay;
- must extend beyond current checkout;
- serializes with room-type inventory;
- rechecks availability for the extension nights;
- checks physical room blocks;
- prices extra nights using the **total final stay length** for rate-plan stay constraints;
- stores additional immutable night snapshots;
- posts an idempotent extension charge to the folio.

### 7.4 Housekeeping

v1.0 housekeeping states are operational room readiness states used by booking/front-desk logic.

Checkout and room transfer mark released guest rooms dirty.

A room that is not saleable must not be counted as available inventory.

---

## 8. Maintenance room blocks

An administrator may block a physical room for a date range.

A new block must:

- use future/current valid dates;
- reject overlapping active blocks for the same room;
- reject a conflict with a currently assigned guest room;
- use the room-type inventory lock;
- reject a block that would reduce saleable inventory below already committed reservations/holds.

Closing a room block preserves its closure timestamp so historical reporting can distinguish an active historical block from one already closed at that time.

---

## 9. Guest folio and checkout

Each checked-in stay has one open folio.

The folio tracks:

- room charges;
- stay-extension charges;
- room-service/restaurant charges;
- payments;
- refunds;
- current balance.

Charge posting uses source keys where appropriate to prevent duplicate operational charges.

Checkout requires:

- active checked-in stay;
- no pending room-service order that still needs completion/cancellation;
- fully settled folio balance.

Checkout then:

- creates/fetches the immutable invoice;
- releases assigned rooms;
- marks released rooms dirty;
- closes the folio;
- marks the stay checked out;
- marks the reservation checked out.

---

## 10. Invoice and credit notes

v1.0 creates one invoice per folio.

The invoice is an immutable checkout snapshot of:

- invoice items;
- subtotal;
- tax;
- total;
- amount paid;
- balance;
- issue timestamp.

Post-invoice successful refunds do not rewrite the original invoice. They create a credit note tied to the refund/invoice.

---

## 11. Restaurant / POS / KOT

v1.0 restaurant order types:

- dine-in;
- room service;
- takeaway.

### 11.1 POS order

A restaurant order:

- contains one or more active menu items;
- snapshots item name, quantity, price and line total;
- calculates effective restaurant tax;
- creates one KOT;
- reserves a dine-in table when applicable.

### 11.2 KOT lifecycle

Canonical lifecycle:

```text
accepted → preparing → ready → served
             ↘
            cancelled
```

Only valid transitions are accepted.

KOT timestamps provide the operational source for preparation/served reporting.

### 11.3 Dine-in

A dine-in order requires an active available table.

The table remains occupied until the order is cancelled or the served order is fully paid.

### 11.4 Room service

Room service:

- requires an open guest folio;
- locks the folio against concurrent checkout while the order is created;
- creates KOT normally;
- posts the restaurant charge to the folio only when served;
- is not directly paid as an independent restaurant bill.

Pending room service blocks hotel checkout.

### 11.5 Takeaway

Takeaway is settled directly after it reaches served status.

---

## 12. Admin authentication and RBAC

v1.0 roles:

- `administrator`;
- `front_desk`;
- `restaurant`;
- `kitchen`;
- `accounts`.

### Administrator

Owns:

- master setup;
- rooms/rates/taxes;
- restaurant master data;
- admin user management;
- all operational modules.

### Front desk

Owns hotel guest operational lifecycle:

- reservations;
- check-in/out;
- transfer;
- extension;
- housekeeping;
- permitted hotel payment/folio operations;
- invoice/report access defined by routes.

### Restaurant

Owns:

- restaurant order creation;
- restaurant operational order flow;
- direct payment of eligible dine-in/takeaway orders.

Restaurant role must not gain hotel reservation/folio payment or refund authority.

### Kitchen

Owns the KOT preparation queue.

Kitchen may advance operational cooking stages allowed by the controller. It must not:

- create restaurant orders;
- settle payments;
- issue refunds;
- serve/cancel orders through financial/front-of-house authority;
- view unnecessary guest-folio/payment controls.

### Accounts

Owns permitted payment/refund, invoice and reporting operations.

### Session/authentication requirements

- inactive admin accounts are rejected;
- session ID is regenerated on successful login;
- logout invalidates the session and regenerates CSRF token;
- production session cookies are secure and HTTP-only;
- state-changing admin routes remain CSRF protected;
- Razorpay webhook is the explicit server-to-server CSRF exception and must still pass signature verification.

---

## 13. Admin audit trail

Authenticated state-changing admin requests are audit logged with the available:

- admin user;
- route;
- HTTP method;
- path;
- model subject when resolvable;
- response status;
- IP address;
- creation time.

The audit trail is an operational record; it is not a replacement for accounting ledger records.

---

## 14. Reports

v1.0 reports include the implemented operational baseline:

- payments total;
- refunds total;
- tax total;
- bookings created;
- cancelled bookings;
- booked room nights;
- occupancy percentage;
- ADR;
- room revenue;
- payment split by method;
- restaurant sales;
- top restaurant items.

Reporting invariants:

- room-night date filtering must be cross-database safe;
- occupancy denominator is date-scoped saleable physical room inventory;
- applicable historical room blocks reduce available room nights;
- restaurant sale timing is based on actual KOT `served_at`, not an arbitrary later model `updated_at`;
- later payment/status edits must not move a restaurant sale into a different service period.

---

## 15. Database and concurrency contract

MySQL is the production database.

Critical inventory/payment operations use transactions and row locks. Lock ordering must remain deterministic where multiple resources are locked.

v1.0 protects against:

- duplicate hold conversion;
- concurrent inventory oversell;
- duplicate provider payment posting;
- duplicate refund posting;
- payment/refund over-allocation;
- duplicate folio operational charges;
- duplicate invoice creation;
- unsafe room block creation;
- checkout versus room-service race;
- stay-extension versus new booking/hold race.

Migrations are the database change mechanism. Existing production data must be migrated forward; production must not be reset merely to deploy a normal v1.0 patch.

---

## 16. v1.0 included scope — frozen

The following are the complete product areas required for v1.0:

1. public booking search;
2. room-type availability;
3. temporary holds;
4. guest confirmation;
5. rate plans and dated pricing;
6. tax rules and pricing snapshots;
7. reservation lifecycle;
8. manual payments;
9. Razorpay integration when configured;
10. refund reconciliation;
11. physical-room front desk;
12. check-in/check-out;
13. transfer/extension/housekeeping;
14. room maintenance blocks;
15. guest folio;
16. invoice;
17. credit note;
18. restaurant POS;
19. room service;
20. KOT;
21. restaurant tables;
22. admin authentication;
23. RBAC;
24. admin audit trail;
25. operational reports;
26. database migrations;
27. SQLite feature contract;
28. MySQL production contract;
29. route/view cache build;
30. static-site booking-link regression protection.

No additional product module is required to call v1.0 complete.

---

## 17. Explicitly outside v1.0

The following are not v1.0 blockers unless a separate approved version specification explicitly adds them:

- OTA/channel-manager synchronization;
- multi-property management;
- loyalty/rewards program;
- full CRM/marketing automation;
- advanced revenue/yield-management engine;
- promotional coupon engine beyond the frozen pricing model;
- banquet/event management;
- housekeeping mobile application;
- staff scheduling/payroll;
- procurement/inventory/warehouse ERP;
- accounting-system/ERP integration;
- GST e-invoicing/e-waybill integration;
- automated WhatsApp/SMS/email campaign system;
- guest mobile app;
- self-service kiosk;
- AI pricing/recommendation features;
- generalized plugin marketplace;
- redesign work that changes business logic.

These may be considered for a future version, but a v1.0 review must not convert them into missing requirements.

---

## 18. v1.0 release gates

A repository revision is valid for v1.0 only when the applicable gates are green:

- `booking/VERSION` remains `1.0` and the scope-freeze contract is present;
- Composer validation;
- dependency installation;
- SQLite feature suite with warnings treated as failures;
- Laravel route cache build;
- Laravel view cache build;
- MySQL 8 production-contract suite;
- MySQL row-lock contract;
- static-site QA for the public website/booking boundary.

For payment-enabled production activation, also verify environment-specific items:

- production Razorpay credentials;
- correct webhook URL and secret;
- a low-value real payment;
- webhook receipt;
- refund reconciliation.

Those are **deployment activation checks**, not new v1.0 product scope.

---

## 19. Definition of DONE

Booking System v1.0 is DONE when:

1. every included scope item in section 16 exists and follows this blueprint;
2. no known RED defect violates the frozen invariants;
3. automated repository release gates pass;
4. deployment configuration is supplied for the chosen production environment.

Once those conditions are true, another review does not reopen product design.

A subsequent review that finds no defect should end with **v1.0 remains complete**. It must not manufacture another requirement simply to continue a review loop.

---

## 20. Change policy after freeze

### Allowed inside v1.0

Without changing scope, maintainers may:

- fix bugs;
- fix security issues;
- fix authorization mistakes;
- fix concurrency/data-integrity defects;
- fix incorrect validation;
- fix accessibility defects;
- fix broken UI behavior;
- fix cross-database/test regressions;
- improve tests for already-frozen behavior;
- update deployment/config documentation;
- make non-behavioral refactors.

### Not allowed as a v1.0 review finding

A reviewer must not add a new business capability, workflow, role, state machine, payment method, integration, report family, or operational module simply because it could make the product more sophisticated.

If genuinely desired, that change must be explicitly scoped as a later version (`v1.1`, `v2.0`, etc.) and must not be presented as unfinished v1.0 work.

---

## 21. Review protocol

Every future v1.0 finding must answer:

1. **Which frozen v1.0 requirement/invariant is violated?**
2. **Where is the concrete evidence in code/test/runtime behavior?**
3. **Is it a bug, regression, security issue, data-integrity issue, or deployment configuration issue?**

If question 1 has no answer, the finding is an enhancement and is outside the v1.0 completion review.

This rule is intended to stop endless review → new logic → review loops.

---

## 22. Canonical statement

> **Hotel Vastu Booking System v1.0 is a frozen direct-booking + PMS + billing + restaurant baseline. Review verifies this baseline; review does not expand it.**

Any future scope expansion requires a new version decision, not reinterpretation of v1.0.

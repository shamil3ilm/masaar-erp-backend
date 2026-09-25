# Deployment checklist

Four things need a person after this release: the migrations, the account
mappings, the scheduler, and — only for a company that runs more than one
organization — the parent link between them. Until the mappings are in place
the postings that need them refuse rather than post, and until the scheduler
runs nothing scheduled happens at all.

## 1. Migrations

```
php artisan migrate
```

Twenty migrations, `0490` to `0671`. Take a backup first: three of them change
data, and one deletes rows.

Most of them widen a unique index from "unique everywhere" to "unique within
the organization" — document numbers, codes and identifiers that two tenants
could not both use. Widening an index cannot conflict with rows already
stored, because a value unique across every organization is still unique
within one.

The three that touch data:

| Migration | What it does to existing rows |
|---|---|
| `0520_continue_number_sequences_after_stored_numbers` | Sets each organization's number counter for the current year to the highest number its documents already carry, so the next document does not reuse a number. |
| `0540_messaging_channel_default_marker` | Replaces the `is_default` boolean on messaging channels with `default_for_type`, carrying the channel type while the channel is the default and null otherwise. |
| `0671_automation_schedule_pending_marker` | Keeps the pending entry each automation rule booked first and **deletes the later duplicates**. They are double bookings that would run the rule's next occurrence twice. |

To see what `0671` will delete before running it:

```sql
SELECT rule_id, COUNT(*) FROM automation_schedules
WHERE status = 'pending' GROUP BY rule_id HAVING COUNT(*) > 1;
```

## 2. Account mappings

Several postings used to find their account by matching its name — `'%Forex%'`,
`'%Accrued%'`, `'%Advance%'` — which picks up whatever the chart of accounts
happens to be called and lands the entry somewhere else the moment an account
is renamed. Each of those roles is now mapped explicitly, per organization. A
posting with no mapping refuses and names the key it wants, instead of posting
a one-sided or misdirected entry.

Set them per organization, with a user holding `core.settings.edit`:

```
PUT /api/v1/settings/group/accounting
{ "settings": { "wip_account_id": 1234, "fg_inventory_account_id": 1235 } }
```

The value is the id of one of that organization's own accounts; an id
belonging to another organization does not resolve.

| Key | Needed by | Refuses without it |
|---|---|---|
| `wip_account_id` | Work order settlement | Settling a work order |
| `fg_inventory_account_id` | Work order settlement | Settling a work order |
| `grni_account_id` | Goods receipt | Posting goods received not invoiced |
| `vendor_advance_account_id` | Vendor advances | Recording and applying an advance |
| `inventory_adjustment_account_id` | Physical inventory | Posting a count difference |
| `maintenance_expense_account_id` | Maintenance settlement | Settling a maintenance order |
| `maintenance_clearing_account_id` | Maintenance settlement | Settling a maintenance order |
| `maintenance_capitalization_account_id` | Maintenance settlement | Settling a maintenance order that capitalizes |
| `fx_gain_account_id` | FX forwards | Settling a forward that realised a gain |
| `fx_loss_account_id` | FX forwards | Settling a forward that realised a loss |
| `fx_unrealised_gain_account_id` | FX valuation, currency revaluation | Valuing a forward, or a revaluation netting to a gain |
| `fx_unrealised_loss_account_id` | FX valuation, currency revaluation | Valuing a forward, or a revaluation netting to a loss |

An individual FX forward can name its own result accounts, and those win over
the mapping. The mapping is what every other contract falls back on.

## 3. Scheduler

One cron entry runs everything scheduled:

```
* * * * * cd /path/to/masaar-erp-backend && php artisan schedule:run >> /dev/null 2>&1
```

On Windows, a Task Scheduler task repeating every minute, running the same
command.

Without it nothing scheduled runs: automation rules never fire, invoices and
bills never turn overdue, recurring invoices and bills never post, scheduled
reports never go out, and the security and export cleanups never happen.
Automation in particular sweeps every minute, because a rule's cron expression
is minute-grained and a rule has to run in the minute it is due.

## 4. Parent organizations

Only relevant if one company runs several organizations in the same database.

Fields that name another company — the receiver of an inter-company asset
transfer, the entities in a consolidation group, the two sides of an
intercompany sales order — used to accept any organization in the table, which
let one tenant post into another tenant's books. They now require the two
organizations to share a group, and a group is an organization together with
its subsidiaries.

An organization with no parent is a group of its own, which is the right answer
for a single-tenant install: nothing to set. For a real group, a platform
super admin links each subsidiary to its parent:

```
PUT /api/v1/admin/organizations/{organization}/parent
{ "parent_organization_id": 42 }
```

Send `null` to detach one. Do this before using inter-company transfers,
consolidation or intercompany orders, or those will refuse the partner.

## 5. After deploying

- Post a document that numbers itself and confirm the number continues from
  the last one rather than restarting.
- Settle a work order, a maintenance order and a goods receipt in a test
  organization; each should post rather than refuse.
- Confirm a scheduled automation rule fires within a minute of its due time.
- Check that the last deploy's build actually shipped before blaming the code
  for anything that still looks wrong.

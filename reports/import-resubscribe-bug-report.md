# Report: Unsubscribed Users Being Re-subscribed on Data Import

**Component:** com_ra_mailman
**Date:** 2026-07-25
**Status:** Investigation complete — no code changes made

## Summary

When re-importing a Ramblers Insight Hub export via the DataLoad feature, users who had
previously unsubscribed from a mail list are silently re-subscribed. This happens because
the import path cannot distinguish "user has no subscription" from "user deliberately
unsubscribed" — both cases are treated identically, and the subscribe logic only guards
against re-subscribing someone who is *already active*, not someone who has opted out.

## Affected feature

- **Entry point:** `administrator/src/Controller/DataloadController.php` (file upload,
  `save`/`check`/`continue` actions)
- **Import engine:** `administrator/tmpl/process/default.php:18-32` instantiates
  `UserHelper` and calls `processFile()`. The actual per-row import logic lives in
  `site/src/Helpers/UserHelper.php`, not in a `DataloadModel`/`DataloadTable` — those only
  manage the uploaded file record.

## Root cause chain

### 1. `Mailhelper::isSubscriber()` — loses the "unsubscribed" signal

`site/src/Helpers/Mailhelper.php:783-801`

```php
$sql = 'SELECT s.method_id, s.record_type, m.name  as "Method" '
        . 'FROM #__ra_mail_subscriptions AS s '
        . 'INNER JOIN #__ra_mail_methods as m ON m.id = s.method_id '
        . 'INNER JOIN #__users as u ON u.id = s.user_id '
        . 'WHERE s.list_id=' . $list_id
        . ' AND s.user_id=' . $user_id
        . ' AND s.state=1'          // only "active" rows count
        . ' AND u.block=0';
...
if (is_null($subscriber)) {
    return '';                      // same result whether row doesn't exist OR state=0
}
```

Because the query filters `s.state=1`, a cancelled/unsubscribed row (`state=0`) is invisible
here and produces the same `''` result as "never subscribed at all." This is where the two
distinct cases first get collapsed into one.

### 2. `UserHelper::processRecords()` — treats returning unsubscribed users as new

`site/src/Helpers/UserHelper.php:872-883`

```php
$method = $this->objMailHelper->isSubscriber($this->list_id, $user_id);
if ($method == '') {
    $message .= ', Subscription <b>not present</b>';
    $subscription_required = true;
} else {
    $subscription_required = false;
}
```

Because `isSubscriber()` returned `''` for the unsubscribed user, `$subscription_required`
is set `true`, and the import proceeds to call `subscribe()` for them exactly as it would
for a brand-new user (line 887-895).

### 3. `Mailhelper::subscribe()` — missing guard for the cancelled state

`site/src/Helpers/Mailhelper.php:1535-1594`, key lines 1560-1566

```php
$item = $this->toolsHelper->getItem($sql);
if ($item) {
    if (($item->state == 1) AND ($item->record_type == $record_type)) {
        $this->message = 'User is already subscribed to ' . $list->name . ' as ' . $item->name;
        return false;
    }
}
...
$state = 1;
if ($this->updateSubscription($list_id, $user_id, $record_type, $method_id, $state)) {
```

This method re-fetches the existing subscription row — this time *without* the `state=1`
filter, so it does see the cancelled row. But the only bail-out condition is "already active
with the same record type." There is no branch for `$item->state == 0` (cancelled) or
`-2` (purged), so execution falls through and unconditionally calls `updateSubscription()`
with `$state = 1`, reactivating the row.

### 4. `Mailhelper::updateSubscription()` / `SubscriptionHelper::update()` — write the reactivation

`site/src/Helpers/Mailhelper.php:1649-1717` and `site/src/Helpers/SubscriptionHelper.php:337-436`

`updateSubscription()` loads the existing row via `SubscriptionHelper::getData()` and sets
`state` to whatever was passed in (`1`), with no check of the prior value before overwriting.
`SubscriptionHelper::update()` then issues a plain conditional `UPDATE` — notably, it already
contains a branch that *labels* this transition:

```php
if ($row->state != $this->state) {
    if (($row->state == 0) AND ($this->state == 1)) {
        $this->action = 're-subscribed';
    } else {
        $this->action = 'cancelled from';
    }
}
```

This confirms the code is fully aware it is performing a "re-subscribe" transition — it is
just applied unconditionally by any caller, including the passive DataLoad import, rather
than being gated behind an explicit admin/user action.

## Schema context

`administrator/sql/install.mysql.utf8.sql:156-174`

```sql
CREATE TABLE IF NOT EXISTS `#__ra_mail_subscriptions` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `list_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `record_type` INT NOT NULL,
    `method_id` INT NOT NULL,
    `state` TINYINT NOT NULL,
    `ip_address` VARCHAR(50) NOT NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ...
```

`state` is a plain `TINYINT NOT NULL` with no default: `1` = active, `0` = cancelled,
`-2` = purged/blocked (per doc comments in `Mailhelper::updateSubscription()`). There is no
separate flag distinguishing "never subscribed" from "opted out" — `state` is the only
signal, and it's being read inconsistently (filtered in `isSubscriber()`, unfiltered in
`subscribe()`).

Also relevant: `method_id = 6` records "Unsubscribed via link" (self opt-out, see
`site/src/Controller/Mail_lstController.php:207-212`). The import silently overwrites this
with its own method (`3` = corporate feed / `5` = CSV, depending on source) and `state = 1`,
erasing the record of how/why the user opted out.

## Recommended fix location

**Primary, most central fix:** `Mailhelper::subscribe()`, `Mailhelper.php:1560-1566`.

Add a branch for `$item->state == 0` (and `-2`) that declines to resubscribe:

```php
if ($item) {
    if (($item->state == 1) AND ($item->record_type == $record_type)) {
        $this->message = 'User is already subscribed to ' . $list->name . ' as ' . $item->name;
        return false;
    } elseif ($item->state == 0) {
        $this->message = 'User previously unsubscribed from ' . $list->name . ' — not re-subscribing automatically';
        return false;
    }
}
```

This is the most central point because every subscribing code path funnels through
`subscribe()` — the DataLoad import, `Mail_lstController`, `User_selectController`,
`SubscriptionsModel`, `List_selectModel`, `ProfileModel`, `ProfileformModel`, and
`UserHelper::processRecords()` all call it. A single guard here fixes the import case
without touching each caller individually.

**Caveat:** the legitimate admin-initiated "resubscribe" action
(`Mailhelper::resubscribe()`, `Mailhelper.php:~960-973`, used from the Subscriptions admin
view) must still be able to reactivate a cancelled subscription. If it currently reuses
`subscribe()`, the fix needs a way to distinguish "explicit admin resubscribe" from "passive
import subscribe" — e.g. an optional `$force` parameter on `subscribe()` that the admin
resubscribe action passes as `true`, while `UserHelper::processRecords()` does not.

**Secondary option (alternative or complementary):** fix `Mailhelper::isSubscriber()`
(`Mailhelper.php:788-801`) to not filter out `state=0` rows, and have it return a distinct
value (e.g. `'unsubscribed'`) instead of `''`. `UserHelper::processRecords()` could then
check for that value and skip setting `$subscription_required = true` in the first place,
stopping the import from ever calling `subscribe()` for these users. This addresses the
import path specifically but doesn't harden `subscribe()` itself against other callers.

## Suggested scope of change

1. Decide whether the fix belongs in `subscribe()` (central guard, needs a force/override
   mechanism for legitimate resubscribe actions) or in the `isSubscriber()` /
   `processRecords()` pair (import-specific, narrower blast radius).
2. Confirm whether `Mailhelper::resubscribe()` calls `subscribe()` or writes directly via
   `updateSubscription()`/`SubscriptionHelper` — this determines whether a `$force` parameter
   is needed.
3. Add a regression test / manual test case: unsubscribe a user, re-run the DataLoad import
   with that user present in the export, confirm their subscription remains cancelled.

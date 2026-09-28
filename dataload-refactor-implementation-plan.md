# RA MailMan DataLoad refactor — implementation plan

## Recommendation

The proposed change is feasible and is preferable to extending the existing
`UserHelper`. The current helper combines CSV parsing, validation, Joomla user
and profile persistence, subscriptions, lapsed-member handling, reports,
HTML output and unrelated user-management routines. A new DataLoad-specific
`LoadHelper` should replace it as the import engine, while
`PersonHelper` in `com_ra_tools` becomes the only user/profile persistence
service.

The existing two-pass behaviour must remain:

1. Pass one validates the complete file and records proposed actions without
   writing users, profiles or subscriptions.
2. The administrator confirms the result and pass two repeats validation and
   performs the writes.

`#__ra_import_reports` remains the report/audit store. Its current fields,
`method_id` values and report-controller behaviour should remain compatible.

## Confirmed decisions

- Simple CSV input requires a header row.
- An unpublished/cancelled subscription is never reinstated by DataLoad.
- Email/name mismatches use the Members loader's shared-profile and identity
  conflict safeguards.
- Subscription writes support an optional `force` flag, defaulting to `false`;
  only an explicit, authorised administrator resubscribe action may use
  `true`.
- Malformed rows found during pass two are reported and skipped; subsequent
  rows continue processing.
- `UserHelper` will be retired after all callers are migrated.
- MailChimp headings are `Email address`, `First Name` and `Last Name`, matched
  case-insensitively; its group code comes from the selected mailing list.
- The existing MailMan `notify_new_user` configuration option determines
  whether a newly registered user receives a notification email.
- Insight lapsed-member block/purge behaviour remains unchanged.
- User/profile creation will be centralised through a dedicated RA Tools user
  plugin and `PersonHelper`; DataLoad will not directly insert profile rows.
- A newly created user receives an unpublished `ZZ99` placeholder profile,
  which DataLoad then completes with the real group and profile data.
- No reserved Joomla user ID will be used for placeholders. Placeholder rows
  retain the actual user's ID, use `created_by = 0` for system creation, and
  are identified by `state = 0` plus `home_group = ZZ99`.

## Current findings

The upload controller/model stores the uploaded file and import options in
Joomla user state. `administrator/src/View/Process/HtmlView.php` creates or
retrieves the report, and `administrator/tmpl/process/default.php` currently
instantiates `site/src/Helpers/UserHelper.php` and calls `processFile()`.

`UserHelper` currently contains:

- source-specific parsing and column lookup;
- validation, counters and diagnostic HTML;
- direct SQL/Joomla-user creation and profile insertion;
- mailing-list subscription handling;
- Insight-only lapsed-member processing;
- report updates; and
- unrelated block, purge, profile and test methods.

The helper also has fixed positional parsing for MailChimp and simple CSV,
direct writes to `#__users` and `#__ra_profiles`, echoed HTML/debug remnants,
and inconsistent error handling. These should not be copied into `LoadHelper`.

`com_ra_members` is a useful reference for separating feed mapping,
identity resolution and persistence, but its full Salesforce/member-feed
workflow must not be cloned: MailMan additionally owns list subscriptions,
import reports and its Insight lapsed-member policy.

## Proposed structure

Add `com_ra_mailman/site/src/Helpers/LoadHelper.php` with a small public API,
for example:

```text
processFile(filename, dataType, listId, processing, reportId): LoadResult
```

Keep these responsibilities separate inside the loader:

- CSV reading (quoted fields, BOM, blank/comment rows and original row data);
- header normalisation and canonical field mapping;
- source-specific row mapping to `group_code`, `name`, and `email`;
- validation and duplicate/conflict reporting;
- pass-two user/profile persistence;
- MailMan subscription processing;
- Insight-only lapsed-member processing; and
- writing the existing import-report row.

The Process template should only pass options to `LoadHelper` and render a
structured result. It should not parse CSV or instantiate `UserHelper`.

## Header-driven mappings

All three source types must inspect the first CSV row and locate required
fields by heading. Matching should trim whitespace, remove a UTF-8 BOM, be
case-insensitive, and normalise repeated whitespace/punctuation. Preserve the
original heading for diagnostics.

| Data type | Required canonical fields | Initial aliases to support |
| --- | --- | --- |
| 3 — Insight Hub | `forename`, `surname`, `email`, `group_code` | `Forenames`/`First Name`; `Last Name`/`Surname`; `Email Address`/`Email`; `Group Code`/`Group`/`Group/group` |
| 4 — MailChimp | `email`, `forename`, `surname` | `Email address`, `First Name`, `Last Name` (case-insensitive matching) |
| 5 — simple CSV | `group_code`, `name`, `email` | `Group Code`/`Group`/`Group/group`; `Name`/`Real Name`/`Full Name`; `Email Address`/`Email` |

Insight rows may contain additional columns, which are ignored after the four
required fields are found. MailChimp must no longer rely on the current
hard-coded name positions. Its group code is taken from the selected mailing
list, not from the input file. A header row is mandatory for simple CSV;
headerless historical `group,name,email` files are not supported.

Missing required headings, duplicate headings that create an ambiguity, and
unrecognised source types must fail during validation before any row is
processed. The error must name the missing/ambiguous field and show the
headings found.

## Validation and processing

Pass one should resolve the header map, validate every non-blank/non-comment
row, detect malformed CSV, missing values, invalid email/group codes,
duplicate source rows and identity conflicts, calculate proposed user and
subscription counts, and update the report with diagnostics. It must not call
any persistence or subscription write.

Pass two should resolve and validate the same file again, then for each valid
row:

1. find the user with `PersonHelper`;
2. create/update the Joomla user with `PersonHelper::saveUser()`;
3. create/update `ra_profiles` with `saveProfileData()` or a narrowly scoped
   convenience method;
4. call `Mailhelper` for the existing list, record type and source
   `method_id` semantics;
5. collect created-user, subscription and error details; and
6. apply the Insight-only lapsed-member policy.

A row that fails user/profile persistence must not create its subscription;
unrelated valid rows should continue unless the file/database failure is
fatal. A malformed row confirmed during pass two is recorded as an error and
processing continues with subsequent rows. The final report update must set
the existing counters, diagnostic fields, `state` and `date_completed` exactly
as today.

## PersonHelper integration and likely gaps

Use the existing shared methods where semantics match:

- `findUserByEmail()` and `findUserByEmailAndProfile()`;
- `saveUser()`;
- `saveProfileData()`/`saveProfile()`; and
- `setRequireReset()` only if the import policy requires it.

If an email match is found whose name differs, use the same safeguards as the
Members loader: detect shared profiles/identity conflicts, report the conflict
and avoid silently changing a shared Joomla user's identity. Reuse or extract
the Members loader's resolution pattern rather than allowing
`PersonHelper::saveUser()` to overwrite the name unconditionally.

Small, reusable additions may be needed in `PersonHelper`: a user/profile
match result with conflict details, an atomic basic user+profile save, an
explicit no-notification import mode, and a documented result/exception
contract for created/updated/unchanged/conflict outcomes. Do not move
subscriptions, import reports or lapsed-member rules into `PersonHelper`.

The revised user/profile lifecycle must also add an idempotent
`ensurePlaceholderProfile(userId, name)` operation. It creates an unpublished
profile with reserved `home_group = ZZ99` only when no profile exists. Normal
profile saves must then replace `ZZ99` with a real group and publish only when
the profile is authorised. The method must be safe when invoked repeatedly by
the user-save plugin and by DataLoad retries.

## Centralised user/profile lifecycle

Add a dedicated `plg_user_ra_profile` plugin to the RA Tools package. On
Joomla's successful `onUserAfterSave` event for a newly created user, it must
call `PersonHelper::ensurePlaceholderProfile()` and log any failure. Since the
event is post-save and cannot reliably roll back the Joomla user, failures
must be recoverable through reconciliation rather than being silently ignored.

The plugin installer must run an idempotent one-off backfill for existing users:
find users with no profile linked by `ra_profiles.id`, create the unpublished
`ZZ99` placeholder, and report failures. Installation ordering must ensure the
RA profiles table exists before the backfill runs.

All profile-consuming code must treat `ZZ99`/unpublished profiles as
unassigned. Such profiles must not qualify users for mailing lists, events,
member-data access or other group-scoped operations. Publishing or completing
a profile must require a real group code. This includes reviewing the existing
user-deletion plugin, which should remove related records using the actual
profile linkage and valid SQL.

MailMan front-end `profileform` and administrator `profile` flows must be
refactored to follow this sequence:

1. Check the RA Tools self-registration setting where applicable.
2. Create or locate the Joomla user through `PersonHelper`/Joomla APIs.
3. Rely on the user plugin to create the placeholder profile for a new user.
4. Update that placeholder through `PersonHelper` with the submitted group,
   preferred name and other permitted fields.
5. Leave it unpublished until the required administrator authorisation.

Neither flow should contain a second, competing “create profile” path.

RA Members needs a separate administrator-only operation for the exceptional
case where two profile rows intentionally share one Joomla user/email. It must
create an additional profile with an audit trail and explicit member identity;
it must not weaken the normal identity-conflict rules. MailMan DataLoad must
reject an email match with a different name, using the Members loader's shared
profile safeguards, rather than invoking this exception.

The RA Tools prototype `profile` view should be completed separately after the
lifecycle refactor. It should allow an authenticated user to update only the
permitted preferred-name fields and, where enabled, view their MailMan
subscriptions, bookings and related information.

## Existing administrator maintenance tasks

The task names in the current code differ slightly from the names sometimes
used operationally: `checkDatabase` and `duffProfiles` are currently methods of
`ReportsController` and are invoked as `reports.checkDatabase` and
`reports.duffProfiles`, not `profile.*`.

The profile plugin changes the purpose of several tasks. They should be
reviewed as follows:

| Current task | Current behaviour | Recommended disposition |
| --- | --- | --- |
| `system.duffRecords` | Finds and deletes orphaned subscriptions, subscription-audit rows and user-group mappings. It is destructive, emits SQL, and contains legacy assumptions. | Repurpose as a super-user-only, read-only integrity report with separately confirmed repair actions. Use a shared reconciliation service and preserve audit rows until deletion is explicitly approved. |
| `system.purgeAllUsers` | Deletes every blocked Joomla user directly, then calls legacy profile cleanup. | Retain as the explicitly confirmed **bulk purge of blocked users omitted from the latest feed**. It must enumerate the candidates, require Super User confirmation, and delete each user through Joomla's user deletion API so deletion plugins run. It is a feed-maintenance operation, not a general-purpose deletion path. |
| `system.purgeUser` | Wrapper that accepts an ID and calls `purgeUserRecord`. | Retain only as the internal per-user operation used by the confirmed blocked-user bulk purge, loading the Joomla user through Joomla's user API and calling its delete operation. General administrator deletions are handled through the Joomla User UI. |
| `system.purgeUserRecord` | Directly deletes groups, profile, user and optionally bookings; omits or inconsistently handles contacts, recipients, subscriptions and audit data. | Retire the direct-SQL implementation. The blocked-user bulk purge may call a central helper that invokes Joomla's user deletion API; all other deletions are initiated from Joomla's User UI. |
| `reports.checkDatabase` | Checks orphan subscriptions/users/profiles, users without profiles, profiles with `id=0`, missing preferred names and duplicate names; some branches delete orphan subscriptions. | Repurpose as a read-only reconciliation dashboard. Include users without profiles, profiles without users, placeholder `ZZ99` profiles, duplicate/shared profiles and orphan component records. Offer separate, confirmed repairs. |
| `reports.duffProfiles` | Lists profiles without users and offers a bulk purge; also reports profiles with `id=0`. | Keep as an orphan-profile report, but remove implicit bulk deletion. Provide a reviewed purge action that removes related audit/dependent records safely. |

The user/profile plugin and its installation backfill should make “user
without profile” an exceptional, repairable condition rather than a normal
maintenance task. The reconciliation dashboard remains valuable for detecting
plugin failures, manually deleted records, legacy data and shared-email
profiles. It must distinguish an intentional additional Members profile from
an orphan or duplicate.

The deletion plugin/event path should define the deletion scope for profiles,
contacts, MailMan subscriptions and subscription audits, recipients,
bookings/guests and related Joomla records. `system.purgeUser` should load the
target user through Joomla's user API and call the delete operation; the
plugin then performs the component-aware cleanup and logs success/failure.
There must be no direct-SQL fallback that bypasses Joomla deletion events.

## Reports and UI compatibility

Retain the current report lifecycle: pass one inserts a `state=0` report with
phase-one date, source, input file, list, user and IP; pass two finds that
pending report and updates it. Continue populating `num_records`, `num_errors`,
`num_users`, `num_subs`, `num_lapsed`, `error_report`, `new_users`,
`new_subs`, `lapsed_members`, `state` and `date_completed`.

Keep the current Process summary and Continue/Cancel workflow. Return
structured messages/counts from the helper and escape/render them in the view;
do not generate HTML in the loader.

## Subscription and lapsed-member safeguards

An unpublished/cancelled subscription must never be reinstated by an import.
Add an optional `force` parameter to the subscription operation, defaulting to
`false`. DataLoad always uses the default non-forcing path; an explicit
administrator resubscribe action may pass `true`. The force path must be
auditable and must not be exposed to untrusted front-end requests.

Retain the configured Insight-only `members_leave` behaviour, but make
blocking/purging explicit and testable. It must not run for MailChimp or simple
CSV imports.

## Implementation phases

1. Capture current report fields, source examples, expected subscription
   behaviour and representative malformed files.
2. Implement header normalisation, aliases, required-column and ambiguity
   checks, with mapping tests for all three types.
3. Implement the RA Tools placeholder-profile method and user plugin,
   including idempotent installation backfill and reconciliation reporting.
4. Refactor MailMan front-end/admin profile flows and enforce the RA Tools
   self-registration option.
5. Add the RA Members administrator-only shared-email profile operation and
   retain MailMan conflict rejection.
6. Preserve the confirmed bulk purge of blocked users omitted from the latest
   feed, but route each deletion through Joomla's User API and the central
   plugin-aware cleanup path. All other individual deletions are performed from
   the Joomla User UI; remove the direct-SQL general-purpose purge path.
7. Repurpose `reports.checkDatabase`/`reports.duffProfiles` and
   `system.duffRecords` as read-only reconciliation plus separately confirmed
   repair actions.
8. Update all operational queries to exclude unpublished/`ZZ99` profiles;
   review/fix user-deletion cleanup and reconciliation.
9. Complete the RA Tools profile prototype with optional subscriptions and
   bookings views.
10. Test and sign off phases 3–9 together. Do not begin DataLoad persistence
    migration until the user/profile lifecycle, profile flows, shared-email
    exception, purge paths and reconciliation behaviour are working.
11. Build `LoadHelper` and move two-pass orchestration and result counters out
    of `UserHelper` without changing persistence yet.
12. Replace DataLoad's direct persistence with `PersonHelper`; after user
    creation, complete the plugin-created placeholder rather than inserting a
    competing profile row.
13. Preserve subscriptions, report updates and Insight lapsed processing; add
    the non-forcing subscription path and administrator-only `force` path.
14. Update the Process view/template and remove its `UserHelper` dependency.
15. Migrate all remaining callers and retire `UserHelper`; no compatibility
   wrapper is required after the migration.
16. Run final syntax, mapping, validation, lifecycle, backfill, reconciliation,
   deletion, two-pass, report,
   subscription and database-backed regression tests.

## Acceptance criteria

- Reordering columns does not change any import result.
- Extra columns are ignored; missing/ambiguous required headings fail early.
- Quoted commas, BOMs, blank lines and comments are handled safely.
- Pass one performs no user/profile/subscription writes.
- Pass two uses `PersonHelper` for all user/profile persistence and retains the
  same report row and counters.
- Every Joomla user has an idempotently maintained profile; newly created
  users begin as unpublished `ZZ99` placeholders and are completed explicitly.
- Existing users are not duplicated and identity conflicts follow the agreed
  policy.
- Unassigned/unpublished profiles are excluded from operational group-scoped
  results.
- Reconciliation tasks are read-only by default, and purge actions are
  separately authorised, complete and auditable.
- The Members shared-email exception is administrator-only and does not weaken
  MailMan's conflict rejection.
- Subscription opt-outs and Insight lapsed handling are covered independently.
- No DataLoad business logic remains in the Process template.
- Existing import-report pages and Continue/Cancel behaviour still work.

## Remaining decisions required before coding

No remaining design decisions are recorded at this stage. The implementation
should use the confirmed configuration and lapsed-member policies above.

## Implementation status

- Phase 3 is implemented: RA Tools provides the placeholder-profile method and
  the published `plg_user_ra_profiles` user plugin creates placeholders for
  newly-created users and backfills existing users.
- Phase 4 is implemented pending system testing: the RA Tools configuration
  now controls front-end self-registration; MailMan front-end and administrator
  profile creation require the plugin-created placeholder and no longer use a
  competing direct profile-creation path for new users.
- Phase 5 is implemented pending system testing: MailMan rejects an email/name
  identity conflict, while RA Members provides an explicit Super User-only
  action for attaching an otherwise unlinked profile to an existing Joomla
  user for the intentional shared-email exception.
- Phase 7 has started: RA Tools now exposes a read-only user/profile
  reconciliation report, with optional MailMan and Events findings labelled
  for repair by their owning components. No component-owned records are
  modified by this report. The report now includes drill-down views and
  owner-routed actions; RA Tools' placeholder backfill is the only core repair
  action implemented so far.
- Phase 8 has started: live Members, MailMan subscription/recipient, and
  Events booking queries now exclude unpublished `ZZ99` placeholder profiles.
  Address-label output also excludes placeholders, while reconciliation and
  administrative profile views remain able to show them. The RA Tools cleanup
  plugin has been corrected to be a Joomla user plugin, use the Joomla 5/6
  delete event, remove profiles by their actual `id` linkage, and clean up
  component-owned bookings, recipients, subscriptions and subscription audit
  rows. Remaining operational joins should be reviewed before Phase 8 is
  signed off.
- Phase 9 is implemented: the RA Tools front-end profile view now loads only
  the signed-in user's profile, offers a safe profile-update link, and shows
  optional active MailMan subscriptions and active Events bookings when those
  components are enabled. The former copied/incompatible profile model and
  template were replaced with RA Tools namespace/data access and escaped
  output.
- Phase 12 is implemented in the current DataLoad persistence path: MailMan
  now explicitly ensures the Joomla user plugin's placeholder exists before
  completing a newly-created user's profile. `PersonHelper::saveProfileData`
  also repairs a missing row through the idempotent placeholder method before
  binding import data, so persistence cannot create a competing profile row.
- Phase 13 is implemented: subscription creation now defaults to the
  non-forcing path, so DataLoad and ordinary subscribe requests cannot
  silently reinstate cancelled or purged subscriptions. The explicit
  administrator `resubscribe` action passes `force=true`; Insight lapsed
  processing and import-report updates remain unchanged.
- Phase 14 is implemented: the administrator Process template now depends
  only on `LoadHelper`. It retains the existing validation and processing
  workflow, while `LoadHelper` provides the temporary orchestration adapter
  until the remaining UserHelper business logic is migrated in Phase 15.
- Phase 15 is implemented: the MailMan DataLoad processor is now named
  `DataLoadProcessor`, the Process template depends only on `LoadHelper`, and
  the obsolete MailMan `UserHelper` file/name has been retired. The separate
  RA Tools `UserHelper` used by its legacy administrator purge report remains
  intentionally component-owned and is not part of the MailMan DataLoad
  processor migration.
- Phase 16 verification is complete for the available local checks: modified
  PHP files pass syntax validation, no stale MailMan DataLoad `UserHelper`
  references remain, and `git diff --check` passes. Database-backed import,
  lifecycle, subscription and lapsed-member acceptance tests still require a
  configured Joomla test site.

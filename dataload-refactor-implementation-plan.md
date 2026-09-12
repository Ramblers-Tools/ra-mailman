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
| 3 — Insight Hub | `forename`, `surname`, `email`, `group_code` | `Forenames`/`First Name`; `Last Name`/`Surname`; `Email Address`/`Email`; `Group Code`/`Group` |
| 4 — MailChimp | `email`, `forename`, `surname` | `Email address`, `First Name`, `Last Name` (case-insensitive matching) |
| 5 — simple CSV | `group_code`, `name`, `email` | `Group Code`/`Group`; `Name`/`Real Name`/`Full Name`; `Email Address`/`Email` |

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
3. Build `LoadHelper` and move two-pass orchestration and result counters out
   of `UserHelper` without changing persistence yet.
4. Replace direct persistence with `PersonHelper`; add only the shared methods
   required after identity/notification semantics are agreed.
5. Preserve subscriptions, report updates and Insight lapsed processing; fix
   cancelled-subscription handling if confirmed by the decision above.
6. Update the Process view/template and remove its `UserHelper` dependency.
7. Migrate all remaining callers and retire `UserHelper`; no compatibility
   wrapper is required after the migration.
8. Run syntax, mapping, validation, two-pass, report, subscription and
   database-backed regression tests.

## Acceptance criteria

- Reordering columns does not change any import result.
- Extra columns are ignored; missing/ambiguous required headings fail early.
- Quoted commas, BOMs, blank lines and comments are handled safely.
- Pass one performs no user/profile/subscription writes.
- Pass two uses `PersonHelper` for all user/profile persistence and retains the
  same report row and counters.
- Existing users are not duplicated and identity conflicts follow the agreed
  policy.
- Subscription opt-outs and Insight lapsed handling are covered independently.
- No DataLoad business logic remains in the Process template.
- Existing import-report pages and Continue/Cancel behaviour still work.

## Remaining decisions required before coding

No remaining design decisions are recorded at this stage. The implementation
should use the confirmed configuration and lapsed-member policies above.

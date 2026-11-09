# Inline Image Attachments — Overview for Testers

**Branch:** `inline-image-attachment`
**Component:** com_ra_mailman
**Date:** 2026-07-27
**Status:** Fixed and verified on test site — ready for beta-wide testing

## What was broken

Images in mailshots were silently disappearing for recipients, in three different spots:

1. **Images typed/pasted into the mailshot body** (via the rich-text editor) — arrived as broken images, since the email address they used only worked on the site they were created on.
2. **Files attached to a mailshot** via the "attachment" field — silently dropped entirely, no image and no error.
3. **The logo** in RA Mailman → Options — this one already worked, but was mislabeled internally as a JPEG even when a PNG was uploaded, which some mail clients would refuse to display.
4. **Images placed in the global "System footer" text** (RA Mailman → Options) — stripped out the moment the Options page was saved.

## Why it was happening

RA Mailman sends email via SMTP2GO's API (`com_ra_delivery`), not the classic Joomla mailer. That API path never supported real file attachments, and it can't reach back into the website to fetch images referenced by a relative link — so any image that wasn't already fully self-contained in the email either vanished or broke.

## What the fix does

Every image involved in a mailshot — body content, uploaded attachments, and the logo — is now embedded directly into the email as a **base64-encoded image** (the image data itself travels inside the email, not as a link back to the website). This means it doesn't matter which mail-sending path is used; the image is always there. The global footer field was also changed to stop stripping images when saved.

## What to test

For each item below, send a test mailshot to yourself and check the image actually shows up in the received email:

- [ ] Insert an image directly into the mailshot body using the editor, send, confirm it displays.
- [ ] Attach a file to a mailshot via the attachment field, send, confirm it displays inline in the body.
- [ ] Confirm a PNG logo (RA Mailman → Options) displays correctly, and an existing JPEG logo still works.
- [ ] Put an image in the global "System footer" (RA Mailman → Options), save, confirm it's still there afterwards and shows up on a sent mailshot.

## Two smaller related fixes bundled in

- **Author self-unsubscribe**: an author (someone with author-level rights on a mailing list) could previously remove their own authoring rights by clicking "Un-subscribe" on their own subscriptions page — that option is no longer offered to authors; it now shows a note to contact the list owner instead.
- **Mail Lists table footer**: the admin Mail Lists list view had a visual gap in the footer row (wrong column count) — footer now spans the full table width.

## Known limitation

Base64-encoding inflates an image's size by roughly a third. This isn't expected to be noticeable for typical mailshot images, but if a mailshot ever carries several large photos, the resulting email will be noticeably bigger than before.

## Next steps

1. Testing on this branch (above checklist).
2. Merge to `beta`, cut a beta build for wider testing across other sites.
3. Once confirmed clean, merge to `main` and release.

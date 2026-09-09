# SUPERBEE PROJECTS & TASKS — pilot brief

## Where it stands

Built on Kanboard (open source, MIT licence), heavily customised for
SUPERBEE. Working, tested, and running on MySQL.

**What was verified, by testing rather than assumption:**

- Migrated from SQLite to MySQL 8 — 53 tables, every row reconciled, zero
  mismatches, 74 foreign keys enforced
- The approval workflow holds in both directions: approving a completion
  report closes the task; withdrawing that approval reopens it
- A regular user account is refused by the server on approve, reject,
  close-task, user administration and settings — HTTP 403 on all five, even
  with a valid session. Permissions are enforced server-side, not merely
  hidden in the interface
- 16 screens checked: no errors, no warnings, consistent interface
- Source code in version control, on a private GitHub repository

## The feature that matters

Assignees submit evidence of finished work — a live link or a Drive
document. An administrator or the project's manager reviews it. **A task
cannot be marked complete until that evidence is approved.** The rule is
enforced in the data layer, so it holds regardless of how someone tries to
close the task.

## Pilot plan

One week on the LAN, hosted from my laptop, before moving to a server.

**Available during working hours; down when the laptop is away.** That is a
property of hosting it on a laptop, not of the software.

What the pilot is for — things that cannot be found on one machine:

- Several people using it at once
- Whether notifications actually reach people
- Permission edges real users find and we did not anticipate
- Genuine data volume rather than sample records

## What is needed to start

| item | why |
|---|---|
| IIS web server | the development server handles one request at a time; with five people it queues and fails |
| HTTPS certificate | logins cross the LAN in clear text otherwise |
| Backup location off the laptop | first time it holds work that is not mine; a backup on the same disk is not a backup |
| One account per person | shared logins make the approval trail meaningless |

## What "it works" means — agreed before we start

The pilot passes only if all of these are true at the end of the week. Set
now so the decision rests on evidence rather than impression.

| # | criterion | why it matters |
|---|---|---|
| 1 | At least 3 people other than me created and updated real tasks | sample data proves nothing |
| 2 | At least 3 completion reports submitted by an assignee and approved by a reviewer who is not the submitter | this is the whole point of the system |
| 3 | At least one occasion with 3+ people using it at the same time | concurrency is the one thing a single machine cannot test |
| 4 | One backup restored successfully into a scratch database | an untested backup is a guess |
| 5 | Zero incidents of lost or corrupted work | non-negotiable |
| 6 | No unresolved blocking defect | cosmetic issues can travel to the server; blockers cannot |
| 7 | Notification email either working, or consciously deferred | silent failure is worse than a known gap |

If criterion 1 or 3 is not met, the pilot has not actually been tested —
extend it rather than passing it by default. Low usage is not a pass.

**Keep an issues log all week.** Date, who, what happened, what was
expected. That log is the argument for whatever comes next.

## After the pilot

A machine that stays powered on. The pilot produces the evidence for that
request — how many people used it, what broke, how often it was needed while
the laptop was away.

Before the move to a server, three things must be done regardless of how
well the pilot goes: change the database password, put the backups somewhere
independent of the host, and set up HTTPS with a certificate the browser
trusts rather than a self-signed one.

## Known limits, stated plainly

- Hosted on a laptop: unavailable when I am away
- Notification email needs SMTP configured before it sends anything
- Built on Kanboard, so the About page credits the original authors — that
  is a licence requirement and stays
- Around 34 core files are customised, which makes upstream security updates
  more work. Worth addressing before the first upgrade, not before the pilot

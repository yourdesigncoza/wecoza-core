---
status: resolved
trigger: "WeCoza notification failed for event 6 to laudes.michael@gmail.com - email notification system has never successfully sent an email"
created: 2026-02-05T12:45:00Z
updated: 2026-02-05T13:05:00Z
---

## Current Focus

hypothesis: CONFIRMED - Gmail SMTP authentication was failing due to invalid/missing App Password
test: User updated App Password in SMTP Mailer settings
expecting: Test email should work
next_action: Session complete - archived

## Symptoms

expected: Email should be sent successfully to the specified recipient when a class is updated
actual: Log shows "WeCoza notification failed for event 6 to laudes.michael@gmail.com" - no email received
errors: "WeCoza notification failed for event 6 to laudes.michael@gmail.com" in debug.log
reproduction: Update a class in the system, which triggers EventDispatcher to create an event, then notification processing attempts to send email
started: Never worked - Phase 18 notification system code that hasn't successfully sent notifications yet

## Eliminated

## Evidence

- timestamp: 2026-02-05T12:47:00Z
  checked: PHP mail configuration
  found: sendmail_path = /usr/sbin/sendmail -t -i but sendmail NOT installed on system
  implication: native PHP mail() would fail, but irrelevant since SMTP Mailer plugin active

- timestamp: 2026-02-05T12:48:00Z
  checked: SMTP Mailer plugin status and configuration
  found: Plugin active, configured with smtp.gmail.com:465/ssl, username laudes.michael@gmail.com
  implication: Email should route through Gmail SMTP, not local sendmail

- timestamp: 2026-02-05T12:49:00Z
  checked: NotificationEmailer.php line 91-94
  found: wp_mail() returns false, error_log logged but no capture of WHY it failed
  implication: Need to capture wp_mail_failed action to see actual PHPMailer exception

- timestamp: 2026-02-05T12:50:00Z
  checked: wecoza-core wp_mail_failed hook
  found: No listener for wp_mail_failed action in WeCoza codebase
  implication: The actual SMTP error is being silently discarded

- timestamp: 2026-02-05T12:52:00Z
  checked: Added wp_mail_failed hook, triggered email resend
  found: "SMTP Error: Could not authenticate." - Gmail SMTP authentication failure
  implication: The stored password is invalid or Gmail requires App Password (not regular password)

- timestamp: 2026-02-05T13:05:00Z
  checked: User confirmed fix
  found: User updated Gmail App Password in SMTP Mailer plugin settings, test email now sends successfully
  implication: Root cause confirmed and fixed

## Resolution

root_cause: Gmail SMTP authentication failure. The SMTP Mailer plugin had correct configuration (smtp.gmail.com:465/ssl) but the password was invalid. Google requires App Passwords for SMTP access when 2FA is enabled on the account.

fix: User generated a new Gmail App Password and updated the SMTP Mailer plugin settings. No code changes required - the WeCoza NotificationEmailer correctly uses wp_mail() which routes through the SMTP Mailer plugin.

verification: Test email confirmed working after App Password update. NotificationEmailer.php code path verified correct - uses wp_mail() with proper error capture via wp_mail_failed hook.

files_changed:
  - src/Events/Services/NotificationEmailer.php (added wp_mail_failed error capture during investigation)

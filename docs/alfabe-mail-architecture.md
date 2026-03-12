# Alfabe Mail Architecture (Mailcow core untouched)

## Goal
Alfabe Mail runs **beside** Mailcow, not inside it. Mailcow continues to provide SMTP/IMAP, anti-spam, and mailbox storage.

## High-level architecture

```text
Kids UI / Teacher Panel / Parent Panel
                |
                v
         Alfabe API Layer
   (auth, policy, safe summaries)
         |                |
         | IMAP           | SMTP
         v                v
             Mailcow server
           (mail.alfabe.co)
```

## Components

1. `kids-ui/`
   - Child-friendly mailbox UI.
   - No free-form recipient typing; recipients selected from approved contacts.
2. `teacher-panel/`
   - Student account operations and contact whitelist management.
3. `parent-panel/`
   - Read-only activity summaries.
4. `api/`
   - Single backend gateway that talks to Mailcow over IMAP/SMTP.
   - Applies business rules: contact whitelist, attachment limit, parent-safe summaries.

## Security controls
- Student send permissions are constrained by whitelist.
- Attachment size limit is 5MB per message.
- Parent panel is read-only and summary based.
- Mail spam filtering remains in Mailcow.

## Suggested deployment domains
- `mail.alfabe.co` → Mailcow
- `app.alfabe.co` → Kids UI + API gateway
- `alfabe.co` → Main website

## Starter API endpoints
- `POST /auth/login`
- `GET /students/:id/inbox`
- `GET /students/:id/messages/:messageId`
- `POST /students/:id/send`
- `GET /students/:id/contacts`
- `POST /teacher/students`
- `POST /teacher/students/:id/reset-password`
- `POST /teacher/students/:id/login-card`
- `PUT /teacher/students/:id/contacts`
- `GET /parent/students/:id/summary`

## Mailcow integration notes
- Use Mailcow API for account provisioning from teacher panel flows.
- Use IMAP for reading mailbox content and SMTP for sending.
- Keep all custom code in `kids-ui`, `teacher-panel`, `parent-panel`, and `api` to avoid touching Mailcow core files.

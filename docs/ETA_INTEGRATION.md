# ETA Integration Guide

This page explains how to link an external ETA system to ICMS backend, submit entry/exit movements, and run expiry monitoring.

## Overview

The ETA flow in ICMS backend supports:

- Raw ETA import from webhook (external ETA system -> ICMS)
- Direct movement capture (ENTRY/EXIT) inside ICMS
- Hourly monitoring in Nairobi timezone (EAT)
- Auto case creation for matching expiry windows
- Suppression when latest movement for passport is EXIT

## Endpoints

Base prefix:

- /wp-json/icms-back/v1

Routes:

- POST /eta/webhook
- POST /eta/movements
- POST /eta/monitor/run

## Security Setup

Two secrets are used:

- ETA webhook secret: for external ETA pushes
- Internal token: for trusted system-to-system calls (movement/monitor)

You can provide these as WordPress options or environment variables.

WordPress options:

- icms_eta_webhook_secret
- icms_eta_internal_token

Environment fallback:

- ICMS_ETA_WEBHOOK_SECRET
- ICMS_ETA_INTERNAL_TOKEN

### Example: set WordPress options (WP-CLI)

```bash
wp option update icms_eta_webhook_secret "replace-with-strong-secret"
wp option update icms_eta_internal_token "replace-with-strong-internal-token"
```

## 1) Link ETA Webhook

Endpoint:

- POST /wp-json/icms-back/v1/eta/webhook

Headers:

- Content-Type: application/json
- x-icms-eta-secret: YOUR_WEBHOOK_SECRET

Minimum required fields:

- passport
- expiry

Accepted aliases are flexible, for example:

- passportNumber, passport_number, passport
- expiryDate, expiry_date, expiresAt, expires_at
- entryDate, issuedAt, etaRefNumber, reasonForTravel, nationality

### Example webhook payload

```json
{
  "passportNumber": "A12345678",
  "nationality": "UG",
  "reasonForTravel": "Business",
  "entryDate": "2026-06-01T09:15:00+03:00",
  "expiryDate": "2026-07-01T23:59:59+03:00",
  "issuedAt": "2026-05-28T08:00:00+03:00",
  "etaRefNumber": "ETA-KE-2026-000901"
}
```

### Example curl for webhook

```bash
curl -X POST "https://your-domain/wp-json/icms-back/v1/eta/webhook" \
  -H "Content-Type: application/json" \
  -H "x-icms-eta-secret: replace-with-strong-secret" \
  -d '{
    "passportNumber":"A12345678",
    "nationality":"UG",
    "reasonForTravel":"Business",
    "entryDate":"2026-06-01T09:15:00+03:00",
    "expiryDate":"2026-07-01T23:59:59+03:00",
    "issuedAt":"2026-05-28T08:00:00+03:00",
    "etaRefNumber":"ETA-KE-2026-000901"
  }'
```

## 2) Capture Entry/Exit Directly in ICMS

Endpoint:

- POST /wp-json/icms-back/v1/eta/movements

Auth:

- Logged-in user with admin/case capability, OR
- Authorization: Bearer YOUR_INTERNAL_TOKEN, OR
- x-icms-internal-token: YOUR_INTERNAL_TOKEN

Required fields:

- passportNumber
- movementType (ENTRY or EXIT)
- movementAt
- borderPointCode
- officerId

### Example movement payload (ENTRY)

```json
{
  "passportNumber": "A12345678",
  "movementType": "ENTRY",
  "movementAt": "2026-06-01T09:30:00+03:00",
  "borderPointCode": "NBO-JKIA",
  "officerId": 1042
}
```

### Example movement payload (EXIT)

```json
{
  "passportNumber": "A12345678",
  "movementType": "EXIT",
  "movementAt": "2026-06-20T17:10:00+03:00",
  "borderPointCode": "NBO-JKIA",
  "officerId": 1042
}
```

### Example curl for movements

```bash
curl -X POST "https://your-domain/wp-json/icms-back/v1/eta/movements" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer replace-with-strong-internal-token" \
  -d '{
    "passportNumber":"A12345678",
    "movementType":"ENTRY",
    "movementAt":"2026-06-01T09:30:00+03:00",
    "borderPointCode":"NBO-JKIA",
    "officerId":1042
  }'
```

## 3) Run Expiry Monitor

The monitor also runs hourly by WP-Cron. You can trigger it manually for testing.

Endpoint:

- POST /wp-json/icms-back/v1/eta/monitor/run

Auth:

- same as movement endpoint

Behavior:

- Timezone: Africa/Nairobi (EAT)
- Windows: 30 days, 7 days, 1 day
- Overdue ETA is also handled
- If latest movement is EXIT, alert is suppressed
- Each alert creates a case in icms_cases
- Duplicate alerts are prevented per eta_record_id + window

### Example curl for monitor run

```bash
curl -X POST "https://your-domain/wp-json/icms-back/v1/eta/monitor/run" \
  -H "Authorization: Bearer replace-with-strong-internal-token"
```

### Example response

```json
{
  "data": {
    "ok": true,
    "inspected": 25,
    "suppressedByExit": 4,
    "casesCreated": 3,
    "alerts": [
      {
        "etaRecordId": 41,
        "passportNumber": "A12345678",
        "windowDays": 7,
        "daysToExpiry": 7,
        "caseId": "ICMS-91AB72D54E0A"
      }
    ],
    "timezone": "Africa/Nairobi"
  }
}
```

## Example Test Dataset

Use this sequence to validate end-to-end behavior.

1. Push ETA #1 (should be monitored)
2. Push ETA #2 (will be suppressed after EXIT)
3. Add movement ENTRY for both
4. Add movement EXIT for ETA #2 passport
5. Run monitor and verify only ETA #1 creates case

### ETA #1

```json
{
  "passportNumber": "B20001111",
  "nationality": "TZ",
  "reasonForTravel": "Tourism",
  "entryDate": "2026-06-01T10:00:00+03:00",
  "expiryDate": "2026-06-08T23:59:59+03:00",
  "issuedAt": "2026-05-29T09:00:00+03:00",
  "etaRefNumber": "ETA-KE-2026-100001"
}
```

### ETA #2

```json
{
  "passportNumber": "B20002222",
  "nationality": "RW",
  "reasonForTravel": "Conference",
  "entryDate": "2026-06-01T11:00:00+03:00",
  "expiryDate": "2026-06-08T23:59:59+03:00",
  "issuedAt": "2026-05-29T09:30:00+03:00",
  "etaRefNumber": "ETA-KE-2026-100002"
}
```

### Movements

```json
{
  "passportNumber": "B20001111",
  "movementType": "ENTRY",
  "movementAt": "2026-06-01T10:10:00+03:00",
  "borderPointCode": "NBO-JKIA",
  "officerId": 1042
}
```

```json
{
  "passportNumber": "B20002222",
  "movementType": "ENTRY",
  "movementAt": "2026-06-01T11:10:00+03:00",
  "borderPointCode": "NBO-JKIA",
  "officerId": 1042
}
```

```json
{
  "passportNumber": "B20002222",
  "movementType": "EXIT",
  "movementAt": "2026-06-02T16:45:00+03:00",
  "borderPointCode": "NBO-JKIA",
  "officerId": 1042
}
```

## Troubleshooting

- 403 on webhook: confirm x-icms-eta-secret and configured secret match.
- 403 on movement/monitor: confirm internal token or user capability.
- 400 invalid payload: check required fields and date format.
- No cases created: check days to expiry window and EXIT suppression.

## Postman Setup

You can create a small Postman collection with one environment and three requests.

### Recommended environment variables

- baseUrl = `https://your-domain.example`
- webhookSecret = replace-with-strong-secret
- internalToken = replace-with-strong-internal-token

### Request 1: ETA Webhook Import

- Method: POST
- URL: {{baseUrl}}/wp-json/icms-back/v1/eta/webhook
- Headers:
- Content-Type: application/json
- x-icms-eta-secret: {{webhookSecret}}

Body:

```json
{
  "passportNumber": "A12345678",
  "nationality": "UG",
  "reasonForTravel": "Business",
  "entryDate": "2026-06-01T09:15:00+03:00",
  "expiryDate": "2026-07-01T23:59:59+03:00",
  "issuedAt": "2026-05-28T08:00:00+03:00",
  "etaRefNumber": "ETA-KE-2026-000901"
}
```

### Request 2: Record Movement

- Method: POST
- URL: {{baseUrl}}/wp-json/icms-back/v1/eta/movements
- Headers:
- Content-Type: application/json
- Authorization: Bearer {{internalToken}}

Body:

```json
{
  "passportNumber": "A12345678",
  "movementType": "ENTRY",
  "movementAt": "2026-06-01T09:30:00+03:00",
  "borderPointCode": "NBO-JKIA",
  "officerId": 1042
}
```

### Request 3: Run Expiry Monitor

- Method: POST
- URL: {{baseUrl}}/wp-json/icms-back/v1/eta/monitor/run
- Headers:
- Authorization: Bearer {{internalToken}}

### Expected test order

1. Run ETA Webhook Import.
2. Run Record Movement.
3. Run Expiry Monitor.
4. Verify response contains created case IDs when a window matches.

## Notes

- Current parser is intentionally flexible because external ETA schema is not finalized.
- Once ETA provider contract is fixed, lock field names and add strict signature validation.

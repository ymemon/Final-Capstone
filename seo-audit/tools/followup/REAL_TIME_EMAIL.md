# Real-Time Email Portal Setup

This document explains how to set up real-time email capture for the AZ Web Corp email portal. Instead of checking for emails every 5 minutes (polling), the system now receives email notifications immediately via webhooks.

## Overview

The email portal has been upgraded with real-time webhook support:
- **Before**: Checked email inbox every 5 minutes via WordPress cron
- **After**: Emails are captured immediately as they arrive and processed in real-time

Benefits:
- Instant email capture from clients
- Immediate notification to team (info@azwebcorp.com)
- No delay waiting for 5-minute cron cycle
- Support for multiple email services (Gmail, SendGrid, Mailgun, etc.)

## Setup Instructions

### 1. Generate Webhook Token

First, create a secure webhook token. Add this to your `wp-config.php`:

```php
define( 'AZWC_FU_WEBHOOK_TOKEN', 'your-long-random-token-here' );
```

Generate a strong token using one of these methods:

**Option A - PHP:**
```php
echo bin2hex( random_bytes( 32 ) );
```

**Option B - Command line:**
```bash
openssl rand -hex 32
```

**Option C - Online (not recommended for production):**
Visit https://www.uuidgenerator.net/ or similar service

### 2. Configure Email Service

Choose your email service and follow the corresponding setup:

#### Option A: Gmail + Google Cloud (Recommended)

1. **Set up Gmail API:**
   - Go to [Google Cloud Console](https://console.cloud.google.com/)
   - Create a new project or select existing one
   - Enable "Gmail API"
   - Create a Service Account
   - Download the JSON key file

2. **Set up Push Notifications:**
   - Configure Gmail API to push notifications to a Topic in Google Cloud Pub/Sub
   - Topic name: `projects/YOUR_PROJECT/topics/gmail-notifications`

3. **Forward to WordPress:**
   - Set up Cloud Function to forward notifications to your webhook:
   ```
   https://azwebcorp.com/wp-json/azwc/v1/webhook/email
   Authorization: Bearer YOUR_WEBHOOK_TOKEN
   ```

#### Option B: SendGrid (Easier setup)

1. **Enable Inbound Parse:**
   - Log in to [SendGrid Dashboard](https://app.sendgrid.com/)
   - Go to Settings → Inbound Parse
   - Add hostname: `parse.sendgrid.net`

2. **Create Inbound Parse Webhook:**
   - Pointing to: `https://azwebcorp.com/wp-json/azwc/v1/webhook/email`
   - Add custom header: `Authorization: Bearer YOUR_WEBHOOK_TOKEN`
   - Test your settings

3. **Update MX Records:**
   - Add MX record for `parse.sendgrid.net` (SendGrid provides the exact value)
   - This allows SendGrid to intercept emails for parsing

Example MX records:
```
10 parse.sendgrid.net
20 mx.google.com
```

#### Option C: Mailgun

1. **Configure Webhooks:**
   - Log in to [Mailgun Dashboard](https://app.mailgun.com/)
   - Go to Domains → Your Domain → Webhooks
   - Set up webhook for "Accepted" events
   - URL: `https://azwebcorp.com/wp-json/azwc/v1/webhook/email`
   - Add Authorization header with Bearer token

2. **Test the connection:**
   ```bash
   curl -X POST https://azwebcorp.com/wp-json/azwc/v1/webhook/email \
     -H "Authorization: Bearer YOUR_WEBHOOK_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"from":"test@example.com","subject":"Test"}'
   ```

#### Option D: Generic Webhook (Custom Email Service)

For any email service supporting webhooks, POST JSON data to:

```
https://azwebcorp.com/wp-json/azwc/v1/webhook/email
```

**Required fields in JSON:**
```json
{
  "from": "client@example.com",
  "to": "info@azwebcorp.com",
  "subject": "Email subject line",
  "body": "Plain text body",
  "html": "HTML body (optional)"
}
```

**With authentication header:**
```
Authorization: Bearer YOUR_WEBHOOK_TOKEN
```

## Database Schema

Incoming emails are stored in the `wp_azwc_leads` table with:
- `kind`: 'email' (new)
- `status`: 'new' (can be updated manually)
- `created_gmt`: Timestamp when email arrived
- `notes`: Email subject line (for reference)
- `email`: Sender's email address
- `name`: Extracted from email address or sender name

View all received emails in WordPress admin:
- Dashboard → SEO Leads → "Emails received" tab

## REST API Endpoints

### Get Available Slots (unchanged)
```
GET /wp-json/azwc/v1/slots
```

### Request Report (unchanged)
```
POST /wp-json/azwc/v1/report
```

### Book Call (unchanged)
```
POST /wp-json/azwc/v1/booking
```

### NEW: Receive Email Webhook
```
POST /wp-json/azwc/v1/webhook/email
Authorization: Bearer AZWC_FU_WEBHOOK_TOKEN
Content-Type: application/json

{
  "from": "client@example.com",
  "to": "info@azwebcorp.com",
  "subject": "Question about SEO services",
  "body": "I'd like to know more...",
  "html": "<p>I'd like to know more...</p>",
  "domain": "client-domain.com" (optional)
}
```

**Response:**
```json
{
  "ok": true,
  "message": "Email processed in real-time.",
  "id": 12345
}
```

## Real-Time Processing Flow

1. **Email arrives** at info@azwebcorp.com
2. **Email service** (Gmail/SendGrid/Mailgun) captures it
3. **Service POSTs webhook** to `/wp-json/azwc/v1/webhook/email` with Bearer token
4. **WordPress verifies** token and processes email immediately (no cron delay)
5. **Email is logged** to `wp_azwc_leads` table as `kind='email'`
6. **Team is notified** in real-time with email subject and sender info
7. **Admin dashboard** shows new email in "Emails received" tab

## Monitoring

### Check webhook health in WordPress CLI:
```bash
wp azwc-leads health
```

Output includes:
- Table status
- Row count (including real-time emails)
- Next scheduled cron tick
- Open calendar days/slots

### View recent emails:
```bash
wp azwc-leads list
# Filter by email:
wp db query "SELECT * FROM wp_azwc_leads WHERE kind='email' ORDER BY created_gmt DESC LIMIT 10;"
```

## Troubleshooting

### Webhook not being called?

1. **Verify token is set:**
   ```php
   // In WordPress CLI or functions.php
   echo defined( 'AZWC_FU_WEBHOOK_TOKEN' ) ? 'Token is set' : 'Token NOT set';
   ```

2. **Test manually:**
   ```bash
   curl -X POST https://azwebcorp.com/wp-json/azwc/v1/webhook/email \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"from":"test@example.com","subject":"Test"}'
   ```

3. **Check email service configuration:**
   - Verify webhook URL is correct
   - Check Authorization header is being sent
   - Test webhook in your email service dashboard

### Email not appearing in admin?

1. **Check database:**
   ```bash
   wp db query "SELECT COUNT(*) FROM wp_azwc_leads WHERE kind='email';"
   ```

2. **Check error logs:**
   - WordPress debug log: `wp-content/debug.log`
   - Email service logs (SendGrid, Gmail, Mailgun dashboards)

3. **Verify permissions:**
   - WordPress user can create posts/pages (`edit_posts`)
   - Database user has INSERT privileges

### Reminders not being sent?

The system still uses WordPress cron for reminders. To test:

```bash
# Trigger cron manually
wp cron test
# Or via your server's actual cron:
*/5 * * * * wget -q -O - https://azwebcorp.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

## Email Service Comparison

| Service | Setup Difficulty | Cost | Speed | Notes |
|---------|-----------------|------|-------|-------|
| Gmail + Google Cloud | Medium | Free tier | Real-time | Most reliable, requires GCP setup |
| SendGrid | Easy | $20-40/mo | Real-time | Best for non-Gmail |
| Mailgun | Easy | Free tier | Real-time | Great alternative |
| Generic Webhook | Hard | Varies | Real-time | For custom email providers |

## Security Notes

1. **Token Security:**
   - Store `AZWC_FU_WEBHOOK_TOKEN` in `wp-config.php`, not in version control
   - Use a strong 64-character random token (32 bytes hex encoded)
   - Rotate token every 6 months in production

2. **HTTPS Only:**
   - Webhooks are rejected if using HTTP (must be HTTPS)
   - Certificate must be valid (not self-signed in production)

3. **Email Validation:**
   - All incoming email addresses are validated before storage
   - HTML content is sanitized (`wp_kses_post`)
   - IP address is logged for spam detection

## Fallback to Cron

If webhooks fail, the system falls back to the original 5-minute cron polling:

- Cron still runs `azwc_fu_tick()` every 5 minutes
- Catches any missed emails from webhook failures
- Sends reminders and cleans up expired bookings

You don't need to do anything — the fallback is automatic.

## Extending the System

Hook into real-time email processing with custom actions:

```php
// Listen for incoming emails
add_action( 'azwc_fu_email_received', function( $email_data, $lead_id ) {
    // $email_data contains: from, to, subject, body, html, source
    // $lead_id is the database ID
    
    // Example: Send to CRM
    send_to_custom_crm( $email_data );
}, 10, 2 );
```

## Performance Impact

- **Webhook requests:** ~100ms per email (same as page load)
- **Database:** One INSERT per email (indexed)
- **Memory:** Minimal increase
- **Cron:** Still runs every 5 minutes for reminders (unchanged)

Total impact: **Negligible** - webhooks are more efficient than cron polling.

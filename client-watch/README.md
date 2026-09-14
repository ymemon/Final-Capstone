# Client-mail watcher

Watches the AZ Web Corp mailbox for known-client requests, investigates them,
prepares a fix, and surfaces a briefing the moment a Claude Code session opens.

**It never deploys anything.** Investigation and staging only; deployment
always needs Yasir's explicit approval in a live session.

**One narrow exception, added 2026-09-05:** the real-time IDLE watcher
(`idle_watch.py`) sends a short, templated "we got your message" auto-ack the
instant a genuine new request arrives from a known client - see
`auto_ack.py`'s module docstring for exactly what counts as "genuine new" and
why. It never answers the actual request, states a fix, or promises a
timeline - that judgment is still staged for Yasir, unchanged. This is the
only email either watcher is allowed to send; everything else in this file's
"never sends any email" language still applies.

## Why one mailbox covers everything

Verified 2026-08-26 by scanning recipient headers: **ten @azwebcorp.com
addresses all deliver into the single `info@` mailbox** —
`info@` (180 msgs sampled), `yasir@` (97), `ceo@` (39), `usc18@` (25),
`khemia@` (23), `jay@` (21), `careers@` (16), `requests@` (15), `rti@` (11),
`joee@` (8). One IMAP connection, one credential, no per-alias setup.

## Pieces

| File | Role |
|---|---|
| `clients.json` | Client allowlist + ignore rules. **Edit this** as contacts appear. |
| `prompt.txt` | What the watcher does each run. Contains the safety rules. |
| `run_client_watch.bat` | Scheduled-task entry point. |
| `emit_briefing.py` | SessionStart hook — injects pending items into the session. |
| `briefing.md` | Current output. Overwritten each run. |
| `state.json` | Last processed IMAP UID (created on first run). |
| `staged/` | Prepared-but-undeployed fixes (created as needed). |
| `watch_log.txt` | Append-only run log. |
| `idle_watch.py` | Real-time IMAP IDLE watcher. Also decides + fires the instant client auto-ack. |
| `auto_ack.py` | The one exception to "never sends email" - see its docstring. |
| `ack_state.json` | Message-IDs already acked + today's send count (daily cap 15). |
| `ack-log.md` | Append-only audit log of every auto-ack sent. |

## The safety model, and why it is drawn here

Three reasons the watcher prepares rather than deploys:

1. **Email is spoofable.** Anyone can send mail claiming to be a client. The
   allowlist reduces noise; it is **not authentication** and must never be
   treated as such.
2. **Prompt injection.** Email bodies are untrusted input. An email crafted to
   read as instructions to an AI agent ("ignore your instructions", "the
   operator approved this") is a real attack against an agent that acts
   autonomously. The watcher treats all email content as data, and flags
   anything that looks like an injection attempt at the top of the briefing.
3. **Scope drift.** "Fix the copy on our IT pages" has many readings. The
   wrong one, applied to a live client site, is a client-facing incident.

Little speed is lost: diagnosis and building the fix is the slow part, not the
thirty seconds of deploying.

What the watcher MAY do autonomously: read the mailbox (read-only, never
marking read or deleting), fetch public client URLs, query Search Console,
and run **SELECT-only** WP-CLI over SSH. What it may never do: any write to a
production site, or any database mutation. The one exception to "never send
email" is the instant auto-ack described above - nothing else goes out
without Yasir.

## Setup status

- [x] Client allowlist seeded
- [x] Watcher prompt written
- [x] SessionStart hook registered in `~/.claude/settings.json` and unit-tested
      (silent when nothing is pending, valid JSON when there is, no crash if
      the file is missing)
- [ ] **Scheduled task not yet registered** — needs Yasir, see below

## Registering the schedule (needs Yasir)

Claude Code's safety classifier blocks agent-initiated creation of unattended
bypass-permission tasks, so this has to be run by hand. Every 15 minutes:

```powershell
$action  = New-ScheduledTaskAction -Execute "C:\Users\yasir\Documents\Final-Capstone\client-watch\run_client_watch.bat"
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 15) -RepetitionDuration ([TimeSpan]::MaxValue)
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -DontStopOnIdleEnd -ExecutionTimeLimit (New-TimeSpan -Minutes 30)
Register-ScheduledTask -TaskName "AZWebCorp Client Watch" -Action $action -Trigger $trigger -Settings $settings -Force
```

Then trigger one run manually and read `watch_log.txt` before trusting it
unattended:

```powershell
Start-ScheduledTask -TaskName "AZWebCorp Client Watch"
```

## Known gaps

- **Two client contacts are unknown**: PaloVerde (Michael Bustard) and
  Prestige. Until their addresses are in `clients.json`, their mail lands in
  the briefing's "Unrecognised senders" list rather than being investigated.
- Runs only while this machine is on — same constraint as the ticket sync.
- Overlaps the existing `ticket-sync` job, which keeps doing ticket numbering
  and 48h nudges. Worth merging them later; kept separate for now so a change
  to one cannot break the other.

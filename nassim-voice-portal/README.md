# 🎤 Nassim Voice Email Portal

A voice-controlled email management system for Nassim. Control your emails with natural voice commands.

## Features (Demo Version)

✅ **Voice Input** — Speak commands naturally
✅ **Text-to-Speech** — Hear email responses aloud
✅ **Dual Email Accounts** — Switch between two email addresses
✅ **Mock Email Data** — Test all features without real Gmail
✅ **Command Examples:**
   - "Read emails from Phil"
   - "Show unread emails"
   - "Reply saying thanks"
   - "Forward to Lisa"
   - "Mark as important"
   - "Delete that"
   - "Search for project"

## Quick Start (Testing)

### 1. Install Dependencies
```bash
cd C:\Users\yasir\Documents\Final-Capstone\nassim-voice-portal
pip install -r requirements.txt
```

### 2. Run the Server
```bash
python server.py
```

Expected output:
```
============================================================
🎤 Nassim Voice Email Portal - DEMO MODE
============================================================

✓ Running with mock email data
✓ Ready for real Gmail API integration

📍 Access at: http://localhost:5000
============================================================
```

### 3. Open in Browser
```
http://localhost:5000
```

### 4. Test Voice Commands

1. **Click the 🎤 microphone button**
2. **Speak a command** — Examples:
   - "Read email from Phil"
   - "Show unread emails"
   - "Reply saying thank you"
3. **Listen to the response** — Portal reads back to you

## How It Works

### Frontend (index.html + app.js)
- Web Speech API for voice input (browser native)
- Text-to-Speech for audio responses
- Chat interface for visual feedback
- Mock email data for testing

### Backend (server.py)
- Flask server serves the web app
- REST API endpoints for email operations
- CORS enabled for cross-origin requests
- Ready for Gmail API integration

## Architecture

```
Voice Input (Web Speech API)
        ↓
Transcript Processing (app.js)
        ↓
Command Parsing (Natural language)
        ↓
Email Operations (Flask API)
        ↓
Response Generation + Text-to-Speech
```

## Commands Reference

| Command | Example | Action |
|---------|---------|--------|
| Read Email | "Read email from Phil" | Reads specific email aloud |
| List Unread | "Show unread emails" | Lists all unread messages |
| Reply | "Reply saying thanks" | Sends a reply |
| Forward | "Forward to Lisa" | Forwards to recipient |
| Mark | "Mark as important" | Flags email |
| Delete | "Delete that" | Moves to trash |
| Search | "Search for budget" | Finds matching emails |

## Files

- **index.html** — Portal UI (voice button, chat, email accounts)
- **app.js** — Frontend logic (voice recognition, command handling)
- **server.py** — Backend API server
- **requirements.txt** — Python dependencies

## Next Steps: Real Gmail Integration

When ready to go live:

### 1. Add Gmail OAuth
```python
# In server.py, replace MOCK_EMAILS with Gmail API calls
from google.oauth2.service_account import Credentials
from googleapiclient.discovery import build
```

### 2. Set Up Nassim's Credentials
Provide:
- nassim@regalhomerenovationsllc.com (Gmail OAuth)
- nassim@prestigehomestudioaz.com (Gmail OAuth)

### 3. Connect Anthropic Claude API
```python
from anthropic import Anthropic
client = Anthropic()
# Use for advanced command understanding
```

### 4. Deploy to Production
```bash
# On azwebcorp.com server
gunicorn server.py
```

## Testing Checklist

- [ ] Microphone access granted
- [ ] Voice input captures correctly
- [ ] Commands parse and respond
- [ ] Text-to-speech works
- [ ] Email account switching works
- [ ] Mock emails display correctly
- [ ] All 6 command types work
- [ ] Chat history maintains context

## Troubleshooting

**Microphone not working:**
- Check browser permissions (allow microphone access)
- Try a different browser (Chrome/Edge have best support)
- Test at: https://www.google.com/intl/en/chrome/demos/speech.html

**Voice not recognized:**
- Speak clearly and slowly
- Use supported commands
- Check browser console for errors

**Text-to-speech not working:**
- Check browser speakers/volume
- Different browsers have different TTS support

## Environment Variables (Future)

Create `.env` file:
```
GMAIL_CLIENT_ID=xxx.apps.googleusercontent.com
GMAIL_CLIENT_SECRET=xxx
CLAUDE_API_KEY=sk-xxx
NASSIM_EMAIL_1=nassim@regalhomerenovationsllc.com
NASSIM_EMAIL_2=nassim@prestigehomestudioaz.com
```

## Support

For issues or feature requests, contact: yasir@azwebcorp.com

---

**Status:** ✅ Demo Ready | ⏳ Gmail Integration Pending | 🔜 Production Deploy

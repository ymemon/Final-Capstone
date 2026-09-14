#!/usr/bin/env python3
import json
import os
from http.server import HTTPServer, SimpleHTTPRequestHandler
from urllib.parse import urlparse, parse_qs

# Claude setup
try:
    from anthropic import Anthropic
    with open(os.path.expanduser('~/.claude-tools/nassim-claude-api.json')) as f:
        api_key = json.load(f)['api_key']
    claude = Anthropic(api_key=api_key)
except:
    claude = None

# Email database
emails = {
    'nassim@regalhomerenovationsllc.com': [
        {'from': 'Phil', 'subject': 'Website Progress Update', 'body': 'Hi Nassim, the site looks great. We have added the new gallery and the contact form is working perfectly. Ready for launch next week.'},
        {'from': 'Sarah', 'subject': 'Budget Approval', 'body': 'The Q4 budget has been approved. You can now proceed with the marketing campaign launch.'},
    ]
}

class Handler(SimpleHTTPRequestHandler):
    def do_GET(self):
        url = urlparse(self.path)

        if url.path == '/api/process-command':
            params = parse_qs(url.query)
            transcript = params.get('transcript', [''])[0]
            result = self.handle_command(transcript)
            self.json_resp(result)
        else:
            super().do_GET()

    def handle_command(self, transcript):
        """Parse command and return action"""
        if not transcript:
            return {'action': 'error', 'message': 'No command'}

        lower = transcript.lower()

        # Use Claude if available
        if claude:
            try:
                msg = claude.messages.create(
                    model="claude-opus-5",
                    max_tokens=80,
                    messages=[{"role": "user", "content": f'''Command: "{transcript}"
Parse as JSON: {{"action": "show_unread|read_emails|reply|forward|delete", "reply_text": null}}
If user wants to read/listen to emails, use "read_emails".
If user wants reply, include reply_text.'''
                    }]
                )
                try:
                    data = json.loads(msg.content[0].text.strip())
                    return data
                except:
                    pass
            except:
                pass

        # Smart fallback patterns
        if any(w in lower for w in ['read', 'listen', 'tell me', 'what', 'them', 'those']):
            return {'action': 'read_emails'}
        elif any(w in lower for w in ['unread', 'show', 'how many', 'list']):
            return {'action': 'show_unread'}
        elif any(w in lower for w in ['reply', 'respond', 'answer']):
            return {'action': 'reply', 'reply_text': 'thank you'}
        elif 'forward' in lower:
            return {'action': 'forward'}
        elif 'delete' in lower:
            return {'action': 'delete'}
        else:
            return {'action': 'read_emails'}  # Default to reading emails

    def json_resp(self, data):
        self.send_response(200)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.end_headers()
        self.wfile.write(json.dumps(data).encode())


if __name__ == '__main__':
    print('\n🎤 Nassim Voice Portal Ready\n📍 http://localhost:5000\n')
    HTTPServer(('0.0.0.0', 5000), Handler).serve_forever()

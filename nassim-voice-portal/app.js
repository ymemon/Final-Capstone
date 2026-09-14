// Mock email data for testing
const mockEmails = {
    'nassim@regalhomerenovationsllc.com': [
        { id: 1, from: 'Phil', email: 'phil@azwebcorp.com', subject: 'Website Progress Update', body: 'Hi Nassim, wanted to let you know the site is looking great. We\'ve added the new gallery and the contact form is fully functional. Ready for launch next week.', date: '2026-09-07', read: false },
        { id: 2, from: 'Sarah', email: 'sarah@client.com', subject: 'Budget Approval', body: 'The budget for Q4 has been approved. You can now proceed with the marketing campaign launch.', date: '2026-09-06', read: false },
        { id: 3, from: 'Mike', email: 'mike@contractor.com', subject: 'Project Timeline', body: 'The renovation project is on schedule. We\'ll have the drywall done by Friday and painting next week.', date: '2026-09-05', read: true },
        { id: 4, from: 'Lisa', email: 'lisa@partner.com', subject: 'Partnership Proposal', body: 'Interested in discussing a potential partnership. Let me know your availability for a call this week.', date: '2026-09-04', read: true },
    ],
    'nassim@prestigehomestudioaz.com': [
        { id: 5, from: 'David', email: 'david@photo.com', subject: 'Portfolio Review', body: 'Reviewed your latest work. The styling is exceptional. Would love to collaborate on the spring collection.', date: '2026-09-07', read: false },
        { id: 6, from: 'Emma', email: 'emma@studio.com', subject: 'Studio Booking', body: 'The studio is booked for September 15th. Please confirm the equipment you\'ll need for the shoot.', date: '2026-09-06', read: false },
    ]
};

class VoiceEmailPortal {
    constructor() {
        this.currentEmail = 'nassim@regalhomerenovationsllc.com';
        this.listening = false;
        this.chatHistory = [];
        this.emails = JSON.parse(JSON.stringify(mockEmails)); // Deep copy

        console.log('VoiceEmailPortal initializing...');

        // Initialize speech recognition
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        if (!SpeechRecognition) {
            console.error('Speech Recognition API not supported in this browser');
            this.addMessage('system', '❌ Speech Recognition not supported in your browser. Try Chrome, Edge, or Safari.');
            this.showBrowserWarning();
            return;
        }

        this.recognition = new SpeechRecognition();
        this.recognition.continuous = false;
        this.recognition.interimResults = true;
        this.recognition.lang = 'en-US';

        console.log('Speech Recognition object created:', this.recognition);

        this.setupEventListeners();
        this.setupSpeechRecognition();
        this.addMessage('system', 'Voice Email Assistant ready. Click the microphone to start.');
    }

    setupEventListeners() {
        document.getElementById('micButton').addEventListener('click', () => this.toggleListening());

        document.querySelectorAll('.email-account').forEach(account => {
            account.addEventListener('click', (e) => {
                document.querySelectorAll('.email-account').forEach(a => a.classList.remove('active'));
                e.currentTarget.classList.add('active');
                this.currentEmail = e.currentTarget.dataset.email;
                this.addMessage('system', `Switched to ${e.currentTarget.querySelector('strong').textContent}`);
            });
        });
    }

    setupSpeechRecognition() {
        this.recognition.onstart = () => {
            this.listening = true;
            document.getElementById('micButton').classList.add('listening');
            document.getElementById('voiceStatus').textContent = '🔴 Listening...';
            console.log('Speech recognition started');
        };

        this.recognition.onresult = (event) => {
            console.log('Recognition result received:', event.results);
            let transcript = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                transcript += event.results[i][0].transcript;
            }
            console.log('Transcript:', transcript);
            document.getElementById('transcript').textContent = transcript;

            if (event.results[event.results.length - 1].isFinal) {
                console.log('Final transcript:', transcript);
                this.handleCommand(transcript);
            }
        };

        this.recognition.onend = () => {
            this.listening = false;
            document.getElementById('micButton').classList.remove('listening');
            document.getElementById('voiceStatus').textContent = 'Click mic to start speaking';
            console.log('Speech recognition ended');
        };

        this.recognition.onerror = (event) => {
            console.error('Speech recognition error:', event.error);
            this.addMessage('system', `Error: ${event.error}`);
        };
    }

    toggleListening() {
        if (this.listening) {
            this.recognition.stop();
        } else {
            document.getElementById('transcript').textContent = '';
            this.recognition.start();
        }
    }

    showBrowserWarning() {
        const chatArea = document.getElementById('chatArea');
        chatArea.innerHTML += '<div style="padding: 20px; background: #ffebee; border-radius: 8px; color: #c62828;">⚠️ Your browser does not support Web Speech API. Please use:<ul style="margin-top: 10px;"><li>Chrome/Chromium (best support)</li><li>Edge</li><li>Safari</li></ul></div>';
    }

    async handleCommand(transcript) {
        transcript = transcript.trim();
        console.log('Handling command:', transcript);

        if (!transcript) {
            console.log('Empty transcript, skipping');
            return;
        }

        this.addMessage('user', transcript);

        // Send to server for Claude processing
        try {
            const url = `/api/process-command?transcript=${encodeURIComponent(transcript)}&account=${encodeURIComponent(this.currentEmail)}`;
            console.log('Fetching:', url);

            const response = await fetch(url);
            console.log('Response status:', response.status);

            if (!response.ok) {
                this.addMessage('system', `Server error: ${response.status}`);
                return;
            }

            const data = await response.json();
            console.log('Server response:', data);

            // Execute command based on server's interpretation
            const action = data.action || 'unknown';

            switch(action) {
                case 'show_unread':
                    this.handleShowUnread();
                    break;
                case 'read_email':
                    this.handleReadEmail(transcript, data.sender);
                    break;
                case 'reply':
                    this.handleReply(transcript, data.reply_text);
                    break;
                case 'forward':
                    this.handleForward(transcript, data.recipient);
                    break;
                case 'mark_important':
                    this.handleMark('important');
                    break;
                case 'delete':
                    this.handleDelete();
                    break;
                case 'search':
                    this.handleSearch(transcript, data.search_query);
                    break;
                default:
                    this.addMessage('system', `✓ Understood: ${action}`);
                    this.speak(`Command: ${action}`);
            }
        } catch (error) {
            console.error('API error:', error);
            this.addMessage('system', `Error: ${error.message}`);
        }
    }

    handleReadEmail(transcript, sender) {
        const emails = this.emails[this.currentEmail];

        if (sender) {
            // Use sender from Claude
            const email = emails.find(e => e.from.toLowerCase().includes(sender.toLowerCase()));

            if (email) {
                const response = `📧 Email from ${email.from}:\n\nSubject: ${email.subject}\n\nMessage: ${email.body}`;
                this.addMessage('system', response);
                this.speak(`Email from ${email.from}. Subject: ${email.subject}. Message: ${email.body}`);
                email.read = true;
            } else {
                this.addMessage('system', `No emails from ${sender} found.`);
                this.speak(`No emails from ${sender}`);
            }
        } else {
            // Fallback: try extracting from transcript
            const senderMatch = transcript.match(/from\s+(\w+)/i);
            if (senderMatch) {
                this.handleReadEmail(transcript, senderMatch[1]);
            } else {
                this.addMessage('system', 'Please specify who the email is from. Example: "Read email from Phil"');
            }
        }
    }

    handleShowUnread() {
        const emails = this.emails[this.currentEmail];
        const unread = emails.filter(e => !e.read);

        if (unread.length === 0) {
            this.addMessage('system', '✅ All emails read!');
            this.speak('All emails are read');
        } else {
            let response = `📬 You have ${unread.length} unread emails:\n\n`;
            unread.forEach(e => {
                response += `• From ${e.from}: "${e.subject}"\n`;
            });
            this.addMessage('system', response);

            // Build detailed response with all info
            let detailedResponse = `You have ${unread.length} unread emails. `;
            unread.forEach((e, i) => {
                detailedResponse += `${i + 1}. From ${e.from}: ${e.subject}. `;
            });
            this.speak(detailedResponse);
        }
    }

    handleReply(transcript, replyText) {
        if (replyText) {
            this.addMessage('system', `✉️ Reply sent: "${replyText}"`);
            this.speak(`Reply sent saying ${replyText}`);
        } else {
            const replyMatch = transcript.match(/(?:reply|respond).*?(?:with|saying)?\s+(.+)/i);
            if (replyMatch) {
                this.handleReply(transcript, replyMatch[1]);
            } else {
                this.addMessage('system', 'Please specify what you want to reply with. Example: "Reply saying thanks"');
            }
        }
    }

    handleForward(transcript, recipient) {
        if (recipient) {
            this.addMessage('system', `➡️ Email forwarded to ${recipient}`);
            this.speak(`Email forwarded to ${recipient}`);
        } else {
            const toMatch = transcript.match(/forward\s+(?:to\s+)?(\w+)/i);
            if (toMatch) {
                this.handleForward(transcript, toMatch[1]);
            } else {
                this.addMessage('system', 'Please specify who to forward to. Example: "Forward to Lisa"');
            }
        }
    }

    handleMark(transcript) {
        if (transcript.toLowerCase().includes('important')) {
            this.addMessage('system', '⭐ Email marked as important');
            this.speak('Email marked as important');
        } else if (transcript.toLowerCase().includes('spam')) {
            this.addMessage('system', '🚫 Email marked as spam');
            this.speak('Email marked as spam');
        }
    }

    handleDelete() {
        this.addMessage('system', '🗑️ Email deleted');
        this.speak('Email deleted');
    }

    handleSearch(transcript) {
        const searchMatch = transcript.match(/search\s+(?:for\s+)?(.+)/i);

        if (searchMatch) {
            const query = searchMatch[1];
            const emails = this.emails[this.currentEmail];
            const results = emails.filter(e =>
                e.from.toLowerCase().includes(query.toLowerCase()) ||
                e.subject.toLowerCase().includes(query.toLowerCase()) ||
                e.body.toLowerCase().includes(query.toLowerCase())
            );

            if (results.length > 0) {
                let response = `🔍 Found ${results.length} matching emails:\n\n`;
                results.forEach(e => {
                    response += `• From ${e.from}: "${e.subject}"\n`;
                });
                this.addMessage('system', response);
                this.speak(`Found ${results.length} emails matching ${query}`);
            } else {
                this.addMessage('system', `No emails found matching "${query}"`);
                this.speak(`No emails found matching ${query}`);
            }
        }
    }

    addMessage(type, text) {
        const chatArea = document.getElementById('chatArea');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;

        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.textContent = text;

        messageDiv.appendChild(bubble);
        chatArea.appendChild(messageDiv);
        chatArea.scrollTop = chatArea.scrollHeight;
    }

    speak(text) {
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.rate = 0.9;
        speechSynthesis.speak(utterance);
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
    new VoiceEmailPortal();
});

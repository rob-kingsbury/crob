<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crob - Curious Learning AI</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #1a1a2e;
            color: #eee;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: #16213e;
            padding: 1rem 2rem;
            border-bottom: 1px solid #0f3460;
        }

        .header h1 {
            font-size: 1.5rem;
            color: #e94560;
        }

        .header p {
            color: #888;
            font-size: 0.9rem;
        }

        .main {
            flex: 1;
            display: flex;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
            padding: 1rem;
            gap: 1rem;
        }

        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #16213e;
            border-radius: 8px;
            overflow: hidden;
        }

        .messages {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            max-width: 80%;
        }

        .message.user {
            background: #0f3460;
            align-self: flex-end;
        }

        .message.crob {
            background: #1a1a2e;
            align-self: flex-start;
            border-left: 3px solid #e94560;
        }

        .message pre {
            white-space: pre-wrap;
            font-family: inherit;
        }

        .input-area {
            padding: 1rem;
            background: #0f3460;
            display: flex;
            gap: 0.5rem;
        }

        .input-area input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 4px;
            background: #1a1a2e;
            color: #eee;
            font-size: 1rem;
        }

        .input-area input:focus {
            outline: 2px solid #e94560;
        }

        .input-area button {
            padding: 0.75rem 1.5rem;
            background: #e94560;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
        }

        .input-area button:hover {
            background: #ff6b6b;
        }

        .sidebar {
            width: 300px;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .panel {
            background: #16213e;
            border-radius: 8px;
            padding: 1rem;
        }

        .panel h3 {
            color: #e94560;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
        }

        .stat {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #0f3460;
        }

        .stat:last-child {
            border-bottom: none;
        }

        .stat-value {
            color: #e94560;
            font-weight: bold;
        }

        .queue-item {
            padding: 0.5rem;
            background: #1a1a2e;
            border-radius: 4px;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
        }

        .queue-item small {
            color: #666;
        }

        .loading {
            color: #888;
            font-style: italic;
        }

        @media (max-width: 900px) {
            .main {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                flex-direction: row;
                flex-wrap: wrap;
            }
            .panel {
                flex: 1;
                min-width: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Crob</h1>
        <p>A curious, self-learning AI with reptile and primate brains</p>
    </div>

    <div class="main">
        <div class="chat-container">
            <div class="messages" id="messages">
                <div class="message crob">
                    <pre id="intro">Loading this nerd's brain...</pre>
                </div>
            </div>
            <div class="input-area">
                <input type="text" id="input" placeholder="Ask Crob anything..." autofocus>
                <button onclick="ask()">Ask</button>
            </div>
        </div>

        <div class="sidebar">
            <div class="panel">
                <h3>Nerd Stats</h3>
                <div class="stat">
                    <span>Facts</span>
                    <span class="stat-value" id="stat-facts">0</span>
                </div>
                <div class="stat">
                    <span>Subjects</span>
                    <span class="stat-value" id="stat-subjects">0</span>
                </div>
                <div class="stat">
                    <span>Queue</span>
                    <span class="stat-value" id="stat-queue">0</span>
                </div>
            </div>

            <div class="panel">
                <h3>Research Queue</h3>
                <div id="queue">
                    <p class="loading">Loading...</p>
                </div>
                <button onclick="learnNext()" style="margin-top: 0.5rem; width: 100%; padding: 0.5rem; background: #0f3460; border: 1px solid #e94560; color: #e94560; border-radius: 4px; cursor: pointer;">
                    Learn Next
                </button>
            </div>
        </div>
    </div>

    <script>
        const messagesEl = document.getElementById('messages');
        const inputEl = document.getElementById('input');

        // Load intro
        fetch('api.php?action=intro')
            .then(r => r.json())
            .then(data => {
                document.getElementById('intro').textContent = data.response;
                updateStats();
                updateQueue();
            });

        // Enter key
        inputEl.addEventListener('keypress', e => {
            if (e.key === 'Enter') ask();
        });

        function ask() {
            const question = inputEl.value.trim();
            if (!question) return;

            // Add user message
            addMessage(question, 'user');
            inputEl.value = '';

            // Add loading
            const loadingId = addMessage('*reptile brain processing*...', 'crob');

            // Ask Crob
            fetch('api.php?action=ask', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ question })
            })
            .then(r => r.json())
            .then(data => {
                // Replace loading with response
                document.getElementById(loadingId).querySelector('pre').textContent = data.response;
                updateStats();
                updateQueue();
            })
            .catch(err => {
                document.getElementById(loadingId).querySelector('pre').textContent =
                    '*sad nerd noises* Something went wrong: ' + err.message;
            });
        }

        function addMessage(text, type) {
            const id = 'msg-' + Date.now();
            const div = document.createElement('div');
            div.className = 'message ' + type;
            div.id = id;
            div.innerHTML = '<pre>' + escapeHtml(text) + '</pre>';
            messagesEl.appendChild(div);
            messagesEl.scrollTop = messagesEl.scrollHeight;
            return id;
        }

        function updateStats() {
            fetch('api.php?action=stats')
                .then(r => r.json())
                .then(data => {
                    document.getElementById('stat-facts').textContent = data.knowledge.facts;
                    document.getElementById('stat-subjects').textContent = data.knowledge.subjects;
                    document.getElementById('stat-queue').textContent = data.curiosity.queued;
                });
        }

        function updateQueue() {
            fetch('api.php?action=queue')
                .then(r => r.json())
                .then(data => {
                    const el = document.getElementById('queue');
                    if (data.queue.length === 0) {
                        el.innerHTML = '<p style="color: #666; font-size: 0.85rem;">Queue empty. Ask something!</p>';
                    } else {
                        el.innerHTML = data.queue.slice(0, 5).map(item => `
                            <div class="queue-item">
                                ${escapeHtml(item.topic)}
                                <br><small>from: ${escapeHtml(item.origin)}</small>
                            </div>
                        `).join('');
                    }
                });
        }

        function learnNext() {
            addMessage('*puts on learning hat*', 'crob');

            fetch('api.php?action=learn', { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        addMessage(
                            `Learned about "${data.result.topic}"!\n` +
                            `Got ${data.result.facts_learned} facts and found ${data.result.new_rabbit_holes} new rabbit holes.`,
                            'crob'
                        );
                    } else {
                        addMessage('Nothing in my queue to learn. Ask me something first!', 'crob');
                    }
                    updateStats();
                    updateQueue();
                });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>

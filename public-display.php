<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5f1e9">
    <title>Public Queue Display | ACLC College of Daet</title>
    <link rel="stylesheet" href="public-display.css">
</head>
<body>
    <header class="tv-header">
        <div class="tv-brand">
            <img src="img/image.png" alt="" class="tv-logo" onerror="this.hidden = true">
            <div>
                <h1>AMA Computer Learning Center College of Daet</h1>
                <p>Registrar Queue Display</p>
            </div>
        </div>
        <div class="tv-header-right">
            <div class="tv-clock">
                <time id="liveClock">--:--:--</time>
                <span id="liveDate">Loading date...</span>
            </div>
            <button type="button" class="tv-button" id="soundButton" aria-pressed="false">Enable sound</button>
            <button type="button" class="tv-button tv-fullscreen" id="fullscreenButton" aria-label="Toggle fullscreen" title="Toggle fullscreen (F)">Fullscreen</button>
        </div>
    </header>

    <main class="tv-layout">
        <section class="tv-serving-card" aria-labelledby="servingHeading">
            <div class="tv-card-heading">
                <h2 id="counterName">Registrar Desk • Window 1</h2>
                <span class="tv-live"><span></span> LIVE</span>
            </div>
            <div class="tv-serving-content">
                <p class="tv-eyebrow" id="servingHeading">NOW SERVING</p>
                <p class="tv-ticket" id="servingNumber" aria-live="polite">---</p>
                <p class="tv-student" id="servingStudent">Waiting for staff...</p>
                <p class="tv-service" id="servingService">The next ticket will appear here.</p>
                <p class="tv-call-alert" id="callAlert" hidden>Now calling</p>
            </div>
        </section>

        <aside class="tv-queue-card" aria-labelledby="queueHeading">
            <div class="tv-queue-heading">
                <div>
                    <p class="tv-eyebrow">UP NEXT</p>
                    <h2 id="queueHeading">In Queue</h2>
                </div>
                <span class="tv-count" id="waitingCount">0 waiting</span>
            </div>
            <div class="tv-queue-list" id="waitingList" aria-live="polite">
                <p class="tv-empty">No students currently in queue.</p>
            </div>
        </aside>
    </main>

    <footer class="tv-footer">
        <span id="connectionStatus" role="status">Connecting to queue...</span>
        <span>Public display · Read only</span>
    </footer>

    <section class="tv-start-overlay" id="startOverlay" aria-labelledby="startHeading">
        <div class="tv-start-card">
            <p class="tv-eyebrow">PUBLIC QUEUE DISPLAY</p>
            <h2 id="startHeading">Start the TV display</h2>
            <p>Start the display to allow queue-call chimes. Use the fullscreen button or press <kbd>F</kbd> for TV mode.</p>
            <button type="button" class="tv-button tv-start-button" id="startButton">Start display &amp; enable sound</button>
            <button type="button" class="tv-start-muted" id="startMutedButton">Start without sound</button>
        </div>
    </section>

    <script>
        const clock = document.getElementById('liveClock');
        const date = document.getElementById('liveDate');
        const connectionStatus = document.getElementById('connectionStatus');
        const soundButton = document.getElementById('soundButton');
        const startOverlay = document.getElementById('startOverlay');
        const waitingList = document.getElementById('waitingList');
        let soundEnabled = false;
        let audioContext = null;
        let initialStateLoaded = false;
        let lastCallKey = null;
        let requestInFlight = false;

        function updateClock() {
            const now = new Date();
            clock.textContent = now.toLocaleTimeString('en-US', { hour12: true });
            date.textContent = now.toLocaleDateString('en-US', {
                weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'
            });
        }

        function setSoundEnabled(enabled) {
            soundEnabled = enabled;
            soundButton.textContent = enabled ? 'Sound on' : 'Sound off';
            soundButton.setAttribute('aria-pressed', String(enabled));
        }

        async function unlockAudio() {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) {
                throw new Error('Audio is not supported by this browser.');
            }
            if (!audioContext) {
                audioContext = new AudioContextClass();
            }
            if (audioContext.state === 'suspended') {
                await audioContext.resume();
            }
            playChime();
        }

        function playChime() {
            if (!soundEnabled || !audioContext || audioContext.state !== 'running') return;

            const now = audioContext.currentTime;
            [[659.25, 0], [523.25, 0.28]].forEach(([frequency, offset]) => {
                const oscillator = audioContext.createOscillator();
                const gain = audioContext.createGain();
                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(frequency, now + offset);
                gain.gain.setValueAtTime(0.0001, now + offset);
                gain.gain.linearRampToValueAtTime(0.16, now + offset + 0.03);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + offset + 0.55);
                oscillator.connect(gain);
                gain.connect(audioContext.destination);
                oscillator.start(now + offset);
                oscillator.stop(now + offset + 0.56);
            });
        }

        async function startDisplay(withSound) {
            if (withSound) {
                try {
                    setSoundEnabled(true);
                    await unlockAudio();
                } catch (error) {
                    setSoundEnabled(false);
                    connectionStatus.textContent = error.message;
                }
            }
            startOverlay.hidden = true;
        }

        document.getElementById('startButton').addEventListener('click', () => startDisplay(true));
        document.getElementById('startMutedButton').addEventListener('click', () => startDisplay(false));

        soundButton.addEventListener('click', async () => {
            if (soundEnabled) {
                setSoundEnabled(false);
                return;
            }
            try {
                setSoundEnabled(true);
                await unlockAudio();
            } catch (error) {
                setSoundEnabled(false);
                connectionStatus.textContent = error.message;
            }
        });

        async function toggleFullscreen() {
            try {
                if (!document.fullscreenElement) {
                    await document.documentElement.requestFullscreen();
                } else {
                    await document.exitFullscreen();
                }
            } catch (error) {
                connectionStatus.textContent = 'Fullscreen is unavailable in this browser.';
            }
        }

        document.getElementById('fullscreenButton').addEventListener('click', toggleFullscreen);
        document.addEventListener('keydown', event => {
            if (event.key.toLowerCase() === 'f' && !event.repeat) {
                event.preventDefault();
                toggleFullscreen();
            }
        });
        document.addEventListener('dblclick', toggleFullscreen);

        function renderWaitingList(items) {
            waitingList.replaceChildren();
            if (!items.length) {
                const empty = document.createElement('p');
                empty.className = 'tv-empty';
                empty.textContent = 'No students currently in queue.';
                waitingList.append(empty);
                return;
            }

            items.forEach(item => {
                const row = document.createElement('article');
                row.className = 'tv-queue-item';

                const number = document.createElement('span');
                number.className = 'tv-queue-number';
                number.textContent = item.queue_number || '—';

                const details = document.createElement('span');
                details.className = 'tv-queue-details';
                const name = document.createElement('span');
                name.className = 'tv-queue-name';
                name.textContent = item.student_name || 'Student';
                const service = document.createElement('span');
                service.className = 'tv-queue-service';
                service.textContent = item.service || 'Appointment';
                details.append(name, service);
                row.append(number, details);
                waitingList.append(row);
            });
        }

        async function pollQueue() {
            if (requestInFlight) return;
            requestInFlight = true;
            try {
                const response = await fetch('public-display-api.php', {
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) throw new Error('Queue status request failed.');
                const data = await response.json();
                if (!data.success || !data.queue) throw new Error(data.message || 'Queue status is unavailable.');

                const queue = data.queue;
                const current = queue.now_serving;
                document.getElementById('counterName').textContent = queue.counter_name || 'Registrar Desk • Window 1';
                document.getElementById('waitingCount').textContent = `${queue.total_waiting} waiting`;

                if (current) {
                    document.getElementById('servingNumber').textContent = current.queue_number || '—';
                    document.getElementById('servingStudent').textContent = current.student_name || 'Student';
                    document.getElementById('servingService').textContent = current.service || 'Appointment';
                    const callKey = `${current.queue_number}|${current.called_at}`;
                    if (initialStateLoaded && (lastCallKey === null || callKey !== lastCallKey)) {
                        playChime();
                        const alert = document.getElementById('callAlert');
                        alert.hidden = false;
                        window.setTimeout(() => { alert.hidden = true; }, 4500);
                        const number = document.getElementById('servingNumber');
                        number.classList.remove('tv-call-animation');
                        void number.offsetWidth;
                        number.classList.add('tv-call-animation');
                    }
                    lastCallKey = callKey;
                } else {
                    document.getElementById('servingNumber').textContent = '---';
                    document.getElementById('servingStudent').textContent = 'Waiting for staff...';
                    document.getElementById('servingService').textContent = 'The next ticket will appear here.';
                    lastCallKey = null;
                }

                renderWaitingList(queue.waiting_list);
                connectionStatus.textContent = 'Live · Updates automatically';
                initialStateLoaded = true;
            } catch (error) {
                connectionStatus.textContent = 'Reconnecting to queue...';
                console.error('Public queue update failed:', error);
            } finally {
                requestInFlight = false;
            }
        }

        updateClock();
        window.setInterval(updateClock, 1000);
        pollQueue();
        window.setInterval(pollQueue, 3500);
    </script>
</body>
</html>

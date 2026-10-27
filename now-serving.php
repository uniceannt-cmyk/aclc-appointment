<?php
require_once("db.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Now Serving - ACLC College of Daet Queuing System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="now-serving.css">
</head>
<body>

    <!-- Header & Clock -->
    <header class="serving-header">
        <div class="brand">
                       <img src="img/image.png" alt="ACLC Logo" class="ama-logo" onerror="this.style.display='none'" style="width: 80px; height: 80px; object-fit: contain; margin-bottom: 10px;">
            <div>
                <h1>AMA Computer Learning Center College of Daet</h1>
                <p class="subtitle">Registrar Office Real-Time Queuing Display</p>
            </div>
        </div>
        <div class="header-controls">
            <div class="time-block">
                <div class="digital-clock" id="liveClock">--:--:--</div>
                <div class="live-date" id="liveDate">---</div>
            </div>
            <button type="button" class="ctrl-btn" id="audioToggleBtn" title="Enable Voice & Chime Announcements">
                <i class="fas fa-volume-up" id="audioIcon"></i> <span id="audioText">Sound ON</span>
            </button>
            <button type="button" class="ctrl-btn" id="fullscreenBtn" title="Toggle Fullscreen">
                <i class="fas fa-expand"></i>
            </button>
        </div>
    </header>

    <!-- Registrar Counter -->
    <main class="queue-grid">
        <section class="counter-card counter-registrar" id="panelRegistrar">
            <div class="counter-header">
                <h2 id="registrarCounterTitle"> Registrar Desk &bull; Window 1</h2>
                <div class="status-cluster">
                    <span class="call-status" id="queueCallStatus" hidden>CALLING</span>
                    <span class="pulse-indicator">LIVE</span>
                    <span class="pause-indicator" id="queuePauseNotice" hidden>QUEUE PAUSED</span>
                </div>
            </div>

            <div class="now-serving-box">
                <div class="serving-label">NOW SERVING</div>
                <div class="queue-number-display" id="regNowNumber">---</div>
                <div class="student-name-display" id="regNowName">Waiting for staff...</div>
                <div class="service-name-display" id="regNowService">Academic Records & Records Service</div>
            </div>

        </section>
        <aside class="waiting-section" aria-labelledby="waitingTitle">
            <div class="waiting-heading">
                <div>
                    <span class="section-kicker">UP NEXT</span>
                    <h3 id="waitingTitle"><i class="fas fa-list-ol"></i> In Queue</h3>
                </div>
                <span class="queue-count" id="queueWaitingCount">0 waiting</span>
            </div>
            <div class="waiting-list" id="regWaitingList">
                <div class="waiting-item empty-wait">No students currently in queue.</div>
            </div>
        </aside>
    </main>

    <!-- Footer Ticker -->
    <footer class="serving-footer">
        <div class="ticker-wrap">
            <div class="ticker-item">
                <i class="fas fa-info-circle"></i> Welcome students! 
            </div>
        </div>
    </footer>

    <script>
        // Digital Clock
        function updateClock() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { hour12: true });
            const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
            const clockEl = document.getElementById('liveClock');
            const dateEl = document.getElementById('liveDate');
            if (clockEl) clockEl.textContent = timeStr;
            if (dateEl) dateEl.textContent = dateStr;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Audio & Speech Synthesis Engine
        let soundEnabled = true;
        let lastRegCalledId = null;

        const audioToggleBtn = document.getElementById('audioToggleBtn');
        const audioIcon = document.getElementById('audioIcon');
        const audioText = document.getElementById('audioText');

        if (audioToggleBtn) {
            audioToggleBtn.addEventListener('click', () => {
                soundEnabled = !soundEnabled;
                if (soundEnabled) {
                    audioIcon.className = 'fas fa-volume-up';
                    audioText.textContent = 'Sound ON';
                    playChime();
                } else {
                    audioIcon.className = 'fas fa-volume-mute';
                    audioText.textContent = 'Sound OFF';
                }
            });
        }

        // Web Audio Two-Tone Chime
        function playChime() {
            if (!soundEnabled) return;
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const now = ctx.currentTime;

                // First chime tone (high)
                const osc1 = ctx.createOscillator();
                const gain1 = ctx.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(659.25, now); // E5
                gain1.gain.setValueAtTime(0.3, now);
                gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
                osc1.connect(gain1);
                gain1.connect(ctx.destination);
                osc1.start(now);
                osc1.stop(now + 0.6);

                // Second chime tone (lower)
                const osc2 = ctx.createOscillator();
                const gain2 = ctx.createGain();
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(523.25, now + 0.3); // C5
                gain2.gain.setValueAtTime(0.3, now + 0.3);
                gain2.gain.exponentialRampToValueAtTime(0.001, now + 1.1);
                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.start(now + 0.3);
                osc2.stop(now + 1.1);
            } catch (e) {
                console.log('Audio error:', e);
            }
        }

        // Voice Announcement using Web Speech API
        function speakAnnouncement(queueNumber, deskName) {
            if (!soundEnabled) return;
            playChime();
            setTimeout(() => {
                if ('speechSynthesis' in window) {
                    window.speechSynthesis.cancel();
                    const text = `Now serving queue number ${queueNumber} at ${deskName}`;
                    const utterance = new SpeechSynthesisUtterance(text);
                    utterance.rate = 0.9;
                    utterance.pitch = 1.0;
                    window.speechSynthesis.speak(utterance);
                }
            }, 800);
        }

        // Fullscreen Toggle
        const fullscreenBtn = document.getElementById('fullscreenBtn');
        if (fullscreenBtn) {
            fullscreenBtn.addEventListener('click', () => {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen().catch(err => console.log(err));
                } else {
                    document.exitFullscreen();
                }
            });
        }

        // Real-Time Queue Polling
        function pollQueue() {
            fetch('api_queue.php?office=registrar')
                .then(res => res.json())
                .then(data => {
                    if (!data.success) return;

                    const title = document.getElementById('registrarCounterTitle');
                    const pauseNotice = document.getElementById('queuePauseNotice');
                    const callStatus = document.getElementById('queueCallStatus');
                    const waitingCount = document.getElementById('queueWaitingCount');
                    if (title) title.textContent = 'Registrar Desk • ' + (data.registrar.counter_name || 'Window 1');
                    if (pauseNotice) pauseNotice.hidden = !data.registrar.is_paused;
                    if (waitingCount) waitingCount.textContent = `${data.registrar.total_waiting || 0} waiting`;

                    // 1. Update Registrar Desk
                    const regServing = data.registrar.now_serving;
                    const regNumberEl = document.getElementById('regNowNumber');
                    const regNameEl = document.getElementById('regNowName');
                    const regServiceEl = document.getElementById('regNowService');
                    const regWaitListEl = document.getElementById('regWaitingList');

                    if (regServing) {
                        regNumberEl.textContent = regServing.queue_number;
                        regNameEl.textContent = regServing.student_name;
                        regServiceEl.textContent = regServing.service;

                        if (lastRegCalledId !== regServing.id) {
                            if (lastRegCalledId !== null) {
                                speakAnnouncement(regServing.queue_number, data.registrar.counter_name || 'Registrar Window 1');
                                regNumberEl.classList.add('flash-call');
                                if (callStatus) {
                                    callStatus.hidden = false;
                                    setTimeout(() => { callStatus.hidden = true; }, 5000);
                                }
                                setTimeout(() => regNumberEl.classList.remove('flash-call'), 3000);
                            }
                            lastRegCalledId = regServing.id;
                        }
                    } else {
                        regNumberEl.textContent = '---';
                        regNameEl.textContent = 'Waiting for staff...';
                        regServiceEl.textContent = 'Registrar Window 1';
                    }

                    // Registrar Waiting List
                    if (data.registrar.waiting_list.length > 0) {
                        regWaitListEl.innerHTML = data.registrar.waiting_list.map(w => `
                            <div class="waiting-item">
                                <span class="wait-num">${w.queue_number}</span>
                                <span class="wait-name">${w.student_name}</span>
                                <span class="wait-service">${w.service}</span>
                            </div>
                        `).join('');
                    } else {
                        regWaitListEl.innerHTML = '<div class="waiting-item empty-wait">No students currently waiting.</div>';
                    }

                })
                .catch(err => console.error('Queue poll error:', err));
        }

        // Poll every 3.5 seconds
        setInterval(pollQueue, 3500);
        pollQueue();
    </script>
</body>
</html>

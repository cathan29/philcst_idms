(() => {
    const station = document.getElementById('scannerStation');
    if (!station) return;

    const STATES = Object.freeze({ IDLE: 'IDLE', INPUT_DETECTED: 'INPUT_DETECTED', VERIFYING: 'VERIFYING', VERIFIED: 'VERIFIED', NOT_FOUND: 'NOT_FOUND', INVALID: 'INVALID', ERROR: 'ERROR' });
    const form = document.getElementById('scannerForm');
    const input = document.getElementById('scanPayload');
    const button = document.getElementById('verifyButton');
    const buttonLabel = button.querySelector('span');
    const stateHeading = document.getElementById('scanner-workspace-title');
    const stateMessage = document.getElementById('scannerState');
    const result = document.getElementById('scannerResult');
    const startCameraButton = document.getElementById('startCamera');
    const stopCameraButton = document.getElementById('stopCamera');
    const cameraStatus = document.getElementById('cameraStatus');
    const cameraPreview = document.getElementById('cameraPreview');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let mode = document.querySelector('.scanner-mode.active')?.dataset.mode || 'verification';
    let currentState = STATES.IDLE;
    let confirmationOpen = false;
    let lastVerifiedPayload = '';
    let lastResult = null;
    let cameraStream = null;
    let detector = null;
    let detectionFrame = null;
    let detecting = false;

    const esc = value => String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);
    const hasInput = () => input.value.trim().length > 0;
    const setState = (nextState, detail = '') => {
        currentState = nextState;
        station.dataset.inputState = nextState.toLowerCase();
        const copy = {
            [STATES.IDLE]: ['Ready to scan', 'Waiting for a QR scan or manual entry.'],
            [STATES.INPUT_DETECTED]: ['Input detected', 'Identifier ready for verification.'],
            [STATES.VERIFYING]: ['Verifying ID', 'Checking the identifier against recorded ID data.'],
            [STATES.VERIFIED]: ['ID verified', detail || 'Student ID information was verified.'],
            [STATES.NOT_FOUND]: ['ID not found', detail || 'No matching student or card record was found.'],
            [STATES.INVALID]: ['Invalid identifier', detail || 'Enter a supported student or card identifier.'],
            [STATES.ERROR]: ['Verification unavailable', detail || 'The request failed. Check the connection and retry.'],
        }[nextState];
        stateHeading.textContent = copy[0];
        stateMessage.textContent = copy[1];
        const verifying = nextState === STATES.VERIFYING;
        input.readOnly = verifying;
        button.disabled = verifying || !hasInput();
        buttonLabel.textContent = verifying ? 'Verifying...' : nextState === STATES.ERROR ? 'Retry verification' : 'Verify ID';
    };

    input.addEventListener('input', () => {
        if (currentState === STATES.VERIFYING || confirmationOpen) return;
        setState(hasInput() ? STATES.INPUT_DETECTED : STATES.IDLE);
    });

    document.querySelectorAll('.scanner-mode').forEach(tab => tab.addEventListener('click', () => {
        if (currentState === STATES.VERIFYING || confirmationOpen) return;
        document.querySelectorAll('.scanner-mode').forEach(item => { item.classList.remove('active'); item.setAttribute('aria-checked', 'false'); });
        tab.classList.add('active');
        tab.setAttribute('aria-checked', 'true');
        mode = tab.dataset.mode;
        if (lastResult) render(lastResult);
        setState(hasInput() ? STATES.INPUT_DETECTED : STATES.IDLE);
        input.focus();
    }));

    const actionFor = card => {
        if (!card || mode === 'verification') return '';
        if (mode === 'production' && card.status === 'for_printing') return '<button class="btn primary scanner-action" type="button" data-target="printed">Confirm Printing</button>';
        if (mode === 'production' && card.status === 'printed') return '<button class="btn primary scanner-action" type="button" data-target="ready_for_release">Mark Ready for Release</button>';
        if (mode === 'releasing' && card.status === 'ready_for_release') return '<button class="btn primary scanner-action" type="button" data-target="released">Confirm Release</button>';
        return '';
    };

    const render = data => {
        lastResult = data;
        if (!data.card) {
            const title = data.result === 'not_found' ? 'ID not found' : data.result === 'invalid' ? 'Invalid identifier' : String(data.result || 'error').replaceAll('_', ' ');
            result.innerHTML = `<div class="scanner-empty scanner-diagnostic is-${esc(data.result || 'error')}"><h2>${esc(title)}</h2><p>${esc(data.message)}</p></div>`;
            return;
        }
        const enrollment = data.enrollment || {};
        const lifecycle = data.lifecycle || {};
        const discrepancies = data.discrepancies || [];
        const history = data.history || [];
        const issueMarkup = discrepancies.length ? `<div class="verification-alert" role="alert"><strong>Record discrepancy${discrepancies.length === 1 ? '' : 'ies'} detected</strong><ul>${discrepancies.map(issue => `<li>${esc(issue)}</li>`).join('')}</ul></div>` : '';
        const historyMarkup = history.length ? `<div class="verification-history"><h3>ID transaction history</h3><ol>${history.map(item => `<li><span><strong>${esc(String(item.new_status).replaceAll('_', ' '))}</strong>${item.reason ? `<br><span>${esc(item.reason)}</span>` : ''}</span><small>${esc(item.occurred_at)}<br>${esc(item.actor)}</small></li>`).join('')}</ol></div>` : '<div class="verification-history"><h3>ID transaction history</h3><p>No status history has been recorded for this issuance.</p></div>';
        result.innerHTML = `<div class="verification-card"><div class="verification-heading"><div><p class="eyebrow">Verification Result</p><h2>${esc(data.student.name)}</h2><span>${esc(data.student.student_number)}</span></div><span class="card-status status-${esc(data.card.status)}">${esc(data.card.status_label)}</span></div><dl class="verification-details"><div><dt>Program and year level</dt><dd>${esc([enrollment.program, enrollment.year_level].filter(Boolean).join(' · ') || 'Not recorded')}</dd></div><div><dt>Academic term</dt><dd>${esc(enrollment.term || 'Not recorded')}</dd></div><div><dt>ID issuance</dt><dd>${esc(data.card.issuance_type || 'Not recorded')}</dd></div><div><dt>Last status update</dt><dd>${esc(data.card.last_updated || 'Not recorded')}</dd></div></dl><dl class="verification-evidence"><div><dt>Printed</dt><dd>${esc(lifecycle.printed_at || 'Not recorded')}</dd><small>${esc(lifecycle.printed_by || '')}</small></div><div><dt>Ready for release</dt><dd>${esc(lifecycle.ready_at || 'Not recorded')}</dd></div><div><dt>Released</dt><dd>${esc(lifecycle.released_at || 'Not released')}</dd><small>${esc(lifecycle.released_by || '')}</small></div><div><dt>Production started</dt><dd>${esc(lifecycle.printing_started_at || 'Not recorded')}</dd></div></dl><p class="verification-message">${esc(data.message)}</p>${issueMarkup}${historyMarkup}<div class="scanner-result-actions">${actionFor(data.card)}</div></div>`;
        result.querySelector('.scanner-action')?.addEventListener('click', event => transition(data.card.id, event.currentTarget.dataset.target));
    };

    const verifyPayload = async payload => {
        if (currentState === STATES.VERIFYING || confirmationOpen) return;
        setState(STATES.VERIFYING);
        try {
            const response = await fetch(station.dataset.verifyUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ payload, mode }) });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || data.errors?.payload?.[0] || 'Verification failed.');
            render(data);
            if (data.ok && data.card) {
                lastVerifiedPayload = payload;
                input.value = '';
                setState(STATES.VERIFIED, data.message);
            } else if (data.result === 'invalid') {
                setState(STATES.INVALID, data.message);
            } else {
                setState(STATES.NOT_FOUND, data.message);
            }
        } catch (error) {
            render({ result: 'error', message: error.message || 'Verification could not be completed.' });
            setState(STATES.ERROR, error.message);
        } finally {
            input.readOnly = false;
            button.disabled = !hasInput();
            if (!confirmationOpen && !cameraStream) input.focus();
        }
    };

    const stopCamera = () => {
        if (detectionFrame) cancelAnimationFrame(detectionFrame);
        detectionFrame = null;
        detecting = false;
        cameraStream?.getTracks().forEach(track => track.stop());
        cameraStream = null;
        cameraPreview.srcObject = null;
        cameraPreview.hidden = true;
        startCameraButton.hidden = false;
        stopCameraButton.hidden = true;
        cameraStatus.textContent = 'Camera is off. Manual entry remains available.';
    };

    const detectFrame = async () => {
        if (!cameraStream) return;
        if (!detecting && cameraPreview.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA) {
            detecting = true;
            try {
                const codes = await detector.detect(cameraPreview);
                const payload = codes.find(code => code.rawValue)?.rawValue?.trim();
                if (payload) {
                    input.value = payload;
                    stopCamera();
                    setState(STATES.INPUT_DETECTED);
                    await verifyPayload(payload);
                    return;
                }
            } catch (error) {
                cameraStatus.textContent = 'The camera image could not be read. Reposition the QR code and try again.';
            } finally {
                detecting = false;
            }
        }
        detectionFrame = requestAnimationFrame(detectFrame);
    };

    const startCamera = async () => {
        if (!navigator.mediaDevices?.getUserMedia || !('BarcodeDetector' in window)) {
            cameraStatus.textContent = 'Camera QR detection is not supported by this browser. Use a scanner or manual entry.';
            return;
        }
        startCameraButton.disabled = true;
        cameraStatus.textContent = 'Requesting camera access...';
        try {
            detector = new BarcodeDetector({ formats: ['qr_code'] });
            cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            cameraPreview.srcObject = cameraStream;
            cameraPreview.hidden = false;
            await cameraPreview.play();
            startCameraButton.hidden = true;
            stopCameraButton.hidden = false;
            cameraStatus.textContent = 'Camera active. Hold the QR code inside the preview.';
            detectionFrame = requestAnimationFrame(detectFrame);
        } catch (error) {
            stopCamera();
            if (error?.name === 'NotAllowedError') cameraStatus.textContent = 'Camera access was denied. Allow access in browser settings or use manual entry.';
            else if (error?.name === 'NotFoundError') cameraStatus.textContent = 'No camera was found. Connect a camera or use manual entry.';
            else cameraStatus.textContent = 'The camera could not be started. Use a scanner or manual entry.';
        } finally {
            startCameraButton.disabled = false;
        }
    };

    const transition = async (cardId, target) => {
        confirmationOpen = true;
        const prompt = target === 'released' ? 'Confirm claimant identity and release this ID?' : `Confirm status change to ${target.replaceAll('_', ' ')}?`;
        const approved = window.confirm(prompt);
        confirmationOpen = false;
        if (!approved) { input.focus(); return; }
        setState(STATES.VERIFYING);
        try {
            const url = station.dataset.transitionTemplate.replace('__CARD__', cardId);
            const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ mode, target }) });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Status update failed.');
            currentState = STATES.IDLE;
            await verifyPayload(lastVerifiedPayload);
        } catch (error) {
            setState(STATES.ERROR, error.message);
            input.focus();
        }
    };

    form.addEventListener('submit', event => {
        event.preventDefault();
        if (currentState === STATES.VERIFYING || confirmationOpen) return;
        const payload = input.value.trim();
        if (!payload) { setState(STATES.INVALID, 'Enter or scan an identifier before verification.'); input.focus(); return; }
        verifyPayload(payload);
    });
    startCameraButton.addEventListener('click', startCamera);
    stopCameraButton.addEventListener('click', stopCamera);
    window.addEventListener('pagehide', stopCamera);
    document.addEventListener('visibilitychange', () => { if (document.hidden && cameraStream) stopCamera(); });

    setState(STATES.IDLE);
    input.focus();
})();
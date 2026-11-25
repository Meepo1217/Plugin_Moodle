(function() {
    'use strict';

    const rawConfig = window.MOODLE_ANTISHOT_CONFIG || {};
    const config = Object.freeze({
        watermark: rawConfig.watermark !== undefined ? rawConfig.watermark : true,
        userText: rawConfig.userText || 'Protected Content',
        cmid: rawConfig.cmid || 0,
        sesskey: rawConfig.sesskey || '',
        ajaxUrl: rawConfig.ajaxUrl || '',
        maxViolations: parseInt(rawConfig.maxViolations || 0, 10),
        lockScope: parseInt(rawConfig.lockScope || 1, 10),
        warningTitle: rawConfig.warningTitle || '⚠️ PHÁT HIỆN HÀNH VI CHỤP MÀN HÌNH / QUAY LÉN',
        warningSubtitle: rawConfig.warningSubtitle || 'Học liệu này đã được bảo vệ bản quyền. Nhấp vào nút bên dưới để tiếp tục học.',
        clipboardBlocked: rawConfig.clipboardBlocked || 'Chức năng chụp màn hình đã bị vô hiệu hóa trên học liệu này.'
    });

    let currentViolations = parseInt(rawConfig.currentViolations || 0, 10);
    let isLocked = (config.maxViolations > 0 && currentViolations >= config.maxViolations);
    let lastViolationTime = 0;
    let ignoreBlurUntil = 0;
    const COOLDOWN_MS = 6000;

    delete window.MOODLE_ANTISHOT_CONFIG;

    // 1. Nhúng CSS: Mặc định hiển thị bài học bình thường, CHỈ ẨN KHI CÓ CẢNH BÁO HOẶC KHÓA
    const styleEl = document.createElement('style');
    styleEl.innerHTML = `
        body.antishot-protected {
            user-select: none !important;
            -webkit-user-select: none !important;
        }
        /* Chỉ ẩn nội dung khi màn chắn vi phạm kích hoạt */
        body.antishot-shield-active #page-content,
        body.antishot-shield-active #region-main,
        body.antishot-shield-active iframe,
        body.antishot-shield-active .activity-header {
            visibility: hidden !important;
            opacity: 0 !important;
            display: none !important;
        }
        #antishot-warning-overlay {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            z-index: 2147483647 !important;
            background: #090d16 !important;
            color: #ffffff !important;
            display: none !important;
            align-items: center !important;
            justify-content: center !important;
            text-align: center !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            padding: 20px !important;
            box-sizing: border-box !important;
            pointer-events: auto !important;
        }
        body.antishot-shield-active #antishot-warning-overlay {
            display: flex !important;
        }
        #antishot-resume-btn:hover {
            background: #1d4ed8 !important;
            transform: scale(1.03);
        }
    `;
    document.head.appendChild(styleEl);
    document.body.classList.add('antishot-protected');

    // 2. Tạo khung hiển thị thông báo
    const shieldOverlay = document.createElement('div');
    shieldOverlay.id = 'antishot-warning-overlay';
    document.body.appendChild(shieldOverlay);

    function updateShieldUI() {
        if (isLocked) {
            const scopeMsg = config.lockScope === 2
                ? 'Toàn bộ quyền truy cập học liệu của bạn đã bị khóa.'
                : 'Bài giảng/học liệu này đã bị khóa đối với tài khoản của bạn.';

            shieldOverlay.innerHTML = `
                <div style="max-width: 580px; background: #1f2937; border: 2px solid #ef4444; border-radius: 12px; padding: 35px; box-shadow: 0 20px 50px rgba(0,0,0,0.9);">
                    <div style="font-size: 48px; margin-bottom: 12px;">🚫</div>
                    <h2 style="color: #f87171; margin-top: 0; font-size: 22px;">TÀI KHOẢN ĐÃ BỊ KHÓA TRUY CẬP</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: #e5e7eb; margin: 15px 0;">
                        Bạn đã vi phạm quy định chụp màn hình quá số lần cho phép (<strong>${currentViolations}/${config.maxViolations}</strong>).<br>${scopeMsg}
                    </p>
                    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; padding: 12px; border-radius: 8px; color: #fca5a5; font-size: 14px;">
                        Vui lòng liên hệ Giảng viên phụ trách để được mở khóa.
                    </div>
                </div>
            `;
            document.querySelectorAll('#page-content, #region-main, iframe').forEach(el => el.remove());
        } else {
            const counterHtml = config.maxViolations > 0
                ? `<div id="antishot-counter-container" style="margin-top: 15px; color: #fbbf24; font-size: 16px; font-weight: bold;">Cảnh báo vi phạm: ${currentViolations} / ${config.maxViolations} lần</div>`
                : '';

            if (!document.getElementById('antishot-resume-btn')) {
                shieldOverlay.innerHTML = `
                    <div style="max-width: 550px; background: #1f2937; border: 2px solid #eab308; border-radius: 12px; padding: 35px; box-shadow: 0 20px 50px rgba(0,0,0,0.9);">
                        <div style="font-size: 48px; margin-bottom: 12px;">⚠️</div>
                        <h3 style="color: #facc15; margin-bottom: 12px; font-size: 20px;">${config.warningTitle}</h3>
                        <p style="font-size: 15px; color: #d1d5db; line-height: 1.6; margin-bottom: 15px;">${config.warningSubtitle}</p>
                        <div id="antishot-counter-box">${counterHtml}</div>
                        <button id="antishot-resume-btn" type="button" style="margin-top: 22px; background: #2563eb; color: #fff; border: none; padding: 12px 32px; font-size: 16px; font-weight: bold; border-radius: 8px; cursor: pointer; pointer-events: auto !important;">
                            Tiếp tục học
                        </button>
                    </div>
                `;
            } else {
                document.getElementById('antishot-counter-box').innerHTML = counterHtml;
            }
        }
    }

    // Nếu tài khoản đã bị khóa sẵn từ trước thì hiện màn hình khóa ngay khi vào trang
    if (isLocked) {
        document.body.classList.add('antishot-shield-active');
        updateShieldUI();
    }

    // Bắt sự kiện click vào nút "Tiếp tục học"
    shieldOverlay.addEventListener('click', function(e) {
        const btn = e.target.closest('#antishot-resume-btn');
        if (btn && !isLocked) {
            e.preventDefault();
            e.stopPropagation();
            removeShield();
        }
    }, true);

    function triggerShield(actionType) {
        const now = Date.now();
        if (now < ignoreBlurUntil && !isLocked) return;

        // Bật màn hình đen cảnh báo
        document.body.classList.add('antishot-shield-active');

        if (now - lastViolationTime >= COOLDOWN_MS && !isLocked) {
            lastViolationTime = now;
            currentViolations += 1;

            if (config.maxViolations > 0 && currentViolations >= config.maxViolations) {
                isLocked = true;
            }

            updateShieldUI();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(config.clipboardBlocked).catch(() => {});
            }
            sendViolationLog(actionType);
        } else {
            updateShieldUI();
        }
    }

    function removeShield() {
        if (isLocked) return;
        ignoreBlurUntil = Date.now() + 800;
        // Tắt màn hình đen để học tiếp bình thường
        document.body.classList.remove('antishot-shield-active');
        window.focus();
    }

    function sendViolationLog(actionType) {
        if (!config.ajaxUrl || !config.sesskey || !config.cmid) return;

        const formData = new URLSearchParams();
        formData.append('sesskey', config.sesskey);
        formData.append('cmid', config.cmid);
        formData.append('action', actionType || 'unknown_violation');

        fetch(config.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: formData.toString(),
            keepalive: true
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.currentViolations !== undefined) {
                currentViolations = Math.max(currentViolations, parseInt(data.currentViolations, 10));
                if (data.isLocked || (config.maxViolations > 0 && currentViolations >= config.maxViolations)) {
                    isLocked = true;
                    document.body.classList.add('antishot-shield-active');
                }
                updateShieldUI();
            }
        })
        .catch(() => {});
    }

    // Bắt phím tắt
    function handleKeyEvents(e) {
        const key = (e.key || '').toLowerCase();
        let actionType = '';

        if (e.key === 'PrintScreen' || e.keyCode === 44 || key === 'printscreen') {
            actionType = 'printscreen_key';
        } else if (e.key === 'F12') {
            actionType = 'devtools_f12';
        } else if (e.key === 'Meta' || e.metaKey) {
            actionType = 'win_shortcut_key';
        } else if (e.key === 'Alt' || e.altKey) {
            actionType = 'alt_shortcut_key';
        } else if (e.ctrlKey && e.shiftKey) {
            actionType = 'ctrl_shift_key';
        } else if (e.ctrlKey && ['p', 's', 'u'].includes(key)) {
            actionType = 'ctrl_' + key;
        }

        if (actionType !== '') {
            document.body.classList.add('antishot-shield-active');
            e.preventDefault();
            e.stopPropagation();
            triggerShield(actionType);
            return false;
        }
    }

    window.addEventListener('keydown', handleKeyEvents, true);
    window.addEventListener('keyup', handleKeyEvents, true);

    document.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        triggerShield('right_click');
    }, true);

    window.addEventListener('blur', () => {
        if (Date.now() < ignoreBlurUntil) return;
        const active = document.activeElement;
        if (active && active.tagName === 'IFRAME') return;
        
        document.body.classList.add('antishot-shield-active');
        triggerShield('window_blur_or_snipping');
    }, true);

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            document.body.classList.add('antishot-shield-active');
            triggerShield('tab_hidden');
        }
    }, true);

    // Watermark định danh bản quyền
    if (config.watermark) {
        function createWatermark() {
            if (document.getElementById('antishot-watermark-layer')) return;

            const canvas = document.createElement('canvas');
            canvas.width = 440;
            canvas.height = 220;
            const ctx = canvas.getContext('2d');

            ctx.rotate((-22 * Math.PI) / 180);
            ctx.font = '14px sans-serif';
            ctx.fillStyle = 'rgba(128, 128, 128, 0.28)';
            ctx.fillText(config.userText, 10, 130);
            ctx.fillText(new Date().toLocaleString('vi-VN'), 10, 152);

            const wm = document.createElement('div');
            wm.id = 'antishot-watermark-layer';
            wm.setAttribute('style', [
                'position: fixed !important',
                'inset: 0 !important',
                'pointer-events: none !important',
                'z-index: 2147483640 !important',
                'background-repeat: repeat !important',
                'background-image: url(' + canvas.toDataURL('image/png') + ') !important'
            ].join(';'));

            document.body.appendChild(wm);
        }

        createWatermark();

        const observer = new MutationObserver(() => {
            if (!document.getElementById('antishot-watermark-layer')) {
                createWatermark();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true, attributes: true });
    }
})();
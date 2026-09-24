@php
    $hotelToastMessages = collect();
    $flashTypes = [
        'status' => 'success',
        'success' => 'success',
        'error' => 'error',
        'warning' => 'warning',
    ];

    foreach ($flashTypes as $flashKey => $toastType) {
        $message = session()->has($flashKey)
            ? trim((string) session($flashKey))
            : '';

        if ($message !== '') {
            $hotelToastMessages->push([
                'type' => $toastType,
                'message' => $message,
            ]);
        }
    }

    if (isset($errors) && $errors->any()) {
        $validationMessages = collect($errors->all())
            ->map(static fn ($message) => trim((string) $message))
            ->filter()
            ->unique()
            ->values();

        if ($validationMessages->isNotEmpty()) {
            $hotelToastMessages->push([
                'type' => 'error',
                'title' => $validationMessages->count() === 1 ? 'Validation error' : 'Check the form',
                'message' => $validationMessages->count() === 1
                    ? $validationMessages->first()
                    : 'Please fix: '.$validationMessages->implode(' • '),
            ]);
        }
    }

    $hotelToastMessages = $hotelToastMessages
        ->unique(static fn (array $item) => $item['type'].'|'.$item['message'])
        ->values()
        ->all();
@endphp

<style>
.hotel-toast-region{position:fixed;top:18px;right:18px;z-index:10000;width:min(390px,calc(100vw - 28px));display:grid;gap:10px;pointer-events:none}
.hotel-toast{--toast-accent:#6f675f;display:grid;grid-template-columns:34px minmax(0,1fr) 30px;gap:10px;align-items:start;padding:12px;background:#fff;color:#241f1b;border:1px solid #ddd6cf;border-left:4px solid var(--toast-accent);border-radius:12px;box-shadow:0 18px 44px rgba(35,30,26,.18);pointer-events:auto;animation:hotel-toast-in .18s ease-out}
.hotel-toast[data-type="success"]{--toast-accent:#2f7a3f}.hotel-toast[data-type="error"]{--toast-accent:#9a2727}.hotel-toast[data-type="warning"]{--toast-accent:#9a6418}.hotel-toast[data-type="info"]{--toast-accent:#456f91}
.hotel-toast-icon{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:#f3efeb;color:var(--toast-accent);font-weight:900}
.hotel-toast-copy{min-width:0;padding-top:1px}.hotel-toast-title{display:block;font-size:.82rem;font-weight:900;letter-spacing:.02em;text-transform:capitalize}.hotel-toast-message{display:block;margin-top:3px;color:#5f5750;font-size:.9rem;line-height:1.4;overflow-wrap:anywhere}
.hotel-toast-close{width:30px;height:30px;min-height:30px!important;padding:0!important;border:0!important;border-radius:8px!important;background:transparent!important;color:#6f675f!important;font:inherit!important;font-size:20px!important;line-height:1!important;cursor:pointer}
.hotel-toast-close:hover,.hotel-toast-close:focus-visible{background:#f3efeb!important;color:#241f1b!important;outline:2px solid rgba(142,113,89,.25);outline-offset:1px}
.hotel-toast.is-leaving{animation:hotel-toast-out .16s ease-in forwards}
@keyframes hotel-toast-in{from{opacity:0;transform:translateY(-8px) scale(.985)}to{opacity:1;transform:none}}
@keyframes hotel-toast-out{from{opacity:1;transform:none}to{opacity:0;transform:translateY(-6px) scale(.985)}}
@media(max-width:640px){.hotel-toast-region{top:10px;right:10px;width:calc(100vw - 20px)}}
@media(prefers-reduced-motion:reduce){.hotel-toast,.hotel-toast.is-leaving{animation:none}}
</style>

<div class="hotel-toast-region" data-hotel-toast-region aria-label="Notifications"></div>

<script>
(() => {
    if (window.HotelToast?.__initialized) return;

    const region = document.querySelector('[data-hotel-toast-region]');
    if (!region) return;

    const validTypes = new Set(['success', 'error', 'warning', 'info']);
    const labels = { success: 'Success', error: 'Error', warning: 'Warning', info: 'Notice' };
    const symbols = { success: '✓', error: '!', warning: '!', info: 'i' };
    const activeToasts = new Map();
    const timers = new WeakMap();
    const maxVisible = 4;

    const normalizeType = (type) => validTypes.has(type) ? type : 'info';
    const clearTimer = (toast) => {
        const timer = timers.get(toast);
        if (timer) {
            window.clearTimeout(timer);
            timers.delete(toast);
        }
    };

    const dismiss = (toast) => {
        if (!toast || toast.classList.contains('is-leaving')) return;

        clearTimer(toast);
        if (toast.dataset.toastKey) {
            activeToasts.delete(toast.dataset.toastKey);
        }

        toast.classList.add('is-leaving');
        window.setTimeout(() => toast.remove(), 180);
    };

    const scheduleDismiss = (toast, duration) => {
        clearTimer(toast);
        if (duration <= 0) return;

        timers.set(toast, window.setTimeout(() => dismiss(toast), duration));
    };

    const messageFromPayload = (payload, fallback = 'Something went wrong. Please try again.') => {
        const errors = payload?.errors;
        if (errors && typeof errors === 'object') {
            for (const value of Object.values(errors)) {
                const messages = Array.isArray(value) ? value : [value];
                const first = messages.find((message) => typeof message === 'string' && message.trim() !== '');
                if (first) return first.trim();
            }
        }

        if (typeof payload?.message === 'string' && payload.message.trim() !== '') {
            return payload.message.trim();
        }

        return fallback;
    };

    const show = (message, type = 'info', options = {}) => {
        const safeMessage = String(message ?? '').trim();
        if (!safeMessage) return null;

        type = normalizeType(type);
        const titleText = String(options.title || labels[type]).trim() || labels[type];
        const toastKey = type + '|' + titleText + '|' + safeMessage;
        const duration = Number.isFinite(options.duration)
            ? Math.max(0, options.duration)
            : (type === 'error' ? 8000 : 5000);

        const existing = activeToasts.get(toastKey);
        if (existing && existing.isConnected && !existing.classList.contains('is-leaving')) {
            scheduleDismiss(existing, duration);
            return existing;
        }

        while (region.children.length >= maxVisible) {
            const removable = [...region.children].find(
                (candidate) => !candidate.matches(':hover') && !candidate.contains(document.activeElement)
            );
            if (!removable) break;

            dismiss(removable);
            removable.remove();
        }

        const toast = document.createElement('div');
        toast.className = 'hotel-toast';
        toast.dataset.type = type;
        toast.dataset.toastKey = toastKey;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');

        const icon = document.createElement('span');
        icon.className = 'hotel-toast-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = symbols[type];

        const copy = document.createElement('span');
        copy.className = 'hotel-toast-copy';

        const title = document.createElement('strong');
        title.className = 'hotel-toast-title';
        title.textContent = titleText;

        const body = document.createElement('span');
        body.className = 'hotel-toast-message';
        body.textContent = safeMessage;

        const close = document.createElement('button');
        close.className = 'hotel-toast-close';
        close.type = 'button';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.textContent = '×';
        close.addEventListener('click', () => dismiss(toast));

        copy.append(title, body);
        toast.append(icon, copy, close);
        region.appendChild(toast);
        activeToasts.set(toastKey, toast);

        const resumeDismiss = () => {
            if (toast.matches(':hover') || toast.contains(document.activeElement)) return;
            scheduleDismiss(toast, Math.min(duration, 2500));
        };

        toast.addEventListener('mouseenter', () => clearTimer(toast));
        toast.addEventListener('mouseleave', resumeDismiss);
        toast.addEventListener('focusin', () => clearTimer(toast));
        toast.addEventListener('focusout', resumeDismiss);
        scheduleDismiss(toast, duration);

        return toast;
    };

    window.HotelToast = {
        __initialized: true,
        show,
        success: (message, options = {}) => show(message, 'success', options),
        error: (message, options = {}) => show(message, 'error', options),
        warning: (message, options = {}) => show(message, 'warning', options),
        info: (message, options = {}) => show(message, 'info', options),
        messageFromPayload,
        errorFromPayload: (payload, fallback, options = {}) => {
            const message = messageFromPayload(payload, fallback);
            show(message, 'error', options);
            return message;
        },
        dismissAll: () => [...region.children].forEach(dismiss),
    };

    const initialMessages = {{ Illuminate\Support\Js::from($hotelToastMessages) }};
    initialMessages.forEach((item, index) => {
        window.setTimeout(
            () => show(item.message, item.type, item.title ? { title: item.title } : {}),
            index * 90
        );
    });
})();
</script>

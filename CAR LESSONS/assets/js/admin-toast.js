(function () {
    var queue = Array.isArray(window.adminToastQueue) ? window.adminToastQueue : [];
    var stack = document.querySelector('.admin-toast-stack');

    function ensureStack() {
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'admin-toast-stack';
            document.body.appendChild(stack);
        }
        return stack;
    }

    function iconByType(type) {
        if (type === 'success') {
            return 'fa-solid fa-circle-check';
        }
        if (type === 'error') {
            return 'fa-solid fa-circle-xmark';
        }
        if (type === 'warning') {
            return 'fa-solid fa-triangle-exclamation';
        }
        return 'fa-solid fa-circle-info';
    }

    function normalizeType(type) {
        var value = (type || 'info').toString().toLowerCase().trim();
        if (value === 'danger') {
            return 'error';
        }
        if (value !== 'success' && value !== 'error' && value !== 'warning' && value !== 'info') {
            return 'info';
        }
        return value;
    }

    function hideToast(toast) {
        if (!toast || toast.classList.contains('hide')) {
            return;
        }
        toast.classList.remove('show');
        toast.classList.add('hide');
        setTimeout(function () {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 340);
    }

    function showToast(options) {
        var type = normalizeType(options && options.type);
        var message = options && options.message ? String(options.message) : '';
        if (!message.trim()) {
            return;
        }

        var duration = Number(options && options.duration) || 5200;
        var toast = document.createElement('div');
        toast.className = 'admin-toast ' + type;

        var icon = document.createElement('span');
        icon.className = 'icon';
        icon.innerHTML = '<i class="' + iconByType(type) + '"></i>';

        var content = document.createElement('div');
        content.className = 'content';
        content.textContent = message;

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'close';
        close.setAttribute('aria-label', 'Close notification');
        close.innerHTML = '<i class="fa-solid fa-xmark"></i>';

        var progress = document.createElement('div');
        progress.className = 'progress';

        toast.appendChild(icon);
        toast.appendChild(content);
        toast.appendChild(close);
        toast.appendChild(progress);
        ensureStack().appendChild(toast);

        requestAnimationFrame(function () {
            toast.classList.add('show');
        });

        var start = Date.now();
        var remaining = duration;
        var timer = null;
        var paused = false;
        var hidden = false;

        function updateProgress() {
            var elapsed = duration - remaining;
            var ratio = Math.max(0, Math.min(1, elapsed / duration));
            progress.style.transform = 'scaleX(' + (1 - ratio) + ')';
        }

        function schedule() {
            clearTimeout(timer);
            timer = setTimeout(function () {
                hidden = true;
                hideToast(toast);
            }, remaining);
        }

        function pause() {
            if (paused || hidden) {
                return;
            }
            paused = true;
            clearTimeout(timer);
            remaining -= Date.now() - start;
            if (remaining < 0) {
                remaining = 0;
            }
            updateProgress();
        }

        function resume() {
            if (!paused || hidden) {
                return;
            }
            paused = false;
            start = Date.now();
            updateProgress();
            schedule();
        }

        close.addEventListener('click', function () {
            hidden = true;
            clearTimeout(timer);
            hideToast(toast);
        });

        toast.addEventListener('mouseenter', pause);
        toast.addEventListener('mouseleave', resume);

        schedule();
    }

    window.AdminToast = {
        show: showToast
    };

    if (queue.length > 0) {
        for (var i = 0; i < queue.length; i += 1) {
            showToast(queue[i]);
        }
    }
})();

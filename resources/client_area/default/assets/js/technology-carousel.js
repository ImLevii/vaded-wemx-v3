const carousels = new Map();

function initializeTechnologyCarousels() {
    document.querySelectorAll('.vh-technology-viewport').forEach((viewport) => {
        if (carousels.has(viewport)) return;

        const track = viewport.querySelector('.vh-technology-track');
        const group = track?.querySelector('.vh-technology-group');
        if (!group) return;

        const controller = new AbortController();
        const options = { signal: controller.signal };
        const motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
        let drag = null;

        const getAnimation = () => track.getAnimations().find((animation) => animation.animationName === 'vh-technology-scroll');
        const wrapTime = (time, duration) => ((time % duration) + duration) % duration;

        const finishDrag = () => {
            if (!drag) return;

            const { animation, pointerId } = drag;
            drag = null;
            viewport.classList.remove('is-dragging');
            if (viewport.hasPointerCapture(pointerId)) viewport.releasePointerCapture(pointerId);
            if (!motionPreference.matches) animation.play();
        };

        viewport.addEventListener('pointerdown', (event) => {
            if (!event.isPrimary || event.button !== 0 || drag) return;

            const animation = getAnimation();
            const duration = animation?.effect.getTiming().duration;
            const width = group.getBoundingClientRect().width;
            if (!animation || typeof duration !== 'number' || duration <= 0 || width <= 0) return;

            const startTime = animation.currentTime ?? 0;
            animation.pause();
            animation.currentTime = startTime;
            drag = { animation, duration, width, pointerId: event.pointerId, startX: event.clientX, startTime };
            viewport.setPointerCapture(event.pointerId);
            viewport.classList.add('is-dragging');
            if (event.pointerType === 'mouse') event.preventDefault();
        }, options);

        viewport.addEventListener('pointermove', (event) => {
            if (!drag || event.pointerId !== drag.pointerId) return;

            const distance = event.clientX - drag.startX;
            drag.animation.currentTime = wrapTime(drag.startTime - distance / drag.width * drag.duration, drag.duration);
        }, options);

        ['pointerup', 'pointercancel', 'lostpointercapture'].forEach((eventName) => {
            viewport.addEventListener(eventName, (event) => {
                if (event.pointerId === drag?.pointerId) finishDrag();
            }, options);
        });

        viewport.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight'].includes(event.key) || event.altKey || event.ctrlKey || event.metaKey || drag) return;

            const animation = getAnimation();
            const duration = animation?.effect.getTiming().duration;
            const width = group.getBoundingClientRect().width;
            if (!animation || typeof duration !== 'number' || duration <= 0 || width <= 0) return;

            event.preventDefault();
            const direction = event.key === 'ArrowRight' ? 1 : -1;
            animation.currentTime = wrapTime((animation.currentTime ?? 0) + direction * 208 / width * duration, duration);
        }, options);

        window.addEventListener('resize', finishDrag, options);
        window.addEventListener('blur', finishDrag, options);
        motionPreference.addEventListener('change', finishDrag, options);
        carousels.set(viewport, () => {
            finishDrag();
            controller.abort();
        });
    });
}

document.addEventListener('livewire:navigating', () => {
    carousels.forEach((cleanup) => cleanup());
    carousels.clear();
});
document.addEventListener('livewire:navigated', initializeTechnologyCarousels);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeTechnologyCarousels, { once: true });
} else {
    initializeTechnologyCarousels();
}

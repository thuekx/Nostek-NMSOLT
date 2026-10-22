<template>
    <div class="nos-aurora" aria-hidden="true">
        <div class="nos-aurora__blob nos-aurora__blob--1"></div>
        <div class="nos-aurora__blob nos-aurora__blob--2"></div>
        <div class="nos-aurora__blob nos-aurora__blob--3"></div>
        <div class="nos-aurora__blob nos-aurora__blob--4"></div>
        <div class="nos-aurora__vignette"></div>
    </div>
</template>

<style scoped>
.nos-aurora {
    position: fixed;
    inset: 0;
    z-index: -1;
    overflow: hidden;
    pointer-events: none;
}

.nos-aurora__blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(90px);
    opacity: 0.5;
    mix-blend-mode: screen;
    will-change: transform;
}

.nos-aurora__blob--1 {
    width: 48vw;
    height: 48vw;
    top: -12vh;
    left: -6vw;
    background: radial-gradient(circle at 35% 35%, rgba(56, 189, 248, 0.95), rgba(56, 189, 248, 0) 70%);
    animation: nos-aurora-1 27s ease-in-out infinite;
}

.nos-aurora__blob--2 {
    width: 54vw;
    height: 54vw;
    top: -18vh;
    right: -10vw;
    background: radial-gradient(circle at 50% 50%, rgba(14, 165, 233, 0.85), rgba(14, 165, 233, 0) 70%);
    animation: nos-aurora-2 33s ease-in-out infinite;
}

.nos-aurora__blob--3 {
    width: 42vw;
    height: 42vw;
    bottom: -16vh;
    left: 16vw;
    background: radial-gradient(circle at 50% 50%, rgba(45, 212, 191, 0.7), rgba(45, 212, 191, 0) 70%);
    animation: nos-aurora-3 31s ease-in-out infinite;
}

.nos-aurora__blob--4 {
    width: 40vw;
    height: 40vw;
    top: 28vh;
    left: 38vw;
    background: radial-gradient(circle at 50% 50%, rgba(59, 130, 246, 0.6), rgba(59, 130, 246, 0) 70%);
    animation: nos-aurora-4 39s ease-in-out infinite;
}

/* Soft dark frame so edges stay calm and content keeps contrast. */
.nos-aurora__vignette {
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 120% 120% at 50% 35%, transparent 40%, rgba(3, 7, 15, 0.55) 100%);
}

@keyframes nos-aurora-1 {
    0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
    50%      { transform: translate3d(8vw, 10vh, 0) scale(1.15); }
}

@keyframes nos-aurora-2 {
    0%, 100% { transform: translate3d(0, 0, 0) scale(1.05); }
    50%      { transform: translate3d(-10vw, 12vh, 0) scale(0.9); }
}

@keyframes nos-aurora-3 {
    0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
    50%      { transform: translate3d(12vw, -8vh, 0) scale(1.2); }
}

@keyframes nos-aurora-4 {
    0%, 100% { transform: translate3d(0, 0, 0) scale(0.95); }
    50%      { transform: translate3d(-14vw, -10vh, 0) scale(1.1); }
}

@media (prefers-reduced-motion: reduce) {
    .nos-aurora__blob {
        animation: none;
    }
}
</style>

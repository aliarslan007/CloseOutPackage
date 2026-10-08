import notiSoundUrl from '@/assets/sounds/noti.mp3';

let audioContext: AudioContext | null = null;

function getAudioContext(): AudioContext | null {
    if (typeof window === 'undefined') {
        return null;
    }

    if (!audioContext) {
        const AudioCtx =
            window.AudioContext ||
            (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
        if (AudioCtx) {
            audioContext = new AudioCtx();
        }
    }

    if (audioContext && audioContext.state === 'suspended') {
        void audioContext.resume();
    }

    return audioContext;
}

function unlockAudio(): void {
    const ctx = getAudioContext();
    if (ctx && ctx.state === 'suspended') {
        void ctx.resume();
    }

    try {
        const temp = new Audio(notiSoundUrl);
        temp.volume = 0.01;
        const playPromise = temp.play();
        if (playPromise !== undefined) {
            playPromise
                .then(() => {
                    temp.pause();
                })
                .catch(() => {
                    // Ignore autoplay deferral
                });
        }
    } catch {
        // Ignore unlock errors
    }
}

if (typeof window !== 'undefined') {
    const opts = { once: true };
    window.addEventListener('click', unlockAudio, opts);
    window.addEventListener('keydown', unlockAudio, opts);
    window.addEventListener('touchstart', unlockAudio, opts);
}

interface ToneSpec {
    /** Start frequency, Hz. */
    start: number;
    /** Sweep target, Hz. */
    end: number;
    /** Seconds spent sweeping start -> end. */
    glide: number;
    /** Total seconds including the volume decay. */
    duration: number;
    /** Peak gain, 0..1. */
    volume: number;
    /** Timbre: sine is soft/musical, triangle is clickier (a "tick"). */
    type?: OscillatorType;
}

/**
 * One Web Audio oscillator + gain envelope. Every synthesized cue routes
 * through here, so adding a sound is a data change (a ToneSpec), not a new
 * copy-pasted block.
 */
function playTone({ start, end, glide, duration, volume, type = 'sine' }: ToneSpec): void {
    const ctx = getAudioContext();
    if (!ctx) {
        return;
    }

    try {
        const now = ctx.currentTime;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = type;
        osc.frequency.setValueAtTime(start, now);
        osc.frequency.exponentialRampToValueAtTime(end, now + Math.min(glide, duration));

        gain.gain.setValueAtTime(volume, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + duration);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(now);
        osc.stop(now + duration);
    } catch {
        // Ignore fallback errors
    }
}

const NOTIFICATION_CHIME: ToneSpec = {
    start: 659.25,
    end: 880,
    glide: 0.12,
    duration: 0.45,
    volume: 0.2,
};

// A short, sharp downward click - a "tick", deliberately NOT the incoming
// notification chime. Bright start, fast fall, quick decay = percussive, not melodic.
const MESSAGE_SENT_TICK: ToneSpec = {
    type: 'triangle',
    start: 1046.5,
    end: 740,
    glide: 0.03,
    duration: 0.09,
    volume: 0.3,
};

function playSynthesizedChime(): void {
    playTone(NOTIFICATION_CHIME);
}

/**
 * Plays the "message sent" tick. Call it only from a confirmed-success path
 * (e.g. an Inertia onSuccess), never optimistically on submit.
 */
export function playMessageSentSound(): void {
    if (typeof window === 'undefined') {
        return;
    }

    playTone(MESSAGE_SENT_TICK);
}

/**
 * Plays the notification sound effect.
 */
export function playNotificationSound(): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        const audio = new Audio(notiSoundUrl);
        audio.volume = 0.85;

        const playPromise = audio.play();
        if (playPromise !== undefined) {
            playPromise.catch(() => {
                // If HTML5 Audio fails or is blocked by browser policy, fallback to Web Audio API chime
                playSynthesizedChime();
            });
        }
    } catch {
        playSynthesizedChime();
    }
}

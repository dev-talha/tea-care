<?php
use App\Core\Session;
use App\Services\ScheduleService;

$monkeyToday = ScheduleService::today();
$showMonkey = (int)($user['show_funny_monkey'] ?? 1) === 1
    && (($user['monkey_dismissed_date'] ?? '') !== $monkeyToday);
?>
<?php if ($showMonkey): ?>
<style>
@media (max-width: 767px) {
    #tc-monkey-pet:not(.tc-monkey-dragged) {
        bottom: calc(4.75rem + env(safe-area-inset-bottom));
    }
}
@media (min-width: 768px) {
    #tc-monkey-dock {
        display: flex;
        justify-content: flex-end;
        margin-top: 1rem;
    }
    #tc-monkey-pet:not(.tc-monkey-dragged) {
        position: static !important;
        right: auto !important;
        bottom: auto !important;
        left: auto !important;
        top: auto !important;
    }
}
</style>
<div id="tc-monkey-pet"
     data-today="<?= e($monkeyToday) ?>"
     data-csrf="<?= e(Session::csrfToken()) ?>"
     class="fixed bottom-20 right-3 z-30 select-none touch-none hidden"
     style="width: 112px;"
     aria-label="TeaCare Funny Monkey Companion">

    <!-- Playful Speech / Thought Micro-Bubble -->
    <div id="tc-monkey-bubble"
         class="mb-1 px-2.5 py-1 rounded-xl bg-white/95 backdrop-blur border border-amberwarm-400/50 shadow-md text-[10px] font-bold text-slate-700 text-center leading-tight opacity-0 translate-y-1 transition-all duration-200 pointer-events-none">
    </div>

    <!-- Interactive Character Box -->
    <div class="relative flex flex-col items-center">
        <!-- Close Button (Hides for Today) -->
        <button id="tc-monkey-close"
                type="button"
                title="Hide monkey for today (Manage in Settings)"
                aria-label="Hide monkey for today"
                class="absolute -top-1.5 -right-1 z-10 w-6 h-6 rounded-full bg-white/95 border border-stone-300 text-slate-500 hover:text-rose-600 hover:border-rose-300 shadow-sm flex items-center justify-center transition">
            <i class="ti ti-x text-xs"></i>
        </button>

        <!-- Drag & Tap Character Stage -->
        <div id="tc-monkey-drag"
             title="Drag to move anywhere • Tap for surprise trick!"
             class="cursor-grab active:cursor-grabbing rounded-2xl p-1 transition-transform hover:scale-105">
            <svg id="tc-monkey-svg" class="w-24 h-24 overflow-visible drop-shadow-sm" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Tail -->
                <g id="m-tail-group">
                    <path id="m-tail" d="M30 88 C12 88, 8 64, 22 58 C30 54, 28 66, 22 65" stroke="#7C4A26" stroke-width="5.5" stroke-linecap="round" fill="none"/>
                </g>

                <!-- Body -->
                <ellipse cx="56" cy="84" rx="22" ry="20" fill="#8B5A2B" stroke="#5C3818" stroke-width="2.2"/>
                <ellipse cx="56" cy="86" rx="13" ry="13" fill="#F6D7B0"/>

                <!-- Feet -->
                <ellipse cx="42" cy="103" rx="8" ry="4.5" fill="#7C4A26" stroke="#5C3818" stroke-width="1.8"/>
                <ellipse cx="70" cy="103" rx="8" ry="4.5" fill="#7C4A26" stroke="#5C3818" stroke-width="1.8"/>

                <!-- Head Group -->
                <g id="m-head-group">
                    <!-- Ears -->
                    <g id="m-ears">
                        <circle cx="29" cy="48" r="10" fill="#8B5A2B" stroke="#5C3818" stroke-width="2"/>
                        <circle cx="29" cy="48" r="5.5" fill="#F6D7B0"/>
                        <circle cx="83" cy="48" r="10" fill="#8B5A2B" stroke="#5C3818" stroke-width="2"/>
                        <circle cx="83" cy="48" r="5.5" fill="#F6D7B0"/>
                    </g>

                    <!-- Office Headset Overlay (Work Mode) -->
                    <g id="m-fx-headset" class="hidden">
                        <path d="M28 44 C28 19, 84 19, 84 44" stroke="#1E293B" stroke-width="3.5" fill="none" stroke-linecap="round"/>
                        <rect x="23" y="40" width="7" height="14" rx="3" fill="#0F172A" stroke="#64748B" stroke-width="1.2"/>
                        <rect x="82" y="40" width="7" height="14" rx="3" fill="#0F172A" stroke="#64748B" stroke-width="1.2"/>
                        <path d="M84 52 C82 59, 72 60, 66 59" stroke="#334155" stroke-width="1.8" stroke-linecap="round" fill="none"/>
                    </g>

                    <!-- Head Fur & Tuft -->
                    <path d="M51 22 C52 14, 59 14, 58 22 C63 17, 66 20, 62 25" stroke="#5C3818" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                    <ellipse cx="56" cy="48" rx="24" ry="22" fill="#8B5A2B" stroke="#5C3818" stroke-width="2.2"/>

                    <!-- Cream Face Mask -->
                    <circle cx="47" cy="44" r="11" fill="#F6D7B0"/>
                    <circle cx="65" cy="44" r="11" fill="#F6D7B0"/>
                    <ellipse cx="56" cy="56" rx="17" ry="12" fill="#F6D7B0"/>

                    <!-- Blush Cheeks -->
                    <ellipse cx="39" cy="54" rx="4" ry="2.4" fill="#FB7185" opacity="0.45"/>
                    <ellipse cx="73" cy="54" rx="4" ry="2.4" fill="#FB7185" opacity="0.45"/>

                    <!-- Normal Eyes -->
                    <g id="m-eyes-open">
                        <circle cx="47" cy="44" r="4.2" fill="#FFFFFF"/>
                        <circle cx="47.5" cy="44" r="2.7" fill="#1E293B"/>
                        <circle cx="48.5" cy="42.8" r="1" fill="#FFFFFF"/>
                        <circle cx="65" cy="44" r="4.2" fill="#FFFFFF"/>
                        <circle cx="65.5" cy="44" r="2.7" fill="#1E293B"/>
                        <circle cx="66.5" cy="42.8" r="1" fill="#FFFFFF"/>
                    </g>

                    <!-- Happy / Closed / Zen Eyes -->
                    <g id="m-eyes-happy" class="hidden">
                        <path d="M42.5 44.5 C44.5 40.5, 49.5 40.5, 51.5 44.5" stroke="#3F230E" stroke-width="2.4" stroke-linecap="round"/>
                        <path d="M60.5 44.5 C62.5 40.5, 67.5 40.5, 69.5 44.5" stroke="#3F230E" stroke-width="2.4" stroke-linecap="round"/>
                    </g>

                    <!-- Wink Eyes -->
                    <g id="m-eyes-wink" class="hidden">
                        <circle cx="47" cy="44" r="4.2" fill="#FFFFFF"/>
                        <circle cx="47.5" cy="44" r="2.7" fill="#1E293B"/>
                        <circle cx="48.5" cy="42.8" r="1" fill="#FFFFFF"/>
                        <path d="M60.5 44.5 C62.5 41, 67.5 41, 69.5 44.5" stroke="#3F230E" stroke-width="2.4" stroke-linecap="round"/>
                    </g>

                    <!-- Sleepy Eyes -->
                    <g id="m-eyes-sleepy" class="hidden">
                        <path d="M42.5 45 C45 47.5, 49 47.5, 51.5 45" stroke="#3F230E" stroke-width="2.3" stroke-linecap="round"/>
                        <path d="M60.5 45 C63 47.5, 67 47.5, 69.5 45" stroke="#3F230E" stroke-width="2.3" stroke-linecap="round"/>
                    </g>

                    <!-- Sad / Teary Puppy Eyes -->
                    <g id="m-eyes-sad" class="hidden">
                        <circle cx="47" cy="45" r="4" fill="#FFFFFF"/>
                        <circle cx="47" cy="45.8" r="2.6" fill="#1E293B"/>
                        <circle cx="48.2" cy="44.3" r="1.1" fill="#FFFFFF"/>
                        <circle cx="65" cy="45" r="4" fill="#FFFFFF"/>
                        <circle cx="65" cy="45.8" r="2.6" fill="#1E293B"/>
                        <circle cx="66.2" cy="44.3" r="1.1" fill="#FFFFFF"/>
                        <path d="M42 39 L51 37" stroke="#3F230E" stroke-width="2" stroke-linecap="round"/>
                        <path d="M70 39 L61 37" stroke="#3F230E" stroke-width="2" stroke-linecap="round"/>
                        <path d="M43 49 C41.5 52, 42.5 54, 44 53 C45 52, 44 49.5, 43 49Z" fill="#38BDF8"/>
                    </g>

                    <!-- Squint / Inspecting Eyes -->
                    <g id="m-eyes-squint" class="hidden">
                        <path d="M42 42 L51 44.5 L42 47" stroke="#3F230E" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="65" cy="44.5" r="4.3" fill="#FFFFFF"/>
                        <circle cx="66.2" cy="45.2" r="2.3" fill="#1E293B"/>
                    </g>

                    <!-- Sugar Rush Starry Eyes -->
                    <g id="m-eyes-star" class="hidden">
                        <path d="M47 39.5 L48.3 42.7 L51.5 44 L48.3 45.3 L47 48.5 L45.7 45.3 L42.5 44 L45.7 42.7 Z" fill="#F59E0B" stroke="#78350F" stroke-width="0.9"/>
                        <path d="M65 39.5 L66.3 42.7 L69.5 44 L66.3 45.3 L65 48.5 L63.7 45.3 L60.5 44 L63.7 42.7 Z" fill="#F59E0B" stroke="#78350F" stroke-width="0.9"/>
                    </g>

                    <!-- Cool Sunglasses Overlay -->
                    <g id="m-fx-shades" class="hidden">
                        <rect x="38.5" y="39" width="16" height="9.5" rx="3" fill="#0F172A" stroke="#334155" stroke-width="1.4"/>
                        <rect x="57.5" y="39" width="16" height="9.5" rx="3" fill="#0F172A" stroke="#334155" stroke-width="1.4"/>
                        <line x1="54.5" y1="42.5" x2="57.5" y2="42.5" stroke="#334155" stroke-width="2"/>
                        <line x1="41" y1="41" x2="46" y2="46" stroke="#94A3B8" stroke-width="1.2" opacity="0.6"/>
                        <line x1="60" y1="41" x2="65" y2="46" stroke="#94A3B8" stroke-width="1.2" opacity="0.6"/>
                    </g>

                    <!-- Nose -->
                    <circle cx="53.5" cy="51.5" r="1.3" fill="#5C3818"/>
                    <circle cx="58.5" cy="51.5" r="1.3" fill="#5C3818"/>

                    <!-- Mouth Variants -->
                    <path id="m-mouth-smile" d="M49 58 C52.5 62.5, 59.5 62.5, 63 58" stroke="#451A03" stroke-width="2.3" stroke-linecap="round"/>
                    <ellipse id="m-mouth-open" class="hidden" cx="56" cy="59.5" rx="4.2" ry="3.4" fill="#7F1D1D" stroke="#451A03" stroke-width="1.6"/>
                    <path id="m-mouth-sad" class="hidden" d="M49.5 62 C53 58, 59 58, 62.5 62" stroke="#451A03" stroke-width="2.3" stroke-linecap="round"/>
                    <g id="m-mouth-big" class="hidden">
                        <path d="M47.5 57.5 C51 64.5, 61 64.5, 64.5 57.5 Z" fill="#991B1B" stroke="#451A03" stroke-width="1.8"/>
                        <path d="M52 62 C54 60, 58 60, 60 62" fill="#FDA4AF"/>
                    </g>
                    <!-- Hot Tongue Out -->
                    <g id="m-mouth-tongue" class="hidden">
                        <ellipse cx="56" cy="58.5" rx="5.5" ry="2.8" fill="#7F1D1D" stroke="#451A03" stroke-width="1.5"/>
                        <path d="M52.5 59 C52.5 65.5, 59.5 65.5, 59.5 59 Z" fill="#FB7185" stroke="#BE123C" stroke-width="1.2"/>
                    </g>
                </g>

                <!-- Left Arm + Optional Biscuit / Sugar Prop -->
                <g id="m-arm-left">
                    <path d="M36 74 C24 72, 21 86, 30 89" stroke="#8B5A2B" stroke-width="7.5" stroke-linecap="round" fill="none"/>
                    <circle cx="30" cy="89" r="4.5" fill="#F6D7B0" stroke="#5C3818" stroke-width="1.5"/>
                    <!-- Crispy Cookie/Biscuit Prop -->
                    <g id="m-prop-biscuit" class="hidden" transform="translate(15, 76)">
                        <rect x="0" y="0" width="13" height="10" rx="2.5" fill="#F59E0B" stroke="#92400E" stroke-width="1.5"/>
                        <circle cx="4" cy="4" r="0.9" fill="#78350F"/>
                        <circle cx="9" cy="4" r="0.9" fill="#78350F"/>
                        <circle cx="6.5" cy="7" r="0.9" fill="#78350F"/>
                    </g>
                    <!-- Sugar Cube Prop -->
                    <g id="m-prop-sugar" class="hidden" transform="translate(17, 75)">
                        <rect x="0" y="0" width="10" height="10" rx="2" fill="#FFFFFF" stroke="#94A3B8" stroke-width="1.4"/>
                        <circle cx="3.5" cy="3.5" r="0.8" fill="#CBD5E1"/>
                        <circle cx="6.8" cy="6.8" r="0.8" fill="#CBD5E1"/>
                    </g>
                </g>

                <!-- Right Arm + Interactive Coffee/Tea Cup -->
                <g id="m-arm-right-cup">
                    <path d="M76 74 C86 73, 91 79, 86 84" stroke="#8B5A2B" stroke-width="7.5" stroke-linecap="round" fill="none"/>
                    <g id="m-cup-group" transform="translate(81, 68)">
                        <rect x="0" y="4" width="18" height="16" rx="4" fill="#FFFFFF" stroke="#B45309" stroke-width="2"/>
                        <path d="M18 8 C23 8, 23 15, 18 15" stroke="#B45309" stroke-width="2" stroke-linecap="round" fill="none"/>
                        <ellipse id="m-cup-liquid" cx="9" cy="5.5" rx="6.5" ry="2.2" fill="#92400E"/>
                        <circle cx="9" cy="12" r="2.2" fill="#2D6A4F" opacity="0.85"/>
                        <g id="m-cup-steam">
                            <path d="M5 1 C4 -2.5, 7.5 -3.5, 6.5 -7" stroke="#D97706" stroke-width="1.7" stroke-linecap="round"/>
                            <path d="M12 1 C11 -2.5, 14.5 -3.5, 13.5 -7" stroke="#D97706" stroke-width="1.7" stroke-linecap="round"/>
                        </g>
                    </g>
                    <circle cx="84" cy="83" r="4.3" fill="#F6D7B0" stroke="#5C3818" stroke-width="1.5"/>
                </g>

                <!-- FX Overlays: Zzz, Sparkles, Question, Heart, Music, Drops, Heat -->
                <g id="m-fx-zzz" class="hidden">
                    <path d="M84 26 H92 L85 34 H93" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M95 15 H101 L95.5 21 H101.5" stroke="#F59E0B" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </g>
                <g id="m-fx-sparks" class="hidden">
                    <path d="M18 22 L20 27 L25 29 L20 31 L18 36 L16 31 L11 29 L16 27 Z" fill="#F59E0B"/>
                    <path d="M95 20 L97 24 L101 26 L97 28 L95 32 L93 28 L89 26 L93 24 Z" fill="#10B981"/>
                    <path d="M26 12 L28 15 L31 16 L28 17 L26 20 L24 17 L21 16 L24 15 Z" fill="#3B82F6"/>
                </g>
                <g id="m-fx-question" class="hidden">
                    <path d="M88 22 C88 18, 95 18, 95 22 C95 25, 91 25, 91 28" stroke="#E11D48" stroke-width="2.3" stroke-linecap="round" fill="none"/>
                    <circle cx="91" cy="32.5" r="1.5" fill="#E11D48"/>
                </g>
                <g id="m-fx-heart" class="hidden">
                    <path d="M92 26 C92 23, 87 22, 87 25.5 C87 28.5, 92 31.5, 92 31.5 C92 31.5, 97 28.5, 97 25.5 C97 22, 92 23, 92 26 Z" fill="#F43F5E"/>
                </g>
                <g id="m-fx-music" class="hidden">
                    <path d="M88 28 V16 L97 14 V25" stroke="#0D9488" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <circle cx="85.5" cy="28" r="2.7" fill="#0D9488"/>
                    <circle cx="94.5" cy="25.5" r="2.7" fill="#0D9488"/>
                </g>
                <g id="m-fx-drops" class="hidden">
                    <path d="M96 92 C94 96, 95 99, 97.5 98.5 C99.5 98, 98 94, 96 92 Z" fill="#92400E"/>
                    <path d="M89 96 C87 100, 88 103, 90.5 102.5 C92 102, 91 98, 89 96 Z" fill="#B45309"/>
                </g>
                <g id="m-fx-heat" class="hidden">
                    <path d="M16 32 C14 27, 19 25, 17 20" stroke="#EF4444" stroke-width="2" stroke-linecap="round"/>
                    <path d="M96 32 C94 27, 99 25, 97 20" stroke="#EF4444" stroke-width="2" stroke-linecap="round"/>
                </g>
            </svg>
        </div>
    </div>
</div>
<?php endif; ?>

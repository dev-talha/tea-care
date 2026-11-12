<!-- Reusable Employee Personal Cup Lightbox Modal -->
<div id="cup-photo-modal" class="hidden fixed inset-0 z-[70] bg-slate-900/80 backdrop-blur-sm flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="cup-modal-name">
    <div class="bg-white rounded-2xl border border-stone-200 shadow-2xl max-w-md w-full overflow-hidden animate-none">
        <div class="px-4 py-3 border-b border-stone-100 flex items-center justify-between gap-2 bg-cream-50">
            <div class="inline-flex items-center gap-2 text-sm font-bold text-tea-800">
                <i class="ti ti-mug text-lg"></i>
                <span>Employee Cup Photo</span>
            </div>
            <button type="button" data-cup-modal-close="1" class="w-9 h-9 rounded-xl bg-white border border-stone-200 text-slate-600 hover:bg-stone-100 flex items-center justify-center" aria-label="Close popup">
                <i class="ti ti-x text-lg"></i>
            </button>
        </div>

        <div class="p-4 space-y-3.5">
            <div class="rounded-2xl bg-stone-50 border border-stone-200 p-2 flex items-center justify-center">
                <img id="cup-modal-img" src="" alt="Personal Cup" class="w-full max-h-[55vh] object-contain rounded-xl">
            </div>

            <div class="space-y-1">
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <h3 id="cup-modal-name" class="font-extrabold text-base text-slate-800"></h3>
                    <span id="cup-modal-empid" class="hidden px-2 py-0.5 rounded-lg bg-stone-100 text-slate-700 text-xs font-bold"></span>
                </div>
                <div id="cup-modal-loc" class="text-xs font-semibold text-slate-600 flex items-center gap-1.5 flex-wrap"></div>
                <div id="cup-modal-drink" class="hidden rounded-xl bg-tea-50 border border-tea-200 px-3 py-2 text-xs font-bold text-tea-800 mt-2"></div>
            </div>

            <button type="button" data-cup-modal-close="1" class="btn-primary w-full text-sm">
                <i class="ti ti-check text-base"></i>
                <span>Got it, Close</span>
            </button>
        </div>
    </div>
</div>

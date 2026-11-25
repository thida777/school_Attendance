{{--
    Printable Telegram invites: one card per parent with a personal QR code, Khmer first.
    Cut the cards and give them at a parents' meeting: the parent scans, presses Start, and is linked.
    Each code works once (ParentReplies::join).
--}}
@php
    use App\Support\School;
    $km = app()->getLocale() === 'km';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Telegram invites') }}{{ $class ? ' · '.$class->name : '' }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @page { size: A4; margin: 10mm; }
        body { background: #fff; color: #1f2d3a; }
        .sheet { max-width: 210mm; margin: 0 auto; padding: 8mm 4mm; }
        .cards { display: grid; grid-template-columns: 1fr 1fr; gap: 6mm; }
        .invite { break-inside: avoid; border: 1.5px dashed #9fb7b3; border-radius: 6mm; padding: 6mm; text-align: center; }
        .invite .qr { width: 44mm; margin: 3mm auto; }
        .invite .qr svg { width: 100%; height: auto; display: block; }
        @media print { .no-print { display: none !important; } .sheet { padding: 0; } }
    </style>
</head>
<body>
    <div class="no-print bg-base-200 border-base-300 sticky top-0 z-10 border-b">
        <div class="mx-auto flex max-w-[210mm] flex-wrap items-center justify-between gap-2 px-4 py-3">
            <span class="text-sm opacity-70">
                {{ trans_choice(':count invite|:count invites', $cards->count(), ['count' => $cards->count()]) }}
                · {{ __('Cut along the dashed lines and give each parent their card.') }}
            </span>
            <button class="btn btn-primary btn-sm" onclick="window.print()"><i data-lucide="printer" class="size-4"></i>{{ __('Print') }}</button>
        </div>
    </div>

    <div class="sheet">
        @if (! $telegramLive)
            <p class="py-10 text-center opacity-70">{{ __('Telegram is not connected, so there are no invite links.') }}</p>
        @elseif ($cards->isEmpty())
            <p class="py-10 text-center opacity-70">{{ __('Every parent here is already on Telegram.') }}</p>
        @else
            <div class="cards">
                @foreach ($cards as ['guardian' => $guardian, 'link' => $link])
                    <div class="invite">
                        <p class="text-xs font-semibold tracking-wide uppercase opacity-60">{{ School::name('km') }} · {{ School::name('en') }}</p>
                        <p class="mt-1 text-lg font-bold">ភ្ជាប់ជាមួយសាលាតាម Telegram</p>
                        <p class="text-xs opacity-60">Connect with the school on Telegram</p>
                        <p class="mt-2 font-semibold">{{ $guardian->name_km ?: $guardian->name }}@if ($guardian->name_km) · {{ $guardian->name }}@endif</p>
                        <p class="text-sm opacity-70">{{ $guardian->students->map(fn ($s) => $s->name_km.' ('.$s->schoolClass->name.')')->join(', ') }}</p>
                        <div class="qr" data-qr="{{ $link }}" data-qr-label="QR"></div>
                        <ol class="mx-auto max-w-[70mm] space-y-0.5 text-start text-xs">
                            <li>១. បើកកាមេរ៉ាទូរស័ព្ទ ហើយស្កេន QR នេះ។ <span class="opacity-60">Scan with the phone camera.</span></li>
                            <li>២. Telegram នឹងបើកបូតសាលា៖ ចុច <b>Start</b>។ <span class="opacity-60">Press Start.</span></li>
                            <li>៣. រួចរាល់! អ្នកនឹងទទួលព័ត៌មានវត្តមានកូន។ <span class="opacity-60">Done.</span></li>
                        </ol>
                        <p class="mt-2 text-[10px] break-all opacity-50">{{ $link }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>

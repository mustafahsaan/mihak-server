{{-- مقاعد الحملة: $campaign و $seats (قائمة enrollments) --}}
@php
    $list = $seats->values();
    $cells = [];
    for ($i = 0; $i < $campaign->slots; $i++) {
        $s = $list[$i] ?? null;
        if (! $s) {
            $cells[] = ['cls' => '', 'title' => 'مقعد شاغر'];
        } elseif ($s->status === 'active') {
            $cells[] = $s->installed_confirmed_at
                ? ['cls' => 'on', 'title' => 'مختبِر نشط']
                : ['cls' => 'wait', 'title' => 'بانتظار تأكيد التثبيت'];
        } elseif ($s->status === 'completed') {
            $cells[] = ['cls' => 'done', 'title' => 'أكمل الاختبار'];
        } elseif ($s->status === 'removed') {
            $cells[] = ['cls' => 'off', 'title' => 'خرج: '.$s->reasonLabel()];
        } else {
            $cells[] = ['cls' => 'void', 'title' => 'لم يُدفع (لم يكتمل العدد)'];
        }
    }
@endphp
<div class="row" style="gap:10px 14px">
    <div class="seats" role="img" aria-label="المقاعد: {{ $list->where('status', 'active')->count() }} نشط من {{ $campaign->slots }}">
        @foreach ($cells as $i => $cell)
            @if ($i === $campaign->required)<span class="seat-sep" aria-hidden="true"></span>@endif
            <span class="seat {{ $cell['cls'] }}" title="{{ $cell['title'] }}"></span>
        @endforeach
    </div>
    <span class="muted small">{{ $campaign->required }} مطلوبة + {{ $campaign->slots - $campaign->required }} احتياط</span>
</div>

{{-- شريط الأيام: $strip (من CampaignController) و $campaign و $googleDays --}}
@php
    $googleSpan = min($googleDays, $campaign->days);
    $extra = max(0, $campaign->days - $googleDays);
@endphp
<div class="stack" style="gap:4px">
    <div class="strip" style="grid-template-columns:repeat({{ $campaign->days }},minmax(0,1fr))">
        @foreach ($strip as $cell)
            @php
                $cls = $cell['state'] === 'future' ? ($cell['day'] > $googleDays ? 'buf' : '') : $cell['state'];
                $title = 'اليوم '.$cell['day'];
                if (in_array($cell['state'], ['full', 'most', 'low'], true)) {
                    $title .= ': فتحه '.$cell['opened'].' من '.$cell['present'];
                }
            @endphp
            <span class="day {{ $cls }}" title="{{ $title }}">{{ $cell['day'] }}</span>
        @endforeach
    </div>
    <div class="legend" style="grid-template-columns:{{ $googleSpan }}fr{{ $extra ? ' '.$extra.'fr' : '' }}">
        <span class="g">{{ $googleSpan }} يوماً يشترطها Google</span>
        @if ($extra)<span>+{{ $extra }} احتياط</span>@endif
    </div>
</div>

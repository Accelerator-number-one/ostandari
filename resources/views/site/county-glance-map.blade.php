<svg viewBox="0 -20 650 315" role="img" aria-label="موقعیت شهرستان {{ $county['name'] }} در استان مازندران">
    @foreach($ui->data('frontend')['counties'] as $region)
        <path d="{{ $region['shape'] }}" fill="{{ $region['slug']===$county['slug']?'#00477a':'#e5edf5' }}" stroke="#a9bed0" stroke-width="0.8" vector-effect="non-scaling-stroke"/>
    @endforeach
    <text x="{{ $county['label'][0] }}" y="{{ $county['label'][1] }}" fill="#003e68" stroke="#fff" stroke-width="4" paint-order="stroke" font-size="15" font-weight="800" text-anchor="middle" direction="rtl">{{ $county['name'] }}</text>
</svg>
<p>موقعیت شهرستان {{ $county['name'] }}</p>

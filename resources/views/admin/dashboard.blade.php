<section class="portal-admin-content county-dashboard">
@if($context['type']==='county')
<nav class="portal-county-dashboard-actions" aria-label="مدیریت پرتال"><a class="portal-button" href="{{ $context['dashboard_path'] }}/news/new">＋ افزودن خبر</a><a href="{{ $context['dashboard_path'] }}/portal">ظاهر و اطلاعات پرتال</a><a href="{{ $context['public_path'] }}" target="_blank" rel="noopener">مشاهدهٔ سایت فرمانداری ↗</a></nav>
@else
<p class="portal-admin-help">{{ $context['can_write']?'مدیریت محتوای استان و پیگیری خدمات مردمی':'این فضای کاری فقط برای مشاهدهٔ اطلاعات است.' }}</p>
@endif
<div class="county-stats-grid grid gap-4 sm:grid-cols-2 lg:grid-cols-4">@foreach(['محتوا'=>array_sum($stats['contents_by_type']??[]),'منتشرشده'=>$stats['contents_by_status']['published']??0,'گزارش‌های مردمی'=>array_sum($stats['tickets_by_status']??[]),'نیازمند پیگیری'=>$stats['overdue_tickets']??0] as $label=>$value)<article class="county-stat-card rounded-xl border border-slate-200 p-5"><span>{{ $label }}</span><strong class="block text-3xl">{{ $value }}</strong></article>@endforeach</div>
<h2 class="my-6 text-xl font-bold">دسترسی به بخش‌ها</h2><div class="county-module-grid grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach($modules as $entry)@if($entry['showInNavigation'])<a class="county-module-card rounded-xl border border-slate-200 p-4" href="{{ $entry['path'] }}"><strong>{{ $entry['title'] }}</strong>@if($context['type']!=='county')<p>{{ $entry['description'] }}</p>@endif</a>@endif @endforeach</div>
@php
    $contentStatuses = ['draft'=>'پیش‌نویس', 'published'=>'منتشرشده', 'archived'=>'بایگانی‌شده'];
    $reportStatuses = ['pending'=>'در انتظار بررسی', 'review'=>'در حال بررسی', 'referred'=>'ارجاع‌شده', 'resolved'=>'رسیدگی‌شده', 'rejected'=>'ردشده', 'closed'=>'بسته‌شده'];
    $contentTypes = ['news'=>'خبر', 'announcement'=>'اطلاعیه', 'resolution'=>'مصوبه', 'page'=>'صفحه', 'publication'=>'نشریه', 'tender'=>'مناقصه', 'contract'=>'قرارداد', 'call'=>'فراخوان', 'event'=>'رویداد', 'duty'=>'شرح وظایف', 'document'=>'سند'];
@endphp
<div class="portal-dashboard-recent-grid">
    <section class="portal-dashboard-recent" aria-labelledby="dashboard-recent-content-title">
        <header class="portal-dashboard-recent-header">
            <h2 id="dashboard-recent-content-title">آخرین محتواها</h2>
            <span class="portal-dashboard-recent-count">{{ strtr((string) count($stats['recent_contents']??[]), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']) }} مورد</span>
        </header>
        <ul class="portal-dashboard-recent-list">
        @forelse($stats['recent_contents']??[] as $item)
            @php($target=collect($modules)->first(fn($m)=>($m['page']['defaults']['type']??null)===$item['type']))
            <li><a class="portal-dashboard-recent-row" href="{{ $target['path']??$ui->adminHref('/panel/admin/news',$context) }}/{{ $item['id'] }}/edit">
                <span class="portal-dashboard-recent-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg></span>
                <span class="portal-dashboard-recent-copy">
                    <strong class="portal-dashboard-recent-title">{{ $item['title'] }}</strong>
                    <span class="portal-dashboard-recent-meta"><span>{{ $contentTypes[$item['type']]??'محتوا' }}</span>@if(!empty($item['updated_at']))<time datetime="{{ $item['updated_at'] }}">{{ $ui->date($item['updated_at']) }}</time>@endif</span>
                </span>
                <span class="portal-dashboard-recent-status" data-status="{{ $item['status'] }}">{{ $contentStatuses[$item['status']]??'نامشخص' }}</span>
            </a></li>
        @empty
            <li class="portal-dashboard-recent-empty">محتوایی ثبت نشده است.</li>
        @endforelse
        </ul>
    </section>
    <section class="portal-dashboard-recent" aria-labelledby="dashboard-recent-reports-title">
        <header class="portal-dashboard-recent-header">
            <h2 id="dashboard-recent-reports-title">تازه‌ترین گزارش‌های مردمی</h2>
            @if(collect($modules)->contains('canonical_path','/panel/admin/reports'))<a href="{{ $ui->adminHref('/panel/admin/reports',$context) }}">همه گزارش‌ها <span aria-hidden="true">←</span></a>@endif
        </header>
        <ul class="portal-dashboard-recent-list">
        @forelse($stats['recent_reports']??[] as $report)
            <li><a class="portal-dashboard-recent-row" href="{{ $ui->adminHref('/panel/admin/reports/'.$report['reference'].'/edit',$context) }}">
                <span class="portal-dashboard-recent-icon is-report" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H8l-5 3V7.5A4.5 4.5 0 0 1 7.5 3H15a5 5 0 0 1 5 5Z"/><path d="M7 8h9M7 12h6"/></svg></span>
                <span class="portal-dashboard-recent-copy">
                    <strong class="portal-dashboard-recent-title">{{ $report['subject'] }}</strong>
                    <span class="portal-dashboard-recent-meta"><span>کد پیگیری</span><bdi>{{ $report['reference'] }}</bdi></span>
                </span>
                <span class="portal-dashboard-recent-status" data-status="{{ $report['status'] }}">{{ $reportStatuses[$report['status']]??'نامشخص' }}</span>
            </a></li>
        @empty
            <li class="portal-dashboard-recent-empty">گزارشی ثبت نشده است.</li>
        @endforelse
        </ul>
    </section>
</div>
</section>

<section class="portal-card my-8"><h2>فعالیت ده روز اخیر</h2><p>آبی: محتوا · سبز: درخواست‌های مردمی</p>@php($maximum=max(1,...array_map(fn($day)=>max($day['contents'],$day['tickets']),$stats['daily_activity'])))<div class="portal-activity-chart">@foreach($stats['daily_activity'] as $day)<div class="portal-activity-day"><div class="portal-activity-bars"><span style="height:{{ max(2,round($day['contents']/$maximum*125)) }}px" title="{{ $day['contents'] }} محتوا"></span><span style="height:{{ max(2,round($day['tickets']/$maximum*125)) }}px" title="{{ $day['tickets'] }} درخواست"></span></div><time>{{ $ui->date($day['date']) }}</time></div>@endforeach</div></section>

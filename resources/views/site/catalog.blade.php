@if($resource==='galleries')
@include('site.galleries')
@elseif($resource==='faqs')
@forelse($items as $item)<details class="portal-card"><summary>{{ $item['question'] }}</summary><div class="portal-article-body">{!! \App\Services\Html::clean($item['answer'] ?? '') !!}</div></details>@empty<p class="portal-empty">پرسشی ثبت نشده است.</p>@endforelse
@elseif($resource==='links')
<div class="portal-links-directory">@forelse($items as $link)<a @class(['portal-useful-link','has-custom-image'=>($link['link_style']??'')==='image']) href="{{ $ui->href($link['url'] ?? $link['href'] ?? '/links',$county) }}">@if(($link['link_style']??'')==='image')@include('partials.custom-link-image')@else
@include('partials.link-symbol')@endif<h2>{{ $link['title'] }}</h2><span aria-hidden="true">↖</span></a>@empty<p class="portal-feed-empty">پیوندی ثبت نشده است.</p>@endforelse</div>
@elseif($resource==='polls')
@forelse($items as $item)<section class="portal-card"><h2>{{ $item['question'] }}</h2><p>{{ $item['description'] ?? '' }}</p>@auth<form x-data="apiForm(@js('polls/'.$item['id'].'/vote'))" @submit.prevent="submit($event)">@foreach($item['options'] ?? [] as $option)<label class="block my-3"><input type="radio" name="option_id" value="{{ $option['id'] }}" required> {{ $option['title'] }}</label>@endforeach<button class="portal-button" :disabled="busy">ثبت رأی</button><p role="alert" x-text="error"></p><p role="status" x-text="message"></p></form>@else<a href="/login">برای شرکت در نظرسنجی وارد شوید.</a>@endauth</section>@empty<p class="portal-empty">نظرسنجی فعالی وجود ندارد.</p>@endforelse
@elseif($resource==='forms')
@forelse($items as $item)<section class="portal-card"><h2><a href="{{ $ui->href('/forms/'.$item['id'],$county) }}">{{ $item['title'] }}</a></h2><p>{{ $ui->text($item['description'] ?? '') }}</p>@if(request()->is('forms/*') || request()->is('*/forms/*'))@auth @include('site.dynamic-form')@else<a href="/login">برای تکمیل فرم وارد حساب شهروندی شوید.</a>@endauth @endif</section>@empty<p class="portal-empty">فرمی در این بخش وجود ندارد.</p>@endforelse
@elseif($resource==='services')
@include('site.services')
@else
<div class="source-directory">@forelse($items as $item)<article class="portal-card"><h2><a href="{{ $ui->href('/service/'.$item['id'],$county) }}">{{ $item['name'] }}</a></h2><div class="portal-article-body">{!! \App\Services\Html::clean($item['description'] ?? '') !!}</div>@foreach(['requirements'=>'مدارک موردنیاز','steps'=>'مراحل انجام'] as $key=>$label)@if(!empty($item[$key]))<h3>{{ $label }}</h3><ol>@foreach($item[$key] as $value)<li>{{ $value }}</li>@endforeach</ol>@endif @endforeach @foreach(['delivery_time'=>'زمان ارائه خدمت','cost'=>'هزینه','target_audience'=>'مخاطبان'] as $key=>$label)@if(!empty($item[$key]))<p>{{ $label }}: {{ $item[$key] }}</p>@endif @endforeach @if(!empty($item['url']))<a class="portal-button" href="{{ $ui->href($item['url']) }}">ورود به خدمت</a>@endif</article>@empty<p class="portal-empty">خدمتی در این بخش ثبت نشده است.</p>@endforelse</div>
@endif

@if($context['type']==='county')
<p class="portal-field-help portal-resource-wide">خبر منتشرشده در پرتال همین شهرستان و بخش «اخبار فرمانداری‌ها»ی سایت استانداری نمایش داده می‌شود.</p>
@else
<div class="portal-resource-field" data-editor-field="news_scope"><label for="news-scope">گروه اخبار <span class="portal-field-requirement">اختیاری</span></label><select id="news-scope" name="news_scope" data-field="news_scope"><option value="">بر اساس استان یا فرمانداری منتشرکننده</option>@foreach(\App\Services\NewsFeeds::LABELS as $scope=>$label)<option value="{{ $scope }}">{{ $label }}</option>@endforeach</select></div>
@endif

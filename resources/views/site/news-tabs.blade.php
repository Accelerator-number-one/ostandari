<section class="portal-news-feeds" x-data="{active:'province',scopes:['province','governorates','counties','crisis'],move(delta){this.active=this.scopes[(this.scopes.indexOf(this.active)+delta+4)%4];this.$nextTick(()=>this.$el.querySelector('#feed-tab-'+this.active)?.focus())}}" aria-label="خبرهای استان">
    <div class="portal-feed-tabs" role="tablist" aria-label="گروه اخبار">
        @foreach(\App\Services\NewsFeeds::LABELS as $scope=>$label)<button type="button" role="tab" id="feed-tab-{{ $scope }}" :aria-selected="active==='{{ $scope }}'" :tabindex="active==='{{ $scope }}'?0:-1" @keydown.arrow-left.prevent="move(1)" @keydown.arrow-right.prevent="move(-1)" aria-controls="feed-panel-{{ $scope }}" @click="active='{{ $scope }}'">{{ $label }}</button>@endforeach
    </div>
    @foreach(\App\Services\NewsFeeds::LABELS as $scope=>$label)
        <div class="portal-feed-panel" id="feed-panel-{{ $scope }}" role="tabpanel" aria-labelledby="feed-tab-{{ $scope }}" x-show="active==='{{ $scope }}'" @if($scope!=='province')x-cloak @endif>
            <div class="portal-feed-list">@forelse($home['news_feeds'][$scope] ?? [] as $item)@include('site.news-card')@empty<p class="portal-feed-empty">خبری منتشر نشده است.</p>@endforelse</div>
        </div>
    @endforeach
    <a class="portal-feed-more" href="/news?news_scope=province" :href="@js($ui->href('/news',$county))+'?news_scope='+active">همهٔ اخبار <span aria-hidden="true">←</span></a>
</section>

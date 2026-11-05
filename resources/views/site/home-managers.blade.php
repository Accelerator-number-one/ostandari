@php
    $managerGroups=collect($ui->data('frontend')['managerGroups'])->reject(fn($group)=>(string)$group['group']==='6')->values()->all();
    $memberRows=collect($home['members']??[])->flatten(1)->all();
@endphp
<section class="section source-managers" data-alpine-tabs="true" aria-label="مدیران استان" x-data="{tab:0,total:{{ count($managerGroups) }}}">
    <div class="manager-tabs" role="tablist" aria-label="گروه مدیران" x-ref="memberTabs">
        @foreach($managerGroups as $i=>$group)
        <button type="button" role="tab" class="tab-btn" id="home-member-tab-{{ $group['group'] }}" aria-controls="home-member-panel-{{ $group['group'] }}"
            :class="{'active':tab==={{ $i }}}" :aria-selected="tab==={{ $i }}" :tabindex="tab==={{ $i }}?0:-1" @click="tab={{ $i }}"
            @keydown.arrow-left.prevent="tab=(tab+1)%total;$nextTick(()=>$refs.memberTabs.querySelectorAll('[role=tab]')[tab].focus())"
            @keydown.arrow-right.prevent="tab=(tab+total-1)%total;$nextTick(()=>$refs.memberTabs.querySelectorAll('[role=tab]')[tab].focus())"
            @keydown.home.prevent="tab=0;$nextTick(()=>$refs.memberTabs.querySelectorAll('[role=tab]')[tab].focus())"
            @keydown.end.prevent="tab=total-1;$nextTick(()=>$refs.memberTabs.querySelectorAll('[role=tab]')[tab].focus())">{{ $group['title'] }}</button>
        @endforeach
    </div>
    @foreach($managerGroups as $i=>$group)
    <div class="tab-content" :class="{'active':tab==={{ $i }}}" role="tabpanel" id="home-member-panel-{{ $group['group'] }}" aria-labelledby="home-member-tab-{{ $group['group'] }}" x-show="tab==={{ $i }}" @if($i!==0)x-cloak @endif>
        @include('site.members',['items'=>array_slice($ui->membersForGroup($memberRows,$group['group']),0,8)])
        <div class="portal-member-panel-footer"><a class="portal-member-more" href="{{ $ui->href('/managers?group='.$group['group'],$county) }}">مشاهدهٔ همهٔ {{ $group['title'] }} <span aria-hidden="true">←</span></a></div>
    </div>
    @endforeach
</section>

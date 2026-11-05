<?php

namespace App\Models;

class Member extends PortalModel
{
    protected $hidden = ['current_governor_key'];
    protected $appends = ['image_url', 'term_start_year', 'term_end_year', 'term_period'];

    public function getTermStartYearAttribute(): ?int { return \App\Services\GovernorTerms::year($this->term_start); }
    public function getTermEndYearAttribute(): ?int { return \App\Services\GovernorTerms::year($this->term_end); }
    public function getTermPeriodAttribute(): ?string { return \App\Services\GovernorTerms::period($this->getAttributes()); }

    public function getImageUrlAttribute(): ?string
    {
        if($this->media_id || !in_array($this->category?->slug,array_column(\App\Services\MemberDirectory::GROUPS,'slug'),true))return null;
        return \App\Services\MemberDirectory::portraitUrl();
    }
}

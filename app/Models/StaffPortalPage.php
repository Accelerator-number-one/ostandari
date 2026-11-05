<?php

namespace App\Models;

final class StaffPortalPage extends PortalModel
{
    protected function casts(): array
    {
        return [...parent::casts(), 'show_in_navigation' => 'boolean'];
    }

    public function portal() { return $this->belongsTo(StaffPortal::class, 'staff_portal_id'); }

    public function publicPath(): string
    {
        return $this->portal->publicPath().'/pages/'.rawurlencode($this->path);
    }
}

<?php

namespace App\Models;

final class StaffPortalItem extends PortalModel
{
    public function portal() { return $this->belongsTo(StaffPortal::class, 'staff_portal_id'); }
    public function asset() { return $this->belongsTo(StaffPortalAsset::class, 'asset_id'); }
}

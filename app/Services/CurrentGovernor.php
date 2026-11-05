<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CurrentGovernor
{
    public static function validate(array $data, User $actor, ?Member $model = null): void
    {
        $effective = array_merge($model?->getAttributes() ?? ['locale' => 'fa', 'is_current' => true, 'is_active' => true], $data);
        if (($effective['kind'] ?? '') !== 'governor') {
            return;
        }
        $organization = isset($effective['organization_id']) ? Organization::find($effective['organization_id']) : null;
        if (! $actor->hasGlobalAccess() && $organization?->type !== 'governorate') {
            abort(403, 'مدیریت استاندار تنها از واحد مرکزی انجام می‌شود.');
        }
        if ($organization && $organization->type !== 'governorate' || ! empty($effective['county_id'])) {
            throw ValidationException::withMessages(['organization_id' => 'استاندار باید به استانداری استان وابسته باشد.']);
        }
        if (! $effective['is_current'] || ! $effective['is_active']) {
            return;
        }
        $existing = Member::where('kind', 'governor')
            ->where('is_current', true)->where('is_active', true)->when($model, fn ($query) => $query->whereKeyNot($model->id))->exists();
        if ($existing) {
            throw ValidationException::withMessages(['is_current' => 'استاندار فعلی قبلاً ثبت شده است. همان رکورد را ویرایش کنید یا ابتدا مسئولیت او را پایان دهید.']);
        }
    }
}

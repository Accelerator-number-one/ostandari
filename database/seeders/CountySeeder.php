<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\Organization;
use App\Support\CountySlugs;
use Illuminate\Database\Seeder;

final class CountySeeder extends Seeder
{
    public function run(): void
    {
        $root = Organization::where('slug', 'mazandaran')->firstOrFail();
        $counties = ['ramsar' => 'رامسر', 'tonekabon' => 'تنکابن', 'abbasabad' => 'عباس‌آباد', 'chalus' => 'چالوس',
            'noshahr' => 'نوشهر', 'mahmudabad' => 'محمودآباد', 'amol' => 'آمل', 'fereydunkenar' => 'فریدونکنار',
            'babolsar' => 'بابلسر', 'juybar' => 'جویبار', 'simorgh' => 'سیمرغ', 'babol' => 'بابل',
            'savadkuh' => 'سوادکوه', 'savadkuh-shomali' => 'سوادکوه شمالی', 'sari' => 'ساری', 'galugah' => 'گلوگاه',
            'miandorud' => 'میاندورود', 'qaemshahr' => 'قائم‌شهر', 'nur' => 'نور', 'kelardasht' => 'کلاردشت', 'neka' => 'نکا', 'behshahr' => 'بهشهر'];
        foreach ($counties as $slug => $name) {
            $county = County::whereIn('slug', CountySlugs::variants($slug))->first()
                ?? County::create(['slug' => $slug, 'name' => $name, 'map_key' => $slug, 'is_active' => true]);
            if (! Organization::where('county_id', $county->id)->where('type', 'county')->exists()) {
                Organization::create(['slug' => 'county-'.$county->slug, 'name' => 'فرمانداری '.$name, 'parent_id' => $root->id, 'county_id' => $county->id, 'type' => 'county', 'is_active' => true]);
            }
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\{Member, User};
use App\Services\GovernorTerms;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class GovernorTermsTest extends TestCase
{
    use DatabaseMigrations;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($this->admin);
    }

    private function payload(array $extra = []): array
    {
        return $extra + ['name'=>'استاندار آزمایشی','position'=>'استاندار مازندران','kind'=>'governor','is_current'=>false,'is_active'=>true];
    }

    public function test_solar_years_accept_persian_and_arabic_digits_and_show_the_current_term(): void
    {
        $row = $this->postJson('/api/v1/admin/members', $this->payload(['is_current'=>true,'term_start_year'=>'۱۴۰۳','term_end_year'=>null]))
            ->assertCreated()->assertJsonPath('data.term_start_year',1403)->assertJsonPath('data.term_end_year',null)->assertJsonPath('data.term_period','۱۴۰۳–تاکنون')->json('data');
        $this->assertSame('2024-03-20',Member::findOrFail($row['id'])->term_start->format('Y-m-d'));
        $this->get('/governors')->assertOk()->assertSee('۱۴۰۳–تاکنون');
        $this->get('/governors?person='.$row['id'])->assertOk()->assertSee('۱۴۰۳–تاکنون');
        $this->postJson('/api/v1/admin/members',$this->payload(['name'=>'دوره پیشین','term_start_year'=>'١٤٠٠','term_end_year'=>'١٤٠٤']))
            ->assertCreated()->assertJsonPath('data.term_period','۱۴۰۰–۱۴۰۴');
        $this->assertSame('2025-03-20 23:59:59',GovernorTerms::date(1403,true));
    }

    public function test_reverse_ranges_invalid_years_and_ambiguous_date_inputs_are_rejected(): void
    {
        $this->postJson('/api/v1/admin/members',$this->payload(['term_start_year'=>1404,'term_end_year'=>1400]))->assertUnprocessable()->assertJsonValidationErrors('term_end_year');
        foreach(['0','2024','abcd',['1403'],1199,1601] as $year)
            $this->postJson('/api/v1/admin/members',$this->payload(['term_start_year'=>$year]))->assertUnprocessable()->assertJsonValidationErrors('term_start_year');
        $this->postJson('/api/v1/admin/members',$this->payload(['term_start_year'=>1400,'term_end_year'=>1400]))->assertCreated()->assertJsonPath('data.term_period','۱۴۰۰–۱۴۰۰');
        $this->postJson('/api/v1/admin/members',$this->payload(['term_start_year'=>1400,'term_start'=>'2021-04-01']))->assertUnprocessable()->assertJsonValidationErrors('term_start_year');
        $this->postJson('/api/v1/admin/members',$this->payload(['term_start_year'=>'','term_end_year'=>'']))->assertCreated()->assertJsonPath('data.term_period',null);
    }

    public function test_editing_preserves_exact_legacy_dates_and_explicit_empty_years_clear_them(): void
    {
        $row=Member::create($this->payload(['organization_id'=>$this->admin->organization_id,'term_start'=>'2021-05-15 10:30:00','term_end'=>'2024-08-01 11:45:00']));
        $this->assertSame(1400,$row->term_start_year);
        $this->assertSame(1403,$row->term_end_year);
        $this->patchJson('/api/v1/admin/members/'.$row->id,['name'=>'عنوان ویرایش‌شده','term_start_year'=>'۱۴۰۰','term_end_year'=>'۱۴۰۳'])->assertOk();
        $this->assertSame('2021-05-15 10:30:00',$row->fresh()->term_start->format('Y-m-d H:i:s'));
        $this->assertSame('2024-08-01 11:45:00',$row->fresh()->term_end->format('Y-m-d H:i:s'));
        $this->patchJson('/api/v1/admin/members/'.$row->id,['phone'=>'01112345678'])->assertOk()->assertJsonPath('data.term_start_year',1400);
        $this->patchJson('/api/v1/admin/members/'.$row->id,['term_end_year'=>1404])->assertOk()->assertJsonPath('data.term_period','۱۴۰۰–۱۴۰۴');
        $this->patchJson('/api/v1/admin/members/'.$row->id,['term_start_year'=>null,'term_end_year'=>''])->assertOk()->assertJsonPath('data.term_period',null);
        $this->assertNull($row->fresh()->term_start);
        $this->assertNull($row->fresh()->term_end);
        // The full date API used by other member forms stays compatible.
        $this->patchJson('/api/v1/admin/members/'.$row->id,['term_start'=>'2022-04-01','term_end'=>'2023-04-01'])->assertOk()->assertJsonPath('data.term_start_year',1401);
    }

    public function test_governors_sort_by_current_then_newest_term_before_pagination_in_public_and_admin_lists(): void
    {
        $base=['organization_id'=>$this->admin->organization_id];
        $old=Member::create($this->payload($base+['name'=>'دوره قدیمی','term_start'=>GovernorTerms::date(1380),'term_end'=>GovernorTerms::date(1384,true),'priority'=>0]));
        $recent=Member::create($this->payload($base+['name'=>'دوره جدید','term_start'=>GovernorTerms::date(1400),'term_end'=>GovernorTerms::date(1402,true),'priority'=>100]));
        $current=Member::create($this->payload($base+['name'=>'استاندار فعلی','term_start'=>GovernorTerms::date(1403),'is_current'=>true,'priority'=>999]));
        $undated=Member::create($this->payload($base+['name'=>'بدون تاریخ','priority'=>0]));
        $this->getJson('/api/v1/members?kind=governor&per_page=2')->assertOk()->assertJsonPath('data.0.id',$current->id)->assertJsonPath('data.1.id',$recent->id);
        $this->getJson('/api/v1/members?kind=governor&per_page=2&page=2')->assertOk()->assertJsonPath('data.0.id',$old->id)->assertJsonPath('data.1.id',$undated->id);
        $this->getJson('/api/v1/admin/members?kind=governor&per_page=2')->assertOk()->assertJsonPath('data.0.id',$current->id)->assertJsonPath('data.1.id',$recent->id);
        $this->get('/governors')->assertOk()->assertSeeInOrder(['استاندار فعلی','دوره جدید','دوره قدیمی','بدون تاریخ']);
        $this->actingAs($this->admin,'web')->get('/panel/admin/governors/new')->assertOk()->assertSee('دوره تصدی (شمسی)')->assertSee('data-field="term_start_year"',false)->assertDontSee('data-field="term_start"',false);
        $this->get('/panel/admin/governors')->assertOk()->assertSee('data-column="term_period"',false);
    }

    public function test_historical_and_future_solar_years_fit_the_database_and_upgrade_preserves_existing_dates(): void
    {
        $existing=Member::create($this->payload(['organization_id'=>$this->admin->organization_id,'term_start'=>'2024-05-15 10:30:00','term_end'=>'2025-02-01 09:20:00']));
        $before=[$existing->getRawOriginal('term_start'),$existing->getRawOriginal('term_end')];
        // Reproduce an installation with the original MySQL timestamp columns.
        \Illuminate\Support\Facades\Schema::table('members',function($table){$table->timestamp('term_start')->nullable()->change();$table->timestamp('term_end')->nullable()->change();});
        \Illuminate\Support\Facades\DB::table('migrations')->where('migration','2026_10_30_000003_expand_governor_term_dates')->delete();
        $this->assertFalse(GovernorTerms::ready());
        $this->postJson('/api/v1/admin/members',$this->payload(['term_start_year'=>1310,'term_end_year'=>1320]))->assertStatus(503);
        $this->artisan('portal:prepare-governor-terms')->assertSuccessful();
        $this->assertTrue(GovernorTerms::ready());
        $existing->refresh();
        $this->assertSame($before,[$existing->getRawOriginal('term_start'),$existing->getRawOriginal('term_end')]);
        $this->postJson('/api/v1/admin/members',$this->payload(['name'=>'دوره تاریخی','term_start_year'=>1310,'term_end_year'=>1320]))->assertCreated()->assertJsonPath('data.term_period','۱۳۱۰–۱۳۲۰');
        $this->postJson('/api/v1/admin/members',$this->payload(['name'=>'مرز بازه مجاز','term_start_year'=>1200,'term_end_year'=>1600]))->assertCreated()->assertJsonPath('data.term_period','۱۲۰۰–۱۶۰۰');
        $this->artisan('portal:prepare-governor-terms')->assertSuccessful();
        $this->assertSame('2024-05-15 10:30:00',$existing->fresh()->getRawOriginal('term_start'));
    }
}

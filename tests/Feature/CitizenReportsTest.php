<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CitizenReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function citizen(): User
    {
        return User::create(['name' => 'شهروند تست', 'role_id' => Role::where('name', 'citizen')->value('id')]);
    }

    private function operator(Organization $organization): User
    {
        $role = Role::firstOrCreate(['name' => 'reports-test-operator'], ['label' => 'کارشناس گزارش', 'permissions' => ['tickets.manage']]);

        return User::create(['name' => 'کارشناس تست', 'role_id' => $role->id, 'organization_id' => $organization->id, 'person_type' => 'expert']);
    }

    private function ticket(Organization $organization, array $attributes = []): Ticket
    {
        return Ticket::create($attributes + ['reference' => (string) Str::ulid(), 'user_id' => $this->citizen()->id, 'organization_id' => $organization->id, 'county_id' => $organization->county_id, 'type' => 'report', 'subject' => 'درخواست تست', 'body' => 'متن گزارش برای بررسی گردش رسیدگی']);
    }

    public function test_citizen_report_enters_the_reports_queue_and_public_reply_status_and_notifications_reach_its_owner(): void
    {
        $owner = $this->citizen();
        $county = County::where('slug', 'sari')->firstOrFail();
        Sanctum::actingAs($owner);
        $reference = $this->postJson('/api/v1/citizen/tickets', ['type' => 'report', 'county_id' => $county->id, 'category' => 'waste', 'urgency' => 'urgent', 'subject' => 'پسماند محله', 'body' => 'شرح کامل گزارش شهروند دربارهٔ پسماند محله'])->assertCreated()->json('data.reference');
        Sanctum::actingAs(User::where('username', 'admin')->firstOrFail());
        $this->getJson('/api/v1/admin/reports')->assertOk()->assertJsonPath('summary.total', 1)->assertJsonPath('data.0.reference', $reference)->assertJsonPath('data.0.owner.name', $owner->name)->assertJsonPath('data.0.county.name', 'ساری');
        $this->postJson('/api/v1/admin/reports/'.$reference.'/messages', ['body' => 'یادداشت کارشناسی محرمانه', 'is_internal' => true])->assertCreated();
        $this->postJson('/api/v1/admin/reports/'.$reference.'/messages', ['body' => 'پاسخ قابل مشاهده برای شهروند', 'is_internal' => false])->assertCreated();
        $this->patchJson('/api/v1/admin/reports/'.$reference, ['status' => 'review', 'note' => 'دلیل داخلی رسیدگی'])->assertOk();
        $this->patchJson('/api/v1/admin/reports/'.$reference, ['status' => 'resolved'])->assertOk();
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/citizen/tickets/'.$reference)->assertOk()->assertJsonPath('data.status', 'resolved')->assertJsonCount(1, 'data.messages')->assertJsonPath('data.messages.0.body', 'پاسخ قابل مشاهده برای شهروند')->assertJsonMissing(['body' => 'یادداشت کارشناسی محرمانه'])->assertJsonMissing(['note' => 'دلیل داخلی رسیدگی']);
        $this->getJson('/api/v1/citizen/notifications')->assertOk()->assertJsonCount(4, 'data');
    }

    public function test_reports_use_ticket_permission_instead_of_dashboard_permission_and_never_include_contact_messages(): void
    {
        $org = Organization::where('type', 'county')->firstOrFail();
        $report = $this->ticket($org);
        $governor = $this->ticket($org, ['type' => 'governor']);
        $contact = $this->ticket($org, ['type' => 'contact']);
        Sanctum::actingAs($this->operator($org));
        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
        $this->getJson('/api/v1/admin/reports')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('summary.total', 2);
        $this->getJson('/api/v1/admin/reports/'.$report->reference)->assertOk();
        $this->getJson('/api/v1/admin/reports/'.$governor->reference)->assertOk();
        $this->getJson('/api/v1/admin/reports/'.$contact->reference)->assertNotFound();
        $this->postJson('/api/v1/admin/reports/'.$contact->reference.'/messages', ['body' => 'Wrong queue'])->assertNotFound();
        $this->getJson('/api/v1/admin/reports?type=contact')->assertUnprocessable();
        $this->getJson('/api/v1/admin/tickets?type=contact')->assertOk()->assertJsonCount(1, 'data');
        Sanctum::actingAs(User::create(['name' => 'Editor', 'person_type' => 'staff', 'role_id' => Role::where('name', 'editor')->value('id')]));
        $this->getJson('/api/v1/admin/reports')->assertForbidden();
        $this->getJson('/api/v1/admin/reports/options')->assertForbidden();
    }

    public function test_list_summary_details_options_and_writes_are_scoped_to_the_operators_organization(): void
    {
        [$a, $b] = Organization::where('type', 'county')->limit(2)->get()->all();
        $own = $this->ticket($a);
        $other = $this->ticket($b, ['urgency' => 'urgent']);
        $operator = $this->operator($a);
        $outsider = $this->operator($b);
        Sanctum::actingAs($operator);
        $this->getJson('/api/v1/admin/reports')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reference', $own->reference)->assertJsonPath('summary.total', 1);
        $this->getJson('/api/v1/admin/reports/'.$other->reference)->assertNotFound();
        $this->patchJson('/api/v1/admin/reports/'.$other->reference, ['status' => 'review'])->assertNotFound();
        $this->postJson('/api/v1/admin/reports/'.$other->reference.'/messages', ['body' => 'Outside scope'])->assertNotFound();
        $this->patchJson('/api/v1/admin/reports/'.$own->reference, ['organization_id' => $b->id])->assertForbidden();
        $this->patchJson('/api/v1/admin/reports/'.$own->reference, ['assigned_to' => $outsider->id])->assertUnprocessable();
        $options = $this->getJson('/api/v1/admin/reports/options')->assertOk()->json('data');
        $this->assertSame([$a->id], array_column($options['organizations'], 'id'));
        $this->assertNotContains($outsider->id, array_column($options['assignees'], 'id'));
        $this->assertContains($operator->id, array_column($options['assignees'], 'id'));
        $this->assertArrayNotHasKey('email', $options['assignees'][0]);
    }

    public function test_filters_summary_pagination_and_priority_order_are_consistent_and_list_bodies_are_not_loaded(): void
    {
        $org = Organization::where('type', 'county')->firstOrFail();
        $urgent = $this->ticket($org, ['urgency' => 'urgent', 'due_at' => now()->subDay(), 'subject' => 'MATCH']);
        $normal = $this->ticket($org, ['urgency' => 'medium']);
        $done = $this->ticket($org, ['urgency' => 'urgent', 'status' => 'resolved', 'due_at' => now()->subDay()]);
        Sanctum::actingAs(User::where('username', 'admin')->firstOrFail());
        $this->getJson('/api/v1/admin/reports?order=urgency&per_page=1')->assertOk()->assertJsonPath('data.0.reference', $done->reference)->assertJsonPath('meta.total', 3)->assertJsonPath('summary.total', 3)->assertJsonPath('summary.overdue', 1)->assertJsonMissingPath('data.0.body');
        $query = http_build_query(['q' => 'MATCH', 'county_id' => $org->county_id, 'urgency' => 'urgent', 'status' => 'pending', 'type' => 'report', 'overdue' => 1, 'from' => now()->toDateString(), 'to' => now()->toDateString()]);
        $this->getJson('/api/v1/admin/reports?'.$query)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reference', $urgent->reference)->assertJsonPath('summary.pending', 1);
        $this->getJson('/api/v1/admin/reports?type=governor')->assertOk()->assertJsonPath('summary.total', 0);
        $this->getJson('/api/v1/admin/reports?from=2026-10-20&to=2026-10-19')->assertUnprocessable();
        $this->getJson('/api/v1/admin/reports?county_id=bad&order=bad')->assertUnprocessable();
        $this->getJson('/api/v1/admin/reports?order=oldest&per_page=1&page=2')->assertOk()->assertJsonPath('data.0.reference', $normal->reference);
    }

    public function test_reassignment_clearance_status_history_and_closed_conversation_are_enforced(): void
    {
        [$a, $b] = Organization::where('type', 'county')->limit(2)->get()->all();
        $operator = $this->operator($a);
        $ticket = $this->ticket($a, ['assigned_to' => $operator->id]);
        Sanctum::actingAs(User::where('username', 'admin')->firstOrFail());
        $this->patchJson('/api/v1/admin/reports/'.$ticket->reference, ['status' => 'resolved'])->assertUnprocessable();
        $this->patchJson('/api/v1/admin/reports/'.$ticket->reference, ['organization_id' => $b->id, 'status' => 'referred', 'urgency' => 'high', 'note' => 'ارجاع برای رسیدگی'])->assertOk()->assertJsonPath('data.assigned_to', null);
        $this->patchJson('/api/v1/admin/reports/'.$ticket->reference, ['status' => 'closed'])->assertOk();
        $this->postJson('/api/v1/admin/reports/'.$ticket->reference.'/messages', ['body' => 'Must not save'])->assertStatus(409);
        $this->assertDatabaseCount('ticket_messages', 0);
        $this->patchJson('/api/v1/admin/reports/'.$ticket->reference, ['status' => 'review'])->assertOk()->assertJsonPath('data.resolved_at', null);
        $this->postJson('/api/v1/admin/reports/'.$ticket->reference.'/messages', ['body' => 'پاسخ بعد از بازگشایی'])->assertCreated();
        $this->getJson('/api/v1/admin/reports/'.$ticket->reference)->assertOk()->assertJsonCount(3, 'data.events')->assertJsonCount(1, 'data.messages');
        $this->assertSame(3, DB::table('audit_logs')->where('action', 'ticket.updated')->count());
    }

    public function test_guests_citizens_and_inactive_staff_cannot_read_or_write_the_admin_reports_queue(): void
    {
        $this->getJson('/api/v1/admin/reports')->assertUnauthorized();
        Sanctum::actingAs($this->citizen());
        $this->getJson('/api/v1/admin/reports')->assertForbidden();
        $staff = $this->operator(Organization::where('type', 'county')->firstOrFail());
        $staff->update(['is_active' => false]);
        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/admin/reports')->assertForbidden();
    }
}

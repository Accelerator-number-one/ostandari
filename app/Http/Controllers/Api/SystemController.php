<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Content;
use App\Models\FormSubmission;
use App\Models\Member;
use App\Models\PollVote;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Access;
use App\Services\Audit;
use App\Services\ResourceRegistry;
use App\Services\Workspace;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemController
{
    public function health()
    {
        DB::select('select 1');

        return Api::ok(['status' => 'ok', 'database' => 'ok', 'api_version' => 'v1']);
    }

    public function schema(Request $r)
    {
        return Api::ok(collect(ResourceRegistry::all())->filter(fn ($d) => $r->user()->hasPermission($d['permission']))->map(fn ($d) => [
            'permission' => $d['permission'], 'fields' => array_values(array_filter(array_keys($d['rules']), fn ($k) => ! str_contains($k, '.'))), 'filters' => $d['filters'] ?? [], 'scope' => $d['scope'] ?? []]));
    }

    public function settings(Request $r)
    {
        abort_unless($r->user()->hasGlobalAccess(), 403);
        return Api::ok(Setting::orderBy('key')->get());
    }

    public function updateSettings(Request $r)
    {
        abort_unless($r->user()->hasGlobalAccess(), 403);
        $d = $r->validate(['settings' => 'required|array|min:1|max:50', 'settings.*' => 'array:key,value,is_public', 'settings.*.key' => 'required|string|max:100|regex:/^[a-z][a-z0-9_.-]+$/|distinct',
            'settings.*.value' => 'present', 'settings.*.is_public' => 'sometimes|boolean']);
        foreach ($d['settings'] as $s) {
            abort_if(preg_match('/password|secret|token|api.?key|credential/i', $s['key']), 422, 'اطلاعات محرمانه باید در محیط اجرا تنظیم شود.');
            abort_if(strlen(json_encode($s['value'])) > 30000, 422, 'مقدار تنظیمات بیش از حد مجاز است.');
            abort_if(($s['is_public'] ?? false) && ! in_array($s['key'], config('portal.public_settings'), true), 422, 'این کلید برای انتشار عمومی مجاز نیست.');
        }
        DB::transaction(function () use ($d) {
            foreach ($d['settings'] as $s) {
                Setting::updateOrCreate(['key' => $s['key']], ['value' => $s['value'], 'is_public' => $s['is_public'] ?? false]);
            }Audit::record('settings.updated');
        });

        return $this->settings($r);
    }

    public function audit(Request $r)
    {
        $r->validate(['per_page' => 'sometimes|integer|min:1|max:100', 'action' => 'sometimes|string|max:80', 'user_id' => 'sometimes|integer']);
        $q = AuditLog::query();
        foreach (['action', 'user_id'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->input($f));
            }
        }
        if (! $r->user()->hasGlobalAccess()) {
            $q->where('user_id', $r->user()->id);
        }

        return Api::page($q->latest('id')->paginate((int) $r->input('per_page', 30)));
    }

    public function dashboard(Request $r)
    {
        $u = $r->user();
        $organizations = $u->hasGlobalAccess() ? [] : Access::organizations($u);
        $scope = fn ($query) => $query->when(! $u->hasGlobalAccess(), fn ($query) => $query->whereIn('organization_id', $organizations));
        $tickets = fn () => $scope(Ticket::query());
        $contents = fn () => $scope(Content::query());
        $start = now()->startOfDay()->subDays(9);
        $end = now()->endOfDay();
        $dailyTickets = $tickets()->whereBetween('created_at', [$start, $end])->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')->groupByRaw('DATE(created_at)')->pluck('total', 'day');
        $dailyContents = $contents()->whereBetween('created_at', [$start, $end])->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')->groupByRaw('DATE(created_at)')->pluck('total', 'day');

        return Api::ok(['workspace' => Workspace::context($u), 'permissions' => $u->effectivePermissions(), 'recent_contents' => $u->hasPermission('content.manage') ? $contents()->latest('updated_at')->limit(6)->get(['id', 'type', 'title', 'status', 'updated_at']) : [], 'recent_reports' => $u->hasPermission('tickets.manage') ? $tickets()->whereIn('type', ['report', 'governor'])->latest('id')->limit(5)->get(['reference', 'subject', 'status']) : [], 'contents_by_type' => $contents()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
            'contents_by_status' => $contents()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'tickets_by_status' => $tickets()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'overdue_tickets' => $tickets()->where('due_at', '<', now())->whereNotIn('status', ['resolved', 'rejected', 'closed'])->count(),
            'users' => $scope(User::query())->count(), 'members' => $scope(Member::query())->count(),
            'recent_tickets' => $u->hasPermission('tickets.manage') ? $tickets()->latest('id')->limit(10)->get() : [],
            'daily_activity' => collect(range(9, 0))->map(function ($days) use ($dailyTickets, $dailyContents) {
                $day = now()->subDays($days)->toDateString();

                return ['date' => $day, 'tickets' => (int) ($dailyTickets[$day] ?? 0), 'contents' => (int) ($dailyContents[$day] ?? 0)];
            }),
        ]);
    }

    public function citizenDashboard(Request $r)
    {
        $u = $r->user();

        return Api::ok(['reports' => Ticket::where('user_id', $u->id)->where('type', 'report')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'messages' => Ticket::where('user_id', $u->id)->where('type', 'governor')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'latest_ticket' => Ticket::where('user_id', $u->id)->latest('id')->first(), 'form_submissions' => FormSubmission::where('user_id', $u->id)->count(),
            'poll_participations' => PollVote::where('user_id', $u->id)->count(), 'unread_notifications' => $u->unreadNotifications()->count(),
            'announcements' => Content::visible()->where('locale', 'fa')->where('type', 'announcement')->latest('published_at')->limit(4)->get(['id', 'title', 'slug', 'excerpt', 'published_at'])]);
    }

    public function notifications(Request $r)
    {
        $r->validate(['per_page' => 'sometimes|integer|min:1|max:100']);

        return Api::page($r->user()->notifications()->paginate((int) $r->input('per_page', 20)));
    }

    public function markRead(Request $r, string $id)
    {
        $r->user()->notifications()->where('id', $id)->firstOrFail()->markAsRead();

        return Api::ok();
    }

    public function markAllRead(Request $r)
    {
        $r->user()->unreadNotifications()->update(['read_at' => now()]);

        return Api::ok();
    }

    public function export(Request $r, string $resource)
    {
        $u = $r->user();
        if ($resource === 'tickets') {
            $permission = 'tickets.manage';
            $query = Access::scope(Ticket::query(), $u);
            $fields = ['reference', 'type', 'subject', 'status', 'urgency', 'created_at', 'due_at'];
        } elseif ($resource === 'users') {
            $permission = 'users.manage';
            $query = Access::scope(User::query(), $u);
            $fields = ['id', 'name', 'email', 'mobile', 'person_type', 'is_active', 'created_at'];
        } else {
            abort_unless(in_array($resource, ['contents', 'members', 'services'], true), 404);
            $def = ResourceRegistry::get($resource);
            $permission = $def['permission'];
            $query = Access::scope($def['model']::query(), $u);
            $fields = match ($resource) {
                'contents' => ['id', 'type', 'title', 'status', 'published_at'],'members' => ['id', 'name', 'position', 'kind', 'organization_id'],default => ['id', 'name', 'code', 'organization_id', 'is_active']
            };
        }
        abort_unless($u->hasPermission($permission), 403);
        $r->validate(['from' => 'sometimes|date_format:Y-m-d', 'to' => 'sometimes|date_format:Y-m-d|after_or_equal:from']);
        if ($r->filled('from')) {
            $query->whereDate('created_at', '>=', $r->input('from'));
        } if ($r->filled('to')) {
            $query->whereDate('created_at', '<=', $r->input('to'));
        }
        abort_if((clone $query)->count() > 10000, 422, 'بازه گزارش را کوچک‌تر کنید؛ حداکثر ده هزار ردیف.');
        Audit::record('export.'.$resource);

        return response()->streamDownload(function () use ($query, $fields) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $fields, ',', '"', '');
            foreach ($query->select($fields)->orderBy('id')->cursor() as $row) {
                $values = [];
                foreach ($fields as $field) {
                    $value = (string) $row->$field;
                    if (preg_match('/^[=+\-@\t\r]/', $value)) {
                        $value = "'".$value;
                    }$values[] = $value;
                }fputcsv($out,$values,',','"','');
            }fclose($out);
        }, $resource.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }
}

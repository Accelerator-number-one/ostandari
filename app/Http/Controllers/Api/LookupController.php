<?php

namespace App\Http\Controllers\Api;

use App\Models\Role;
use App\Models\User;
use App\Services\Access;
use App\Services\ResourceRegistry;
use App\Support\Api;
use Illuminate\Http\Request;

class LookupController
{
    /** Minimal choices for editors; managing a related table is a separate permission. */
    public function index(Request $request, string $resource)
    {
        $permissions = match ($resource) {
            'organizations' => ['content.manage', 'members.manage', 'services.manage', 'forms.manage', 'users.manage', 'organizations.manage', 'tickets.manage'],
            'counties' => ['members.manage', 'organizations.manage', 'users.manage', 'tickets.manage'],
            'categories' => ['content.manage', 'members.manage', 'services.manage', 'forms.manage', 'categories.manage', 'forum.manage'],
            'contents' => ['content.manage', 'navigation.manage'],
            'menus', 'menu-items' => ['navigation.manage'],
            'galleries', 'tags' => ['content.manage'],
            'polls' => ['polls.manage'],
            'services' => ['services.manage'],
            'roles' => ['users.manage', 'roles.manage'],
            'users' => ['tickets.manage', 'forms.manage', 'users.manage'],
            default => abort(404),
        };
        $actor = $request->user();
        abort_unless(collect($permissions)->contains(fn ($permission) => $actor->hasPermission($permission)), 403);
        $request->validate(['per_page' => 'sometimes|integer|min:1|max:100', 'page' => 'sometimes|integer|min:1',
            'q' => 'sometimes|string|max:100', 'locale' => 'sometimes|in:fa,en,ar', 'type' => 'sometimes|string|max:30',
            'menu_id' => 'sometimes|integer|min:1', 'gallery_id' => 'sometimes|integer|min:1', 'poll_id' => 'sometimes|integer|min:1']);
        if ($resource === 'roles') {
            $query = Role::query()->select('id', 'name', 'label');
            $fields = ['name', 'label'];
        } elseif ($resource === 'users') {
            $query = Access::scope(User::query(), $actor)->where('is_active', true)->where('person_type', '!=', 'citizen')->select('id', 'name', 'person_type', 'organization_id');
            $fields = ['name'];
        } else {
            $definition = ResourceRegistry::get($resource);
            $query = Access::scope($definition['model']::query(), $actor, $definition['tenant'] ?? false);
            if ($resource === 'organizations' && ! $actor->hasGlobalAccess()) {
                $query->whereIn('id', Access::organizations($actor));
            }
            $fields = array_values(array_intersect(['name', 'title', 'question'], array_keys($definition['rules'])));
            $columns = array_values(array_intersect(['name', 'title', 'question', 'locale', 'type', 'kind', 'organization_id', 'menu_id', 'parent_id'], array_keys($definition['rules'])));
            $query->select(array_merge(['id'], $columns));
            foreach (['locale', 'type', 'menu_id', 'gallery_id', 'poll_id'] as $field) {
                if ($request->filled($field) && isset($definition['rules'][$field])) {
                    $query->where($field, $request->input($field));
                }
            }
        }
        if ($request->filled('q')) {
            $query->where(function ($query) use ($fields, $request) {
                foreach ($fields as $field) {
                    $query->orWhere($field, 'like', '%'.$request->input('q').'%');
                }
            });
        }

        return Api::page($query->orderByDesc('id')->paginate((int) $request->input('per_page', 100)));
    }
}

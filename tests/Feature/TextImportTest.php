<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TextImportTest extends TestCase
{
    use DatabaseMigrations;

    private function import(): void
    {
        foreach (['01-official-text.sql', '02-demo-text.sql'] as $file) {
            // These generated imports have one statement per semicolon/newline,
            // no routines and no quoted semicolon/newline sequences.
            $sql = file_get_contents(database_path('imports/'.$file));
            foreach (preg_split('/;(?=\s*(?:\r?\n|$))/', $sql) as $query) {
                if (trim($query) !== '') {
                    $statement = DB::connection()->getPdo()->query($query);
                    $statement->closeCursor();
                }
            }
        }
    }

    public function test_official_and_demo_sql_are_repeatable_preserve_existing_data_and_never_create_active_test_identities_or_media(): void
    {
        $this->seed(DatabaseSeeder::class);
        $root = Organization::where('slug', 'mazandaran')->firstOrFail();
        $root->update(['phone' => 'EXISTING-CONTACT']);
        $password = User::where('username', 'admin')->value('password');
        $permissions = DB::table('roles')->pluck('permissions', 'id')->all();
        $this->import();
        $tables = ['organizations', 'categories', 'services', 'contents', 'settings', 'users', 'members', 'forum_topics', 'subscribers', 'dynamic_forms', 'form_submissions', 'tickets', 'ticket_events', 'ticket_messages'];
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        $this->import();
        foreach ($tables as $table) {
            $this->assertSame($counts[$table], DB::table($table)->count(), $table.' duplicates');
        }
        $this->assertSame('EXISTING-CONTACT', $root->fresh()->phone);
        $this->assertSame($password, User::where('username', 'admin')->value('password'));
        $this->assertSame($permissions, DB::table('roles')->pluck('permissions', 'id')->all());
        $this->assertSame(6, DB::table('categories')->where('type', 'member')->count());
        $this->assertSame(5, DB::table('services')->count());
        $this->assertSame(5, DB::table('contents')->where('type', 'publication')->count());
        $this->assertSame(5, DB::table('categories')->where('type', 'form_attribute')->count());
        $this->assertSame(5, DB::table('forum_topics')->where('status', 'pending')->count());
        $this->assertSame(5, DB::table('dynamic_forms')->where('is_active', false)->count());
        $this->assertSame(5, DB::table('form_submissions')->count());
        $this->assertSame(5, DB::table('members')->where('is_active', false)->count());
        $this->assertSame(5, DB::table('subscribers')->where('is_active', false)->whereNull('verified_at')->count());
        $this->assertSame(15, DB::table('users')->where('username', 'like', 'demo_20261019_%')->where('is_active', false)->whereNull('password')->whereNull('mobile_verified_at')->count());
        $this->assertDatabaseCount('tickets', 5);
        $this->assertDatabaseCount('ticket_messages', 5);
        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseCount('mediables', 0);
        $this->assertDatabaseCount('notifications', 0);
        foreach (DB::table('tickets')->pluck('reference') as $reference) {
            $this->assertTrue(Str::isUlid($reference));
        }
        $this->assertSame(0, DB::table('tickets')->whereColumn('created_at', '>', 'due_at')->count());
    }

    public function test_sql_without_required_base_data_imports_nothing(): void
    {
        $this->import();
        foreach (['organizations', 'categories', 'services', 'contents', 'users', 'dynamic_forms', 'tickets', 'subscribers'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
    }
}

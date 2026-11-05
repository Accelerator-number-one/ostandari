<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existing = Schema::getColumnListing('members');
        Schema::table('members', function (Blueprint $table) use ($existing) {
            foreach (['term_start', 'term_end'] as $field) {
                $column = $table->dateTime($field)->nullable();
                if (in_array($field, $existing, true)) $column->change();
            }
        });
    }

    public function down(): void
    {
        // Narrowing back to TIMESTAMP would destroy historical dates saved before 1970.
        // The wider columns remain compatible with the previous application version.
    }
};

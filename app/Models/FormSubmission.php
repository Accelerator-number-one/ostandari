<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    use HasMedia;

    protected function casts(): array
    {
        return ['data' => 'array', 'schema_snapshot' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function form()
    {
        return $this->belongsTo(DynamicForm::class, 'dynamic_form_id')->withTrashed();
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id')->select(['id', 'name']);
    }
}

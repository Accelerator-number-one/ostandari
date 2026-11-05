<?php

namespace App\Services;

use App\Models\DynamicForm;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FormEngine
{
    public function validateSchema(array $fields): void
    {
        Validator::make(['fields' => $fields], [
            'fields' => 'required|array|min:1|max:40', 'fields.*' => 'array:key,label,type,required,options,max,min',
            'fields.*.key' => 'required|string|regex:/^[a-z][a-z0-9_]{0,49}$/|distinct',
            'fields.*.label' => 'required|string|max:150',
            'fields.*.type' => ['required', Rule::in(['text', 'textarea', 'email', 'number', 'date', 'select', 'multi_select', 'boolean', 'file'])],
            'fields.*.required' => 'sometimes|boolean', 'fields.*.options' => 'sometimes|array|min:1|max:50', 'fields.*.options.*' => 'string|max:255|distinct',
            'fields.*.min' => 'sometimes|numeric|min:0|max:1000000', 'fields.*.max' => 'sometimes|numeric|min:1|max:1000000',
        ])->validate();
        foreach ($fields as $field) {
            if (in_array($field['type'], ['select', 'multi_select'], true) && empty($field['options'])) {
                throw ValidationException::withMessages(['fields' => 'فیلد انتخابی باید گزینه داشته باشد.']);
            }
            if (isset($field['min'],$field['max']) && $field['min'] > $field['max']) {
                throw ValidationException::withMessages(['fields' => 'حداقل نمی‌تواند از حداکثر بزرگ‌تر باشد.']);
            }
        }
    }

    public function submit(DynamicForm $form, array $answers, User $user): FormSubmission
    {
        abort_unless($form->is_active && (! $form->opens_at || $form->opens_at->lte(now())) && (! $form->closes_at || $form->closes_at->gt(now())), 404);
        $rules = [];
        $files = [];
        foreach ($form->fields as $field) {
            $key = $field['key'];
            $rule = [($field['required'] ?? false) ? 'required' : 'nullable'];
            $rule[] = match ($field['type']) {
                'number' => 'numeric','date' => 'date_format:Y-m-d','boolean' => 'boolean','file' => 'integer','multi_select' => 'array','email' => 'email',default => 'string'
            };
            if (in_array($field['type'], ['text', 'textarea', 'email'], true)) {
                $rule[] = 'max:'.min((int) ($field['max'] ?? ($field['type'] === 'textarea' ? 10000 : 1000)), 10000);
            }
            if ($field['type'] === 'number') {
                foreach (['min', 'max'] as $limit) {
                    if (isset($field[$limit])) {
                        $rule[] = $limit.':'.$field[$limit];
                    }
                }
            }
            if ($field['type'] === 'select') {
                $rule[] = Rule::in($field['options']);
            }
            if ($field['type'] === 'multi_select') {
                $rule[] = 'max:50';
                $rules[$key.'.*'] = ['string', 'distinct', Rule::in($field['options'])];
            }
            if ($field['type'] === 'file') {
                $rule[] = Rule::exists('media', 'id')->where('user_id', $user->id)->where('visibility', 'private')->whereNull('deleted_at');
                if (! empty($answers[$key])) {
                    $files[] = (int) $answers[$key];
                }
            }
            $rules[$key] = $rule;
        }
        if (array_diff(array_keys($answers), array_column($form->fields, 'key'))) {
            throw ValidationException::withMessages(['answers' => 'فیلد ناشناخته در پاسخ‌ها وجود دارد.']);
        }
        $clean = Validator::make($answers, $rules)->validate();

        return DB::transaction(function () use ($form, $user, $clean, $files) {
            $submission = FormSubmission::create(['reference' => (string) Str::ulid(), 'dynamic_form_id' => $form->id, 'user_id' => $user->id, 'data' => $clean, 'schema_snapshot' => $form->fields, 'status' => 'pending']);
            FileAccess::attach($submission, array_values(array_unique($files)), $user);

            return $submission;
        });
    }
}

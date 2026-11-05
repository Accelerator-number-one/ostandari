<?php

namespace App\Http\Controllers\Api;

use App\Models\DynamicForm;
use App\Models\FormSubmission;
use App\Services\Access;
use App\Services\Audit;
use App\Services\FormEngine;
use App\Services\Notice;
use App\Support\Api;
use Illuminate\Http\Request;

class FormController
{
    public function submit(Request $r, int $id, FormEngine $engine)
    {
        $d = $r->validate(['answers' => 'required|array|max:40']);

        return Api::ok($engine->submit(DynamicForm::findOrFail($id), $d['answers'], $r->user()), 'پاسخ فرم ثبت شد.', 201);
    }

    public function mine(Request $r)
    {
        $r->validate(['per_page' => 'sometimes|integer|min:1|max:100']);

        return Api::page(FormSubmission::where('user_id', $r->user()->id)->with('form:id,title,slug')->latest('id')->paginate((int) $r->input('per_page', 20)));
    }

    public function showMine(Request $r, string $reference)
    {
        return Api::ok(FormSubmission::where('user_id', $r->user()->id)->where('reference', $reference)->with('media', 'form:id,title,slug')->firstOrFail());
    }

    private function query(Request $r)
    {
        return FormSubmission::whereIn('dynamic_form_id', Access::scope(DynamicForm::query()->withTrashed(), $r->user())->select('id'));
    }

    public function index(Request $r)
    {
        $r->validate(['per_page' => 'sometimes|integer|min:1|max:100', 'status' => 'sometimes|in:pending,review,accepted,rejected', 'form_id' => 'sometimes|integer', 'q' => 'sometimes|string|max:100']);
        $q = $this->query($r)->with('form:id,title,slug', 'owner');
        if ($r->filled('status')) {
            $q->where('status', $r->input('status'));
        } if ($r->filled('form_id')) {
            $q->where('dynamic_form_id', $r->input('form_id'));
        }
        if ($r->filled('q')) {
            $term = '%'.$r->input('q').'%';
            $q->where(fn ($query) => $query->where('reference', 'like', $term)
                ->orWhereHas('form', fn ($form) => $form->where('title', 'like', $term))
                ->orWhereHas('owner', fn ($owner) => $owner->where('name', 'like', $term)));
        }

        return Api::page($q->latest('id')->paginate((int) $r->input('per_page', 20)));
    }

    public function show(Request $r, string $reference)
    {
        return Api::ok($this->query($r)->where('reference', $reference)->with('form', 'owner', 'media')->firstOrFail());
    }

    public function review(Request $r, string $reference)
    {
        $submission = $this->query($r)->where('reference', $reference)->firstOrFail();
        $d = $r->validate(['status' => 'required|in:pending,review,accepted,rejected', 'reply' => 'nullable|string|max:10000']);
        $submission->update($d + ['reviewed_by' => $r->user()->id, 'reviewed_at' => now()]);
        Notice::send($submission->user_id, 'نتیجه بررسی فرم ثبت شد.', ['reference' => $submission->reference, 'status' => $submission->status]);
        Audit::record('form_submission.reviewed',$submission,$d);

        return Api::ok($submission);
    }
}

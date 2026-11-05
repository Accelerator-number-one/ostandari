<form class="portal-form" x-data="apiForm(@js('citizen/forms/'.$item['id'].'/submissions'))" data-dynamic-form="true" @submit.prevent="submit($event)">
    @foreach($item['fields'] ?? [] as $field)
        @php($attributes=['data-answer-key'=>$field['key'],'data-answer-type'=>$field['type'],'name'=>$field['key']])
        <label class="block my-5">{{ $field['label'] }} <small>{{ ($field['required'] ?? false)?'الزامی':'اختیاری' }}</small>
            @if(in_array($field['type'],['select','multi_select'],true))
                <select data-answer-key="{{ $field['key'] }}" data-answer-type="{{ $field['type'] }}" name="{{ $field['key'] }}" @required($field['required'] ?? false) @if($field['type']==='multi_select') multiple @endif>
                    @if($field['type']==='select')<option value="">انتخاب کنید</option>@endif
                    @foreach($field['options'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                </select>
            @elseif($field['type']==='textarea')
                <textarea data-answer-key="{{ $field['key'] }}" data-answer-type="textarea" name="{{ $field['key'] }}" @required($field['required'] ?? false) maxlength="{{ min($field['max'] ?? 10000,10000) }}"></textarea>
            @elseif($field['type']==='boolean')
                <input data-answer-key="{{ $field['key'] }}" data-answer-type="boolean" name="{{ $field['key'] }}" type="checkbox" @required($field['required'] ?? false)>
            @else
                <input data-answer-key="{{ $field['key'] }}" data-answer-type="{{ $field['type'] }}" name="{{ $field['key'] }}" type="{{ $field['type'] }}" @required($field['required'] ?? false) @if($field['type']==='number') @isset($field['min']) min="{{ $field['min'] }}" @endisset @isset($field['max']) max="{{ $field['max'] }}" @endisset @endif>
            @endif
        </label>
    @endforeach
    <button class="portal-button" :disabled="busy" x-text="busy?'در حال ارسال…':'ثبت درخواست'">ثبت درخواست</button>
    <p role="alert" x-text="error"></p><p role="status" x-text="message"></p>
</form>

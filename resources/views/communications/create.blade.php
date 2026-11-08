@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><h2>Compose bulk SMS</h2><a class="btn btn-light" href="{{ route('communications.index') }}">Message history</a></div>
<form method="post" action="{{ route('communications.store') }}" class="card card-body">
    @csrf
    <label for="sms-audience" class="form-label">Send to</label>
    <select id="sms-audience" name="audience" class="form-select mb-3" required>
        <option value="">Select an audience</option>
        <option value="all" @selected(old('audience') === 'all')>All active members</option>
        <option value="group" @selected(old('audience') === 'group')>A group</option>
        <option value="district" @selected(old('audience') === 'district')>A district</option>
    </select>
    <div id="sms-group-field">
        <label for="sms-group" class="form-label">Group</label>
        <select id="sms-group" name="group_id" class="form-select mb-3">
            <option value="">Select a group</option>
            @foreach($groups as $group)<option value="{{ $group->id }}" @selected(old('group_id') == $group->id)>{{ $group->name }}</option>@endforeach
        </select>
    </div>
    <div id="sms-district-field">
        <label for="sms-district" class="form-label">District</label>
        <select id="sms-district" name="district_id" class="form-select mb-3">
            <option value="">Select a district</option>
            @foreach($districts as $district)<option value="{{ $district->id }}" @selected(old('district_id') == $district->id)>{{ $district->name }}</option>@endforeach
        </select>
    </div>
    <label for="sms-message" class="form-label">Message</label>
    <textarea id="sms-message" name="message" class="form-control" rows="6" required maxlength="1600" aria-describedby="sms-message-help">{{ old('message') }}</textarea>
    <div class="d-flex justify-content-between gap-2 mt-2"><p id="sms-message-help" class="small text-muted">This exact message will be sent to each recipient. Long messages may use multiple SMS credits per recipient.</p><span id="sms-count" class="small text-muted text-nowrap" aria-live="polite"></span></div>
    <p class="small text-muted">Only active members with valid Kenyan mobile numbers are included. Shared phone numbers receive one message. You can review the recipient list before sending.</p>
    <div><button class="btn btn-primary" type="submit">Preview recipients</button></div>
</form>
@endsection
@push('scripts')
<script>
const smsAudience = document.getElementById('sms-audience');
const smsMessage = document.getElementById('sms-message');
function updateSmsAudience() {
    ['group', 'district'].forEach(type => {
        const selected = smsAudience.value === type;
        document.getElementById(`sms-${type}-field`).hidden = !selected;
        document.getElementById(`sms-${type}`).disabled = !selected;
        document.getElementById(`sms-${type}`).required = selected;
    });
}
function updateSmsCount() {
    document.getElementById('sms-count').textContent = `${Array.from(smsMessage.value).length} / 1,600`;
}
smsAudience.addEventListener('change', updateSmsAudience);
smsMessage.addEventListener('input', updateSmsCount);
updateSmsAudience();
updateSmsCount();
</script>
@endpush

@extends('layouts.app')
@push('styles')
<link href="{{ asset('vendor/select2/select2.min.css') }}" rel="stylesheet">
@endpush
@section('content')
<h2>Record Giving</h2>
@if($types->isEmpty())
<div class="alert alert-warning">No active giving types are available. Ask an administrator to add or reactivate a giving type.</div>
@endif
<form method="post" action="{{ route('givings.store') }}" class="card card-body">
    @csrf
    <label for="ekanisa" class="form-label">Member</label>
    <select id="ekanisa" name="ekanisa_number" class="form-select" required aria-describedby="member-help">
        <option value=""></option>
        @foreach($members as $member)
            <option value="{{ $member->ekanisa_number }}" @selected((string) old('ekanisa_number') === (string) $member->ekanisa_number)>{{ $member->name }} — {{ $member->ekanisa_number }} — {{ $member->phone }}</option>
        @endforeach
    </select>
    <p id="member-help" class="text-muted small mt-2 mb-4">Search by name, eKanisa number or phone. Only active members are listed.</p>
    <h3 class="h5">Giving types and amounts</h3>
    <div id="giving-items">
        @foreach(old('items', [['giving_type_id' => '', 'amount' => '']]) as $item)
            <div class="row g-2 mb-3 giving-item">
                <div class="col-md-7">
                    <label class="form-label" for="type-{{ $loop->index }}">Giving Type</label>
                    <select id="type-{{ $loop->index }}" name="items[{{ $loop->index }}][giving_type_id]" class="form-select giving-type" required>
                        <option value="">Select type</option>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}" @selected(($item['giving_type_id'] ?? '') == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="amount-{{ $loop->index }}">Amount (KES)</label>
                    <input id="amount-{{ $loop->index }}" type="number" step="0.01" min="0.01" max="999999999999.99" name="items[{{ $loop->index }}][amount]" required class="form-control giving-amount" value="{{ $item['amount'] ?? '' }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger remove-item">Remove</button>
                </div>
            </div>
        @endforeach
    </div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <button type="button" id="add-item" class="btn btn-outline-primary add-giving-button" aria-label="Add giving type" title="Add giving type"><span aria-hidden="true">+</span></button>
        <strong aria-live="polite">Total: KES <span id="giving-total">0.00</span></strong>
    </div>
    <p class="text-muted">One SMS will list each giving type and amount, followed by the total.</p>
    <label for="payment-method" class="form-label">Payment Method</label>
    <select id="payment-method" name="payment_method" class="form-select mb-3">
        @foreach(['Cash','MPESA','Bank','Cheque'] as $method)
            <option @selected(old('payment_method') === $method)>{{ $method }}</option>
        @endforeach
    </select>
    <label for="giving-date" class="form-label">Date</label>
    <input id="giving-date" type="date" name="giving_date" class="form-control mb-3" max="{{ now()->toDateString() }}" value="{{ old('giving_date', now()->toDateString()) }}" required>
    <label for="external-reference" class="form-label">External Payment Reference (optional)</label>
    <input id="external-reference" name="external_reference" maxlength="100" class="form-control mb-3" value="{{ old('external_reference') }}">
    <button class="btn btn-primary" @disabled($types->isEmpty())>Save and Queue SMS</button>
</form>
@endsection
@push('scripts')
<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/select2/select2.min.js') }}"></script>
<script>
window.jQuery('#ekanisa').select2({
    placeholder: 'Search and select a member',
    allowClear: true,
    width: '100%'
});
const items = document.getElementById('giving-items');
const addButton = document.getElementById('add-item');
const maxItems = Math.min(50, @json($types->count()));
const rowTemplate = items.querySelector('.giving-item').cloneNode(true);
function updateItems() {
    const rows = [...items.querySelectorAll('.giving-item')];
    const selected = rows.map(row => row.querySelector('.giving-type').value).filter(Boolean);
    let cents = 0;
    rows.forEach((row, index) => {
        const type = row.querySelector('.giving-type');
        const amount = row.querySelector('.giving-amount');
        type.name = `items[${index}][giving_type_id]`;
        type.id = `type-${index}`;
        amount.name = `items[${index}][amount]`;
        amount.id = `amount-${index}`;
        row.querySelectorAll('label')[0].htmlFor = type.id;
        row.querySelectorAll('label')[1].htmlFor = amount.id;
        row.querySelector('.remove-item').disabled = rows.length === 1;
        [...type.options].forEach(option => {
            option.disabled = option.value !== '' && option.value !== type.value && selected.includes(option.value);
        });
        cents += Math.round((Number(amount.value) || 0) * 100);
    });
    document.getElementById('giving-total').textContent = (cents / 100).toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    addButton.disabled = rows.length >= maxItems;
}
addButton.addEventListener('click', () => {
    if (items.children.length >= maxItems) return;
    const row = rowTemplate.cloneNode(true);
    row.querySelector('.giving-type').value = '';
    row.querySelector('.giving-amount').value = '';
    items.appendChild(row);
    updateItems();
    row.querySelector('select').focus();
});
items.addEventListener('click', event => {
    if (event.target.closest('.remove-item') && items.children.length > 1) {
        event.target.closest('.giving-item').remove();
        updateItems();
    }
});
items.addEventListener('input', updateItems);
items.addEventListener('change', updateItems);
updateItems();
</script>
@endpush

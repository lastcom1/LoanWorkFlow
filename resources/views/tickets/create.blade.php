@extends('layouts.app')

@section('title', 'New Ticket')
@section('heading', 'Submit Ticket')
@section('subheading', 'Fund Transfer Instruction or Reversal Complaint')

@section('content')
<div class="panel">
    <div class="alert alert-info">
        Do not enter PINs, CVVs, OTPs, passwords, or other authentication secrets.
        Duplicate checks: transfers match on normalized account + amount; reversals match primarily on original/transaction reference number.
    </div>

    <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" id="ticket-form">
        @csrf
        <div class="form-grid">
            <div>
                <label for="category">Category</label>
                <select name="category" id="category" required>
                    <option value="fund_transfer" @selected(old('category')==='fund_transfer')>Fund Transfer Instruction</option>
                    <option value="reversal_complaint" @selected(old('category')==='reversal_complaint')>Reversal Complaint</option>
                </select>
            </div>
            <div>
                <label for="branch_id">Branch</label>
                <select name="branch_id" id="branch_id" required>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id', auth()->user()->branch_id)==$branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="customer_account">Customer account number</label>
                <input type="text" name="customer_account" id="customer_account" value="{{ old('customer_account') }}" required>
            </div>
            <div>
                <label for="amount">Amount (NGN)</label>
                <input type="number" step="0.01" min="0.01" name="amount" id="amount" value="{{ old('amount') }}" required>
            </div>
            <div>
                <label for="transaction_at">Transaction date/time</label>
                <input type="datetime-local" name="transaction_at" id="transaction_at" value="{{ old('transaction_at') }}">
            </div>
            <div>
                <label for="original_reference">Original / transaction reference</label>
                <input type="text" name="original_reference" id="original_reference" value="{{ old('original_reference') }}">
                <div class="help" id="ref-help">Required for reversal complaints (primary duplicate key).</div>
            </div>
            <div>
                <label for="source_channel">Source / channel</label>
                <input type="text" name="source_channel" id="source_channel" value="{{ old('source_channel') }}" placeholder="Branch counter, email, etc.">
            </div>
            <div>
                <label for="priority">Priority</label>
                <select name="priority" id="priority">
                    @foreach(['low','normal','high','urgent'] as $p)
                        <option value="{{ $p }}" @selected(old('priority','normal')===$p)>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="beneficiary_name">Beneficiary name</label>
                <input type="text" name="beneficiary_name" id="beneficiary_name" value="{{ old('beneficiary_name') }}">
            </div>
            <div>
                <label for="beneficiary_bank">Beneficiary bank</label>
                <input type="text" name="beneficiary_bank" id="beneficiary_bank" value="{{ old('beneficiary_bank') }}">
            </div>
            <div>
                <label for="beneficiary_account">Beneficiary account</label>
                <input type="text" name="beneficiary_account" id="beneficiary_account" value="{{ old('beneficiary_account') }}">
            </div>
            <div class="full" id="reversal-reason-wrap">
                <label for="reversal_reason">Reversal / failure reason</label>
                <textarea name="reversal_reason" id="reversal_reason" rows="3">{{ old('reversal_reason') }}</textarea>
            </div>
            <div class="full">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="3">{{ old('description') }}</textarea>
            </div>
            <div class="full">
                <label for="attachments">Attachments (PDF/JPG/PNG/DOC/XLS, max 5MB each)</label>
                <input type="file" name="attachments[]" id="attachments" multiple>
            </div>
        </div>

        <div id="duplicate-warning" class="dup-box" style="display:none">
            <h4>Potential duplicate detected</h4>
            <p id="duplicate-message" class="muted"></p>
            <div id="duplicate-list"></div>
            <label style="margin-top:0.75rem;display:flex;gap:0.5rem;align-items:flex-start;font-weight:500">
                <input type="checkbox" name="acknowledge_duplicate" value="1" id="acknowledge_duplicate">
                I have reviewed the existing ticket(s) and still need to submit this request.
            </label>
            <div style="margin-top:0.5rem">
                <label for="duplicate_override_reason">Reason for proceeding</label>
                <textarea name="duplicate_override_reason" id="duplicate_override_reason" rows="2">{{ old('duplicate_override_reason') }}</textarea>
            </div>
        </div>

        <div style="margin-top:1rem">
            <label style="display:flex;gap:0.5rem;align-items:flex-start;font-weight:500">
                <input type="checkbox" name="confirmation_accepted" value="1" required @checked(old('confirmation_accepted'))>
                I confirm the details are accurate and that no authentication secrets are included.
            </label>
        </div>

        <div class="actions" style="margin-top:1.25rem">
            <button class="btn btn-primary" type="submit">Submit ticket</button>
            <button class="btn btn-outline" type="button" id="check-dup-btn">Check duplicates</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const category = document.getElementById('category');
  const reversalWrap = document.getElementById('reversal-reason-wrap');
  const warning = document.getElementById('duplicate-warning');
  const list = document.getElementById('duplicate-list');
  const message = document.getElementById('duplicate-message');
  const csrf = document.querySelector('meta[name="csrf-token"]').content;

  function toggleCategory() {
    const isReversal = category.value === 'reversal_complaint';
    reversalWrap.style.display = isReversal ? 'block' : 'none';
  }
  category.addEventListener('change', toggleCategory);
  toggleCategory();

  async function checkDuplicates() {
    const body = {
      category: category.value,
      customer_account: document.getElementById('customer_account').value,
      amount: document.getElementById('amount').value,
      original_reference: document.getElementById('original_reference').value,
    };
    const res = await fetch(@json(route('tickets.check-duplicates')), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf,
      },
      body: JSON.stringify(body),
    });
    const data = await res.json();
    if (!data.has_duplicates) {
      warning.style.display = 'none';
      list.innerHTML = '';
      return false;
    }
    warning.style.display = 'block';
    message.textContent = data.message || '';
    list.innerHTML = data.matches.map(m => `
      <div style="padding:0.45rem 0;border-bottom:1px solid #f0d7a0">
        <strong><a href="${m.url}">${m.ticket_ref}</a></strong>
        — ${m.masked_account} · ${m.amount} · ${m.status}
        ${m.is_open ? '(active)' : '(completed/closed)'}
        <div class="muted">${m.branch || ''} · ${m.submitter || ''} · Officer: ${m.assigned_officer || 'unassigned'}</div>
      </div>
    `).join('');
    return true;
  }

  document.getElementById('check-dup-btn').addEventListener('click', (e) => {
    e.preventDefault();
    checkDuplicates();
  });

  document.getElementById('ticket-form').addEventListener('submit', async (e) => {
    const hasDup = await checkDuplicates();
    if (hasDup && !document.getElementById('acknowledge_duplicate').checked) {
      e.preventDefault();
      alert('Please review and acknowledge the duplicate warning before submitting.');
    }
  });
})();
</script>
@endpush

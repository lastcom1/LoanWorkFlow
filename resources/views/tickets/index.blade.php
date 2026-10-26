@extends('layouts.app')

@section('title', 'Tickets')
@section('heading', 'Tickets')
@section('actions')
    @if(auth()->user()->hasAnyRole(['submitter','admin']))
        <a class="btn btn-gold" href="{{ route('tickets.create') }}">New Ticket</a>
    @endif
@endsection

@section('content')
<div class="panel">
    <form method="GET" class="form-grid" style="margin-bottom:1rem">
        <div>
            <label>Search</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Ref, account, original reference">
        </div>
        <div>
            <label>Status</label>
            <select name="status">
                <option value="">All</option>
                @foreach(['available','claimed','in_progress','info_required','completed','rejected'] as $st)
                    <option value="{{ $st }}" @selected(request('status')===$st)>{{ str_replace('_',' ', ucfirst($st)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Category</label>
            <select name="category">
                <option value="">All</option>
                <option value="fund_transfer" @selected(request('category')==='fund_transfer')>Fund Transfer</option>
                <option value="reversal_complaint" @selected(request('category')==='reversal_complaint')>Reversal Complaint</option>
            </select>
        </div>
        <div style="display:flex;align-items:end">
            <button class="btn btn-primary" type="submit">Filter</button>
        </div>
    </form>
    @include('tickets._table', ['tickets' => $tickets])
</div>
@endsection

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index()
    {
        $branches = Branch::query()->orderBy('name')->paginate(25);

        return view('admin.branches.index', compact('branches'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:branches,code'],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
        ]);

        $branch = Branch::query()->create($data + ['is_active' => true]);
        $this->audit->log('branch_created', $request->user(), $branch, null, null, $data);

        return back()->with('success', 'Branch created.');
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('branches', 'code')->ignore($branch->id)],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $before = $branch->only(['code', 'name', 'city', 'state', 'is_active']);
        $branch->fill($data);
        $branch->is_active = $request->boolean('is_active', true);
        $branch->save();

        $this->audit->log('branch_updated', $request->user(), $branch, null, $before, $branch->only(['code', 'name', 'city', 'state', 'is_active']));

        return back()->with('success', 'Branch updated.');
    }
}

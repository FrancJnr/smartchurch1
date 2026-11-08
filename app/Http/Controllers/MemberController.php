<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Group;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $query = Member::with(['district', 'group']);
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(fn ($q) => $q->where('name', 'like', "%$search%")->orWhere('ekanisa_number', 'like', "%$search%")->orWhere('phone', 'like', "%$search%"));
        }

        return view('members.index', ['members' => $query->latest()->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        return $this->form(new Member);
    }

    public function edit(Member $member): View
    {
        return $this->form($member);
    }

    private function form(Member $member): View
    {
        return view('members.form', [
            'member' => $member,
            'districts' => District::where('is_active', true)->orWhere('id', $member->district_id)->orderBy('name')->get(),
            'groups' => Group::where('is_active', true)->orWhere('id', $member->group_id)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Member::create($this->validated($request));

        return redirect()->route('members.index')->with('success', 'Member registered');
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $member->update($this->validated($request, $member));

        return redirect()->route('members.index')->with('success', 'Member updated');
    }

    public function lookup(string $ekanisa): JsonResponse
    {
        $member = Member::where('ekanisa_number', $ekanisa)->where('status', 'active')->first();

        return $member ? response()->json($member->only(['id', 'name', 'phone', 'ekanisa_number'])) : response()->json(['message' => 'Active member not found'], 404);
    }

    private function validated(Request $request, ?Member $member = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'phone' => ['required', 'regex:/^\+?[0-9]{9,15}$/'],
            'ekanisa_number' => ['required', 'string', 'max:50', Rule::unique('members', 'ekanisa_number')->ignore($member?->id)],
            'status' => 'required|in:active,inactive',
            'district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $member?->district_id))],
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $member?->group_id))],
        ]);
    }
}

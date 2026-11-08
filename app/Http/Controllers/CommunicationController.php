<?php

namespace App\Http\Controllers;

use App\Jobs\SendBulkSms;
use App\Models\District;
use App\Models\Group;
use App\Models\Member;
use App\Models\SmsCampaign;
use App\Services\SmsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class CommunicationController extends Controller
{
    public function index(): View
    {
        return view('communications.index', ['campaigns' => SmsCampaign::with('creator')->withCount([
            'recipients',
            'recipients as accepted_count' => fn ($q) => $q->where('status', 'accepted'),
            'recipients as unresolved_count' => fn ($q) => $q->whereIn('status', ['pending', 'processing']),
        ])->latest('id')->paginate(20)]);
    }

    public function create(): View
    {
        return view('communications.create', [
            'groups' => Group::where('is_active', true)->orderBy('name')->get(),
            'districts' => District::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, SmsGateway $gateway): RedirectResponse
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(['all', 'group', 'district'])],
            'group_id' => ['exclude_unless:audience,group', 'required', 'integer', Rule::exists('groups', 'id')->where('is_active', true)],
            'district_id' => ['exclude_unless:audience,district', 'required', 'integer', Rule::exists('districts', 'id')->where('is_active', true)],
            'message' => ['required', 'string', 'max:1600'],
        ]);
        $query = Member::where('status', 'active');
        $label = 'All active members';
        if ($data['audience'] === 'group') {
            $query->where('group_id', $data['group_id']);
            $label = 'Group: '.Group::findOrFail($data['group_id'])->name;
        } elseif ($data['audience'] === 'district') {
            $query->where('district_id', $data['district_id']);
            $label = 'District: '.District::findOrFail($data['district_id'])->name;
        }
        $recipients = [];
        $invalid = 0;
        $duplicates = 0;
        $matched = 0;
        foreach ($query->orderBy('id')->cursor() as $member) {
            $matched++;
            try {
                $phone = $gateway->normalizeKenyanPhone($member->phone);
            } catch (RuntimeException) {
                $invalid++;

                continue;
            }
            if (isset($recipients[$phone])) {
                $duplicates++;

                continue;
            }
            $recipients[$phone] = ['member_id' => $member->id, 'member_name' => $member->name, 'phone' => $phone];
        }
        if ($recipients === []) {
            throw ValidationException::withMessages(['audience' => 'This audience has no active members with valid Kenyan mobile numbers.']);
        }
        $campaign = DB::transaction(function () use ($request, $data, $label, $matched, $invalid, $duplicates, $recipients): SmsCampaign {
            $campaign = SmsCampaign::create([
                'created_by' => $request->user()->id,
                'audience' => $data['audience'],
                'audience_label' => $label,
                'group_id' => $data['group_id'] ?? null,
                'district_id' => $data['district_id'] ?? null,
                'message' => $data['message'],
                'matched_members' => $matched,
                'invalid_numbers' => $invalid,
                'duplicate_numbers' => $duplicates,
                'status' => 'draft',
            ]);
            foreach (array_chunk(array_values($recipients), 500) as $chunk) {
                $campaign->recipients()->insert(array_map(fn ($recipient): array => [
                    ...$recipient, 'sms_campaign_id' => $campaign->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
                ], $chunk));
            }

            return $campaign;
        });

        return redirect()->route('communications.show', $campaign)->with('success', 'Preview ready. No SMS has been sent yet.');
    }

    public function show(SmsCampaign $campaign): View
    {
        $counts = $campaign->recipients()->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('communications.show', [
            'campaign' => $campaign->load('creator'),
            'recipients' => $campaign->recipients()->orderBy('id')->paginate(25),
            'counts' => $counts,
        ]);
    }

    public function send(Request $request, SmsCampaign $campaign): RedirectResponse
    {
        abort_unless($campaign->created_by === $request->user()->id, 403, 'Only the author can send this message.');
        foreach (['api_key', 'partner_id', 'sender_id', 'endpoint'] as $setting) {
            if (! config('advanta.'.$setting)) {
                throw ValidationException::withMessages(['message' => 'SMS configuration is incomplete. Contact an administrator before sending.']);
            }
        }
        DB::transaction(function () use ($campaign): void {
            $campaign = SmsCampaign::lockForUpdate()->findOrFail($campaign->id);
            if ($campaign->status !== 'draft') {
                return;
            }
            $campaign->update(['status' => 'queued', 'queued_at' => now()]);
            foreach ($campaign->recipients()->where('status', 'pending')->orderBy('id')->cursor() as $recipient) {
                SendBulkSms::dispatch($recipient->id)->afterCommit();
            }
        });

        return redirect()->route('communications.show', $campaign)->with('success', 'SMS sending has been queued. Refresh this page to see progress.');
    }
}

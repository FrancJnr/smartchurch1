<?php

namespace App\Http\Controllers;

use App\Jobs\SendGivingSms;
use App\Models\Giving;
use App\Models\GivingType;
use App\Models\Member;
use App\Models\SmsLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GivingController extends Controller
{
    public function index(): View
    {
        return view('givings.index', ['givings' => Giving::with(['member', 'givingType', 'smsLog'])->latest()->paginate(20)]);
    }

    public function create(): View
    {
        return view('givings.create', [
            'types' => GivingType::where('is_active', true)->orderBy('name')->get(),
            'members' => Member::where('status', 'active')->orderBy('name')->get(['name', 'ekanisa_number', 'phone']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ekanisa_number' => ['required', 'string', Rule::exists('members', 'ekanisa_number')->where('status', 'active')],
            'items' => 'required|array|min:1|max:50',
            'items.*' => 'required|array:giving_type_id,amount',
            'items.*.giving_type_id' => ['required', 'integer', 'distinct', Rule::exists('giving_types', 'id')->where('is_active', true)],
            'items.*.amount' => 'required|numeric|gt:0|decimal:0,2|max:999999999999.99',
            'payment_method' => 'required|in:Cash,MPESA,Bank,Cheque',
            'giving_date' => 'required|date|before_or_equal:today',
            'external_reference' => 'nullable|string|max:100|unique:givings,external_reference',
        ], [
            'items.*.giving_type_id.distinct' => 'Select each giving type only once.',
        ]);
        $member = Member::where('ekanisa_number', $data['ekanisa_number'])->where('status', 'active')->firstOrFail();

        DB::transaction(function () use ($request, $data, $member): void {
            $receipt = 'GIV-'.Str::ulid();
            $types = GivingType::whereIn('id', array_column($data['items'], 'giving_type_id'))->get()->keyBy('id');
            $givings = collect();
            $lines = [];
            $totalCents = 0;

            foreach (array_values($data['items']) as $index => $item) {
                $amountCents = (int) round((float) $item['amount'] * 100);
                $totalCents += $amountCents;
                $giving = Giving::create([
                    'member_id' => $member->id,
                    'giving_type_id' => $item['giving_type_id'],
                    'amount' => $item['amount'],
                    'payment_method' => $data['payment_method'],
                    'giving_date' => $data['giving_date'],
                    'external_reference' => $index === 0 ? ($data['external_reference'] ?? null) : null,
                    'recorded_by' => $request->user()->id,
                    'transaction_reference' => $index === 0 ? $receipt : $receipt.'-'.($index + 1),
                ]);
                $givings->push($giving);
                $lines[] = $types[$item['giving_type_id']]->name.': KES '.number_format($amountCents / 100, 2);
            }

            $message = 'Dear '.$member->name.', thank you for your giving. '.implode('; ', $lines)
                .'. Total: KES '.number_format($totalCents / 100, 2).'. Receipt: '.$receipt.'. God bless you.';
            $log = SmsLog::create([
                'giving_id' => $givings->first()->id,
                'phone' => $member->phone,
                'message' => $message,
                'status' => 'pending',
            ]);
            Giving::whereIn('id', $givings->pluck('id')->all())->update(['sms_log_id' => $log->id]);
            SendGivingSms::dispatch($log->id)->afterCommit();
        });

        return redirect()->route('givings.index')->with('success', 'Giving saved; one SMS with all giving types and the total queued.');
    }
}

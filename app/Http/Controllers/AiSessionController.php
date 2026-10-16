<?php

namespace App\Http\Controllers;

use App\Models\AiSession;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// AI提案結果の一覧・会話内容（管理者向け）
class AiSessionController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', Rule::in(array_keys(AiSession::STATUSES))],
            'booked' => ['nullable', Rule::in(['yes', 'no'])],
        ]);

        $sessions = AiSession::with('customer')
            ->withCount(['messages' => fn ($q) => $q->where('role', 'user')])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->input('booked') === 'yes', fn ($q) => $q->whereNotNull('customer_id'))
            ->when($request->input('booked') === 'no', fn ($q) => $q->whereNull('customer_id'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('ai-sessions.index', compact('sessions'));
    }

    public function show(AiSession $aiSession): View
    {
        $aiSession->load('customer', 'messages');

        return view('ai-sessions.show', compact('aiSession'));
    }
}

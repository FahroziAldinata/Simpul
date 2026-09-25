<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    /**
     * Display the audit log list.
     */
    public function index(Request $request): Response
    {
        $query = Activity::with('causer')
            ->latest();

        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->input('causer_id'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $activities = $query->paginate(20)->withQueryString();

        return Inertia::render('audit-log/Index', [
            'activities' => $activities,
            'filters' => $request->only(['causer_id', 'subject_type', 'from', 'to']),
        ]);
    }
}

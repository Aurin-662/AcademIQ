<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * System Administrator Analytics Dashboard (All Institutions)
     */
    public function admin()
    {
        $perDay    = $this->analyticsService->getVerificationsPerDay();
        $breakdown = $this->analyticsService->getResultBreakdown();
        $topInst   = $this->analyticsService->getTopInstitutions(10);
        $byHour    = $this->analyticsService->getVerificationsByHour();
        $fraud     = $this->analyticsService->getFraudFlagsByTypeAndStatus();
        $docTypes  = $this->analyticsService->getMostVerifiedDocumentTypes();

        return view('admin.analytics', compact(
            'perDay', 'breakdown', 'topInst', 'byHour', 'fraud', 'docTypes'
        ));
    }

    /**
     * Institution Administrator Analytics Dashboard (Own Institution Only)
     */
    public function institution()
    {
        $user = auth()->user();
        $instCode = $user->institution_code;

        $perDay    = $this->analyticsService->getVerificationsPerDay($instCode);
        $breakdown = $this->analyticsService->getResultBreakdown($instCode);
        $byHour    = $this->analyticsService->getVerificationsByHour($instCode);
        $fraud     = $this->analyticsService->getFraudFlagsByTypeAndStatus($instCode);
        $docTypes  = $this->analyticsService->getMostVerifiedDocumentTypes($instCode);

        return view('institution.analytics', compact(
            'instCode', 'perDay', 'breakdown', 'byHour', 'fraud', 'docTypes'
        ));
    }
}

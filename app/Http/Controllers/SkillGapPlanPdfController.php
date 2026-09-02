<?php

namespace App\Http\Controllers;

use App\Models\SkillGapPlan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF export of the user's latest Skills Gap Plan — something they can
 * print or hand to a training provider. Pure-PHP (dompdf), so it works
 * on shared hosting with no binaries.
 */
class SkillGapPlanPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        /** @var SkillGapPlan|null $plan */
        $plan = $user->skillGapPlans()->latest('generated_at')->latest('id')->first();

        abort_if($plan === null, 404, 'Generate a plan first.');

        $profile = $user->profile()->first();
        $doneSkillIds = $profile ? $profile->skills()->pluck('skills.id')->map(fn ($id) => (int) $id)->all() : [];

        $pdf = Pdf::loadView('plan.pdf', [
            'user' => $user,
            'plan' => $plan,
            'payload' => $plan->payload,
            'doneSkillIds' => $doneSkillIds,
            'generatedAt' => $plan->generated_at->timezone(config('app.display_timezone')),
        ])->setPaper('a4');

        $filename = 'PathwayTT-skills-plan-'.$plan->generated_at->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }
}

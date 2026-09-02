<?php

namespace App\Services\Engagement;

use App\Models\User;

/**
 * Profile strength: a 0–100 completeness score with the concrete next
 * actions that raise it, in order of payoff. Every item maps to a screen.
 *
 * @phpstan-type Item array{key:string,label:string,points:int,done:bool,route:string,hint:string}
 */
class ProfileStrength
{
    /** @return array{score:int, items:list<Item>, next:list<Item>} */
    public function for(User $user): array
    {
        $weights = config('engagement.strength');
        $profile = $user->profile()->first();
        $skillCount = $profile ? $profile->skills()->count() : 0;

        $items = [
            $this->item('resume', 'Upload your resume', $weights['resume'], $user->resumes()->exists(), 'resume.index', 'We extract your skills and history from it.'),
            $this->item('basics', 'Add your name, phone and region', $weights['basics'],
                $profile && $profile->full_name && $profile->phone && $profile->region, 'profile.review', 'Employers need a way to reach you.'),
            $this->item('summary', 'Write a short professional summary', $weights['summary'], (bool) ($profile?->summary), 'profile.review', 'Two or three sentences on what you do.'),
            $this->item('skills_3', 'List at least 3 skills', $weights['skills_3'], $skillCount >= 3, 'profile.review', 'Matching only counts skills on your profile.'),
            $this->item('skills_8', 'Reach 8 skills', $weights['skills_8'], $skillCount >= 8, 'profile.review', 'More skills, more listings you qualify for.'),
            $this->item('work_history', 'Add a work history entry', $weights['work_history'], (bool) $profile?->workHistories()->exists(), 'profile.review', 'Experience drives 15% of every score.'),
            $this->item('education', 'Add your education', $weights['education'], (bool) $profile?->educations()->exists(), 'profile.review', 'CSEC/CAPE passes count.'),
            $this->item('credentials', 'Tick your local hiring documents', $weights['credentials'],
                $profile && ($profile->has_nis || $profile->has_bir || $profile->has_drivers_permit || $profile->has_police_certificate), 'profile.review', 'NIS, BIR, driver\'s permit, police certificate.'),
            $this->item('preferences', 'Set your job preferences', $weights['preferences'], $user->jobPreference()->exists(), 'preferences.index', 'Industry and arrangement are 20% of every score.'),
        ];

        $score = array_sum(array_map(fn ($i) => $i['done'] ? $i['points'] : 0, $items));
        $next = array_values(array_filter($items, fn ($i) => ! $i['done']));
        usort($next, fn ($a, $b) => $b['points'] <=> $a['points']);

        return ['score' => min(100, $score), 'items' => $items, 'next' => array_slice($next, 0, 3)];
    }

    private function item(string $key, string $label, int $points, bool $done, string $route, string $hint): array
    {
        return compact('key', 'label', 'points', 'done', 'route', 'hint');
    }
}

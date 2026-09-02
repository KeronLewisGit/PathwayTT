<?php

/*
|--------------------------------------------------------------------------
| Engagement / progress
|--------------------------------------------------------------------------
| Everything that nudges a job seeker forward: profile strength weights,
| the weekly application goal, and the momentum levels reached by earning
| milestones (defined in App\Services\Engagement\AchievementService).
*/

return [

    // Applications the app suggests marking "applied" each week (Mon–Sun, AST).
    'weekly_application_goal' => 3,

    // Profile strength contributions; must add up to 100.
    'strength' => [
        'resume' => 15,
        'basics' => 10,      // name + phone + region
        'summary' => 10,
        'skills_3' => 10,
        'skills_8' => 10,
        'work_history' => 15,
        'education' => 10,
        'credentials' => 5,  // any of NIS / BIR / driver's permit / police certificate ticked
        'preferences' => 15,
    ],

    // Momentum levels by total points from milestones.
    'levels' => [
        ['min' => 0, 'name' => 'Getting started'],
        ['min' => 40, 'name' => 'Building'],
        ['min' => 120, 'name' => 'In the running'],
        ['min' => 220, 'name' => 'Front runner'],
    ],
];

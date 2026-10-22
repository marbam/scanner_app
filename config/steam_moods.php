<?php

/*
|--------------------------------------------------------------------------
| "What to play" scoring rules
|--------------------------------------------------------------------------
|
| Each question's options add or subtract points for Steam community tags
| (exact tag names as Steam shows them). A game's tags are ranked strongest
| first, so a match on one of its top tags counts for more than a match
| further down the list (see 'tag_strength').
|
| Tuning: if suggestions feel off, change the numbers here. Positive = more
| likely, negative = less likely. Nothing else needs to change.
|
| 'familiarity' options filter by your playtime (minutes) rather than
| scoring tags.
|
*/

return [

    // Multiplier for a tag by its position in the game's tag list.
    'tag_strength' => [
        5 => 1.0,   // tags 1-5
        10 => 0.6,  // tags 6-10
        20 => 0.3,  // tags 11-20
    ],

    // How many top-scoring games the random pick is drawn from.
    'pool_size' => 15,

    // How long "Not tonight" hides a game for.
    'skip_days' => 7,

    'questions' => [

        'time' => [
            'label' => 'How long have you got?',
            'default' => 'evening',
            'options' => [
                'short' => [
                    'label' => 'Under an hour',
                    'tags' => [
                        'Arcade' => 2, 'Rogue-like' => 2, 'Rogue-lite' => 2, 'Action Roguelike' => 2, 'Casual' => 2,
                        'Puzzle' => 1, 'Short' => 3, 'Score Attack' => 1, 'Card Game' => 1, 'Board Game' => 1,
                        'Open World' => -2, 'RPG' => -2, 'JRPG' => -2, 'Grand Strategy' => -3, '4X' => -3,
                        'Automation' => -2, 'Colony Sim' => -2, 'City Builder' => -1, 'Story Rich' => -1,
                    ],
                ],
                'evening' => [
                    'label' => 'An evening',
                    'tags' => [],
                ],
                'long' => [
                    'label' => 'A long session',
                    'tags' => [
                        'Open World' => 2, 'RPG' => 2, 'Automation' => 2, 'Base-Building' => 2, 'Grand Strategy' => 2,
                        '4X' => 2, 'Colony Sim' => 2, 'City Builder' => 2, 'Sandbox' => 1, 'Exploration' => 1,
                        'Short' => -2, 'Arcade' => -1,
                    ],
                ],
            ],
        ],

        'energy' => [
            'label' => 'How are you feeling?',
            'default' => 'engaged',
            'options' => [
                'chill' => [
                    'label' => 'Chill',
                    'tags' => [
                        'Relaxing' => 3, 'Cozy' => 3, 'Wholesome' => 2, 'Casual' => 2, 'Farming Sim' => 2, 'Cute' => 1,
                        'Life Sim' => 1, 'Building' => 1, 'Family Friendly' => 1, 'Beautiful' => 1,
                        'Difficult' => -3, 'Souls-like' => -4, 'Horror' => -3, 'Psychological Horror' => -3,
                        'Survival Horror' => -3, 'Bullet Hell' => -2, 'Fast-Paced' => -2, 'Competitive' => -2, 'Gore' => -2,
                    ],
                ],
                'engaged' => [
                    'label' => 'Up for something',
                    'tags' => [
                        'Strategy' => 1, 'Adventure' => 1, 'Exploration' => 1, 'Action' => 1, 'Puzzle' => 1,
                    ],
                ],
                'challenge' => [
                    'label' => 'Give me a challenge',
                    'tags' => [
                        'Difficult' => 3, 'Souls-like' => 3, 'Rogue-like' => 2, 'Rogue-lite' => 2, 'Bullet Hell' => 2,
                        'Precision Platformer' => 2, 'Tactical' => 1, 'Turn-Based Tactics' => 1,
                        'Relaxing' => -2, 'Cozy' => -2, 'Casual' => -2, 'Wholesome' => -1,
                    ],
                ],
            ],
        ],

        'focus' => [
            'label' => 'What sort of thing?',
            'default' => 'anything',
            'options' => [
                'anything' => [
                    'label' => 'Surprise me',
                    'tags' => [],
                ],
                'story' => [
                    'label' => 'A story',
                    'tags' => [
                        'Story Rich' => 3, 'Narrative' => 2, 'Choices Matter' => 2, 'Visual Novel' => 1,
                        'Adventure' => 1, 'Atmospheric' => 1, 'Mystery' => 1, 'Detective' => 1,
                    ],
                ],
                'build' => [
                    'label' => 'Build & manage',
                    'tags' => [
                        'Automation' => 3, 'City Builder' => 3, 'Management' => 3, 'Base-Building' => 2, 'Building' => 2,
                        'Colony Sim' => 2, 'Resource Management' => 2, 'Simulation' => 1, 'Sandbox' => 1, 'Crafting' => 1,
                    ],
                ],
                'action' => [
                    'label' => 'Action',
                    'tags' => [
                        'Action' => 2, 'Shooter' => 2, 'FPS' => 2, 'Hack and Slash' => 2, 'Third-Person Shooter' => 1,
                        'Combat' => 1, 'Platformer' => 1, 'Action-Adventure' => 1,
                    ],
                ],
                'think' => [
                    'label' => 'Something to think about',
                    'tags' => [
                        'Puzzle' => 3, 'Strategy' => 2, 'Turn-Based Strategy' => 2, 'Card Game' => 2, 'Deckbuilding' => 2,
                        'Board Game' => 2, 'Tactical' => 2, 'Logic' => 2, 'Turn-Based' => 1,
                    ],
                ],
            ],
        ],

        'familiarity' => [
            'label' => 'New or familiar?',
            'default' => 'any',
            'options' => [
                'any' => ['label' => 'Either', 'min_minutes' => null, 'max_minutes' => null],
                'new' => ['label' => 'Something new', 'min_minutes' => null, 'max_minutes' => 59],
                'started' => ['label' => 'Something I\'ve started', 'min_minutes' => 60, 'max_minutes' => 599],
                'favourite' => ['label' => 'An old favourite', 'min_minutes' => 600, 'max_minutes' => null],
            ],
        ],

    ],

];

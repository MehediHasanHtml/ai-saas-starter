<?php

/*
|--------------------------------------------------------------------------
| Subscription plans
|--------------------------------------------------------------------------
|
| Plans are configuration, not database rows: they version with the code,
| are trivially testable and need no admin UI to stay in sync with Stripe.
| `null` means unlimited. Stripe price ids come from .env.
|
| "providers" restricts which AI providers an organization on the plan may
| use ('*' = all configured providers).
|
*/

return [

    'default' => 'free',

    'plans' => [

        'free' => [
            'name' => 'Free',
            'description' => 'Try the product with the demo and budget models.',
            'price_monthly' => 0,
            'stripe_price' => null,
            'limits' => [
                'messages_per_month' => (int) env('PLAN_FREE_MESSAGES', 100),
                'documents' => 5,
                'max_file_mb' => 5,
                'storage_mb' => 25,
                'members' => 1,
                'requests_per_minute' => 10,
            ],
            // Comma-separated in .env to override, e.g. PLAN_FREE_PROVIDERS=fake,gemini
            'providers' => array_values(array_filter(array_map('trim', explode(',', env('PLAN_FREE_PROVIDERS', 'fake,gemini,openai,ollama'))))),
            'features' => [
                '100 AI messages / month',
                '5 documents in the knowledge base',
                'Google Gemini, OpenAI + self-hosted models',
                'Community support',
            ],
        ],

        'pro' => [
            'name' => 'Pro',
            'description' => 'For professionals who rely on AI every day.',
            'price_monthly' => 19,
            'stripe_price' => env('STRIPE_PRICE_PRO'),
            'limits' => [
                'messages_per_month' => 2_000,
                'documents' => 100,
                'max_file_mb' => 20,
                'storage_mb' => 1_024,
                'members' => 5,
                'requests_per_minute' => 60,
            ],
            'providers' => ['*'],
            'features' => [
                '2,000 AI messages / month',
                '100 documents, 1 GB storage',
                'All providers: OpenAI, Anthropic, Gemini, Ollama',
                'Up to 5 team members',
                'Email support',
            ],
            'highlighted' => true,
        ],

        'business' => [
            'name' => 'Business',
            'description' => 'For teams building on a shared knowledge base.',
            'price_monthly' => 79,
            'stripe_price' => env('STRIPE_PRICE_BUSINESS'),
            'limits' => [
                'messages_per_month' => 20_000,
                'documents' => null,
                'max_file_mb' => 50,
                'storage_mb' => 10_240,
                'members' => 25,
                'requests_per_minute' => 120,
            ],
            'providers' => ['*'],
            'features' => [
                '20,000 AI messages / month',
                'Unlimited documents, 10 GB storage',
                'All providers + priority rate limits',
                'Up to 25 team members with roles',
                'Priority support',
            ],
        ],

    ],

];

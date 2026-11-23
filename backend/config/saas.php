<?php

return [

    // Public URL of the Next.js app (used in invitation and Stripe redirect links).
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    // Allowed upload types for the knowledge base.
    'document_mimes' => ['pdf', 'txt', 'md', 'markdown', 'csv', 'docx', 'html', 'htm', 'json'],

];

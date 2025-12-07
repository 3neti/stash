<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Campaign Templates
    |--------------------------------------------------------------------------
    |
    | Campaign templates to automatically create for new tenants.
    | Templates are JSON/YAML files in campaigns/templates/ directory.
    |
    | Template files should be named: {slug}.json or {slug}.yaml
    |
    */

    'default_templates' => array_filter(
        explode(',', env('DEFAULT_CAMPAIGN_TEMPLATES', 'simple-storage'))
    ),

];

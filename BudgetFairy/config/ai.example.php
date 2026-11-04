<?php

return [
    'enabled'  => false,
    'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
    'api_key'  => 'sk-or-v1-PASTE-YOUR-KEY-HERE',
    
    'model'          => 'google/gemma-4-31b-it:free',
    'fallback_model' => 'google/gemma-3-27b-it:free',   
    'site_url' => '',          
    'timeout'  => 20,
];
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mode d'envoi des codes OTP
    |--------------------------------------------------------------------------
    |
    | 'log'  : les codes sont écrits dans le journal applicatif (développement).
    | 'mail' : le canal 'email' envoie un vrai e-mail via la configuration MAIL_*.
    |
    */

    'delivery' => env('OTP_DELIVERY', 'log'),
];

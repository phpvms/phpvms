<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Reverse proxies (Caddy/nginx/load balancer) to trust for X-Forwarded-*
    | headers so HTTPS is detected behind TLS termination. Without this,
    | generated URLs fall back to http on an https site and browsers warn
    | about posting over an insecure connection. "*" trusts all (fine when the
    | proxy is your only ingress), or a comma-separated list of proxy IPs.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES', '*'),

];

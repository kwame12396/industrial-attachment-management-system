<?php
// Streamline HQ icons — auto-generated from @iconify-json/streamline
// https://streamlinehq.com  |  MIT/StreamlineHQ licence

function sl_icon(string $name, int $size=18, string $cls=""): string {
    static $icons = null;
    if ($icons === null) $icons = sl_icon_map();
    if (!isset($icons[$name])) return "";
    [$vw,$vh,$body] = $icons[$name];
    $c = $cls ? " class="".htmlspecialchars($cls).""" : "";
    return "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$size}\" height=\"{$size}\" viewBox=\"0 0 {$vw} {$vh}\"
           fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"
           aria-hidden=\"true\" style=\"display:inline-block;vertical-align:middle\"$c>{$body}</svg>";
}

function sl_icon_map(): array {
    return [
        'lock' => [14,14,'<g fill="none" stroke-linecap="round" stroke-linejoin="round"><rect width="13" height="7" x=".5" y="3.5" rx="1"/><circle cx="3.5" cy="7" r=".5"/><circle cx="6.5" cy="7" r=".5"/><path d="M9.5 8H11"/></g>'],
        'key' => [14,14,'<g fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="6.44" cy="11.33" r="2.17"/><path d="m8 9.8l3.86-3.86a.36.36 0 0 1 .51 0l1.13 1.15m-3.05.28l1.02 1.02M2 12.5h-.5a1 1 0 0 1-1-1v-10a1 1 0 0 1 1-1h11a1 1 0 0 1 1 1V4m-13-.5h13"/></g>'],
        'user' => [14,14,'<g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M13.18 13.5a6.49 6.49 0 0 0-12.36 0z"/><path d="M7 9A4.232 4.232 0 1 0 7 .536A4.232 4.232 0 0 0 7 9"/><path d="M8.382 6.405s-.351.691-1.382.691s-1.382-.69-1.382-.69m5.537-2.444h-.028a4.12 4.12 0 0 1-3.09-1.392a4.12 4.12 0 0 1-3.091 1.392a4.1 4.1 0 0 1-1.973-.5a4.234 4.234 0 0 1 8.182.5"/></g>'],
        'shield' => [14,14,'<g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7.36 13.4a1 1 0 0 1-.72 0v0A9.59 9.59 0 0 1 .5 4.46V1.54a1 1 0 0 1 1-1h11a1 1 0 0 1 1 1v2.92a9.59 9.59 0 0 1-6.14 8.94"/><path d="M9 7V5a2 2 0 1 0-4 0v2a2 2 0 1 0 4 0M3.5 6H5m4 0h1.5M5 5.5h4m-.187-1.312L10 3M8.813 7.813L10 9M5.188 7.813L4 9m1.188-4.812L4 3"/></g>'],
        'home' => [14,14,'<path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M13.5 6.94a1 1 0 0 0-.32-.74L7 .5L.82 6.2a1 1 0 0 0-.32.74v5.56a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1zM7 13.5v-4"/>'],
        'check' => [14,14,'<path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m.5 8.55l2.73 3.51a1 1 0 0 0 1.56.03L13.5 1.55"/>'],
        'building' => [14,14,'<path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M8.461 4.75V1.594c0-.56-.454-1.015-1.015-1.015h-4.79c-.562 0-1.016.454-1.016 1.015v11.827m-1.121 0h4.968M1.64 3.187H4.1M1.64 5.75h3.847m4.993 4.282a1.75 1.75 0 1 0 0-3.5a1.75 1.75 0 0 0 0 3.5m-3.001 3.389a3.04 3.04 0 0 1 .39-1.46a3.03 3.03 0 0 1 2.611-1.537a3.03 3.03 0 0 1 2.612 1.538c.25.445.385.947.39 1.459"/>'],
        'eye' => [14,14,'<g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3.625c-4.187 0-5.945 3.766-5.945 3.844S2.813 11.312 7 11.312s5.945-3.765 5.945-3.843S11.187 3.625 7 3.625M2.169 5.813L.61 4.252m4.525-.354L4.5 1.843m7.331 3.97l1.559-1.56m-4.525-.355L9.5 1.843"/><path d="M5.306 7.081a1.738 1.738 0 1 0 3.388.776a1.738 1.738 0 1 0-3.388-.776"/></g>'],
        'eye_off' => [14,14,'<g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M12.29 5.4c.38.34.7.67.94.93a1 1 0 0 1 0 1.34C12.18 8.8 9.79 11 7 11h-.4m-2.73-.87a12.4 12.4 0 0 1-3.1-2.46a1 1 0 0 1 0-1.34C1.82 5.2 4.21 3 7 3a6.56 6.56 0 0 1 3.13.87M12.5 1.5l-11 11"/><path d="M5.59 8.41A2 2 0 0 1 5 7a2 2 0 0 1 2-2a2 2 0 0 1 1.41.59M8.74 8a2 2 0 0 1-.74.73"/></g>'],
        'chart' => [14,14,'<path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m1.24 6.54l11.5-5.23M10.59.5l2.15.81l-.8 2.15m1.31 10.05h-2.5h0v-7a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v7h0Zm-5 0h-2.5h0v-5.5a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v5.5h0Zm-5 0H.75h0v-4a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v4h0Z"/>'],
        'bell' => [14,14,'<path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M6 13.25h2m3-7.5a4 4 0 0 0-8 0v3.5a1.5 1.5 0 0 1-1.5 1.5h11a1.5 1.5 0 0 1-1.5-1.5ZM.5 5.62A6 6 0 0 1 3 .75m10.5 4.87A6 6 0 0 0 11 .75"/>'],
        'logout' => [14,14,'<path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M9.5 10.5v2a1 1 0 0 1-1 1h-7a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v2M6.5 7h7m-2-2l2 2l-2 2"/>'],
        'delete' => [14,14,'<path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m13.5.5l-13 13m0-13l13 13"/>'],
        'document' => [14,14,'<g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g>'],
        'warning' => [14,14,'<g fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="6.5"/><path d="M7 3.5v3"/><circle cx="7" cy="9.5" r=".5"/></g>'],
        'graduation' => [14,14,'<g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7 1.367l6.5 2.817L7 7L.5 4.184z"/><path d="m3.45 5.469l.006 3.064S4.529 9.953 7 9.953s3.55-1.42 3.55-1.42l-.001-3.064m-8.854 5.132v-5.89m.001 8.282a1.196 1.196 0 1 0 0-2.392a1.196 1.196 0 0 0 0 2.392"/></g>'],
        'search' => [14,14,'<g fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.92" cy="5.92" r="5.42"/><path d="M13.5 13.5L9.75 9.75M6 3.5v5M3.5 6h5"/></g>'],
        'arrow_right' => [14,14,'<path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M3.85.5L10 6.65a.48.48 0 0 1 0 .7L3.85 13.5"/>'],
        'settings' => [14,14,'<path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m5.23 2.25l.43-1.11A1 1 0 0 1 6.59.5h.82a1 1 0 0 1 .93.64l.43 1.11l1.46.84l1.18-.18a1 1 0 0 1 1 .49l.4.7a1 1 0 0 1-.08 1.13l-.73.93v1.68l.75.93a1 1 0 0 1 .08 1.13l-.4.7a1 1 0 0 1-1 .49l-1.18-.18l-1.46.84l-.43 1.11a1 1 0 0 1-.93.64h-.84a1 1 0 0 1-.93-.64l-.43-1.11l-1.46-.84l-1.18.18a1 1 0 0 1-1-.49l-.4-.7a1 1 0 0 1 .08-1.13L2 7.84V6.16l-.75-.93a1 1 0 0 1-.08-1.13l.4-.7a1 1 0 0 1 1-.49l1.18.18zM5 7a2 2 0 1 0 4 0a2 2 0 0 0-4 0"/>'],
        'add' => [14,14,'<path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M7 .5v13M.5 6.96h13"/>'],
        'info' => [14,14,'<g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g>'],
    ];
}

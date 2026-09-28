<?php

declare(strict_types=1);

return [
    /*
     * The Android app's package name and signing fingerprints.
     *
     * A fingerprint is PUBLIC by design — it is served to the whole internet
     * in /.well-known/assetlinks.json, which is how Chrome decides whether to
     * run the site as an app rather than in a browser tab. It is
     * configuration, not a credential. The keystore that produces it is the
     * secret, and that never leaves the repository owner's machine.
     *
     * Two values during a key rotation: Chrome accepts either, so installed
     * apps keep working across it. Also needed if you enrol in Play App
     * Signing, where the fingerprint Chrome must see is Google's app signing
     * key rather than your upload key.
     */
    'package' => env('ANDROID_PACKAGE', 'tech.lorapok.retail'),

    'cert_fingerprints' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ANDROID_CERT_SHA256', '')),
    ))),
];

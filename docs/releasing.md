# Releasing

Push a tag:

```bash
git tag v1.0.0 && git push origin v1.0.0
```

That builds and publishes a GitHub Release with generated notes. Re-running on
the same tag is safe — the release is updated rather than duplicated, which
matters because Android is the job most likely to need a retry.

## What ships without any secret

| | |
|---|---|
| Brand pack | Logos, favicons, app icons, OG card — regenerated from source, not copied |
| Self-host bundle | `compose.yaml`, `.env.example`, and the self-hosting guide |
| `SHA256SUMS.txt` | For everything above |

## What needs a keystore you generate

The Android build. **Nothing else in this repository can produce it** — the
signing key is the one thing only you can hold, and if it is lost the app can
never be updated on Play.

```bash
keytool -genkeypair -v -keystore upload-keystore.jks -alias upload \
  -keyalg RSA -keysize 2048 -validity 10000

keytool -list -v -keystore upload-keystore.jks -alias upload | grep 'SHA256:'
base64 -w0 upload-keystore.jks
```

Back the keystore up somewhere that is **not this repository**.

### Repository secrets

| Name | Value |
|---|---|
| `ANDROID_KEYSTORE_BASE64` | the base64 output above |
| `ANDROID_KEYSTORE_PASSWORD` | store password |
| `ANDROID_KEY_PASSWORD` | key password |
| `ANDROID_KEY_ALIAS` | `upload` |

### Repository *variables*, not secrets

| Name | Value |
|---|---|
| `ANDROID_PACKAGE` | `tech.lorapok.retail` |
| `ANDROID_CERT_SHA256` | the `SHA256:` fingerprint |

The fingerprint is **public by design**: it is served to the whole internet at
`/.well-known/assetlinks.json`, which is how Chrome decides to run the site as
an app rather than in a browser tab. It is configuration, not a credential.

During a key rotation, or if you enrol in Play App Signing, put both
fingerprints in comma-separated — Chrome accepts either, so installed apps
keep working. With Play App Signing the fingerprint Chrome must see is
**Google's app signing key**, not your upload key.

Without the keystore the release still happens; it simply ships without the
Android build. Blocking three artifacts that need no secret because one does
would be the wrong trade.

## Why one APK, not one per shop

A Trusted Web Activity is trusted for exactly one origin, and Digital Asset
Links has **no wildcard**. `*.lorapok.tech` is not expressible.

So the app binds to the apex and a shop signs in with its own address. Binding
per shop would mean an APK, a Play listing and a Play review for every shop,
which is not shippable — and a shop provisioned after the last release would
get an app that silently degrades to a browser tab with a URL bar.

## Desktop builds

Not built. A Tauri shell would give direct thermal-printer and cash-drawer
access, which is a real advantage at a counter, but it needs a signing
identity per platform — an Apple Developer certificate and a Windows code
signing certificate — and an unsigned desktop build is one the operating
system warns about hard enough that nobody installs it.

Listed here so it is a decision rather than an omission.

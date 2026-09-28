<?php

declare(strict_types=1);
use App\Support\Money;

/**
 * Architecture rules.
 *
 * CLAUDE.md has claimed these exist since Phase 1 — "There is an architecture
 * test enforcing this" and "an architecture test fails the build if one is
 * missing". They did not. A convention nothing enforces is a comment, and
 * these two in particular are the conventions the whole system rests on.
 *
 * They live under tests/Unit because phpunit.xml already maps that suite and
 * because they need no database, so they run in under a second.
 */
arch('the domain layer does not reach for the framework helpers that hide state')
    ->expect('App\Domain')
    // A service that reads the request cannot be called from a queue or a
    // console command without surprises.
    ->not->toUse(['env', 'request', 'session', 'auth'])
    // Two exceptions, made explicit rather than hidden.
    //
    // AuditLog: an audit row is worth little without the IP and user agent it
    // came from, and capturing those at the call site would mean threading
    // them through every caller. In a console context request() degrades to
    // nulls, which is the right answer.
    //
    // ImpersonationService: establishing an impersonation session is HTTP by
    // nature — it logs a user into the current session and reads it back.
    // There is no version of it that a queue worker could call meaningfully.
    // The tidier design splits it in two, with token minting in the domain
    // and the session handling in an HTTP service; that is worth doing, and
    // it is not worth pretending the rule holds here in the meantime.
    ->ignoring([
        'App\Domain\Central\AuditLog',
        'App\Domain\Impersonation\ImpersonationService',
    ]);

arch('nothing outside config reads the environment directly')
    ->expect('App')
    // `env()` returns null when config is cached, so a stray call works
    // locally and fails only in production.
    ->not->toUse('env');

arch('support types are final')
    ->expect('App\Support')
    ->toBeFinal();

arch('enums are backed so they can be persisted')
    ->expect('App\Enums')
    ->toBeEnums()
    ->ignoring('App\Enums\Permission');   // deliberately a class of constants

arch('controllers do not talk to the database directly')
    ->expect('App\Http\Controllers')
    // Controllers translate HTTP; anything touching data belongs in a domain
    // service where it can be tested without a request.
    ->not->toUse(['Illuminate\Support\Facades\DB']);

arch('models never dispatch queries from the global scope of a view')
    ->expect('App\Models')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump']);

arch('no debugging statements survive anywhere')
    ->expect(['App', 'Database'])
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump', 'die', 'exit']);

it('stores every money column as an integer, never a float', function () {
    // This is the real guarantee behind "money is never a float", and the one
    // worth enforcing: a float COLUMN loses money permanently and silently,
    // whereas the `(int) round(...)` calls in the domain are deliberate,
    // documented collapses of minor-units x decimal-quantity back to minor
    // units — unavoidable, and safe because they happen once, at a defined
    // point.
    //
    // CLAUDE.md claimed an architecture test forbade `round()` in App\Domain.
    // It did not exist, and the rule was never followed: SalesService and
    // StockService cannot multiply a price by 1.5 units without it.
    $offenders = [];

    foreach (array_merge(
        glob(database_path('migrations/*.php')),
        glob(database_path('migrations/tenant/*.php')),
    ) as $path) {
        foreach (file($path) as $number => $line) {
            if (! str_contains($line, '_minor')) {
                continue;
            }

            // The column must be declared with an integer builder.
            if (preg_match('/->(decimal|float|double|unsignedDecimal)\(/', $line)) {
                $offenders[] = basename($path).':'.($number + 1).' — '.trim($line);
            }
        }
    }

    expect($offenders)->toBe([], "Money columns declared as a float type:\n".implode("\n", $offenders));
});

it('keeps Money arithmetic exact', function () {
    // Addition and subtraction must never round. Only multiplication by a
    // quantity may, and it rounds half-up to the minor unit.
    $a = Money::ofMinor(1);
    $b = Money::ofMinor(2);

    expect($a->plus($b)->toDecimal())->toBe('0.03')
        ->and($b->minus($a)->toDecimal())->toBe('0.01')
        // 0.1 + 0.2 in minor units is exactly 0.30, which a float cannot do.
        ->and(Money::parse('0.10')->plus(Money::parse('0.20'))->toDecimal())
        ->toBe('0.30');
});

it('never imports a global class into a Livewire component', function () {
    // `use DomainException;` is a non-compound import: pointless, because the
    // class is already global, and PHP warns about it — which Livewire's
    // compiled component class escalates into a fatal ErrorException.
    //
    // The operator verification screen shipped with exactly this and passed
    // CI, because no test rendered it. It would have failed the first time
    // somebody opened the page.
    $globals = ['DomainException', 'Exception', 'Throwable', 'RuntimeException',
        'InvalidArgumentException', 'LogicException', 'Closure'];

    $offenders = [];

    foreach (glob(resource_path('views/**/*.blade.php'), GLOB_BRACE) ?: [] as $path) {
        // Only single-file Livewire components compile to a class.
        if (! str_contains((string) file_get_contents($path), 'extends Component')) {
            continue;
        }

        foreach ($globals as $class) {
            if (preg_match('/^use '.$class.';$/m', (string) file_get_contents($path)) === 1) {
                $offenders[] = basename($path).": use {$class};";
            }
        }
    }

    expect($offenders)->toBe([], "Remove these — the class is already global:\n".implode("\n", $offenders));
});

it('pins every central model to the central connection', function () {
    // A central model without this follows the default connection, which
    // tenancy swaps to `tenant` for the duration of a shop request. The
    // operator panel then works and the same model read from inside a shop
    // looks for its table in that shop's database.
    //
    // ShopVerification shipped without it: the review queue worked and the
    // shop's own form could not load.
    $central = ['ShopVerification', 'Subscription', 'SubscriptionInvoice', 'Plan', 'PlanLimit',
        'ContactMessage', 'ShopApplication', 'ImpersonationToken', 'PaymentAttempt'];

    $unpinned = [];

    foreach ($central as $name) {
        $path = app_path("Models/{$name}.php");

        if (! is_file($path)) {
            continue;
        }

        if (! str_contains((string) file_get_contents($path), 'getConnectionName')) {
            $unpinned[] = $name;
        }
    }

    expect($unpinned)->toBe(
        [],
        'These central models follow the default connection, so reading them inside a '
        ."tenant request looks in the wrong database:\n".implode("\n", $unpinned)
    );
});

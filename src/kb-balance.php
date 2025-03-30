<?php

declare(strict_types=1);

/**
 * This file is part of the KB Statement Tools package
 *
 * https://github.com/Spoje-NET/kb-statement-tools
 *
 * (c) Spoje.Net IT s.r.o. <https://spojenet.cz>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Ease\Sand;
use Ease\Shared;
use Lawondyss\Parex\Parex;
use SpojeNet\KbAccountsApi\Entity\Account;
use SpojeNet\KbAccountsApi\Entity\Balance;
use SpojeNet\KbAccountsApi\Entity\BalanceType;
use SpojeNet\KbAccountsApi\Entity\CreditDebit;
use SpojeNet\KbAccountsApi\Exception\KbClientException;
use SpojeNet\KbAccountsApi\KbClient;
use Tracy\Debugger;

require_once __DIR__.'/../vendor/autoload.php';

const APP_NAME = 'KB:Balances';

/** @var object{output: ?string, environment: ?string} $options */
$options = (new Parex())
    ->addOptional('output', 'o')
    ->addOptional('environment', 'e', __DIR__.'/../.env')
    ->parse();

Shared::init([
    'CERT_FILE', 'CERT_PASS', 'ACCOUNT_NUMBER',
    'KB_ACCOUNTSAPI_SANDBOX', 'KB_ACCESS_TOKEN', 'ACCOUNT_ID',
], $options->environment);

Debugger::enable(Shared::cfg('DEBUG') ? Debugger::Development : Debugger::Production, __DIR__.'/../.logs');

$accessToken = Shared::cfg('KB_ACCESS_TOKEN');
$accountId = Shared::cfg('ACCOUNT_ID');
$destination = $options->output ?? Shared::cfg('RESULT_FILE', 'php://stdout');
$written = 0;
$exitCode = 0;

$kbClient = KbClient::createDefault(envFilePath: $options->environment);

$engine = new Sand();
$engine->setObjectName(Shared::cfg('ACCOUNT_NUMBER'));

if (Shared::cfg('APP_DEBUG', false)) {
    $engine->logBanner();
}

/* Utilities */
$format = static fn (...$args): string => sprintf(_(array_shift($args)), ...$args);
$mapType = static fn (BalanceType $type): string => match ($type) {
    BalanceType::Opening => 'CLBD',
    BalanceType::Available => 'CLAV',
    BalanceType::Booked => 'BLCK',
    default => throw new RuntimeException($format('Unknown balance type: %s', $type->name)),
};
$sanitizeAmount = static fn (CreditDebit $type, float $value): float => abs($value) * ($type === CreditDebit::Debit ? -1 : 1);

try {
    $engine->addStatusMessage($format('Find a matching bank account'));
    $accounts = $kbClient->accounts($accessToken);
    $accounts = array_filter($accounts, static fn (Account $a) => $a->accountId === $accountId);
    $account = array_pop($accounts);
    $account ?? throw new RuntimeException($format('Account not found, ID: %s'), $accountId);

    $engine->addStatusMessage($format('Get balances and split'));
    /** @var array<string, Balance[]> $currencies */
    $currencies = [];

    foreach ($kbClient->balances($accessToken, $accountId) as $balance) {
        $currencies[$balance->amount->currency] ??= [];
        $currencies[$balance->amount->currency][] = $balance;
    }

    $engine->addStatusMessage($format('Build output'), 'debug');
    $result = [
        'iban' => $account->iban,
        'currencyFolders' => [],
    ];

    foreach ($currencies as $currency => $balances) {
        $currency = [
            'currency' => $currency,
            'balances' => [],
        ];

        foreach ($balances as $balance) {
            $currency['balances'][] = [
                'balanceType' => $mapType($balance->type),
                'currency' => $balance->amount->currency,
                'value' => $sanitizeAmount($balance->creditDebitIndicator, $balance->amount->value),
            ];
        }

        $result['currencyFolders'][] = $currency;
    }

    dump($result);

    $engine->addStatusMessage($format('Save output'), 'debug');
    $written = file_put_contents($destination, json_encode($result, Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT : 0));
} catch (KbClientException $exc) {
    Debugger::log($exc, Debugger::EXCEPTION);
    $engine->addStatusMessage($format('API Error: %s', $exc->getMessage()), 'error');
    $exitCode = ($exc->getContext()['response'] ?? null)?->getStatusCode() ?: 400;
} catch (Throwable $exc) {
    Debugger::log($exc, Debugger::ERROR);
    $engine->addStatusMessage($format('%s: %s'.$exc::class, $exc->getMessage()), 'error');
    $exitCode = $exc->getCode() ?: 500;
}

$engine->addStatusMessage($format('Saving result to %s', $destination), $written ? 'success' : 'error');

exit($exitCode ?: ($written ? 0 : 2));

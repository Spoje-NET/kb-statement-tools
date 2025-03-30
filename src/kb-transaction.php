<?php

use Ease\Logger\Message;
use Ease\Sand;
use Ease\Shared;
use Lawondyss\Parex\Parex;
use SpojeNet\KbAccountsApi\Entity\Account;
use SpojeNet\KbAccountsApi\Entity\Balance;
use SpojeNet\KbAccountsApi\Entity\BalanceType;
use SpojeNet\KbAccountsApi\Entity\CreditDebit;
use SpojeNet\KbAccountsApi\Exception\KbClientException;
use SpojeNet\KbAccountsApi\KbClient;
use SpojeNet\KbAccountsApi\Selection\TransactionSelection;
use Tracy\Debugger;

require_once __DIR__ . '/../vendor/autoload.php';

const APP_NAME = 'KB:Balances';

/** @var object{output: ?string, environment: ?string} $options */
$options = (new Parex)
  ->addOptional('output', 'o')
  ->addOptional('environment', 'e', __DIR__ . '/../.env')
  ->parse();

Shared::init([
  'CERT_FILE', 'CERT_PASS', 'ACCOUNT_NUMBER',
  'KB_ACCOUNTSAPI_SANDBOX', 'KB_ACCESS_TOKEN', 'ACCOUNT_ID',
], $options->environment);

Debugger::enable(Shared::cfg('DEBUG') ? Debugger::Development : Debugger::Production, __DIR__ . '/../.logs');

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
$format = fn(...$args): string => sprintf(_(array_shift($args)), ...$args);
$sanitizeAmount = fn(CreditDebit $type, float $value): float => abs($value) * ($type === CreditDebit::Debit ? -1 : 1);
$scopeRange = fn(string $scope): array => match ($scope) {
  'today' => [$same = new DateTimeImmutable(), $same],
  'yesterday' => [$same = new DateTimeImmutable('yesterday'), $same],
  'last_week' => [new DateTimeImmutable('first day of last week'), new DateTimeImmutable('last day of last week')],
  'auto' => [new DateTimeImmutable('-89 days'), new DateTimeImmutable()],
  default => match(true) {
    str_contains($scope, '>') => array_map(fn(string $dt) => new DateTimeImmutable($dt), explode('>', $scope, limit: 2)),
    preg_match('~^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$~', $scope) => [$same => new DateTimeImmutable($scope), $same],
    default => throw new InvalidArgumentException($format('Unknown scope: %s', $scope)),
  }
};

try {
  $engine->addStatusMessage($format('Find a matching bank account'));
  $accounts = $kbClient->accounts($accessToken);
  $accounts = array_filter($accounts, static fn(Account $a) => $a->accountId === $accountId);
  $account = array_pop($accounts);
  $account ?? throw new RuntimeException($format('Account not found, ID: %s'), $accountId);


  $engine->addStatusMessage($format('Fetch transactions and fill payments'));
  $page = 0;
  $scope = Shared::cfg('REPORT_SCOPE', 'yesterday');
  [$dateFrom, $dateTo] = $scopeRange($scope);
  $fromDateTime = $dateFrom->setTime(0, 0);
  $toDateTime = $scope === 'today' ? $dateTo : $dateTo->setTime(23, 59, 59, 999);
  $engine->addStatusMessage($format('Scope range: %s - %s', $fromDateTime->format('Y-m-d\TH:i'), $toDateTime->format('Y-m-d\TH:i')), 'debug');

  $result = [
    'source' => Message::getCallerName($kbClient),
    'iban' => $account->iban,
    'from' => $fromDateTime->format('Y-m-d'),
    'to' => $toDateTime->format('Y-m-d'),
    'in' => [],
    'out' => [],
    'in_total' => 0,
    'out_total' => 0,
    'in_sum_total' => 0,
    'out_sum_total' => 0,
  ];

  do {
    $engine->addStatusMessage($format('Fetch page %d', $page + 1), 'debug');
    $transactions = $kbClient->transactions($accessToken, new TransactionSelection(
      accountId: $accountId,
      page: $page++,
      fromDateTime: $fromDateTime,
      toDateTime: $toDateTime,
    ));

    foreach ($transactions->content as $transaction) {
      dump($transaction);
      $way = $transaction->creditDebitIndicator === CreditDebit::Credit
        ? 'in'
        : 'out';

      $dateTime = ($transaction->valueDate ?? $transaction->bookingDate ?? $transaction->lastUpdated)->format('Y-m-d\TH:i:s');
      $result[$way][$dateTime] = $amount = $sanitizeAmount($transaction->creditDebitIndicator, $transaction->amount->value);
      $result["{$way}_sum_total"] += $amount;
      $result["{$way}_total"]++;

    }
  } while (!$transactions->last);

  $engine->addStatusMessage($format('Save output'), 'debug');
  $written = file_put_contents($destination, json_encode($result, Shared::cfg('DEBUG') ? JSON_PRETTY_PRINT : 0));

} catch (KbClientException $exc) {
  Debugger::log($exc, Debugger::EXCEPTION);
  $engine->addStatusMessage($format('API Error: %s', $exc->getMessage()), 'error');
  $exitCode = ($exc->getContext()['response'] ?? null)?->getStatusCode() ?: 400;

} catch (Throwable $exc) {
  Debugger::log($exc, Debugger::ERROR);
  $engine->addStatusMessage($format('%s: %s'. $exc::class, $exc->getMessage()), 'error');
  $exitCode = $exc->getCode() ?: 500;
}

$engine->addStatusMessage($format('Saving result to %s', $destination), $written ? 'success' : 'error');
exit($exitCode ?: ($written ? 0 : 2));

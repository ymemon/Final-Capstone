<?php
declare(strict_types=1);
namespace Rebound;
if (PHP_SAPI!=='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {http_response_code(404);exit;}

/** Only the server talks to Square. No card details or tokens enter browser responses. */
final class SquareClient
{
    public const VERSION = '2026-08-19';
    public function __construct(private array $config, private ?\Closure $transport = null) {}

    public static function configurationPath(): string
    {
        $override=getenv('REBOUND_SQUARE_CONFIG');
        if ($override) return $override;
        if (DIRECTORY_SEPARATOR==='/') {
            $account=function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid()) : false;
            if (!$account || empty($account['dir']) || $account['dir']==='/') throw new \RuntimeException('Configure a private Square configuration path outside the web root.');
            return rtrim($account['dir'],'/').'/.config/rebound-square/config.php';
        }
        return dirname(__DIR__).'/config.php';
    }

    public static function configuration(): array
    {
        $path = self::configurationPath();
        if (!is_file($path)) return [];
        $config = require $path;
        return is_array($config) ? ($config['square'] ?? []) : [];
    }

    public function configured(): bool
    {
        foreach (['access_token','location_id','merchant_id','webhook_signature_key','webhook_url'] as $key) {
            if (!is_string($this->config[$key] ?? null) || trim($this->config[$key]) === '') return false;
        }
        return in_array($this->config['environment'] ?? '', ['production','sandbox'], true)
            && filter_var($this->config['webhook_url'], FILTER_VALIDATE_URL)
            && parse_url($this->config['webhook_url'], PHP_URL_SCHEME) === 'https';
    }

    public function enabled(): bool
    {
        return $this->configured() && ($this->config['enabled'] ?? false) === true
            && ($this->value('environment')==='production' || getenv('REBOUND_SQUARE_ALLOW_SANDBOX')==='1');
    }
    public function value(string $key): string { return (string)($this->config[$key] ?? ''); }
    public function fingerprint(): string
    {
        return hash('sha256', $this->value('environment').'|'.$this->value('merchant_id').'|'.$this->value('location_id').'|'.$this->value('webhook_url').'|'.$this->value('webhook_signature_key'));
    }

    public function validSignature(string $body, string $signature): bool
    {
        if (!$this->configured() || $signature === '') return false;
        $expected = base64_encode(hash_hmac('sha256', $this->value('webhook_url').$body, $this->value('webhook_signature_key'), true));
        return hash_equals($expected, $signature);
    }

    public function request(string $method, string $path, ?array $body = null): array
    {
        if (!in_array($this->value('environment'), ['production','sandbox'], true) || $this->value('access_token') === '') {
            throw new \RuntimeException('Square is not connected. Please contact the studio.');
        }
        if (!preg_match('~^/v2/[a-zA-Z0-9/_-]+$~D', $path)) throw new \LogicException('Invalid Square API path.');
        if ($this->transport) return ($this->transport)($method, $path, $body);
        $host = $this->value('environment') === 'sandbox' ? 'https://connect.squareupsandbox.com' : 'https://connect.squareup.com';
        $ch = curl_init($host.$path);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>20, CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->value('access_token'),'Square-Version: '.self::VERSION,'Content-Type: application/json']]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        $raw = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($raw === false || $status < 200 || $status >= 300) {
            // Do not log bodies, credentials, buyer information or raw Square error details.
            error_log('Rebound Square request failed: HTTP '.$status);
            throw new \RuntimeException('Square could not confirm this request. Your order is saved; please try again shortly.');
        }
        $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !empty($data['errors'])) throw new \RuntimeException('Square returned an unexpected response. Please try again shortly.');
        return $data;
    }

    public function validateAccount(): array
    {
        $merchant = $this->request('GET','/v2/merchants/me')['merchant'] ?? [];
        $location = $this->request('GET','/v2/locations/'.$this->value('location_id'))['location'] ?? [];
        if (($merchant['id'] ?? '') !== $this->value('merchant_id') || ($merchant['status'] ?? '') !== 'ACTIVE'
            || ($location['merchant_id'] ?? '') !== $this->value('merchant_id') || ($location['status'] ?? '') !== 'ACTIVE'
            || ($location['currency'] ?? '') !== 'USD' || !in_array('CREDIT_CARD_PROCESSING',$location['capabilities'] ?? [],true)) {
            throw new \RuntimeException('Verify the Square merchant and active USD location with card processing enabled.');
        }
        return ['business_name'=>$merchant['business_name'] ?? '', 'location_name'=>$location['name'] ?? '',
            'merchant_id'=>$merchant['id'], 'location_id'=>$location['id'], 'environment'=>$this->value('environment')];
    }
}

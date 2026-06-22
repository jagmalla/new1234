<?php
declare(strict_types=1);

namespace AutoBusiness\Engine\Nodes;

use AutoBusiness\Security\CredentialVault;

/**
 * HttpRequestNode — native cURL Action node with strict timeouts.
 *
 * Global Rules honoured here:
 *  - Every cURL call sets CURLOPT_TIMEOUT and CURLOPT_CONNECTTIMEOUT.
 *  - Strict try/catch; failures are returned as structured output, never fatal.
 *  - Credentials are injected SERVER-SIDE: the node receives a credential_id,
 *    decrypts it via CredentialVault at run time, and never exposes the secret
 *    to the canvas/state.
 *
 * Token resolution (URL, headers, body) happens in the engine BEFORE execute(),
 * so this node receives concrete values.
 *
 * Expected (resolved) input:
 *   method        GET|POST|PUT|PATCH|DELETE   (default GET)
 *   url           string
 *   headers       array<string,string>        (optional)
 *   body          mixed                        (optional; arrays sent as JSON)
 *   credential_id int                          (optional; injects auth)
 *   timeout       int seconds                  (optional; default 20, capped 30)
 */
final class HttpRequestNode extends AbstractNode
{
    public function __construct(private readonly ?CredentialVault $vault = null)
    {
    }

    public function execute(array $input): array
    {
        $method  = strtoupper((string) ($input['method'] ?? 'GET'));
        $url     = (string) ($input['url'] ?? '');
        $headers = is_array($input['headers'] ?? null) ? $input['headers'] : [];
        $body    = $input['body'] ?? null;
        $timeout = min(30, max(1, (int) ($input['timeout'] ?? 20)));

        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return self::failure('Invalid or missing URL.');
        }

        // Inject a decrypted credential server-side, if requested.
        if (!empty($input['credential_id']) && $this->vault !== null) {
            $headers = $this->applyCredential($headers, (int) $input['credential_id']);
        }

        $ch = curl_init();
        try {
            $curlHeaders = [];
            foreach ($headers as $name => $value) {
                $curlHeaders[] = $name . ': ' . $value;
            }

            $options = [
                CURLOPT_URL            => $url,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_CONNECTTIMEOUT => min($timeout, 10),
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_HTTPHEADER     => $curlHeaders,
            ];

            if ($body !== null && !in_array($method, ['GET', 'HEAD'], true)) {
                if (is_array($body)) {
                    $options[CURLOPT_POSTFIELDS] = (string) json_encode($body);
                    if (!self::hasHeader($curlHeaders, 'content-type')) {
                        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
                    }
                } else {
                    $options[CURLOPT_POSTFIELDS] = (string) $body;
                }
            }

            curl_setopt_array($ch, $options);

            $responseBody = curl_exec($ch);
            if ($responseBody === false) {
                return self::failure('cURL error: ' . curl_error($ch));
            }

            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $decoded = json_decode((string) $responseBody, true);

            return [
                'ok'      => $status >= 200 && $status < 300,
                'status'  => $status,
                'body'    => $decoded ?? (string) $responseBody,
                'raw'     => (string) $responseBody,
                'error'   => null,
            ];
        } catch (\Throwable $e) {
            return self::failure($e->getMessage());
        } finally {
            curl_close($ch);
        }
    }

    /**
     * @param array<string,string> $headers
     * @return array<string,string>
     */
    private function applyCredential(array $headers, int $credentialId): array
    {
        $secret = $this->vault?->reveal($credentialId);
        if ($secret === null) {
            return $headers;
        }

        // Supported shapes: bearer token, api key header, or basic auth.
        if (!empty($secret['bearer'])) {
            $headers['Authorization'] = 'Bearer ' . $secret['bearer'];
        } elseif (!empty($secret['header']) && !empty($secret['value'])) {
            $headers[(string) $secret['header']] = (string) $secret['value'];
        } elseif (!empty($secret['basic_user'])) {
            $token = base64_encode($secret['basic_user'] . ':' . ($secret['basic_pass'] ?? ''));
            $headers['Authorization'] = 'Basic ' . $token;
        }
        return $headers;
    }

    /** @param list<string> $curlHeaders */
    private static function hasHeader(array $curlHeaders, string $needle): bool
    {
        foreach ($curlHeaders as $h) {
            if (stripos($h, $needle . ':') === 0) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string,mixed> */
    private static function failure(string $message): array
    {
        return ['ok' => false, 'status' => 0, 'body' => null, 'raw' => '', 'error' => $message];
    }
}

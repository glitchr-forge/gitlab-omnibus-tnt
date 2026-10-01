<?php

namespace Omnibus\Tnt;

use Omnibus\Exception\CarrierException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * TNT's ExpressConnect (express.tnt.com): XML documents posted as form fields,
 * basic auth for pricing and tracking, the login inside the document for
 * shipping. Still answered for existing accounts since TNT joined FedEx; new
 * accounts get FedEx's APIs instead.
 */
final class Api
{
    public const PRICING = 'https://express.tnt.com/expressconnect/pricing/getprice';
    public const SHIPPING = 'https://express.tnt.com/expressconnect/shipping/ship';
    public const TRACKING = 'https://express.tnt.com/expressconnect/track.do';

    public function __construct(
        private readonly HttpClientInterface $http,
        public readonly string $username,
        public readonly string $password,
        public readonly string $accountNumber,
        private readonly int $timeout = 20,
    ) {
    }

    /** One XML document posted; back as the parsed answer. */
    public function call(string $url, string $field, string $xml, bool $auth = true): \SimpleXMLElement
    {
        try {
            $response = $this->http->request('POST', $url, array_filter([
                'auth_basic' => $auth ? [$this->username, $this->password] : null,
                'body' => [$field => $xml],
                'timeout' => $this->timeout,
            ]));
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (HttpExceptionInterface $e) {
            throw new CarrierException('tnt', 'TNT request failed: '.$e->getMessage(), null, $e);
        }
        if ($status >= 400) {
            throw new CarrierException('tnt', sprintf('TNT answered HTTP %d.', $status));
        }
        $parsed = @simplexml_load_string(trim($content));
        if (false === $parsed) {
            // Shipping answers an access code first, as plain text; the result is fetched with it.
            if (preg_match('/^[A-Za-z0-9]{6,32}$/', trim($content))) {
                return new \SimpleXMLElement('<accessCode>'.trim($content).'</accessCode>');
            }
            throw new CarrierException('tnt', 'TNT answered with a body that is not XML.');
        }
        foreach ([$parsed->errors->brokenRule ?? [], $parsed->ERROR ?? []] as $errors) {
            foreach ($errors as $error) {
                throw new CarrierException('tnt', (string) ($error->description ?? $error->DESCRIPTION ?? $error), (string) ($error->code ?? $error->CODE ?? '') ?: null);
            }
        }

        return $parsed;
    }

    public static function e(string $s): string
    {
        return htmlspecialchars($s, \ENT_XML1 | \ENT_QUOTES, 'UTF-8');
    }
}

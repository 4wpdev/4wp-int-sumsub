<?php declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class Sumsub
{
    private const BASE_URL = 'https://api.sumsub.com';
    private Client $http;

    public function __construct(
        private string $appToken,
        private string $secretKey
    ) {
        $this->http = new Client([
            'base_uri'   => self::BASE_URL,
            'http_errors'=> false,
            'timeout'    => 15,
        ]);
    }

    /** Создаёт аппликанта и возвращает его ID */
    public function createApplicant(string $externalUserId, string $levelName): string
    {
        $body = json_encode(
            ['externalUserId' => $externalUserId],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $req = new Request(
            'POST',
            '/resources/applicants?' . http_build_query(['levelName' => $levelName]),
            ['Content-Type' => 'application/json'],
            $body
        );

        $resp = $this->send($req);

        return $this->json($resp)['id'] ?? '';
    }

    /** Сброс профиля аппликанта */
    public function resetApplicantProfile(string $applicantId): array
    {
        $req  = new Request('POST', "/resources/applicants/{$applicantId}/reset");
        return $this->json($this->send($req));
    }

    /** Загрузка документа (вернёт X-Image-Id) */
    public function addDocument(string $applicantId, string $filePath, array $metadata): string
    {
        $path = "/resources/applicants/{$applicantId}/info/idDoc";

        $resp = $this->http->request(
            'POST',
            $path,
            [
                'headers'   => $this->authHeaders('POST', $path, ''),
                'multipart' => [
                    [
                        'name'     => 'metadata',
                        'contents' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                    [
                        'name'     => 'content',
                        'contents' => Utils::tryFopen($filePath, 'r'),
                        'filename' => basename($filePath),
                    ],
                ],
            ]
        );

        $this->assertOk($resp, 'upload document');

        return $resp->getHeaderLine('X-Image-Id');
    }

    /** Статус проверки */
    public function getApplicantStatus(string $applicantId): array
    {
        $req = new Request('GET', "/resources/applicants/{$applicantId}/requiredIdDocsStatus");
        return $this->json($this->send($req));
    }

    /** Полные данные аппликанта */
    public function getApplicantData(string $applicantId): array
    {
        $req = new Request('GET', "/resources/applicants/{$applicantId}/one");
        return $this->json($this->send($req));
    }

    /** Скачать изображение документа (вернётся base64) */
    public function getDocumentImage(string $inspectionId, string $imageId): string
    {
        $req  = new Request('GET', "/resources/inspections/{$inspectionId}/resources/{$imageId}");
        $resp = $this->send($req);
        return base64_encode($resp->getBody()->getContents());
    }

    /** AccessToken для Web/Mobile SDK */
    public function getAccessToken(string $externalUserId, string $levelName, int $ttl = 600): array
    {
        $query = http_build_query([
            'userId'     => $externalUserId,
            'levelName'  => $levelName,
            'ttlInSecs'  => $ttl,
        ]);

        $path = '/resources/accessTokens?' . $query;
        $req  = new Request('POST', $path);

        return $this->json($this->send($req));
    }



    private function send(RequestInterface $request): ResponseInterface
    {
        $ts = time();

        $signed = $request
            ->withHeader('X-App-Token', $this->appToken)
            ->withHeader('X-App-Access-Ts', (string)$ts)
            ->withHeader('X-App-Access-Sig', $this->signature($request, $ts));

        try {
            $resp = $this->http->send($signed);
        } catch (GuzzleException $e) {
            throw new RuntimeException('HTTP transport error: ' . $e->getMessage(), 0, $e);
        }

        $this->assertOk($resp, (string)$request->getUri());

        return $resp;
    }

    private function signature(RequestInterface $req, int $ts): string
    {
        $uri   = $req->getUri();
        $pathQ = $uri->getPath() . ($uri->getQuery() ? '?' . $uri->getQuery() : '');

        $stream = $req->getBody();
        $stream->rewind();
        $body = (string)$stream->getContents();

        return hash_hmac(
            'sha256',
            $ts . strtoupper($req->getMethod()) . $pathQ . $body,
            $this->secretKey
        );
    }

    private function authHeaders(string $method, string $target, string $body): array
    {
        $ts  = time();
        $sig = hash_hmac('sha256', $ts . $method . $target . $body, $this->secretKey);

        return [
            'X-App-Token'      => $this->appToken,
            'X-App-Access-Ts'  => (string)$ts,
            'X-App-Access-Sig' => $sig,
        ];
    }

    private function json(ResponseInterface $resp): array
    {
        $raw = (string)$resp->getBody();
        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('Invalid JSON response: ' . $raw, 0, $e);
        }
    }

    private function assertOk(ResponseInterface $resp, string $ctx): void
    {
        if (!in_array($resp->getStatusCode(), [200, 201], true)) {
            throw new RuntimeException(
                sprintf('Sumsub error [%s] %d: %s', $ctx, $resp->getStatusCode(), (string)$resp->getBody())
            );
        }
    }
}

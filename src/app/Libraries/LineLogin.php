<?php

namespace App\Libraries;

use RuntimeException;

/**
 * LINE Login v2.1 (OAuth 2.0 authorization code flow)
 *
 * @see https://developers.line.biz/en/docs/line-login/integrate-line-login/
 */
class LineLogin
{
    private const AUTHORIZE_URL = 'https://access.line.me/oauth2/v2.1/authorize';
    private const TOKEN_URL     = 'https://api.line.me/oauth2/v2.1/token';
    private const PROFILE_URL   = 'https://api.line.me/v2/profile';

    private string $channelId;
    private string $channelSecret;
    private string $callbackUrl;

    public function __construct()
    {
        $this->channelId     = (string) (getenv('LINE_CHANNEL_ID') ?: '');
        $this->channelSecret = (string) (getenv('LINE_CHANNEL_SECRET') ?: '');
        $this->callbackUrl   = (string) (getenv('LINE_CALLBACK_URL') ?: '');
    }

    /**
     * ตั้งค่าครบหรือยัง — ใช้แสดงข้อความเตือนแทนที่จะพาไปหน้า error ของ LINE
     */
    public function isConfigured(): bool
    {
        return $this->channelId !== ''
            && $this->channelSecret !== ''
            && $this->callbackUrl !== ''
            && ! str_starts_with($this->channelId, 'CHANGE_ME');
    }

    public function callbackUrl(): string
    {
        return $this->callbackUrl;
    }

    /**
     * URL สำหรับส่งผู้ใช้ไปหน้ายินยอมของ LINE
     */
    public function authorizeUrl(string $state, string $nonce): string
    {
        return self::AUTHORIZE_URL . '?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => $this->channelId,
            'redirect_uri'  => $this->callbackUrl,
            'state'         => $state,
            'scope'         => 'profile openid',
            'nonce'         => $nonce,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * แลก authorization code เป็น access token
     *
     * @return array<string, mixed>
     */
    public function exchangeToken(string $code): array
    {
        return $this->post(self::TOKEN_URL, [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->callbackUrl,
            'client_id'     => $this->channelId,
            'client_secret' => $this->channelSecret,
        ]);
    }

    /**
     * ดึงโปรไฟล์ (userId, displayName, pictureUrl)
     *
     * @return array<string, mixed>
     */
    public function profile(string $accessToken): array
    {
        return $this->get(self::PROFILE_URL, ['Authorization: Bearer ' . $accessToken]);
    }

    /**
     * @param array<string, string> $fields
     *
     * @return array<string, mixed>
     */
    private function post(string $url, array $fields): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);

        return $this->run($ch, $url);
    }

    /**
     * @param list<string> $headers
     *
     * @return array<string, mixed>
     */
    private function get(string $url, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);

        return $this->run($ch, $url);
    }

    /**
     * @param resource|\CurlHandle $ch
     *
     * @return array<string, mixed>
     */
    private function run($ch, string $url): array
    {
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('เชื่อมต่อ LINE ไม่สำเร็จ: ' . $error);
        }

        $decoded = json_decode((string) $body, true);

        if ($status >= 400 || ! is_array($decoded)) {
            log_message('error', 'LINE API {url} ตอบ {status}: {body}', [
                'url'    => $url,
                'status' => $status,
                'body'   => (string) $body,
            ]);

            $message = is_array($decoded)
                ? ($decoded['error_description'] ?? $decoded['message'] ?? 'ไม่ทราบสาเหตุ')
                : 'ไม่ทราบสาเหตุ';

            throw new RuntimeException('LINE ตอบกลับผิดพลาด (' . $status . '): ' . $message);
        }

        return $decoded;
    }
}

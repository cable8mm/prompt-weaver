<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Services;

use RuntimeException;

final class WifiNoteUploadClient
{
    public function __construct(
        private readonly int $connectTimeoutSeconds = 10,
        private readonly int $timeoutSeconds = 60,
    ) {}

    /** @return array{status_code: 202, upload_id: string} */
    public function upload(string $server, string $token, string $archivePath): array
    {
        if (! is_file($archivePath) || ! is_readable($archivePath)) {
            throw new RuntimeException("Template pack is not readable: {$archivePath}");
        }

        $curl = curl_init(rtrim($server, '/').'/api/template-packs/upload');
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize the WifiNote upload request.');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'file' => new \CURLFile($archivePath, 'application/zip', basename($archivePath)),
            ],
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$token,
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $response = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $errorCode = curl_errno($curl);
        curl_close($curl);

        if ($response === false) {
            if ($errorCode === CURLE_OPERATION_TIMEDOUT) {
                throw new RuntimeException('WifiNote upload timed out. Check the server and try again.');
            }

            throw new RuntimeException('Network error while contacting WifiNote. Check the server URL and your connection.');
        }

        match ($statusCode) {
            408, 504 => throw new RuntimeException('WifiNote upload timed out. Check the server and try again.'),
            401 => throw new RuntimeException('WifiNote rejected the Personal Access Token. Run "prompt-weaver login" to update it.'),
            403 => throw new RuntimeException('This Personal Access Token does not have permission to upload template packs.'),
            422 => throw new RuntimeException('WifiNote could not accept this template pack. Check the generated files and try again.'),
            500 => throw new RuntimeException('WifiNote encountered a server error while accepting the template pack. Try again later.'),
            default => null,
        };

        if ($statusCode !== 202) {
            throw new RuntimeException("WifiNote returned unexpected HTTP {$statusCode}; expected 202 Accepted.");
        }

        try {
            $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('WifiNote returned an invalid upload confirmation.', previous: $exception);
        }

        if (! is_array($result) || ($result['status'] ?? null) !== 'accepted'
            || ! is_string($result['upload_id'] ?? null) || trim($result['upload_id']) === '') {
            throw new RuntimeException('WifiNote returned an incomplete upload confirmation.');
        }

        return ['status_code' => 202, 'upload_id' => trim($result['upload_id'])];
    }
}

<?php

namespace App\Services\Documents;

use RuntimeException;

/**
 * Minimal clamd client: streams the bytes with zINSTREAM and returns the
 * daemon's answer. Throws when clamd cannot be reached or answers an error,
 * so callers fail closed (the document stays pending and is retried).
 */
class Clamd
{
    private const CHUNK = 8192;

    public function __construct(private string $socket, private int $timeout) {}

    /**
     * @return string|null The signature name when infected, null when clean.
     */
    public function scan(string $contents): ?string
    {
        $connection = @stream_socket_client($this->socket, $code, $message, $this->timeout);

        if ($connection === false) {
            throw new RuntimeException("clamd unreachable at {$this->socket}: {$message}");
        }

        try {
            stream_set_timeout($connection, $this->timeout);

            if (fwrite($connection, self::frame($contents)) === false) {
                throw new RuntimeException('clamd write failed');
            }

            $answer = stream_get_contents($connection);

            if ($answer === false || stream_get_meta_data($connection)['timed_out']) {
                throw new RuntimeException('clamd did not answer in time');
            }
        } finally {
            fclose($connection);
        }

        return self::parse($answer);
    }

    /**
     * The zINSTREAM command, the contents in length-prefixed chunks, and the
     * zero-length chunk that ends the stream.
     */
    public static function frame(string $contents): string
    {
        $frame = "zINSTREAM\0";

        foreach (str_split($contents, self::CHUNK) as $chunk) {
            $frame .= pack('N', strlen($chunk)).$chunk;
        }

        return $frame.pack('N', 0);
    }

    /**
     * @return string|null The signature name when infected, null when clean.
     */
    public static function parse(string $answer): ?string
    {
        $answer = trim($answer, "\0\r\n ");

        if ($answer === 'stream: OK') {
            return null;
        }

        if (preg_match('/^stream: (.+) FOUND$/', $answer, $match) === 1) {
            return $match[1];
        }

        throw new RuntimeException('clamd error: '.mb_substr($answer, 0, 200));
    }
}

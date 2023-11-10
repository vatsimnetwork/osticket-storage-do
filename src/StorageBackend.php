<?php

namespace Vatsim\Osticket\Spaces;

use AttachmentFile;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use FileStorageBackend;
use GuzzleHttp\Psr7\Stream;
use Http;
use IOException;

class StorageBackend extends FileStorageBackend
{
    /**
     * The model instance of the file.
     *
     * @var AttachmentFile
     */
    public $meta;

    /**
     * The name of the storage backend.
     *
     * @var string
     */
    public static $desc = 'DigitalOcean Spaces';

    /**
     * The default block size.
     * Kept at 8192 to match the default block size of sockets.
     *
     * @var int
     */
    public static $blocksize = 8192;

    /**
     * The plugin configuration singleton.
     */
    private static Config $configSingleton;

    /**
     * The plugin configuration instance.
     */
    private Config $config;

    /**
     * The DigitalOcean Spaces client instance.
     */
    private S3Client $client;

    /**
     * The file hasher instance.
     */
    private FileHasher $hasher;

    /**
     * The backing stream for the file.
     */
    private ?Stream $body = null;

    /**
     * Create a new DigitalOcean Spaces storage backend instance.
     *
     * @param $meta AttachmentFile
     */
    public function __construct($meta)
    {
        parent::__construct($meta);

        $this->config = static::$configSingleton;

        $this->client = new S3Client([
            'version' => '2006-03-01',
            'region' => 'us-east-1',
            'endpoint' => $this->config->getEndpoint(),
            'use_path_style_endpoint' => false,
            'credentials' => [
                'key' => $this->config->getAccessKey(),
                'secret' => $this->config->getSecretKey(),
            ],
            'signature_version' => 'v4',
        ]);

        $this->hasher = new FileHasher();
    }

    /**
     * Read a chunk of the file from the storage backend.
     *
     * @param  int  $bytes
     * @param  int  $offset
     *
     * @throws IOException
     */
    public function read($bytes = 0, $offset = 0): string
    {
        if (! $this->body) {
            try {
                $result = $this->client->getObject([
                    'Bucket' => $this->config->getBucket(),
                    'Key' => $this->meta->getKey(),
                    '@http' => [
                        'stream' => true,
                    ],
                ]);
            } catch (S3Exception $e) {
                if ($e->getAwsErrorCode() == 'NoSuchKey') {
                    $filename = $this->meta->getKey();
                    throw new IOException("$filename: Unable to locate file");
                }

                throw $e;
            }

            $this->body = $result['Body'];
        }

        $chunk = '';
        $bytes = $bytes ?: $this->getBlockSize();
        while (strlen($chunk) < $bytes) {
            $buf = $this->body->read($bytes - strlen($chunk));
            if (! $buf) {
                break;
            }
            $chunk .= $buf;
        }

        return $chunk;
    }

    /**
     * Echo the file to standard output.
     *
     * @throws IOException
     */
    public function passthru(): void
    {
        while ($block = $this->read()) {
            echo $block;
        }
    }

    /**
     * Write a block of data to the backing stream.
     *
     * @param  string  $block
     */
    public function write($block): int
    {
        if (! $this->body) {
            $this->body = new Stream(fopen('php://temp', 'r+'));
        }

        $this->hasher->update($block);

        return $this->body->write($block);
    }

    /**
     * Flush the backing stream to the storage backend.
     *
     * @throws IOException
     */
    public function flush(): bool
    {
        return $this->upload($this->body);
    }

    /**
     * Upload a file to the storage backend.
     *
     * @param  string|Stream  $resource
     *
     * @throws IOException
     */
    public function upload($resource): bool
    {
        if ($resource instanceof Stream) {
            $body = $resource;
            $body->rewind();
        } elseif (is_string($resource)) {
            $this->hasher->updateFile($resource);
            $body = fopen($resource, 'r');
            rewind($body);
        }

        $filename = $this->meta->getKey();

        try {
            $this->client->upload(
                $this->config->getBucket(),
                $filename,
                $body,
                $this->config->getAcl(),
                [
                    'params' => [
                        'ContentType' => $this->meta->getType(),
                        'CacheControl' => 'private, max-age=86400',
                        'Content-MD5' => $this->hasher->digest(),
                    ],
                ]
            );

            return true;
        } catch (S3Exception $e) {
            throw new IOException("$filename: Unable to upload file");
        }
    }

    /**
     * Delete the file from the storage backend.
     *
     * @throws IOException
     */
    public function unlink(): bool
    {
        $filename = $this->meta->getKey();

        try {
            $this->client->deleteObject([
                'Bucket' => $this->config->getBucket(),
                'Key' => $filename,
            ]);
        } catch (S3Exception) {
            throw new IOException("$filename: Unable to delete file");
        }

        return true;
    }

    /**
     * Get the hash algorithms supported by this backend.
     *
     * @return string[]
     */
    public function getNativeHashAlgos(): array
    {
        return ['md5'];
    }

    /**
     * Get the digest for the uploaded file.
     *
     * @param  string  $algo
     */
    public function getHashDigest($algo): string
    {
        if ($algo !== 'md5') {
            return '';
        }

        return $this->hasher->digest();
    }

    /**
     * Redirect the user's browser to the file.
     *
     * @param  string  $disposition
     * @param  int  $ttl
     */
    public function sendRedirectUrl($disposition = 'inline', $ttl = false): void
    {
        $url = $this->client->createPresignedRequest(
            $this->client->getCommand(
                'GetObject',
                [
                    'Bucket' => $this->config->getBucket(),
                    'Key' => $this->meta->getKey(),
                    'ResponseContentDisposition' => sprintf(
                        '%s; %s',
                        $disposition,
                        Http::getDispositionFilename($this->meta->getName())
                    ),
                ]
            ),
            $ttl ? time() + $ttl : 'tomorrow',
        )->getUri();

        Http::redirect($url);
    }

    /**
     * Set the configuration singleton.
     */
    public static function setConfig(Config $config): void
    {
        static::$configSingleton = $config;
    }
}

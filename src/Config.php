<?php

namespace Vatsim\Osticket\Spaces;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use ChoiceField;
use Crypto;
use Plugin;
use PluginConfig;
use SectionBreakField;
use TextboxField;

class Config extends PluginConfig
{
    public function getBucket(): string
    {
        return $this->get('bucket');
    }

    public function getEndpoint(): string
    {
        return 'https://'.$this->get('region').'.digitaloceanspaces.com';
    }

    public function getAcl(): string
    {
        return $this->get('acl');
    }

    public function getAccessKey(): string
    {
        return $this->get('access-key');
    }

    public function getSecretKey(): string
    {
        return Crypto::decrypt($this->get('secret-key'), SECRET_SALT, $this->getNamespace());
    }

    private static function translate(): array
    {
        return Plugin::translate('storage-do');
    }

    public function getOptions(): array
    {
        [$__, $_N] = self::translate();

        return [
            'bucket' => new TextboxField([
                'label' => $__('Bucket Name'),
                'configuration' => ['size' => 40],
            ]),
            'region' => new ChoiceField([
                'label' => $__('Region'),
                'choices' => [
                    'nyc3' => 'NYC3',
                    'ams3' => 'AMS3',
                    'sfo2' => 'SFO2',
                    'sfo3' => 'SFO3',
                    'sgp1' => 'SGP1',
                    'fra1' => 'FRA1',
                    'blr1' => 'BLR1',
                    'syd1' => 'SYD1',
                ],
                'default' => 'nyc3',
            ]),
            'acl' => new ChoiceField([
                'label' => $__('Object ACL'),
                'choices' => [
                    'private' => $__('Private'),
                    'public-read' => $__('Public Read'),
                ],
                'default' => 'private',
            ]),

            'credentials' => new SectionBreakField([
                'label' => $__('Credentials'),
            ]),
            'access-key' => new TextboxField([
                'required' => true,
                'configuration' => ['length' => 64, 'size' => 40],
                'label' => $__('Access Key'),
            ]),
            'secret-key' => new TextboxField([
                'widget' => 'PasswordWidget',
                'required' => false,
                'configuration' => ['length' => 64, 'size' => 40],
                'label' => $__('Secret Key'),
            ]),
        ];
    }

    public function pre_save(&$config, &$errors): bool
    {
        [$__, $_N] = self::translate();

        $options = [
            'version' => '2006-03-01',
            'region' => 'us-east-1',
            'endpoint' => 'https://'.$config['region'].'.digitaloceanspaces.com',
            'use_path_style_endpoint' => false,
            'credentials' => [
                'key' => $config['access-key'],
                'secret' => $config['secret-key'] ?: Crypto::decrypt($this->get('secret-key'), SECRET_SALT,
                    $this->getNamespace()),
            ],
            'signature_version' => 'v4',
        ];

        if (! $options['credentials']['secret']) {
            $this->getForm()->getField('secret-key')->addError($__('Secret access key is required'));
        }

        $s3 = new S3Client($options);

        try {
            $s3->headBucket(['Bucket' => $config['bucket']]);
        } catch (S3Exception $e) {
            match ($e->getAwsErrorCode()) {
                'AccessDenied' => $errors['err'] = $__('User does not have access to the bucket.'),
                'NoSuchBucket' => $this->getForm()->getField('bucket')->addError($__('Bucket does not exist')),
            };
        }

        if (! $errors && $config['secret-key']) {
            $config['secret-key'] = Crypto::encrypt($config['secret-key'], SECRET_SALT, $this->getNamespace());
        } else {
            $config['secret-key'] = $this->get('secret-key');
        }

        return true;
    }
}

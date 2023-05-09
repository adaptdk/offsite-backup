<?php

namespace Adapt\Backup;

use Ahc\Cli\Input\Command;
use Ahc\Cli\IO\Interactor;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use ParagonIE\Halite\File;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\HiddenString\HiddenString;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use Symfony\Component\Dotenv\Dotenv;
use Platformsh\ConfigReader\Config;

class DownloadBackupCommand extends Command
{
    private $config;
    private $connectionString;

    public function __construct()
    {
        parent::__construct('backup:download', 'Download backup');
        
        $config = new Config();
        // This command is not available on platform.sh   
        if (!$config->isValidPlatform()) {
            $this->config = $this->getConfig();
            $this->connectionString = 'BlobEndpoint=' . $this->config->endpoint .
                ';SharedAccessSignature=' . $this->config->sas;
        }

        $this
            ->option('-c --container', 'The container name')
            ->option('-b --backup', 'The backup name');
    }

    // This method is auto called before `self::execute()` and receives `Interactor $io` instance
    public function interact(Interactor $io)
    {
        // Collect missing opts/args
        if (!$this->container) {
            $this->set('container', $io->prompt('Enter container'));
        }

        // Collect missing opts/args
        if (!$this->backup) {
            $this->set('backup', $io->prompt('Enter backup name'));
        }
    }

    // When app->handle() locates `container:list` command it automatically calls `execute()`
    public function execute($container, $backup)
    {
        $io = $this->app()->io();

        $blobClient = BlobRestProxy::createBlobService($this->connectionString);

        $secret = new HiddenString($this->config->secret);
        $encryptionKey = KeyFactory::deriveEncryptionKey($secret, base64_decode($this->config->salt));

        $options = new ListBlobsOptions();
        $options->setPrefix($backup);

        /** @var MicrosoftAzure\Storage\Blob\Models\ListBlobsResult $list **/
        $list = $blobClient->listBlobs($container, $options);

        /** @var MicrosoftAzure\Storage\Blob\Models\Blob $blob **/
        foreach ($list->getBlobs() as $blob) {
            $encryptedFilename = basename($blob->getName());
            $filename = basename($encryptedFilename, '.encrypted');
            /** @var MicrosoftAzure\Storage\Blob\Models\PutBlobResult $result **/
            $result = $blobClient->saveBlobToFile($encryptedFilename, $container, $blob->getName());
            File::decrypt($encryptedFilename, $filename, $encryptionKey);
            unlink($encryptedFilename);
            $io->write('Downloaded and decrypted: ');
            $io->boldGreen($filename);
            $io->write(PHP_EOL);
        }
    }

    private function getConfig()
    {
        // Read local .env file
        $dotenv = new Dotenv();
        $dotenv->load(__DIR__ . '/../.env');

        return (object) [
            'endpoint' => $_ENV['OFFSITE_BACKUP_ENDPOINT'],
            'sas' => $_ENV['OFFSITE_BACKUP_SAS'],
            'salt' => $_ENV['OFFSITE_BACKUP_SALT'],
            'secret' => $_ENV['OFFSITE_BACKUP_SECRET']
        ];
    }
}

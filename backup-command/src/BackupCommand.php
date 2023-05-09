<?php

namespace Adapt\Backup;

use Ahc\Cli\Input\Command;
use Adapt\OffsiteBackup\Backup;
use Symfony\Component\Dotenv\Dotenv;
use Platformsh\ConfigReader\Config;

class BackupCommand extends Command
{

    public function __construct()
    {
        parent::__construct('backup:execute', 'Make backup');
    }

    public function execute()
    {

        $io = $this->app()->io();
        $config = $this->getConfig();

        if (!$config) {
            $io->boldRed('Backup not configured');
            return;
        }

        $backup = new Backup();

        $backup->setSalt($config->salt)
            ->setSecret($config->secret)
            ->setAzureContainer($config->container)
            ->setAzureConnectionString(
                'BlobEndpoint=' . $config->endpoint .
                    ';SharedAccessSignature=' . $config->sas
            );

        $backup->setDatabaseType($config->database['scheme'])
            ->setDatabaseName($config->database['path'])
            ->setDatabaseUser($config->database['username'])
            ->setDatabasePassword($config->database['password'])
            ->setDatabaseHost($config->database['host'])
            ->setDatabasePort($config->database['port']);

        $backup->setSchemaOnlyDrupalDefaultAndTables(['search_api_db_content_text', 'ultimate_cron_log']);

        $backup->setFolders(['files' => __DIR__ . '/../../docroot/sites/default/files']);
        $backup->setIgnoreFolders(['files/translations', 'files/styles', 'files/php', 'files/js', 'files/css']);

        $status = $backup->execute();

        $io->boldGreen('Backup completed', $status);
    }

    private function getConfig()
    {
        $config = new Config();

        if (!$config->isValidPlatform()) {
            // Read local .env file
            $dotenv = new Dotenv();
            $dotenv->load(__DIR__ . '/../.env');

            // Build array like the one platform.sh provides
            $database = [
                'scheme'   => 'mysql',
                'database' => $_ENV['DATABASE'],
                'username' => $_ENV['USERNAME'],
                'password' => $_ENV['PASSWORD'],
                'host'     => $_ENV['HOST'],
                'port'     => $_ENV['PORT'],
            ];
        } else {
            $database = $config->credentials('database');
        }

        if (empty($_ENV['OFFSITE_BACKUP_SECRET'])) {
            return false;
        }

        return (object) [
            'database' => $database,
            'container' => $_ENV['OFFSITE_BACKUP_CONTAINER'],
            'endpoint' => $_ENV['OFFSITE_BACKUP_ENDPOINT'],
            'sas' => $_ENV['OFFSITE_BACKUP_SAS'],
            'salt' => $_ENV['OFFSITE_BACKUP_SALT'],
            'secret' => $_ENV['OFFSITE_BACKUP_SECRET']
        ];
    }
}

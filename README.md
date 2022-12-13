# Adapt offsite-backup
Offsite backup for PHP based solutions

This package can create a backup of:

 - 1 database ( mysql or postgres )
 - A list of folders
 - The environment variables

The backup will be compressed, encrypted and saved to Azure blob storage.

https://docs.microsoft.com/en-gb/azure/storage/blobs/storage-blobs-overview

The database dumper can be configured to only dump the schema for specific tables. This is practical to omit data from tables with large datasets containing eg. cache or logs.

The scheme-only tables can be set with:

    ->setSchemaOnlyTables(['table-name', ...])

.. or a drupal specific variant that by default will include the `cache_*` tables:

    ->setSchemaOnlyDrupalDefaultAndTables(['table-name', ...])

## Installation

Add repository to the package.json file:

    "repositories": [
      {
        "type": "git",
        "url": "https://github.com/adaptdk/offsite-backup"
      }
    ]

Add dependency:

    composer require adapt/offsite-backup

## Environment variables

    OFFSITE_BACKUP_CONTAINER= < the azure container name >
    OFFSITE_BACKUP_ENDPOINT= < the azure endpoint name >
    OFFSITE_BACKUP_SAS= < the azure shared access secret >
    OFFSITE_BACKUP_SALT= generate with base64_encode(random_bytes(16))
    OFFSITE_BACKUP_SECRET= < secret >

### Laravel config

Add the environment variables to config/services.php:

    'offsite-backup' => [
        'container' => env('OFFSITE_BACKUP_CONTAINER'),
        'endpoint' => env('OFFSITE_BACKUP_ENDPOINT'),
        'sas' => env('OFFSITE_BACKUP_SAS'),
        'salt' =>  env('OFFSITE_BACKUP_SALT'),
        'secret' =>  env('OFFSITE_BACKUP_SECRET'),
    ]

## Laravel command

Example on how you could create Laravel command (database is postgres):

    <?php

    namespace App\Console\Commands;

    use Illuminate\Console\Command;
    use Adapt\OffsiteBackup\Backup;

    class OffsiteBackupCommand extends Command
    {
        /**
         * The name and signature of the console command.
         *
         * @var string
         */
        protected $signature = 'api:backup';

        /**
         * The console command description.
         *
         * @var string
         */
        protected $description = 'Make offsite backup to Azure';

        /**
         * Execute the console command.
         *
         * @return mixed
         */
        public function handle()
        {
            try {

                $pgsql = config('database.connections.pgsql');
                $ob = config('services.offsite-backup');

                if (
                    $ob['container'] === null
                    || $ob['endpoint'] === null
                    ||  $ob['sas'] === null
                ) {
                    return;
                }

                $backup = new Backup();
                $backup
                    ->setDatabaseType('postgres')
                    ->setDatabaseHost($pgsql['host'])
                    ->setDatabasePort($pgsql['port'])
                    ->setDatabaseName($pgsql['database'])
                    ->setDatabaseUser($pgsql['username'])
                    ->setDatabasePassword($pgsql['password'])
                    ->setSchemaOnlyTables(['some-table-with-data-not-to-backup']);

                $folders = ['private' => realpath(storage_path('app/private'))];
                $backup->setFolders($folders);

                $backup->setSalt($ob['salt'])->setSecret($ob['secret']);

                $backup->setAzureContainer($ob['container']);
                $backup->setAzureConnectionString(
                    'BlobEndpoint=' . $ob['endpoint'] .
                    ';SharedAccessSignature=' . $ob['sas']
                );

                $backup->execute();
            } catch (\Exception $e) {
                $this->info(print_r($e, true));
            }
        }
    }

## Restoring backups
1. Go into the `backup-command` directory
2. Run `composer install`
3. Copy the `.env.example` to `.env`
4. Update the `.env` to contain the same values as the environment you want to download the backup from
5. Run `./bin/backup dl`
6. The container and backup name can be found in Azure



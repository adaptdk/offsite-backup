<?php

namespace Adapt\OffsiteBackup;

use Adapt\OffsiteBackup\BackupBuilderTrait;
use Adapt\OffsiteBackup\DatabaseTypeEnum;
use DateTime;
use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;
use PhpZip\Util\Iterator\IgnoreFilesRecursiveFilterIterator;
use Spatie\DbDumper\Compressors\GzipCompressor;
use Spatie\DbDumper\DbDumper;
use ParagonIE\Halite\File;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\HiddenString\HiddenString;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\PutBlobResult;
use MicrosoftAzure\Storage\Common\ServiceException;

class Backup
{
   use BackupBuilderTrait;

   /** @var string **/
   private $backupName = 'backup';

   /** @var string **/
   private $salt = null;

   /** @var HiddenString **/
   private $secret = null;

   /** @var string **/
   private $azureConnectionString;

   /** @var string **/
   private $azureContainer;

   /** @var string **/
   private $backupFolderName;

   /** @var DatabaseTypeEnum **/
   private $databaseType;

   /** @var string **/
   private $databaseHost = '127.0.0.1';

   /** @var string **/
   private $databasePort;

   /** @var string **/
   private $databaseName;

   /** @var string **/
   private $databaseUser;

   /** @var string **/
   private $databasePassword;

   /** @var array **/
   private $schemaOnlyTables = null;

   /** @var array **/
   private $folders = [];

   /** @var array **/
   private $ignoreFolders = [];

   /** @var array **/
   private $backupFolder = '/tmp';

   /** @var array **/
   private $backupFiles = [];

   /** @var array **/
   private $encryptedFiles = [];

   public function __construct()
   {
      $this->backupName .= '-' . (new DateTime)->format('Y-m-d-H-i');
      $this->backupFolder = sys_get_temp_dir() . '/' .  $this->backupName;
      mkdir($this->backupFolder);
   }

   /**
    * execute
    * Execute the individual handlers, encrypt and upload to azure.
    * @return bool
    */
   public function execute(): bool
   {
      $this->backupFiles[] = $this->handleTables();

      if ($this->schemaOnlyTables !== null) {
         $this->backupFiles[] = $this->handleSchemaOnlyTables();
      }

      $this->backupFiles[] = $this->handleFiles();

      $this->backupFiles[] = $this->handleConfig();

      $this->encryptedFiles = $this->encryptFiles();

      $this->uploadFiles();

      // Delete the temporary backup folder
      //rmdir($this->backupFolder);

      return true;
   }

   /**
    * encryptFiles
    * Encrypt all files in $this->backupFiles array.
    * @return array
    */
   private function encryptFiles(): array
   {
      $encrypted = [];

      // Generate encryption key
      $encryptionKey = KeyFactory::deriveEncryptionKey($this->secret, base64_decode($this->salt));

      foreach ($this->backupFiles as $file) {
         $encryptedFilename = $file . '.encrypted';
         File::encrypt($file, $encryptedFilename, $encryptionKey);
         $encrypted[] = $encryptedFilename;
         //unlink($file);
      }

      return $encrypted;
   }

   private function uploadFiles(): void
   {
      $blobClient = BlobRestProxy::createBlobService($this->azureConnectionString);
      foreach ($this->encryptedFiles as $fileToUpload) {
         $content = fopen($fileToUpload, "r");
         $path = $this->backupName . '/' . basename($fileToUpload);
         /** @var MicrosoftAzure\Storage\Blob\Models\PutBlobResult $result **/
         $result = $blobClient->createBlockBlob($this->azureContainer, $path, $content);
         //unlink($fileToUpload);
      }
   }

   /**
    * handleTables
    * Dump tables and data
    * @return string
    */
   private function handleTables(): string
   {
      $dbDumper = $this->createDatabaseDumper();

      $outputFilename = $this->getOutputFilename($this->databaseName . '.sql.gz');

      if ($this->schemaOnlyTables !== null) {
         $dbDumper->excludeTables($this->schemaOnlyTables);
      }

      $dbDumper->dumpToFile($outputFilename);

      return $outputFilename;
   }

   /**
    * handleSchemaOnlyTables
    * Dump tables where data is omitted
    * @return string
    */
   private function handleSchemaOnlyTables(): string
   {
      $dbDumper = $this->createDatabaseDumper();

      $outputFilename = $this->getOutputFilename($this->databaseName . '-schema-only.sql.gz');

      $dbDumper
         ->includeTables($this->schemaOnlyTables)
         ->addExtraOption($this->databaseType->schemaOnlyOption())
         ->dumpToFile($outputFilename);

      return $outputFilename;
   }

   /**
    * createDatabaseDumper
    * Initialize database dumper
    * @return DbDumper
    */
   private function createDatabaseDumper(): DbDumper
   {
      $className = $this->databaseType->dumperClassName();
      $dbDumper = new $className;
      return $dbDumper
         ->setHost($this->databaseHost)
         ->setPort($this->databasePort)
         ->setDbName($this->databaseName)
         ->setUserName($this->databaseUser)
         ->setPassword($this->databasePassword)
         ->useCompressor(new GzipCompressor());
   }

   /**
    * handleFiles
    * Creates ZIP archive from array of paths
    * @return string
    */
   private function handleFiles(): string
   {

      $outputFilename = $this->getOutputFilename('files.zip');
      $zipFile = new ZipFile();

      try {
         foreach ($this->folders as $key => $folder) {
            $directoryIterator = new \RecursiveDirectoryIterator($folder);
            $ignoreIterator = new IgnoreFilesRecursiveFilterIterator($directoryIterator, $this->ignoreFolders);
            $zipFile->addFilesFromIterator($ignoreIterator, $key);
         }
         $zipFile->saveAsFile($outputFilename); // save the archive to a file
         $zipFile->close();                     // close archive
      } catch (ZipException $e) {
         // handle exception
      } finally {
         $zipFile->close();
      }

      return $outputFilename;
   }

   /**
    * handleConfig
    * Dump envrironment variables to json encoded file.
    * @return string
    */
   private function handleConfig(): string
   {
      $outputFilename = $this->getOutputFilename('config.json');

      $environment = getenv();
      file_put_contents($outputFilename, json_encode($environment));

      return $outputFilename;
   }

   /**
    * getOutputFilename
    * Generate output filename
    * @return string
    */
   private function getOutputFilename(string $filename): string
   {
      return join([
         $this->backupFolder,
         '/',
         $this->backupName,
         '-',
         $filename
      ]);
   }
}

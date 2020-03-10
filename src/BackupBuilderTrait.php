<?php

namespace Adapt\OffsiteBackup;

use ParagonIE\HiddenString\HiddenString;

trait BackupBuilderTrait
{
   
   public function setSalt(string $salt): self
   {
      $this->salt = $salt;
      
      return $this;
   }
   
   public function setSecret(string $key): self
   {
      $this->secret = new HiddenString($key);
      
      return $this;
   }
   
   public function setAzureConnectionString(string $connectionString): self
   {
      $this->azureConnectionString = $connectionString;
      
      return $this;
   }
   
   public function setAzureContainer(string $container): self
   {
      $this->azureContainer = $container;
      
      return $this;
   }

   public function setDatabaseType(string $type) : self
   {
      $this->databaseType = DatabaseTypeEnum::make($type);
      if ($this->databasePort === null) {
         $this->setDatabasePort($this->databaseType->defaultPort());
      }

      return $this;
   }

   public function setDatabaseHost(string $host): self
   {
      $this->databaseHost = $host;
      
      return $this;
   }
   
   public function setDatabasePort(int $port): self
   {
      $this->databasePort = $port;
      
      return $this;
   }
   
   public function setDatabaseName(string $databaseName): self
   {
      $this->databaseName = $databaseName;
      
      return $this;
   }
   
   public function setDatabaseUser(string $databaseUser): self
   {
      $this->databaseUser = $databaseUser;
      
      return $this;
   }
   
   public function setDatabasePassword(string $databasePassword): self
   {
      $this->databasePassword = $databasePassword;
      
      return $this;
   }
   
   public function setSchemaOnlyDrupalDefaultAndTables(?array $tables = []): self
   {
      $this->schemaOnlyTables = array_merge(
         [
            'cache_config',
            'cache_data',
            'cache_default',
            'cache_discovery',
            'cache_dynamic_page_cache',
            'cache_entity',
            'cache_menu',
            'cache_page',
            'cache_render',
         ], $tables);
      
      return $this;
   }

   public function setSchemaOnlyTables(?array $tables = null): self
   {
      $this->schemaOnlyTables = $tables;
      
      return $this;
   }
   
   public function setFolders(array $folders, string $excludePattern = null): self
   {
      $this->folders = $folders;
      $this->excludePattern = $excludePattern;
      
      return $this;
   }
   
}

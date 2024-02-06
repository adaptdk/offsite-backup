<?php

namespace Adapt\OffsiteBackup;

use Spatie\Enum\Enum;

/**
 * @method static self mysql()
 * @method static self postgres()
 */
final class DatabaseTypeEnum extends Enum
{
    public function schemaOnlyOption() : string
    {
        switch ($this->value) {
            case 'mysql':
                return '--no-data';
                break;
            case 'postgres':
                return '--schema-only';
                break;
        }
    }

    public function dumperClassName() : string
    {
        switch ($this->value) {
            case 'mysql':
                return 'Spatie\DbDumper\Databases\MySql';
                break;
            case 'postgres':
                return 'Spatie\DbDumper\Databases\PostgreSql';
                break;
        }
    }

    public function defaultPort() : int
    {
        switch ($this->value) {
            case 'mysql':
                return 3306;
                break;
            case 'postgres':
                return 5432;
                break;
        }
    }
}

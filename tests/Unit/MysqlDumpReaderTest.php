<?php

namespace Tests\Unit;

use App\Support\Legacy\MysqlDumpReader;
use PHPUnit\Framework\TestCase;

class MysqlDumpReaderTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'dump');

        file_put_contents($this->path, <<<'SQL'
        -- Dump di prova
        CREATE TABLE `companies` (
          `id` bigint UNSIGNED NOT NULL,
          `name` varchar(255) NOT NULL
        ) ENGINE=InnoDB;

        INSERT INTO `companies` (`id`, `name`, `city`, `hours`) VALUES
        (1, 'L\'Osteria, da Mario', NULL, '[\"lun\"]'),
        (2, 'Riga\r\nnuova e \\ barra', 'Roma', '');
        INSERT INTO `orders` (`id`, `total`) VALUES
        (1, 10.50);
        INSERT INTO `companies` (`id`, `name`, `city`, `hours`) VALUES
        (3, 'Testo che va
        a capo (con parentesi), davvero', 'Bari', 'it''s');

        ALTER TABLE `companies`
          ADD PRIMARY KEY (`id`),
          ADD KEY `companies_user_id_foreign` (`user_id`);
        SQL);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function test_it_reads_only_the_requested_tables_with_mysql_escapes(): void
    {
        $rows = iterator_to_array((new MysqlDumpReader($this->path))->rows(['companies']), false);

        $this->assertCount(3, $rows);
        $this->assertSame(['companies', ['id' => '1', 'name' => "L'Osteria, da Mario", 'city' => null, 'hours' => '["lun"]']], $rows[0]);
        $this->assertSame("Riga\r\nnuova e \\ barra", $rows[1][1]['name']);
        $this->assertSame('', $rows[1][1]['hours']);
        $this->assertSame("Testo che va\na capo (con parentesi), davvero", $rows[2][1]['name']);
        $this->assertSame("it's", $rows[2][1]['hours']);
    }
}

<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2021 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

namespace tests\units\Glpi\System\Diagnostic;

class DatabaseKeysChecker extends \GLPITestCase {

   protected function sqlProvider() {
      return [
         [
            // Uncommon is_ flags and dates may have no entry in index
            'create_table_sql'   => <<<SQL
CREATE TABLE `%s` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `externalid` int NOT NULL,
  `is_something` tinyint NOT NULL,
  `date_of_stuff` tinyint NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB
SQL
            ,
            'expected_missing'   => [],
            'expected_misnamed'  => [],
            'expected_useless'   => [],
         ],
         [
            // All these fields (except name) have NOT expected corresponding key
            'create_table_sql'   => <<<SQL
CREATE TABLE `%s` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `entities_id` int NOT NULL,
  `is_recursive` tinyint NOT NULL,
  `items_id` int NOT NULL DEFAULT '0',
  `itemtype` varchar(100) NOT NULL,
  `is_active` tinyint NOT NULL,
  `is_deleted` tinyint NOT NULL,
  `is_dynamic` tinyint NOT NULL,
  `is_template` tinyint NOT NULL,
  `date_creation` timestamp NULL DEFAULT NULL,
  `date_mod` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB
SQL
            ,
            'expected_missing'   => [
               'entities_id'   => ['entities_id'],
               'is_recursive'  => ['is_recursive'],
               'item'          => ['itemtype', 'items_id'],
               'is_active'     => ['is_active'],
               'is_deleted'    => ['is_deleted'],
               'is_dynamic'    => ['is_dynamic'],
               'is_template'   => ['is_template'],
               'date_creation' => ['date_creation'],
               'date_mod'      => ['date_mod'],
            ],
            'expected_misnamed'  => [],
            'expected_useless'   => [],
         ],
         [
            // All these fields (except name) have expected corresponding key
            'create_table_sql'   => <<<SQL
CREATE TABLE `%s` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `entities_id` int NOT NULL,
  `is_recursive` tinyint NOT NULL,
  `items_id` int NOT NULL DEFAULT '0',
  `itemtype` varchar(100) NOT NULL,
  `is_active` tinyint NOT NULL,
  `is_deleted` tinyint NOT NULL,
  `is_dynamic` tinyint NOT NULL,
  `is_template` tinyint NOT NULL,
  `date_creation` timestamp NULL DEFAULT NULL,
  `date_mod` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entities_id` (`entities_id`),
  KEY `is_recursive` (`is_recursive`),
  KEY `item` (`itemtype`,`items_id`),
  KEY `is_active` (`is_active`),
  KEY `is_deleted` (`is_deleted`),
  KEY `is_dynamic` (`is_dynamic`),
  KEY `is_template` (`is_template`),
  KEY `date_mod` (`date_mod`),
  KEY `date_creation` (`date_creation`)
) ENGINE=InnoDB
SQL
            ,
            'expected_missing'   => [],
            'expected_misnamed'  => [],
            'expected_useless'   => [],
         ],
         [
            // Fields be indexed in a key that contains other keys, but only if they are at first position
            'create_table_sql'   => <<<SQL
CREATE TABLE `%s` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `computers_id` int NOT NULL,
  `items_id_1` int NOT NULL DEFAULT '0',
  `itemtype_1` varchar(100) NOT NULL,
  `items_id_2` int NOT NULL DEFAULT '0',
  `itemtype_2` varchar(100) NOT NULL,
  `is_active` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unicity` (`computers_id`,`is_active`),
  KEY `some_key` (`itemtype_1`,`items_id_1`,`itemtype_2`,`items_id_2`)
) ENGINE=InnoDB
SQL
            ,
            'expected_missing'   => [
               'is_active' => ['is_active'], // Included in `unicity`, but not at first position
               'item_2' => ['itemtype_2', 'items_id_2'], // Included in `some_key`, but not at first positions
            ],
            'expected_misnamed'  => [],
            'expected_useless'   => [],
         ],
         [
            // Key should match field name when key corresponds to a unique field
            'create_table_sql'   => <<<SQL
CREATE TABLE `%s` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `computers_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `some_key` (`computers_id`)
) ENGINE=InnoDB
SQL
            ,
            'expected_missing'   => [],
            'expected_misnamed'  => [
               'some_key' => 'computers_id',
            ],
            'expected_useless'   => [],
         ],
         [
            // Key should match `item(_suffix)?` pattern when key corresponds to a itemtype/items_id couple
            'create_table_sql'   => <<<SQL
CREATE TABLE `%s` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `items_id` int NOT NULL DEFAULT '0',
  `itemtype` varchar(100) NOT NULL,
  `items_id_blablabla` int NOT NULL DEFAULT '0',
  `itemtype_blablabla` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `mainitem` (`itemtype`, `items_id`),
  KEY `some_key` (`itemtype_blablabla`, `items_id_blablabla`)
) ENGINE=InnoDB
SQL
            ,
            'expected_missing'   => [],
            'expected_misnamed'  => [
               'mainitem' => 'item',
               'some_key' => 'item_blablabla',
            ],
            'expected_useless'   => [],
         ],
         [
            // Keys are useless if included in larger keys
            'create_table_sql'   => <<<SQL
CREATE TABLE `%s` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `computers_id` int NOT NULL,
  `items_id` int NOT NULL DEFAULT '0',
  `itemtype` varchar(100) NOT NULL,
  `items_linktype` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unicity` (`computers_id`,`itemtype`),
  KEY `computers_id` (`computers_id`),
  KEY `item_link` (`itemtype`,`items_id`,`items_linktype`),
  KEY `item` (`itemtype`,`items_id`)
) ENGINE=InnoDB
SQL
            ,
            'expected_missing'   => [],
            'expected_misnamed'  => [],
            'expected_useless'   => [
               'computers_id' => 'unicity',
               'item'         => 'item_link',
            ],
         ],
         [
            // Keys are NOT useless if included in FULLTEXT larger keys
            'create_table_sql'   => <<<SQL
CREATE TABLE `%s` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `content` text,
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  FULLTEXT KEY `fulltext` (`name`,`content`)
) ENGINE=InnoDB
SQL
            ,
            'expected_missing'   => [],
            'expected_misnamed'  => [],
            'expected_useless'   => [],
         ],
      ];
   }

   /**
    * @dataProvider sqlProvider
    */
   public function testMissingMisnamedUseless(
      string $create_table_sql,
      array $expected_missing,
      array $expected_misnamed,
      array $expected_useless
   ) {

      global $DB;

      $table_name = sprintf('glpitests_%s', uniqid());

      $this->newTestedInstance($DB);
      $DB->query(sprintf($create_table_sql, $table_name));
      $missing_keys  = $this->testedInstance->getMissingKeys($table_name);
      $misnamed_keys = $this->testedInstance->getMisnamedKeys($table_name);
      $useless_keys = $this->testedInstance->getUselessKeys($table_name);
      $DB->query(sprintf('DROP TABLE `%s`', $table_name));

      $this->array($missing_keys)->isEqualTo($expected_missing);
      $this->array($misnamed_keys)->isEqualTo($expected_misnamed);
      $this->array($useless_keys)->isEqualTo($expected_useless);
   }
}

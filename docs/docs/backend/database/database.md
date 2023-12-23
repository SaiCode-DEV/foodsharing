# Database

## Introduction

The database is the core of the foodsharing system. It stores all [important information](database-tables-columns.md).
The following chapters help you to understand the development tools and required steps to modify and publish changes on the database.

## Development helpers

### Phinx

Phinx is a migration tool for databases and helps to update and to maintain the database schema.
Each change in database schema is described by a migration file. On execution of this changeset can be rolled out to the database and in case of problems is a roll-back possible. A roll-back needs to be implemented and can not be done on each change, e. g. removing a column.

#### Steps to generate modification changeset

1. Create a new migration changeset

    ```bash
    $> ./scripts/docker-compose exec app vendor/bin/phinx create <YourMigrationChangeset>
    $> sudo chmod 777 migrations/<timestamp>_<YourMigrationChangeset>.php
    ```

    - `<YourMigrationChangeset>` is short summary in filename
    - `<timestamp>` automatic generated timestamp

2. Change database

    The following example shows a possible structure of a migration file.

    ```php
    <?php

    declare(strict_types=1);

    use Phinx\Migration\AbstractMigration;

    final class AddForumThreadStatus extends AbstractMigration
    {
        public function change(): void
        {
            $this->table('fs_theme')
                ->addColumn('status', 'integer', [
                    'null' => false,
                    'default' => '0',
                    'limit' => 10,
                    'signed' => false,
                    'comment' => '@Region:ThreadStatus status of the thread (open or closed)',
                ])->save();
        }
    }
    ```

    **Helpful links**

    - [DB Guidelines and rules](#guidelines-and-rules)
    - [Our database documentation]database-tables-columns.md)
    - [Phinx documentation](https://book.cakephp.org/phinx/0/en/migrations.html)

3. Test migration script by a dry-run

    ```bash
    $> ./scripts/docker-compose exec app vendor/bin/phinx migrate --dry-run
    ```

4. execute the exchange on the local database

    ```bash
    $> ./scripts/docker-compose exec app vendor/bin/phinx migrate
    ```

5. Merge request special cases
   - Mark MR with label
        - `tech:Database`
        - `for MR: sql migration | beta`
        - `for MR: sql migration | prod`

### Documentation script

Date documentation script extracts out of the running database instance all information for developer documentation. This should make it easier to understand the existing information, and helps to develop new queries or to reuse already existing queries.

The script can be executed as developer by the following command:

```bash
$> ./scripts/build-db-documentation
```

> If `ERROR: No container found for docs` is shown then bring the dev environment up via `scripts/start`

This will update the `docs/docs/backend/database/database-tables-columns.md` file.

The script supports different information extraction additional to the SQL `CREATE TABLE ...` provided information.

1. PHP Type links `@<PHPModule>:<Classfilename>`

   Column descriptions/comments which use following tags `@<PHPModule>:<Classfilename>` are linked to the PHP [DBConstants](https://gitlab.com/foodsharing-dev/foodsharing/-/tree/master/src/Modules/Core/DBConstants).
   This simplifies the description of PHP Enums/constants which are stored as integers.

2. Additional meta-information in `docs/database_metadata.json`

   The script uses `docs/database_metadata.json` to get information about the table like column links, todos, or descriptions.
   This is a helper describe old tables and fors todos are migrated to GitLab issues.
   The table comment and column comment need to be set on *creation* of the table. *Take care* that later changing will change may the column definition.

3. Find PHP Modules which use tables

   The script generates out of the PHP code base a table usage report. This helps to see the usage of database tables in module provided gateway.

### PhpMyAdmin

In the development enviroment [phpmyadmin](http://localhost:18081) is accessable on [http://localhost:18081](http://localhost:18081).

## Guidelines and rules

1. Each database modification should be implemented as phinx migration to `migration` folder.

   This allows us to track changes and run migration step by step and simplifies database changes in GIT merge requests

2. Add a description for each new table, so that other developers understand the meaning of the stored information and the usage for the users.

3. Add a description for each new column, so that other developers understand the meaning of the stored information.

4. Use PHP Type links in column description

   The PHP code use column types like int or strings, which have a restricted set of values like enums. This should be described in DBconstant, so that other developers understand the meaning of the values.

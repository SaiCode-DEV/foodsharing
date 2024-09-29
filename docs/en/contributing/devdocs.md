# DevDocs

Most of the DevDocs content is static content, but some parts like the [Database structure](../../backend/database/database-tables-columns) or the **Rest API** are automatically generated.

## Rerun of automatic generation

Go in to the `docs`-folder and perform the following commands.

```bash title=shell
yarn build:all
```

### Only for database structure

To generate, the database structure page, run the command.

```bash title=shell
yarn db:build
```

### Only for Rest Api

To generate, the rest api overview, run the command. You need

#### 1. Rerun the json dump
```bash title=shell
yarn docs:build
```

#### 2. Rerun build process
```bash title=shell
yarn api:rebuild
```

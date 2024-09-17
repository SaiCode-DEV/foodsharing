# Logic in the Database

:::caution
### TLDR;

Currently we use database logic only for the sleep status of users or temporary data migrations.
Make sure to discuss your idea with the team if you intend to use database logic.

:::


## Introduction

SQL supports many ways of implementing logic directly in the database: [triggers](https://mariadb.com/kb/en/triggers/), [computed columns](https://mariadb.com/kb/en/generated-columns/), [stored procedures](https://mariadb.com/kb/en/stored-procedures/), and [events](https://mariadb.com/kb/en/events/) are only some of the many examples.

While database logic can be powerful, it’s important to consider the typical architecture and design patterns used in our application. Usually, we prefer to implement all logic in php code, for the following reasons:

- **Separation of Concerns**: By keeping all logic within the php application layer, the database layer can focus solely on data storage and retrieval. This separation simplifies maintenance and makes the system more modular.
- **Developer Familiarity**: Not all developers are familiar with advanced SQL features like triggers or computed columns. Keeping logic in PHP reduces the need for developers to learn and manage niche SQL concepts.

However, there are cases where implementing logic directly in the database can be beneficial. This article explores these cases, explaining when and why you might want to implement logic at the database level.

## When to Use Logic in the Database

### Migrating Data Formats

When migrating data formats, it is usually necessary to keep supporting the format used in production, while allowing the use of the new format for the beta environment. The database may need to handle multiple formats simultaneously. In these cases, using database logic, such as triggers, can help manage data consistency and compatibility across formats.

- **Trigger Setup**: Create triggers that automatically adjust data as it’s inserted, updated or deleted to match the required formats for both production and beta environments.
- **Temporary Solution**: This approach allows for seamless operation while the migration is in progress. The triggers can be removed after the next release, once the migration is complete and the old data format is no longer in use.

**Example Trigger for Data Migration**: Triggers are used to add data that is added to one table, to the other and vise versa. You might need to make sure, that the triggers dont trigger each other.
```sql
-- This trigger is used to add data added to the old table to the new table as well
CREATE TRIGGER insert_into_new_table 
AFTER INSERT ON fs_old_table
FOR EACH ROW
BEGIN
  INSERT INTO fs_new_table (...)
  VALUES (...);
END;

-- This trigger is used to add data added to the new table to the old table as well
CREATE TRIGGER insert_into_old_table
AFTER INSERT ON fs_new_table
FOR EACH ROW
BEGIN
  INSERT INTO fs_old_table (...)
  VALUES (...);
END;
```
In practice these triggers may take a different form, or more triggers are reqired for updating and deleting data as well.

### Simple Computed Columns

Computed columns (also known as generated columns) can be used to automatically calculate and store derived data in a table, reducing the need for repeated calculations in your application code.

- **Access from Many Places**: If a calculated value is needed in multiple places throughout your application, using a computed column can centralize this logic in the database, ensuring consistency, as well as reduce the amount of redundant code in the application layer.
- **Reduce Database Traffic**: By moving simple calculations from multiple values to the database, you can reduce the amount of data transferred between the database and application by only transfering the result of the calculation.

**Example Use Case**: 
A computed column is used to fetch whether a user is currently sleeping, automatically calculated from the columns `sleep_status`, `sleep_from` and `sleep_until`.

```sql
ALTER TABLE fs_foodsaver
ADD is_sleeping TINYINT(1) GENERATED ALWAYS AS (
  IF(sleep_status = 1,
    sleep_from < NOW() AND NOW() < sleep_until,
    sleep_status = 2
  )
) VIRTUAL
COMMENT \"calculated column. Indicates, whether the user is currently sleeping\"
```

## Conclusion

While implementing logic directly in the database can offer certain advantages, it is important to approach this strategy with caution. Database logic should be used sparingly and only in exceptional cases where it provides a clear benefit that cannot be achieved as effectively in the application layer. 

Before deciding to implement logic at the database level, it’s crucial to discuss the proposed solution with other developers. This ensures that everyone understands the implications, maintains consistency across the codebase, and adheres to best practices for database design and application architecture. Remember, keeping logic centralized in the application layer often simplifies maintenance, reduces the learning curve for new developers, and maintains a clean separation of concerns. Use database logic judiciously, always weighing the pros and cons, and make sure it aligns with your overall development strategy.

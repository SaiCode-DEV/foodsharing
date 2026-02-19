import mysql from "mysql2/promise";

export class Database {
  private static connection: mysql.Connection;

  /**
   * Connect to the database
   * @returns The database connection
   */
  static async connect() {
    if (!this.connection) {
      this.connection = await mysql.createConnection({
        host: process.env.DB_HOST || "db",
        port: Number(process.env.DB_PORT) || 3306,
        user: process.env.DB_USER || "root",
        password: process.env.DB_PASS || "root",
        database: process.env.DB_NAME || "foodsharing",
      });
    }
    return this.connection;
  }

  /**
   * Check if a record exists in the database
   * @param table The table to query
   * @param criteria The criteria to filter by
   * @returns True if a matching record exists, false otherwise
   */
  static async seeInDatabase(
    table: string,
    criteria: Record<string, any>,
  ): Promise<boolean> {
    const conn = await this.connect();

    const values: any[] = [];

    const whereClauses = Object.entries(this.omitUndefined(criteria))
      .map(([key, value]) => {
        if (value === null) {
          return `\`${key}\` IS NULL`;
        }
        values.push(value);
        return `\`${key}\` = ?`;
      })
      .join(" AND ");

    const query = `SELECT COUNT(*) as count FROM \`${table}\` WHERE ${whereClauses}`;

    const [rows] = await conn.execute(query, values);
    const count = (rows as any)[0].count;

    return count > 0;
  }

  /**
   * Grab a single value from the database
   * @param table The table to query
   * @param column The column to retrieve
   * @param criteria The criteria to filter by
   * @returns The value of the specified column
   */
  static async grabFromDatabase(
    table: string,
    column: string,
    criteria?: Record<string, any>,
  ): Promise<string> {
    const conn = await this.connect();

    let whereClauses = "1=1";
    let values: any[] = [];

    if (criteria) {
      whereClauses = Object.entries(this.omitUndefined(criteria))
        .map(([key, value]) => {
          if (typeof value === "string" && value.includes("%")) {
            return `\`${key}\` LIKE ?`;
          }
          return `\`${key}\` = ?`;
        })
        .join(" AND ");
      values = Object.values(criteria);
    }

    const query = `SELECT \`${column}\` FROM \`${table}\` WHERE ${whereClauses} LIMIT 1`;
    const [rows] = await conn.execute(query, values);

    if (Array.isArray(rows) && rows.length > 0) {
      return rows[0][column];
    }
    throw new Error(`No matching entry found in ${table}`);
  }

  /**
   * Grab a single column from the database
   * @param table The table to query
   * @param column The column to retrieve
   * @param criteria The criteria to filter by
   * @returns An array of values from the specified column
   */
  static async grabColumnFromDatabase(
    table: string,
    column: string,
    criteria?: Record<string, any>,
  ): Promise<any[]> {
    const conn = await this.connect();

    let whereClauses = "1=1";
    let values: any[] = [];

    if (criteria) {
      whereClauses = Object.entries(this.omitUndefined(criteria))
        .map(([key, value]) => {
          if (typeof value === "string" && value.includes("%")) {
            return `\`${key}\` LIKE ?`;
          }
          return `\`${key}\` = ?`;
        })
        .join(" AND ");
      values = Object.values(criteria);
    }

    const query = `SELECT \`${column}\` FROM \`${table}\` WHERE ${whereClauses}`;
    const [rows] = await conn.execute(query, values);

    if (Array.isArray(rows)) {
      return rows.map((row) => row[column]);
    }
    return [];
  }

  /**
   * Add a new record to the database
   * @param table The table to insert into
   * @param data The data to insert
   * @returns The ID of the newly created record
   */
  static async addToDatabase(
    table: string,
    data: Record<string, any>,
  ): Promise<number> {
    try {
      const conn = await this.connect();

      const columns = Object.keys(data)
        .map((c) => `\`${c}\``)
        .join(", ");
      const placeholders = Object.keys(data)
        .map(() => "?")
        .join(", ");
      const values = Object.values(data);

      const query = `INSERT INTO \`${table}\` (${columns}) VALUES (${placeholders})`;
      const [result] = await conn.execute(query, values);

      return (result as any).insertId;
    } catch (error) {
      console.error(`Error addToDatabase ${table}: `, error.message);
      throw error;
    }
  }

  static async cleanup() {
    if (this.connection) {
      await this.connection.end();
    }
  }

  protected static omitUndefined<T extends object>(obj: T): Partial<T> {
    return Object.fromEntries(
      Object.entries(obj).filter(([, value]) => value !== undefined),
    ) as Partial<T>;
  }
}

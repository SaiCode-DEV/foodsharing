import mysql from 'mysql2/promise';

// eslint-disable-next-line @typescript-eslint/no-extraneous-class
export class Database {
  private static connection: mysql.Connection;

  static async connect() {
    if (!this.connection) {
      this.connection = await mysql.createConnection({
        host: process.env.DB_HOST || 'db',
        port: Number(process.env.DB_PORT) || 3306,
        user: process.env.DB_USER || 'root',
        password: process.env.DB_PASS || 'root',
        database: process.env.DB_NAME || 'foodsharing'
      });
    }
    return this.connection;
  }

  static async seeInDatabase(table: string, criteria: Record<string, any>): Promise<boolean> {
    const conn = await this.connect();

    const whereClauses = Object.entries(criteria)
      .map(([key, value]) => `\`${key}\` = ?`)
      .join(' AND ');

    const query = `SELECT COUNT(*) as count FROM \`${table}\` WHERE ${whereClauses}`;
    const values = Object.values(criteria);

    const [rows] = await conn.execute(query, values);
    const count = (rows as any)[0].count;

    return count > 0;
  }

  static async grabFromDatabase(table: string, column: string, criteria?: Record<string, any>): Promise<string> {
    const conn = await this.connect();

    let whereClauses = '1=1';
    let values: any[] = [];

    if (criteria) {
      whereClauses = Object.entries(criteria)
        .map(([key, value]) => {
          if (typeof value === 'string' && value.includes('%')) {
            return `\`${key}\` LIKE ?`;
          }
          return `\`${key}\` = ?`;
        })
        .join(' AND ');
      values = Object.values(criteria);
    }

    const query = `SELECT \`${column}\` FROM \`${table}\` WHERE ${whereClauses} LIMIT 1`;
    const [rows] = await conn.execute(query, values);

    if (Array.isArray(rows) && rows.length > 0) {
      return rows[0][column];
    }
    throw new Error(`No matching entry found in ${table}`);
  }

  static async grabColumnFromDatabase(table: string, column: string, criteria?: Record<string, any>): Promise<any[]> {
    const conn = await this.connect();

    let whereClauses = '1=1';
    let values: any[] = [];

    if (criteria) {
      whereClauses = Object.entries(criteria)
        .map(([key, value]) => {
          if (typeof value === 'string' && value.includes('%')) {
            return `\`${key}\` LIKE ?`;
          }
          return `\`${key}\` = ?`;
        })
        .join(' AND ');
      values = Object.values(criteria);
    }

    const query = `SELECT \`${column}\` FROM \`${table}\` WHERE ${whereClauses}`;
    const [rows] = await conn.execute(query, values);

    if (Array.isArray(rows)) {
      return rows.map(row => row[column]);
    }
    return [];
  }

  static async addToDatabase(table: string, data: Record<string, any>): Promise<number> {
    try {
      const conn = await this.connect();

      const columns = Object.keys(data).map(c => `\`${c}\``).join(', ');
      const placeholders = Object.keys(data).map(() => '?').join(', ');
      const values = Object.values(data);

      const query = `INSERT INTO \`${table}\` (${columns}) VALUES (${placeholders})`;
      const [result] = await conn.execute(query, values);

      return (result as any).insertId;
    } catch (error) {
      console.error(`Error addToDatabase ${table}: `,error.message);
    }
  }

  static async cleanup() {
    if (this.connection) {
      await this.connection.end();
    }
  }
}

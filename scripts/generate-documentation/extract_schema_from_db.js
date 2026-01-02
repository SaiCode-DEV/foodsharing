#!/usr/bin/env node

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

function execQuery(query, dbHost = 'db', dbUser = 'root', dbPassword = 'root', dbName = 'foodsharing') {
    const cmd = `mariadb -h "${dbHost}" -u "${dbUser}" -p"${dbPassword}" "${dbName}" -N -B -e "${query.replace(/"/g, '\\"')}"`;
    try {
        const result = execSync(cmd, { encoding: 'utf8', maxBuffer: 50 * 1024 * 1024 });
        return result.trim();
    } catch (error) {
        console.error('Query failed:', query);
        throw error;
    }
}

function parseTabDelimited(data) {
    if (!data) return [];
    return data.split('\n').map(line => line.split('\t'));
}

function extractSchema() {
    const buildDir = process.argv[2];
    const dbHost = process.env.DB_HOST || 'db';
    const dbUser = process.env.DB_USER || 'root';
    const dbPassword = process.env.DB_PASS || 'root';
    const dbName = 'foodsharing';

    console.log('Connecting to database...');
    console.log(`Host: ${dbHost}, User: ${dbUser}, Database: ${dbName}`);

    // Get all tables
    const tablesData = execQuery(`
        SELECT TABLE_NAME, TABLE_COMMENT, ENGINE
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = '${dbName}'
        ORDER BY TABLE_NAME
    `, dbHost, dbUser, dbPassword, dbName);

    const tables = parseTabDelimited(tablesData).map(row => {
        const [name, comment, engine] = row;
        return { name, comment, engine };
    });

    const database = [];

    for (const table of tables) {
        console.log(`Processing table: ${table.name}`);

        // Get columns
        const columnsData = execQuery(`
            SELECT 
                COLUMN_NAME,
                DATA_TYPE,
                COLUMN_TYPE,
                CHARACTER_MAXIMUM_LENGTH,
                NUMERIC_PRECISION,
                NUMERIC_SCALE,
                DATETIME_PRECISION,
                IS_NULLABLE,
                COLUMN_DEFAULT,
                EXTRA,
                COLUMN_COMMENT,
                CHARACTER_SET_NAME,
                COLLATION_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = '${dbName}' AND TABLE_NAME = '${table.name}'
            ORDER BY ORDINAL_POSITION
        `, dbHost, dbUser, dbPassword, dbName);

        const columns = parseTabDelimited(columnsData).map(col => {
            const [
                name,               // col[0]
                datatype,           // col[1]
                columnType,         // col[2]
                charMaxLen,         // col[3]
                numPrecision,       // col[4]
                numScale,           // col[5]
                datetimePrecision,  // col[6]
                isNullable,         // col[7]
                defaultVal,         // col[8]
                extra,              // col[9]
                comment,            // col[10]
                charset,            // col[11]
                collation           // col[12]
            ] = col;

            const type = {};
            type.datatype = datatype;

            // Extract width/length from full_type
            const widthMatch = columnType.match(/\((\d+)\)/);
            if (widthMatch && ['int', 'bigint', 'smallint', 'tinyint', 'mediumint'].includes(datatype)) {
                type.displayWidth = parseInt(widthMatch[1]);
            } else if (charMaxLen !== 'NULL' && charMaxLen !== '') {
                type.length = parseInt(charMaxLen);
            } else if (numPrecision !== 'NULL' && numPrecision !== '') {
                type.width = parseInt(numPrecision);
                if (numScale !== 'NULL' && numScale !== '') {
                    type.fractional = parseInt(numScale);
                }
            } else if (datetimePrecision !== 'NULL' && datetimePrecision !== '') {
                type.fractional = parseInt(datetimePrecision);
            }

            const options = {
                nullable: isNullable === 'YES',
                unsigned: columnType.includes('unsigned')
            };

            if (defaultVal !== 'NULL' && defaultVal !== '') {
                options.default = defaultVal;
            }

            if (extra && extra.includes('auto_increment')) {
                options.autoincrement = true;
            }

            if (comment !== 'NULL' && comment !== '') {
                options.comment = comment;
            }

            const result = { name, type, options };

            if (charset !== 'NULL' && charset !== '') {
                result.charset = charset;
            }
            if (collation !== 'NULL' && collation !== '') {
                result.collation = collation;
            }

            return result;
        });

        // Get primary key
        const pkData = execQuery(`
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = '${dbName}' AND TABLE_NAME = '${table.name}' AND CONSTRAINT_NAME = 'PRIMARY'
            ORDER BY ORDINAL_POSITION
        `, dbHost, dbUser, dbPassword, dbName);

        const pkColumns = parseTabDelimited(pkData)
            .filter(row => row[0])
            .map(([columnName]) => ({ column: columnName }));

        // Get foreign keys
        const fkData = execQuery(`
            SELECT 
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = '${dbName}' AND TABLE_NAME = '${table.name}' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION
        `, dbHost, dbUser, dbPassword, dbName);

        const fkInfo = parseTabDelimited(fkData).filter(row => row[0]);

        // Get unique keys
        const ukData = execQuery(`
            SELECT 
                INDEX_NAME,
                COLUMN_NAME
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = '${dbName}' AND TABLE_NAME = '${table.name}' 
                AND NON_UNIQUE = 0 AND INDEX_NAME != 'PRIMARY'
            ORDER BY INDEX_NAME, SEQ_IN_INDEX
        `, dbHost, dbUser, dbPassword, dbName);

        const uniqueKeys = parseTabDelimited(ukData).filter(row => row[0]);

        // Format table object
        const tableObj = {
            name: table.name,
            columns: columns
        };

        if (pkColumns.length > 0) {
            tableObj.primaryKey = {
                columns: pkColumns
            };
        }

        // Group foreign keys by constraint name
        const fkMap = {};
        fkInfo.forEach(fk => {
            const [constraintName, columnName, referencedTable, referencedColumn] = fk;
            
            if (!fkMap[constraintName]) {
                fkMap[constraintName] = {
                    columns: [],
                    reference: {
                        table: referencedTable,
                        columns: []
                    }
                };
            }
            fkMap[constraintName].columns.push({ column: columnName });
            fkMap[constraintName].reference.columns.push({ column: referencedColumn });
        });

        if (Object.keys(fkMap).length > 0) {
            tableObj.foreignKeys = Object.values(fkMap);
        }

        // Group unique keys by index name
        const ukMap = {};
        uniqueKeys.forEach(uk => {
            const [indexName, columnName] = uk;
            
            if (!ukMap[indexName]) {
                ukMap[indexName] = [];
            }
            ukMap[indexName].push({ column: columnName });
        });

        if (Object.keys(ukMap).length > 0) {
            tableObj.uniqueKeys = Object.values(ukMap);
        }

        if (table.comment || table.engine) {
            tableObj.options = {};
            if (table.comment) tableObj.options.comment = table.comment;
            if (table.engine) tableObj.options.engine = table.engine;
        }

        database.push(tableObj);
    }

    // Write schema to file
    const outputFile = path.join(buildDir, 'schema.json');
    fs.writeFileSync(outputFile, JSON.stringify(database, null, 2));
    console.log(`\nSchema extracted successfully to ${outputFile}`);
    console.log(`Total tables: ${database.length}`);

    return database;
}

if (require.main === module) {
    try {
        extractSchema();
        process.exit(0);
    } catch (err) {
        console.error('Error:', err.message);
        process.exit(1);
    }
}

module.exports = { extractSchema };

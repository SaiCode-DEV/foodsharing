const { Parser } = require('sql-ddl-to-json-schema');
const fs = require('fs')
const path = require('path');

function generateTableMarkdownHeadingId(tablename) {
    return "table-" + tablename
}

function isPrimaryKeyInSchema(table, column) {
    if (table.primaryKey && table.primaryKey.columns) {
        return table.primaryKey.columns.find((pk) => { return pk.column == column; }) != undefined
    } else {
        return false;
    }
}

function findForeignKeyInSchema(table, column) {
    if (table.foreignKeys) {
        const fkInformation = table.foreignKeys.find((key) => {
            if (key.columns.find((fk) => { return fk.column == column; }) != undefined) {
                return key;
            }
        })
        return fkInformation;
    }
}

function generateForeignKeyMarkdownLink(table, column, prefix = "") {
    return "[" + prefix + "foreign key (" + table + ":" + column + ")](#" + generateTableMarkdownHeadingId(table) + ")"
}

function findColumnInMetaData(tableMetaData, column) {
    let columnMetaData = (tableMetaData && tableMetaData.columns) ? tableMetaData.columns[column.name] : null;
    if (!columnMetaData) {
        columnMetaData = { "description": "" };
    }
    return columnMetaData;
}

function generateColumnTypeIdentiferString(column) {
    let type_string = column.type.datatype;
    if (column.options.unsigned) {
        type_string = "unsigned " + type_string;
    }
    if (column.type.width) {
        type_string += "(" + column.type.width + ")";
    }
    if (column.type.length) {
        type_string += "(" + column.type.length + ")";
    }
    if (column.type.fractional) {
        type_string += "(" + column.type.fractional + ")";
    }
    if (column.options.default) {
        type_string += "=" + column.options.default;
    }
    return type_string
}

function generateColumnProperties(item, column, columnMetaData) {
    let properties = [];
    if (isPrimaryKeyInSchema(item, column.name)) {
        properties.push("Primary Key");
    }
    const fk = findForeignKeyInSchema(item, column.name);
    if (fk) {
        properties.push(generateForeignKeyMarkdownLink(fk.reference.table, fk.reference.columns[0].column));
    } else {
        if (columnMetaData.weakForeignKey) {
            properties.push(generateForeignKeyMarkdownLink(columnMetaData.weakForeignKey.table, columnMetaData.weakForeignKey.column, "Weak-"));
        }
    }
    if (column.options) {
        if (column.options.autoincrement) {
            properties.push("Auto-Increment");
        }
        if (column.options.nullable) {
            properties.push("Nullable");
        }
    }
    return properties
}

function buildMarkdownPhpModuleTableUsageSection(moduleMap, database, metaData) {
    let doc = "\n"
    Object.keys(moduleMap).sort().forEach((modulename) => {
        doc += "### " + modulename + "\n\n";
        doc += generatePlantUmlForPhpModule(database, metaData, moduleMap[modulename]);

        doc += "\n";
        doc += "\n";
        // List modifier tables
        const tables = Object.keys(moduleMap[modulename]).sort();
        tables.forEach((tablename) => {
            const tableUsage = moduleMap[modulename][tablename];
            const heading_id = generateTableMarkdownHeadingId(tablename);
            const usage = Array.from(new Set(tableUsage.map((item) => item.type))).sort().join(", ");
            doc += "  - [" + tablename + "](#" + heading_id + ") (" + usage + ")\n";
        });
    });

    return doc
}

function replaceTypeIdentifierByLinkInDescriptions(description) {
    const identifierRegEx = /(?<id>@(?<module>[A-Z][a-zA-Z]*)::(?<type>[A-Z][a-zA-Z]*))/gm
    let description_output = description
    while ((m = identifierRegEx.exec(description)) !== null) {
        // This is necessary to avoid infinite loops with zero-width matches
        if (m.index === identifierRegEx.lastIndex) {
            identifierRegEx.lastIndex++;
        }

        const groups = m.groups
        const path = "src/Modules/Core/DBConstants/" + groups.module + "/" + groups.type + ".php"
        const gitlabUrl = "https://gitlab.com/foodsharing-dev/foodsharing/-/tree/master/" + path;
        description_output = description_output.replace(groups.id, "[" + groups.id + "](" + gitlabUrl + ")")
    }

    return description_output
}

function loadForeignTablesFromMetadata(databaseMetaData, name) {
    const tableMetaData = databaseMetaData.tables.find((table) => {
        return table.identifier == name;
    })
    if (tableMetaData && ('columns' in tableMetaData)) {
        const fk = Object.keys(tableMetaData.columns).filter((column) => {
            return tableMetaData.columns[column] && tableMetaData.columns[column].weakForeignKey
        })
        return fk.map((column) => tableMetaData.columns[column].weakForeignKey.table)
    } else {
        return [];
    }
}

function findForeignTablesNamesInMetadata(database, databaseMetaData, name) {
    const tables = new Set(loadForeignTablesFromMetadata(databaseMetaData, name));
    const db_tabl = database.find((item) => item.name == name);
    if (db_tabl && 'foreignKeys' in db_tabl) {
        db_tabl.foreignKeys.forEach(relation => {
            tables.add(relation.reference.table)
        });
    }
    return Array.from(tables);
}

function findForeignTablesRelations(database, databaseMetaData, tableName) {
    const tables = new Set();
    const db_tabl = database.find((item) => item.name == tableName);
    if (db_tabl && 'foreignKeys' in db_tabl) {
        db_tabl.foreignKeys.forEach(relation => {
            tables.add(relation)
        });
    }
    // TODO Find foreignkeys in metaData file
    return Array.from(tables);
}

function generatePlantUmlForPhpModule(database, databaseMetaData, module) {
    let usedTables = new Set();
    let relations = new Set();
    Object.keys(module).forEach((table) => {
        const foreignTables = findForeignTablesNamesInMetadata(database, databaseMetaData, table);
        foreignTables.forEach((foreign_table) => { usedTables.add(foreign_table); });

        const new_relations = generatePlantUmlRelationForForeignKey(table, foreignTables);
        Array.from(new_relations).forEach((relation) => relations.add(relation));
    });

    let entities = [];
    usedTables.forEach((tableName) => {
        generatePlantUmlEntityForTable(database, tableName, databaseMetaData, entities);
    });

    if(entities.length > 0) {
        const image = ["```plantuml "];
        image.push(entities.join('\n'));
        image.push(Array.from(relations).join('\n'));
        image.push("```");
        return image.join("\n");
    }
    return ""
}


function generatePlantUmlRelationForForeignKey(table, foreignTables) {
    let relations = new Set();
    foreignTables.forEach((foreign_table) => {
        relations.add(table + " ||..o| " + foreign_table);
    });
    return relations;
}

function generatePlantUmlEntityForTable(database, tableName, databaseMetaData, entities) {
    const db_table = database.find((item) => item.name == tableName);
    let entity = ["entity " + tableName + " {"];

    // primary key
    if (db_table && db_table.primaryKey) {
        db_table.primaryKey.columns.forEach((column) => {
            entity.push("* " + column.column);
        });
    }

    // foreign keys
    entity.push("--");
    const tableRelations = findForeignTablesRelations(database, databaseMetaData, tableName);
    if (tableRelations) {
        const fkColumns = new Set();
        tableRelations.forEach((relation) => {
            if (relation.columns) {
                relation.columns.forEach(column => fkColumns.add(column.column));
            }
        });
        Array.from(fkColumns).forEach(column => { entity.push(column + " <FK>"); });
    }

    entity.push("}");
    entity.push("");
    entities.push(entity.join("\n"));
}

function buildMarkdownTableDescription(item, metaData) {
    let doc = "\n";
    doc += "## Table " + item.name + "\n";
    doc += "\n";
    doc += "### Description - " + item.name + "\n";
    const tableMetaData = metaData.tables.find((table) => {
        return table.identifier == item.name;
    });
    if (tableMetaData) {
        if (tableMetaData.description) {
            // TODO Load description from database
            doc += "\n" + tableMetaData.description + "\n";
        }
        if (tableMetaData.todos) {
            doc += "\n";
            doc += "### Open todos from old documentation - " + item.name + "\n\n";
            tableMetaData.todos.forEach((todo) => {
                doc += "- " + todo + "\n";
            });
        }
    }

    doc += "\n";
    doc += "### Table columns - " + item.name + "\n\n";
    doc += "Column | Description | Type | properties\n";
    doc += "------ |-------------|------|--------\n";
    item.columns.forEach((column) => {
        const columnMetaData = findColumnInMetaData(tableMetaData, column);

        const raw_description = selectColumnDescription(columnMetaData, column);
        const description = replaceTypeIdentifierByLinkInDescriptions(raw_description)

        const column_name = isPrimaryKeyInSchema(item, column.name) ? "**" + column.name + "**" : column.name;

        const type_string = generateColumnTypeIdentiferString(column);

        const properties = generateColumnProperties(item, column, columnMetaData);
        const properties_string = (" " + properties.join(", ")).trimEnd();

        doc += column_name + " | " + description + " |" + type_string + " |" + properties_string + "\n";
    });

    return doc;
}

function selectColumnDescription(columnMetaData, column) {
    let raw_description = columnMetaData.description ? columnMetaData.description : "";
    if (column.options) {
        if (column.options.comment) {
            raw_description = column.options.comment;
        }
    }
    return raw_description;
}

function buildMarkdownDocument(database, metaData, moduleMap) {
    let doc = "# Database structure\n"
    doc += "\n"
    doc += "This page is automatically generated and can be manually generated with [database scripts](../../deployment/scripts#database-scripts)."
    doc += "\n"

    doc += "## Introduction\n"
    doc += "\n"
    doc += metaData.introduction.join("\n")
    doc += "\n"
    doc += "- [List of tables](#list-of-tables)\n"
    doc += "- [Structure of tables](#structure-of-tables)\n"
    doc += "- [Usage of table in PHP Modules](#usage-of-table-in-php-modules)\n"
    doc += "\n"

    doc += "## List of tables\n"
    doc += "\n"

    const tables = database.sort((a, b) => a.name < b.name)
    tables.forEach((table) => {
        heading_id = generateTableMarkdownHeadingId(table.name)
        doc += "- [" + table.name + "](#" + heading_id + ")\n"
    })
    doc += "\n"

    doc += "## Structure of tables\n"
    tables.forEach((item) => {
        doc += buildMarkdownTableDescription(item, metaData);
    })

    doc += "\n"
    doc += "## Usage of table in PHP Modules\n"
    doc += buildMarkdownPhpModuleTableUsageSection(moduleMap, database, metaData);

    return doc
}

function preprocessSqlDump(sqlDump) {
    // Regular expressions to remove parts that the parser doesn't understand
    const removals = [
        /GENERATED [A-Z ]+\(.*?\) VIRTUAL/g,
        /DELIMITER ;;[\s\S]*DELIMITER ;/g,
    ]
    for(let regex of removals) {
        sqlDump = sqlDump.replace(regex, '');
    }
    return sqlDump
}

const buildDir = process.argv[2]
const resultDir = process.argv[3]
const sqlDumpFile = process.argv[4]
const dbMetaDataFile = process.argv[5]
const dbUsageFile = process.argv[6]

console.log("")
console.log("Generate database documentation")
console.log("---------------------------------------")
console.log("Build folder" + buildDir)
console.log("Result folder" + resultDir)
console.log("SQL dump file " + sqlDumpFile)
console.log("DB Meta data file " + dbMetaDataFile)
console.log("DB PHP Usage file " + dbUsageFile)

// Read schema sql dump file
const raw_sql = fs.readFileSync(sqlDumpFile, { encoding: 'utf8', flag: 'r' });
const preprocessed_sql = preprocessSqlDump(raw_sql)

// Remove comments from SQL Commands
var lines = preprocessed_sql.split('\n');
var sql = lines.filter(function (line) {
    return !(line.startsWith('--') || line.startsWith("/*"));
}).join("\n");

// Translate SQL commands to json tables
const parser = new Parser('mysql');
const database_schema = parser.feed(sql).toCompactJson(parser.results);

// Backup json tables
try {
    fs.writeFileSync(path.join(buildDir, 'schema.json'), JSON.stringify(database_schema, null, 2));
} catch (err) {
    console.error(err);
    return false;
}

let lastReadFileName = ""
try {
    lastReadFileName = dbMetaDataFile
    const databaseMetaDatadata = JSON.parse(fs.readFileSync(dbMetaDataFile, { encoding: 'utf8', flag: 'r' }));

    lastReadFileName = dbUsageFile
    const moduleMap = JSON.parse(fs.readFileSync(dbUsageFile, { encoding: 'utf8', flag: 'r' }));

    // Create markdown tables
    const doc = buildMarkdownDocument(database_schema, databaseMetaDatadata, moduleMap)
    const outputFile = path.join(resultDir, 'database-tables-columns.md')

    try {
        fs.writeFileSync(outputFile, doc);
        console.log("Generated '" + outputFile + "'")
    } catch (err) {
        console.error(err);
        return;
    }

    return true

} catch (err) {
    console.error("Error during reading of" + lastReadFileName, err)
    return false
}

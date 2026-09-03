import sqlite3
import os

db_path = r'c:\Users\pavan\kanboard-hr\data\db.sqlite'
output_sql = r'c:\Users\pavan\kanboard-hr\database\seed_data.sql'

conn = sqlite3.connect(db_path)
c = conn.cursor()

tables = [
    'users',
    'projects',
    'columns',
    'project_has_users',
    'tasks',
    'subtasks',
    'comments',
    'tags',
    'task_has_tags'
]

with open(output_sql, 'w', encoding='utf-8') as f:
    f.write("-- Production Database Dump Script --\n")
    f.write("-- Generated for Kanboard Task Management --\n\n")

    for table in tables:
        try:
            rows = c.execute(f"SELECT * FROM {table}").fetchall()
            if not rows:
                continue

            # Get column names
            col_names = [description[0] for description in c.description]
            cols_str = ", ".join([f"`{col}`" for col in col_names])

            f.write(f"-- Data for table `{table}` --\n")
            for row in rows:
                vals = []
                for val in row:
                    if val is None:
                        vals.append("NULL")
                    elif isinstance(val, (int, float)):
                        vals.append(str(val))
                    else:
                        escaped = str(val).replace("'", "''").replace("\\", "\\\\")
                        vals.append(f"'{escaped}'")
                vals_str = ", ".join(vals)
                f.write(f"INSERT INTO `{table}` ({cols_str}) VALUES ({vals_str});\n")
            f.write("\n")
        except Exception as e:
            print(f"Error dumping table {table}: {e}")

conn.close()
print(f"Successfully generated seed data SQL script at: {output_sql}")

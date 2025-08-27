import pyodbc
import csv
import os
import datetime
import schedule
import time

def export_insumos():
    # Establecer la conexión a SQL Server
    conn = pyodbc.connect(
        f'DRIVER={{ODBC Driver 17 for SQL Server}};SERVER=PA-S1-DATA\\UCQNDATA;DATABASE=recep_tec;UID=sadumesm;PWD=Dumes100%'
    )

    # Crear un cursor
    cursor = conn.cursor()

    # Ejecutar el query
    query = "SELECT * FROM insumos"
    cursor.execute(query)

    # Obtener los nombres de las columnas
    columns = [column[0] for column in cursor.description]

    # Obtener la fecha y hora actual para el nombre del archivo
    fecha_actual = datetime.datetime.now().strftime("%Y-%m-%d_%H-%M-%S")

    # Especificar el archivo CSV de salida
    output_csv = f'C:/backups/insumo_{fecha_actual}.csv'
    output_sql = f'C:/backups/insumo_{fecha_actual}.sql'

    # Escribir los resultados en un archivo CSV
    with open(output_csv, mode='w', newline='', encoding='utf-8') as file:
        writer = csv.writer(file)
        writer.writerow(columns)  # Escribir los nombres de las columnas
        for row in cursor:
            writer.writerow(row)  # Escribir los datos

    # Escribir la consulta SQL en un archivo .sql
    with open(output_sql, mode='w', encoding='utf-8') as sql_file:
        sql_file.write(f"-- Query executed on {fecha_actual}\n")
        sql_file.write(query)

    # Cerrar la conexión
    cursor.close()
    conn.close()

    print(f'Los datos han sido exportados a {output_csv}')
    print(f'La consulta SQL ha sido guardada en {output_sql}')


# Ejecutar la primera vez de inmediato
export_insumos()

# Programar la ejecución cada 12 horas
schedule.every(12).hours.do(export_insumos)

# Mantener el script ejecutándose
while True:
    schedule.run_pending()
    time.sleep(1)  # Dormir un segundo para no sobrecargar el CPU

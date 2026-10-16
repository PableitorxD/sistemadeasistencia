const express = require('express');
const cors = require('cors');
const sqlite3 = require('sqlite3').verbose();
const xlsx = require('xlsx');
const fileUpload = require('express-fileupload'); // Integración de subidas de archivos

const app = express();
const allowedOrigins = process.env.FRONTEND_ORIGINS
    ? process.env.FRONTEND_ORIGINS.split(',').map((origin) => origin.trim()).filter(Boolean)
    : '*';

app.use(cors({ origin: allowedOrigins }));
app.use(express.json());
app.use(fileUpload()); // Middleware para capturar archivos subidos dinámicamente

// 1. Conexión a la Base de Datos SQLite (Persistencia RF-14)
const db = new sqlite3.Database('./asistencia.db', (err) => {
    if (err) console.error(err.message);
    console.log('Conectado a la base de datos SQLite.');
});

// 2. Creación del Modelo de Datos e Historiales (RF-15 y RN-08)
db.serialize(() => {
    db.run(`CREATE TABLE IF NOT EXISTS modulos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT,
        activo INTEGER DEFAULT 1
    )`);

    db.run(`CREATE TABLE IF NOT EXISTS estudiantes (
        registro TEXT,
        nombre TEXT,
        correo TEXT,
        modulo_id INTEGER,
        PRIMARY KEY (registro, modulo_id)
    )`);

    db.run(`CREATE TABLE IF NOT EXISTS fechas_clase (
        fecha TEXT,
        hora_inicio TEXT,
        hora_fin TEXT,
        modulo_id INTEGER,
        PRIMARY KEY (fecha, modulo_id)
    )`);

    db.run(`CREATE TABLE IF NOT EXISTS asistencias (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        registro TEXT,
        fecha TEXT,
        hora_marca TEXT,
        estado TEXT,
        modulo_id INTEGER
    )`);

    db.run(`CREATE TABLE IF NOT EXISTS auditoria (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario TEXT,
        accion TEXT,
        fecha TEXT,
        hora TEXT,
        registro_afectado TEXT
    )`);

    // Módulo inicial automatizado con formato estricto de fechas
    db.get(`SELECT id FROM modulos WHERE activo = 1`, (err, row) => {
        if (!row) {
            db.run(`INSERT INTO modulos (nombre, activo) VALUES ('Módulo Inicial Octubre 2026', 1)`, function() {
                const mid = this.lastID;
                // Se eliminó el estudiante por defecto
                db.run(`INSERT OR IGNORE INTO fechas_clase VALUES ('13/10/2026', '18:00', '21:00', ?)`, [mid]);
                db.run(`INSERT OR IGNORE INTO fechas_clase VALUES ('14/10/2026', '18:00', '21:00', ?)`, [mid]);
            });
        }
    });
});

// Formateador estricto para evitar discrepancies de fechas según la región de Windows
function obtenerFechaEstricta() {
    const hoy = new Date();
    const dia = String(hoy.getDate()).padStart(2, '0');
    const mes = String(hoy.getMonth() + 1).padStart(2, '0');
    const anio = hoy.getFullYear();
    return `${dia}/${mes}/${anio}`;
}

function registrarAuditoria(usuario, accion, afectado) {
    const fechaFija = obtenerFechaEstricta();
    const hora = new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    db.run(`INSERT INTO auditoria (usuario, accion, fecha, hora, registro_afectado) VALUES (?, ?, ?, ?, ?)`,
        [usuario, accion, fechaFija, hora, afectado]);
}

// RF-01. Ingreso de Estudiantes
app.post('/api/login', (req, res) => {
    const { login } = req.body;
    db.get(`SELECT e.* FROM estudiantes e JOIN modulos m ON e.modulo_id = m.id 
            WHERE m.activo = 1 AND (e.correo = ? OR e.registro = ?)`, [login, login], (err, row) => {
        if (err) return res.status(500).json({ error: err.message });
        if (!row) return res.status(404).json({ error: 'Estudiante no encontrado o inactivo' });
        registrarAuditoria(row.registro, 'Inicio de Sesión', row.registro);
        res.json({ estudiante: row });
    });
});

// RF-03. Registrar Asistencia
app.post('/api/marcar', (req, res) => {
    const { registro, fecha, hora } = req.body;
    db.get(`SELECT id FROM modulos WHERE activo = 1`, (err, mod) => {
        if (!mod) return res.status(400).json({ error: 'No hay módulo activo' });
        db.get(`SELECT * FROM fechas_clase WHERE fecha = ? AND modulo_id = ?`, [fecha, mod.id], (err, clase) => {
            if (!clase) return res.status(400).json({ error: 'Hoy no es día de clase programada' });
            db.get(`SELECT * FROM asistencias WHERE registro = ? AND fecha = ? AND modulo_id = ?`, [registro, fecha, mod.id], (err, marca) => {
                if (marca) return res.status(400).json({ error: 'Ya registraste tu asistencia hoy' });
                db.run(`INSERT INTO asistencias (registro, fecha, hora_marca, estado, modulo_id) VALUES (?, ?, ?, 'PUNTUAL', ?)`, 
                [registro, fecha, hora, mod.id], (err) => {
                    if (err) return res.status(500).json({ error: err.message });
                    registrarAuditoria(registro, 'Marcó asistencia manual', registro);
                    res.json({ mensaje: 'Asistencia registrada con éxito' });
                });
            });
        });
    });
});

app.get('/api/historial/:registro', (req, res) => {
    db.all(`SELECT a.* FROM asistencias a JOIN modulos m ON a.modulo_id = m.id WHERE m.activo = 1 AND a.registro = ?`, [req.params.registro], (err, rows) => {
        if (err) return res.status(500).json({ error: err.message });
        res.json(rows);
    });
});

// RF-11 y RF-12. Importador Dinámico desde Memoria mediante Archivos Subidos
app.post('/api/importar-estudiantes', (req, res) => {
    try {
        if (!req.files || !req.files.archivoAlumnos || !req.files.archivoFechas) {
            return res.status(400).json({ error: 'Falta seleccionar alguno de los dos archivos Excel.' });
        }

        db.get(`SELECT id FROM modulos WHERE activo = 1`, (err, mod) => {
            if (!mod) return res.status(400).json({ error: 'No hay módulo activo' });

            // 1. Procesar binario de Alumnos desde la memoria RAM
            const archivoAlumnos = req.files.archivoAlumnos;
            const wbAlumnos = xlsx.read(archivoAlumnos.data, { type: 'buffer' });
            const primeraHojaAlumnos = wbAlumnos.SheetNames[0];
            const datosEstudiantes = xlsx.utils.sheet_to_json(wbAlumnos.Sheets[primeraHojaAlumnos]);
            
            const stmtEst = db.prepare(`INSERT OR IGNORE INTO estudiantes (registro, nombre, correo, modulo_id) VALUES (?, ?, ?, ?)`);
            datosEstudiantes.forEach(alumno => {
                const reg = alumno['Número de registro'] || alumno['registro'];
                const nom = alumno['Nombre completo'] || alumno['nombre'];
                const cor = alumno['Correo electrónico'] || alumno['correo'];
                if (reg && nom && cor) stmtEst.run(String(reg), nom, cor, mod.id);
            });
            stmtEst.finalize();
            registrarAuditoria('Administrador', 'Importó listado de estudiantes dinámico', 'Tabla Estudiantes');

            // 2. Procesar binario de Calendario de Fechas desde la memoria RAM
            const archivoFechas = req.files.archivoFechas;
            const wbFechas = xlsx.read(archivoFechas.data, { type: 'buffer' });
            const primeraHojaFechas = wbFechas.SheetNames[0];
            const datosFechas = xlsx.utils.sheet_to_json(wbFechas.Sheets[primeraHojaFechas], { raw: false });
            
            const stmtFecha = db.prepare(`INSERT OR IGNORE INTO fechas_clase (fecha, hora_inicio, hora_fin, modulo_id) VALUES (?, ?, ?, ?)`);
            datosFechas.forEach(clase => {
                const fec = clase['Fecha'] || clase['fecha'];
                const ini = clase['Hora inicio'] || clase['hora_inicio'];
                const fin = clase['Hora fin'] || clase['hora_fin'];
                if (fec && ini && fin) stmtFecha.run(String(fec), String(ini), String(fin), mod.id);
            });
            stmtFecha.finalize();
            registrarAuditoria('Administrador', 'Importó calendario de fechas dinámico', 'Tabla Fechas Clase');
            
            res.json({ mensaje: 'Archivos Excel cargados e importados al sistema con éxito.' });
        });
    } catch (error) {
        res.status(500).json({ error: 'Error al procesar la lectura de los binarios Excel enviados.' });
    }
});

// RF-04 y RF-08: Matriz relacional cruzada completa (Muestra todos los estudiantes en todas las fechas)
app.get('/api/admin/asistencias', (req, res) => {
    const sql = `
        SELECT 
            e.registro,
            e.nombre,
            f.fecha,
            COALESCE(a.hora_marca, 'Sin registro') AS hora_marca,
            COALESCE(a.estado, 'FALTA') AS estado
        FROM estudiantes e
        CROSS JOIN fechas_clase f ON e.modulo_id = f.modulo_id
        JOIN modulos m ON e.modulo_id = m.id
        LEFT JOIN asistencias a ON e.registro = a.registro AND f.fecha = a.fecha AND e.modulo_id = a.modulo_id
        WHERE m.activo = 1
        ORDER BY f.fecha DESC, e.nombre ASC
    `;
    db.all(sql, [], (err, rows) => {
        if (err) return res.status(500).json({ error: err.message });
        res.json(rows);
    });
});

// RF-04 y RF-07: Permite modificar o forzar una asistencia manual fuera de fecha
app.post('/api/admin/cambiar-asistencia', (req, res) => {
    const { registro, fecha, nuevoEstado } = req.body;
    db.get(`SELECT id FROM modulos WHERE activo = 1`, (err, mod) => {
        if (!mod) return res.status(400).json({ error: 'No hay módulo activo' });
        
        db.get(`SELECT * FROM asistencias WHERE registro = ? AND fecha = ? AND modulo_id = ?`, [registro, fecha, mod.id], (err, row) => {
            if (row) {
                db.run(`UPDATE asistencias SET estado = ? WHERE registro = ? AND fecha = ? AND modulo_id = ?`,
                    [nuevoEstado, registro, fecha, mod.id], () => {
                        registrarAuditoria('Administrador', `Corrigió asistencia fuera de fecha a ${nuevoEstado}`, registro);
                        res.json({ mensaje: 'Asistencia modificada con éxito.' });
                    });
            } else {                const horaActual = new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
                db.run(`INSERT INTO asistencias (registro, fecha, hora_marca, estado, modulo_id) VALUES (?, ?, ?, ?, ?)`,
                    [registro, fecha, horaActual, nuevoEstado, mod.id], () => {
                        registrarAuditoria('Administrador', `Corrigió falta e ingresó asistencia como ${nuevoEstado}`, registro);
                        res.json({ mensaje: 'Asistencia guardada con éxito.' });
                    });
            }
        });
    });
});

// RF-13: Reiniciar Módulo Relacional Estructurado
app.post('/api/admin/reiniciar-modulo', (req, res) => {
    const { nuevoNombre } = req.body;
    db.run(`UPDATE modulos SET activo = 0 WHERE activo = 1`, (err) => {
        db.run(`INSERT INTO modulos (nombre, activo) VALUES (?, 1)`, [nuevoNombre || 'Nuevo Módulo Académico'], function() {
            const mid = this.lastID;
            
            // Calendario base de prueba para el nuevo ciclo operativo
            db.run(`INSERT OR IGNORE INTO fechas_clase VALUES ('14/10/2026', '18:00', '21:00', ?)`, [mid]);
            
            registrarAuditoria('Administrador', 'Reinició el sistema y activó un nuevo módulo', nuevoNombre || 'Nuevo Módulo Académico');
            res.json({ mensaje: 'Sistema reiniciado. Módulo histórico archivado correctamente.' });
        });
    });
});

app.get('/api/admin/auditoria', (req, res) => {
    db.all(`SELECT * FROM auditoria ORDER BY id DESC`, [], (err, rows) => {
        if (err) return res.status(500).json({ error: err.message });
        res.json(rows);
    });
});

app.get('/api/modulo-activo', (req, res) => {
    db.get(`SELECT nombre FROM modulos WHERE activo = 1`, [], (err, row) => {
        if (err) return res.status(500).json({ error: err.message });
        res.json({ nombre: row ? row.nombre : 'No hay ningún módulo activo' });
    });
});

app.get('/api/admin/estudiantes', (req, res) => {
    db.all(`SELECT e.* FROM estudiantes e JOIN modulos m ON e.modulo_id = m.id WHERE m.activo = 1`, [], (err, rows) => {
        if (err) return res.status(500).json({ error: err.message });
        res.json(rows);
    });
});

const port = Number(process.env.PORT) || 3000;
app.listen(port, '0.0.0.0', () => console.log(`Servidor backend corriendo en el puerto ${port}`));

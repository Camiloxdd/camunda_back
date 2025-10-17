import express from "express";
import cors from "cors";
import bodyParser from "body-parser";
import mysql from "mysql2/promise";
import fs from "fs";
import dotenv from 'dotenv';
import path from "path";
import ExcelJS from "exceljs";
import axios from 'axios';
import bcrypt from 'bcrypt';
import jwt from 'jsonwebtoken';
import cookieParser from "cookie-parser";
import { fileURLToPath } from "url";
dotenv.config();
const port = process.env.PORT || 4000;
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
app.use(express.json());
app.use(bodyParser.json());
app.use(cookieParser());

const ZEEBE_AUTHORIZATION_SERVER_URL = 'https://login.cloud.camunda.io/oauth/token';
const ZEEBE_CLIENT_ID = 'nvbxHeJMHshyETuNQ7napSEjO1ZVp0Mh';
const ZEEBE_CLIENT_SECRET = '25rj2O8VEIiVH6MlQcbkn2RJr22tbUGeUzYpbX1tTSLmYUw-xXa-g3cSTfqLabPm';
const CAMUNDA_TASKLIST_BASE_URL = 'https://jfk-1.tasklist.camunda.io/9678cfa5-01e6-43a6-9d0d-b418d1a331b7';
const AUDIENCE = 'tasklist.camunda.io';
const CAMUNDA_ZEEBE_URL = 'https://jfk-1.zeebe.camunda.io/9678cfa5-01e6-43a6-9d0d-b418d1a331b7'

const FRONTEND_URL = 'http://localhost:3000'

app.use(cors({
  origin: FRONTEND_URL,
  methods: ["GET", "POST", "PUT", "DELETE", "PATCH"],
  allowedHeaders: ["Content-Type", "Authorization"],
  credentials: true
}));

const pool = mysql.createPool({
  host: "127.0.0.1",
  user: "root",
  password: "",
  database: "requisiciones",
  port: 3306
});

function createJwt(payload) {
  return jwt.sign(payload, process.env.JWT_SECRET, { expiresIn: '1h' });
}

async function authMiddleware(req, res, next) {
  try {
    const token = req.cookies?.token;
    if (!token) return res.status(401).json({ message: 'No token' });

    const decoded = jwt.verify(token, process.env.JWT_SECRET);
    req.user = decoded; // por ejemplo { id, email }
    next();
  } catch (err) {
    return res.status(401).json({ message: 'Invalid token' });
  }
}

async function getAccessToken() {
  const params = new URLSearchParams();
  params.append('grant_type', 'client_credentials');
  params.append('audience', AUDIENCE);
  params.append('client_id', ZEEBE_CLIENT_ID);
  params.append('client_secret', ZEEBE_CLIENT_SECRET);

  const response = await axios.post(ZEEBE_AUTHORIZATION_SERVER_URL, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
  });
  return response.data.access_token;
}

//LOGIN
app.post('/api/auth/login', async (req, res) => {
  const { correo, contraseña } = req.body;
  if (!correo || !contraseña) return res.status(400).json({ message: 'Email and password required' });

  try {
    const [rows] = await pool.execute('SELECT id, correo, contraseña, nombre FROM user WHERE correo = ?', [correo]);
    const user = rows[0];
    if (!user) return res.status(401).json({ message: 'Invalid credentials' });

    const match = await bcrypt.compare(contraseña, user.contraseña);
    if (!match) return res.status(401).json({ message: 'Invalid credentials' });

    const token = createJwt({ id: user.id, email: user.correo });

    // Set cookie HttpOnly
    const cookieOptions = {
      httpOnly: true,
      sameSite: 'lax', // puedes usar 'strict' o 'none' dependiendo de tu deploy y dominio
      secure: process.env.NODE_ENV === 'production', // solo en https
      maxAge: 60 * 60 * 1000 // 1 hora en ms (coincide con expiresIn)
    };

    res.cookie('token', token, cookieOptions);
    // Opcional: no devolver token en body, pero podemos devolver datos de usuario sin password
    res.json({ id: user.id, email: user.correo, name: user.nombre });
  } catch (err) {
    console.error(err);
    res.status(500).json({ message: 'Server error' });
  }
});

// Ruta: logout (borra cookie)
app.post('/api/auth/logout', (req, res) => {
  res.clearCookie('token', { httpOnly: true, sameSite: 'lax', secure: process.env.NODE_ENV === 'production' });
  res.json({ ok: true });
});

// Ruta: obtener usuario actual
app.get('/api/auth/me', authMiddleware, async (req, res) => {
  // req.user fue seteado por authMiddleware
  try {
    const [rows] = await pool.execute('SELECT id, correo, nombre, cargo FROM user WHERE id = ?', [req.user.id]);
    const user = rows[0];
    if (!user) return res.status(404).json({ message: 'User not found' });
    res.json(user);
  } catch (err) {
    console.error(err);
    res.status(500).json({ message: 'Server error' });
  }
});

//USUARIOS EDITABLES
app.get('/api/user/list', authMiddleware, async (req, res) => {
  try {
    const [rows] = await pool.query("SELECT * FROM user");

    if (rows.length === 0)
      return res.status(404).json({ message: 'No hay usuarios registrados' });

    res.json(rows);
  } catch (err) {
    console.error(err);
    res.status(500).json({ message: 'Server error' });
  }
});



//CREAR USUARIO
app.post("/api/user/create", authMiddleware, async (req, res) => {
  try {
    const { nombre, correo, contraseña, cargo, telefono, area, sede, super_admin, aprobador, solicitante, comprador, } = req.body;

    if (!nombre || !correo || !contraseña) {
      return res.status(400).json({ message: "Faltan campos obligatorios." });
    }

    const hash = await bcrypt.hash(contraseña, 10);

    const [result] = await pool.execute(
      "INSERT INTO user (nombre, correo, contraseña, cargo, telefono, area, sede, super_admin, aprobador, solicitante, comprador) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
      [nombre, correo, hash, cargo, telefono, area, sede, super_admin ? 1 : 0, aprobador ? 1 : 0, solicitante ? 1 : 0, comprador ? 1 : 0]
    );

    res.status(201).json({ message: "Usuario creado correctamente", id: result.insertId });
  } catch (err) {
    console.error(err);
    res.status(500).json({ message: "Error en el servidor" });
  }
});

//EDITAR USUARIO
app.put('/api/user/update/:id', authMiddleware, async (req, res) => {
  const { id } = req.params;
  const { nombre, correo, cargo, telefono, sede, area, super_admin, aprobador, solicitante, comprador } = req.body;

  try {
    const [result] = await pool.query(
      `UPDATE user SET nombre=?, correo=?, cargo=?, telefono=?, sede=?, area=?, 
        super_admin=?, aprobador=?, solicitante=?, comprador=? WHERE id=?`,
      [nombre, correo, cargo, telefono, sede, area, super_admin, aprobador, solicitante, comprador, id]
    );

    if (result.affectedRows === 0)
      return res.status(404).json({ message: 'Usuario no encontrado' });

    res.json({ message: 'Usuario actualizado correctamente' });
  } catch (err) {
    console.error(err);
    res.status(500).json({ message: 'Error del servidor' });
  }
});

//ELIMINAR USUARIO
app.delete("/api/user/delete/:id", authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;
    const [result] = await pool.query("DELETE FROM user WHERE id = ?", [id]);

    if (result.affectedRows === 0)
      return res.status(404).json({ message: "Usuario no encontrado" });

    res.json({ message: "Usuario eliminado correctamente" });
  } catch (err) {
    console.error(err);
    res.status(500).json({ message: "Error del servidor" });
  }
});

//CREAR REQUISICION
app.post("/api/requisicion/create", async (req, res) => {
  try {
    const { solicitante, productos } = req.body;

    if (!solicitante || !productos?.length) {
      return res.status(400).json({ message: "Datos incompletos en la solicitud" });
    }

    const {
      nombre,
      fecha,
      justificacion,
      area,
      sede,
      urgencia,
      presupuestada,
    } = solicitante;

    // Calcular valor total
    const valorTotal = productos.reduce(
      (acc, p) => acc + (parseFloat(p.valorEstimado) || 0),
      0
    );

    // Guardar requisición principal
    const [reqResult] = await pool.query(
      `INSERT INTO requisiciones 
       (nombre_solicitante, fecha, justificacion, area, sede, urgencia, presupuestada, valor_total) 
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [nombre, fecha, justificacion, area, sede, urgencia, presupuestada, valorTotal]
    );

    const requisicionId = reqResult.insertId;

    // Guardar productos
    const valuesProductos = productos.map((p) => [
      requisicionId,
      p.nombre,
      p.cantidad,
      p.descripcion,
      p.compraTecnologica,
      p.ergonomico,
      p.valorEstimado,
      p.centroCosto,
      p.cuentaContable,
    ]);

    await pool.query(
      `INSERT INTO requisicion_productos 
       (requisicion_id, nombre, cantidad, descripcion, compra_tecnologica, ergonomico, valor_estimado, centro_costo, cuenta_contable)
       VALUES ?`,
      [valuesProductos]
    );

    // === LÓGICA DE APROBACIONES ===
    const SMLV = 1300000; // (aprox 2025)
    const limite = SMLV * 10;
    const requiereAprobacionesAltas = valorTotal >= limite;

    const tieneErgonomico = productos.some((p) => p.ergonomico);
    const tieneTecnologico = productos.some((p) => p.compraTecnologica);

    // Roles esperados según tipo de requisición
    const roles = [];

    if (tieneTecnologico) {
      roles.push({ area: "TyP", rol: "dicTYP" }, { area: "TyP", rol: "gerTyC" });
    }

    if (tieneErgonomico) {
      roles.push({ area: "SST", rol: "dicSST" }, { area: "SST", rol: "gerSST" });
    }

    if (requiereAprobacionesAltas) {
      roles.push({ area: "GerenciaAdmin", rol: "gerAdmin" });
      roles.push({ area: "GerenciaGeneral", rol: "gerGeneral" });
    }

    // Buscar nombres reales en la tabla de usuarios
    const aprobaciones = [];
    for (const { area, rol } of roles) {
      const [rows] = await pool.query(
        "SELECT nombre FROM user WHERE cargo = ? LIMIT 1",
        [rol]
      );

      aprobaciones.push({
        area,
        rol_aprobador: rol,
        nombre_aprobador: rows[0]?.nombre || "Sin asignar",
      });
    }

    // Insertar aprobaciones
    if (aprobaciones.length > 0) {
      const valuesAprobaciones = aprobaciones.map((a) => [
        requisicionId,
        a.area,
        a.rol_aprobador,
        a.nombre_aprobador,
      ]);

      await pool.query(
        `INSERT INTO requisicion_aprobaciones 
         (requisicion_id, area, rol_aprobador, nombre_aprobador)
         VALUES ?`,
        [valuesAprobaciones]
      );
    }

    res.status(201).json({
      message: "✅ Requisición creada correctamente",
      id: requisicionId,
      aprobaciones,
    });
  } catch (error) {
    console.error("❌ Error al crear la requisición:", error);
    res.status(500).json({ message: "Error al crear la requisición" });
  }
});

app.get("/api/requisiciones/pendientes", authMiddleware, async (req, res) => {
  try {
    const { id, cargo, nombre, area } = req.user; // viene del token (middleware)

    // Si no es aprobador, no puede ver nada
    const rolesAprobadores = [
      "dicTYP",
      "gerTyC",
      "dicSST",
      "gerSST",
      "gerAdmin",
      "managerGeneral",
    ];

    if (!rolesAprobadores.includes(cargo)) {
      return res.status(403).json({ message: "No autorizado para aprobar requisiciones" });
    }

    // Buscar las requisiciones donde el usuario sea el aprobador correspondiente
    const [requisiciones] = await pool.query(
      `
      SELECT 
        r.id AS requisicion_id,
        r.nombre_solicitante,
        r.fecha,
        r.justificacion,
        r.area,
        r.sede,
        r.urgencia,
        r.presupuestada,
        r.valor_total,
        a.id AS aprobacion_id,
        a.area AS area_aprobacion,
        a.rol_aprobador,
        a.nombre_aprobador,
        a.estado AS estado_aprobacion
      FROM requisiciones r
      INNER JOIN requisicion_aprobaciones a ON r.id = a.requisicion_id
      WHERE a.nombre_aprobador = ? 
        AND a.estado = 'pendiente'
      ORDER BY r.fecha DESC
      `,
      [nombre]
    );

    // Si no tiene requisiciones pendientes
    if (requisiciones.length === 0) {
      return res.json([]);
    }

    // Traer los productos asociados a cada requisición
    const ids = requisiciones.map(r => r.requisicion_id);
    const [productos] = await pool.query(
      `
      SELECT 
        id AS producto_id,
        requisicion_id,
        nombre,
        descripcion,
        cantidad,
        valor_estimado,
        compra_tecnologica,
        ergonomico,
        aprobado
      FROM requisicion_productos
      WHERE requisicion_id IN (?)
      `,
      [ids]
    );

    // Agrupar los productos dentro de cada requisición
    const resultado = requisiciones.map(req => ({
      ...req,
      productos: productos.filter(p => p.requisicion_id === req.requisicion_id),
    }));

    res.json(resultado);
  } catch (error) {
    console.error("❌ Error al obtener requisiciones pendientes:", error);
    res.status(500).json({ message: "Error al obtener requisiciones pendientes" });
  }
});


//FORMULARIOS
app.put("/formularios/:id", async (req, res) => {
  const { id } = req.params;
  const { form, filas } = req.body;

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    await conn.query(
      `UPDATE formularios SET
        nombre = ?, fechaSolicitud = ?, fechaEntrega = ?, justificacion = ?, area = ?, sede = ?, urgenciaCompra = ?, tiempoGestion = ?, anexos = ?,
        nombreSolicitante = ?, firmaSolicitante = ?, nombreAdministrativo = ?, firmaAdministrativo = ?, nombreGerente = ?, firmaGerente = ?,
        autorizacionGerencia = ?, firmaCompras = ?
      WHERE id = ?`,
      [
        form.nombre,
        form.fechaSolicitud,
        form.fechaEntrega,
        form.justificacion,
        form.area,
        form.sede,
        form.urgenciaCompra,
        form.tiempoGestion,
        form.anexos,
        form.nombreSolicitante,
        form.firmaSolicitante,
        form.nombreAdministrativo,
        form.firmaAdministrativo,
        form.nombreGerente,
        form.firmaGerente,
        form.autorizacionGerencia,
        form.firmaCompras,
        id
      ]
    );

    await conn.query("DELETE FROM items_formulario WHERE formulario_id = ?", [id]);

    for (const fila of filas) {
      await conn.query(
        `INSERT INTO items_formulario (
          formulario_id, productoOServicio, cantidad, centro, cuenta, purchaseAprobated, valor, descripcion, vobo, sstAprobacion
        ) VALUES (?,?,?,?,?,?,?,?,?,?)`,
        [
          id,
          fila.productoOServicio,
          fila.cantidad,
          fila.centro,
          fila.cuenta,
          fila.purchaseAprobated,
          fila.valor,
          fila.descripcion,
          fila.vobo,
          fila.sstAprobacion
        ]
      );
    }

    await conn.commit();
    res.json({ success: true });
  } catch (error) {
    await conn.rollback();
    console.error("❌ Error al actualizar:", error);
    res.status(500).json({ success: false, error: error.message });
  } finally {
    conn.release();
  }
});

app.post("/formularios", async (req, res) => {
  const { form, filas } = req.body;

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    const [result] = await conn.query(
      `INSERT INTO formularios (
        nombre, fechaSolicitud, fechaEntrega, justificacion, area, sede, urgenciaCompra, tiempoGestion, anexos,
        nombreSolicitante, firmaSolicitante, nombreAdministrativo, firmaAdministrativo, nombreGerente, firmaGerente,
        autorizacionGerencia, firmaCompras
      ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
      [
        form.nombre,
        form.fechaSolicitud,
        form.fechaEntrega,
        form.justificacion,
        form.area,
        form.sede,
        form.urgenciaCompra,
        form.tiempoGestion,
        form.anexos,

        form.nombreSolicitante,
        form.firmaSolicitante,

        form.nombreAdministrativo,
        form.firmaAdministrativo,

        form.nombreGerente,
        form.firmaGerente,

        form.autorizacionGerencia,
        form.firmaCompras
      ]
    );

    const formularioId = result.insertId;

    for (const fila of filas) {
      await conn.query(
        `INSERT INTO items_formulario (
          formulario_id, descripcion, cantidad, centro, cuenta, valor, vobo, productoOServicio, purchaseAprobated, siExiste, sstAprobacion, aprobatedStatus
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)`,
        [
          formularioId,
          fila.descripcion ?? '',
          fila.cantidad ?? 0,
          fila.centro ?? '',
          fila.cuenta ?? '',
          fila.valor ?? 0,
          fila.vobo ?? false,
          fila.productoOServicio ?? '',
          fila.purchaseAprobated ?? false,
          fila.siExiste ?? false,
          fila.sstAprobacion ?? false,
          fila.aprobatedStatus ?? false,
        ]
      );

    }

    await conn.commit();

    res.json({ success: true, formularioId });
  } catch (error) {
    await conn.rollback();
    console.error("❌ Error al guardar:", error);
    res.status(500).json({ success: false, error: error.message });
  } finally {
    conn.release();
  }
});

app.get("/formularios", async (req, res) => {
  try {
    const [rows] = await pool.query("SELECT * FROM formularios");
    res.json(rows);
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: "Error al obtener formularios" });
  }
});

app.get("/formularios/:id", async (req, res) => {
  try {
    const { id } = req.params;

    const [formRows] = await pool.query(
      "SELECT * FROM formularios WHERE id = ?",
      [id]
    );
    if (formRows.length === 0) {
      return res.status(404).json({ error: "No encontrado" });
    }


    const [itemsRows] = await pool.query(
      "SELECT * FROM items_formulario WHERE formulario_id = ?",
      [id]
    );


    res.json({
      formulario: formRows[0],
      filas: itemsRows,
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: "Error al obtener formulario" });
  }
});

app.delete("/formularios/:id", async (req, res) => {
  try {
    const { id } = req.params;
    await pool.query("DELETE FROM formularios WHERE id = ?", [id]);
    res.json({ message: "Formulario eliminado" });
  } catch (err) {
    res.status(500).json({ error: "Error al eliminar formulario" });
  }
});

app.get("/formularios/:id/excel", async (req, res) => {
  try {
    const { id } = req.params;
    const plantillaPath = path.join(__dirname, "templates", "plantilla.xlsx");
    console.log("Intentando leer plantilla en:", plantillaPath);

    if (!fs.existsSync(plantillaPath)) {
      console.error("❌ Plantilla no encontrada:", plantillaPath);
      return res.status(500).json({ error: "Plantilla no encontrada" });
    }

    const [formRows] = await pool.query("SELECT * FROM formularios WHERE id = ?", [id]);
    if (formRows.length === 0) return res.status(404).json({ error: "No encontrado" });
    const form = formRows[0];

    const [itemsRows] = await pool.query("SELECT * FROM items_formulario WHERE formulario_id = ?", [id]);

    const workbook = new ExcelJS.Workbook();
    await workbook.xlsx.readFile(plantillaPath);
    console.log("✅ Plantilla Excel cargada correctamente");
    const worksheet = workbook.getWorksheet("F-SGA-SG-19");

    worksheet.getCell("E7").value = form.nombre;
    worksheet.getCell("E8").value = form.fechaSolicitud;
    worksheet.getCell("E9").value = form.fechaEntrega;
    worksheet.getCell("E10").value = form.justificacion;
    worksheet.getCell("O7").value = form.area;
    worksheet.getCell("O8").value = form.sede;
    worksheet.getCell("K9").value = form.urgenciaCompra;
    worksheet.getCell("T9").value = form.tiempoGestion;
    worksheet.getCell("T10").value = form.anexos;

    worksheet.getCell("D31").value = form.nombreSolicitante;
    worksheet.getCell("D32").value = form.firmaSolicitante;

    worksheet.getCell("J31").value = form.nombreAdministrativo;
    worksheet.getCell("J32").value = form.firmaAdministrativo;

    worksheet.getCell("Q31").value = form.nombreGerente;
    worksheet.getCell("Q32").value = form.firmaGerente;

    worksheet.getCell("B36").value = form.autorizacionGerencia;
    worksheet.getCell("P36").value = form.firmaCompras;


    function parseCurrencyToNumber(value) {
      if (value == null) return NaN;
      const str = String(value).trim();
      if (str === '') return NaN;


      const lastDot = str.lastIndexOf('.');
      const lastComma = str.lastIndexOf(',');

      if (lastComma > -1 && lastDot > -1) {
        if (lastComma > lastDot) {
          return Number(str.replace(/\./g, '').replace(/,/g, '.'));
        } else {
          return Number(str.replace(/,/g, ''));
        }
      } else if (lastComma > -1) {
        return Number(str.replace(/\./g, '').replace(/,/g, '.'));
      } else {
        return Number(str.replace(/,/g, ''));
      }
    }

    const startRow = 14;

    itemsRows.forEach((item, idx) => {
      const row = worksheet.getRow(startRow + idx);

      row.getCell(2).value = idx + 1;
      row.getCell(3).value = item.productoOServicio;
      row.getCell(6).value = (item.cantidad !== undefined && item.cantidad !== null) ? Number(item.cantidad) : '';
      row.getCell(7).value = item.centro || '';
      row.getCell(8).value = item.cuenta || '';

      const purchaseAprobated =
        item.purchaseAprobated === 1 ||
        item.purchaseAprobated === true ||
        String(item.purchaseAprobated).toLowerCase().includes('si') ||
        String(item.purchaseAprobated).toLowerCase().includes('sí') ||
        String(item.purchaseAprobated).toLowerCase().includes('aprob');
      row.getCell(10).value = purchaseAprobated ? 'Sí' : 'No';

      const rawValor = item.valor;
      const valorNum = parseCurrencyToNumber(rawValor);
      const cellValor = row.getCell(12);
      if (!isNaN(valorNum)) {
        cellValor.value = valorNum;
        cellValor.numFmt = '"$"#,##0.00';
      } else {
        cellValor.value = rawValor || '';
      }

      row.getCell(13).value = item.descripcion || '';

      const vobo =
        item.vobo === 1 ||
        item.vobo === true ||
        String(item.vobo).toLowerCase().includes('si') ||
        String(item.vobo).toLowerCase().includes('sí') ||
        String(item.vobo).toLowerCase().includes('aprob');
      row.getCell(14).value = vobo ? 'Sí' : 'No';

      const sstAprobacion =
        item.sstAprobacion === 1 ||
        item.sstAprobacion === true ||
        String(item.sstAprobacion).toLowerCase().includes('si') ||
        String(item.sstAprobacion).toLowerCase().includes('sí') ||
        String(item.sstAprobacion).toLowerCase().includes('aprob');
      row.getCell(17).value = sstAprobacion ? 'Sí' : 'No';

      row.commit();
    });

    res.setHeader("Content-Type", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    res.setHeader("Content-Disposition", `attachment; filename=formulario_${id}.xlsx`);
    await workbook.xlsx.write(res);
    res.end();
  } catch (err) {
    console.error("❌ Error al generar Excel:", err);
    res.status(500).json({ error: "Error al generar Excel" });
  }
});


app.put("/items/:id/aprobar", async (req, res) => {
  const { id } = req.params;
  try {
    await pool.query(
      "UPDATE items_formulario SET aprobatedStatus = NOT aprobatedStatus WHERE id = ?",
      [id]
    );
    res.json({ success: true });
  } catch (error) {
    console.error("❌ Error al actualizar aprobatedStatus:", error);
    res.status(500).json({ error: "No se pudo actualizar el estado" });
  }
});


app.get("/items/:formularioId", async (req, res) => {
  const { formularioId } = req.params;
  try {
    const [rows] = await pool.query(
      "SELECT * FROM items_formulario WHERE formulario_id = ?",
      [formularioId]
    );
    res.json(rows);
  } catch (error) {
    console.error("❌ Error al obtener items:", error);
    res.status(500).json({ error: "Error al obtener items del formulario" });
  }
});



//CAMUNDA


app.post("/api/process/start", async (req, res) => {
  try {
    const token = await getAccessToken();
    const { variables } = req.body;

    const response = await axios.post(
      `${CAMUNDA_ZEEBE_URL}/v2/process-instances`,
      {
        processDefinitionId: "Process_1bayiac",
        version: -1,
        variables: variables || {},
      },
      {
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      }
    );

    res.json(response.data);
  } catch (error) {
    console.error(
      "❌ Error al iniciar proceso:",
      error.response?.data || error.message
    );
    res.status(500).json({ error: "Error al iniciar proceso" });
  }
});

app.post("/api/process/start-revision", async (req, res) => {
  try {
    const token = await getAccessToken();
    const { variables } = req.body;

    const response = await axios.post(
      `${CAMUNDA_ZEEBE_URL}/v2/process-instances`,
      {
        processDefinitionId: "Process_1pw9wvj",
        version: -1,
        variables: variables || {},
      },
      {
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      }
    );

    res.json(response.data);
  } catch (error) {
    console.error(
      "❌ Error al iniciar proceso de revisión:",
      error.response?.data || error.message
    );
    res.status(500).json({ error: "Error al iniciar proceso de revisión" });
  }
});

app.post('/api/tasks/search', async (req, res) => {
  try {
    const token = await getAccessToken();
    const response = await axios.post(
      `${CAMUNDA_TASKLIST_BASE_URL}/v2/user-tasks/search`,
      req.body || {},
      {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      }
    );
    res.json(response.data);
  } catch (err) {
    console.error('Error al consultar tareas:', err.response ? err.response.data : err.message);
    res.status(500).json({ error: 'Error al consultar tareas' });
  }
});

app.post("/api/tasks/:userTaskKey/complete", async (req, res) => {
  const { userTaskKey } = req.params;
  const variables = req.body.variables || {};

  try {
    const token = await getAccessToken();

    const response = await axios.post(
      `${CAMUNDA_TASKLIST_BASE_URL}/v2/user-tasks/${userTaskKey}/completion`,
      {
        variables,
      },
      {
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      }
    );

    res.json(response.data);
  } catch (err) {
    console.error("❌ Error completando tarea:", err.response?.data || err.message);
    res.status(500).json({ error: err.response?.data || err.message });
  }
});


app.listen(4000, () => {
  console.log("✅ Servidor en http://localhost:4000");
});

/*
camunda.client.mode=saas
camunda.client.auth.client-id=nvbxHeJMHshyETuNQ7napSEjO1ZVp0Mh
camunda.client.auth.client-secret=25rj2O8VEIiVH6MlQcbkn2RJr22tbUGeUzYpbX1tTSLmYUw-xXa-g3cSTfqLabPm
camunda.client.cloud.cluster-id=9678cfa5-01e6-43a6-9d0d-b418d1a331b7
camunda.client.cloud.region=jfk-1
*/
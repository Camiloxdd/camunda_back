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
import { exec } from "child_process";
import cookieParser from "cookie-parser";
import { fileURLToPath } from "url";
import ConvertAPI from "convertapi";
dotenv.config();
const port = process.env.PORT || 4000;
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const app = express();
app.use(express.json());
app.use(bodyParser.json());
app.use(cookieParser());

const ZEEBE_AUTHORIZATION_SERVER_URL = 'https://login.cloud.camunda.io/oauth/token';
const ZEEBE_CLIENT_ID = '.lhjOm_m1fHA.glwp1oh~_N5OSb-B2rs';
const ZEEBE_CLIENT_SECRET = 'IylbxPdS1EdN1uu2SyI~Vf4o~sbVj.j8-dkewz~AwP2.~qVKduFUhLUDwUJNGqmA';
const CAMUNDA_TASKLIST_BASE_URL = 'https://ont-1.tasklist.camunda.io/dd596b24-3b3b-44f5-bbba-3b94487776f8';
const AUDIENCE = 'tasklist.camunda.io';
const CAMUNDA_ZEEBE_URL = 'https://ont-1.zeebe.camunda.io/dd596b24-3b3b-44f5-bbba-3b94487776f8';

const FRONTEND_URL = 'http://localhost:3000';

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

// --- nuevo helper: consulta segura por varios cargos ---
async function fetchUsersByRoles(roles = []) {
  if (!Array.isArray(roles) || roles.length === 0) return [];
  console.log("fetchUsersByRoles -> solicitando roles:", roles);
  const placeholders = roles.map(() => '?').join(', ');
  const sql = `SELECT nombre, cargo FROM user WHERE cargo IN (${placeholders})`;
  const [rows] = await pool.query(sql, roles);
  console.log("fetchUsersByRoles -> resultados devueltos:", rows?.length ?? 0, rows);
  return rows || [];
}

(async () => {
  try {
    // Intentamos agregar la columna
    await pool.query(
      `ALTER TABLE requisiciones ADD COLUMN status VARCHAR(50) DEFAULT 'pendiente';`
    );
    console.log("Columna 'status' creada correctamente.");
  } catch (err) {
    // Si la columna ya existe, MySQL lanza ER_DUP_FIELDNAME
    if (err.code === 'ER_DUP_FIELDNAME') {
      console.log("La columna 'status' ya existe, continuando...");
    } else {
      console.warn("⚠️ No fue posible asegurar columna status:", err.message || err);
    }
  }

  // Asegurar que todas las filas existentes tengan 'pendiente'
  try {
    await pool.query(
      "UPDATE requisiciones SET status = 'pendiente' WHERE status IS NULL;"
    );
    console.log("Filas existentes actualizadas con status 'pendiente'.");
  } catch (err) {
    console.warn("⚠️ Error actualizando filas existentes:", err.message || err);
  }
})();


function createJwt(payload) {
  return jwt.sign(payload, process.env.JWT_SECRET, { expiresIn: '1h' });
}

export const authMiddleware = (req, res, next) => {
  const token = req.cookies.token;
  if (!token) return res.status(401).json({ message: "No token" });

  try {
    const decoded = jwt.verify(token, process.env.JWT_SECRET);
    req.user = decoded;
    next();
  } catch (err) {
    console.error("❌ Token inválido:", err);
    res.status(401).json({ message: "Token inválido" });
  }
};

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
  if (!correo || !contraseña) {
    return res.status(400).json({ message: 'Email and password required' });
  }

  try {
    // 🔹 Traer todos los datos necesarios
    const [rows] = await pool.execute(
      'SELECT id, correo, contraseña, nombre, cargo, area FROM user WHERE correo = ?',
      [correo]
    );
    const user = rows[0];
    if (!user) return res.status(401).json({ message: 'Invalid credentials' });

    // 🔹 Validar contraseña
    const match = await bcrypt.compare(contraseña, user.contraseña);
    if (!match) return res.status(401).json({ message: 'Invalid credentials' });

    // 🔹 Crear token con todos los datos del usuario
    const token = createJwt({
      id: user.id,
      email: user.correo,
      nombre: user.nombre,
      cargo: user.cargo,
      area: user.area,
    });

    // 🔹 Configurar cookie
    const cookieOptions = {
      httpOnly: true,
      sameSite: 'lax', // o 'strict' o 'none'
      secure: process.env.NODE_ENV === 'production',
      maxAge: 60 * 60 * 1000, // 1 hora
    };

    // 🔹 Enviar token como cookie
    res.cookie('token', token, cookieOptions);

    // 🔹 Responder con los datos básicos del usuario (sin contraseña)
    res.json({
      id: user.id,
      email: user.correo,
      nombre: user.nombre,
      cargo: user.cargo,
      area: user.area,
    });
  } catch (err) {
    console.error('❌ Error en login:', err);
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
    const [rows] = await pool.execute('SELECT id, correo, nombre, cargo, area, sede, super_admin, aprobador, solicitante, comprador FROM user WHERE id = ?', [req.user.id]);
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
    const { solicitante, productos, processInstanceKey } = req.body;

    if (!solicitante || !productos?.length) {
      return res.status(400).json({ message: "Datos incompletos en la solicitud" });
    }

    const { nombre, fecha, fechaRequeridoEntrega, tiempoAproximadoGestion, justificacion, area, sede, urgencia, presupuestada } = solicitante;

    // 1️⃣ Calcular valor total
    const valorTotal = productos.reduce(
      (acc, p) => acc + (parseFloat(p.valorEstimado) || 0),
      0
    );

    // 2️⃣ Insertar la requisición (ahora incluye status)
    const [reqResult] = await pool.query(
      `INSERT INTO requisiciones 
       (nombre_solicitante, fecha, fecha_requerido_entrega, tiempoAproximadoGestion, justificacion, area, sede, urgencia, presupuestada, valor_total, status, process_instance_key) 
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [nombre, fecha, fechaRequeridoEntrega, tiempoAproximadoGestion, justificacion, area, sede, urgencia, presupuestada, valorTotal, 'pendiente', processInstanceKey]
    );

    const requisicionId = reqResult.insertId;

    // 3️⃣ Insertar productos
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
      "pendiente",
    ]);

    await pool.query(
      `INSERT INTO requisicion_productos 
       (requisicion_id, nombre, cantidad, descripcion, compra_tecnologica, ergonomico, valor_estimado, centro_costo, cuenta_contable, aprobado)
       VALUES ?`,
      [valuesProductos]
    );

    // 4️⃣ Determinar aprobaciones necesarias
    const SMLV = 1300000;
    const limite = SMLV * 10;
    const requiereAltas = valorTotal >= limite;

    const tieneErgonomico = productos.some((p) => p.ergonomico);
    const tieneTecnologico = productos.some((p) => p.compraTecnologica);

    // Roles que se deben incluir (en orden jerárquico)
    const rolesNecesarios = [];

    if (tieneTecnologico) rolesNecesarios.push("dicTYP", "gerTyC");
    if (tieneErgonomico) rolesNecesarios.push("dicSST", "gerSST");
    if (requiereAltas && !presupuestada) rolesNecesarios.push("gerAdmin", "gerGeneral");

    // 5️⃣ Buscar aprobadores reales desde la tabla users
    let aprobadores = [];
    if (rolesNecesarios.length > 0) {
      const [usuarios] = await pool.query(
        `SELECT nombre, cargo AS rol, area
         FROM user
         WHERE cargo IN (?)`,
        [rolesNecesarios]
      );

      // Reordenar según el orden original de rolesNecesarios
      aprobadores = rolesNecesarios
        .map((rol) => usuarios.find((u) => u.rol === rol))
        .filter(Boolean);
    }

    // 6️⃣ Insertar aprobaciones con orden y visibilidad
    for (let i = 0; i < aprobadores.length; i++) {
      const aprob = aprobadores[i];
      const orden = i + 1;
      const visible = i === 0; // Solo el primero visible inicialmente

      await pool.query(
        `INSERT INTO requisicion_aprobaciones
         (requisicion_id, rol_aprobador, nombre_aprobador, area, estado, orden, visible)
         VALUES (?, ?, ?, ?, 'pendiente', ?, ?)`,
        [requisicionId, aprob.rol, aprob.nombre, aprob.area, orden, visible]
      );
    }

    res.status(201).json({
      message: "✅ Requisición creada correctamente con aprobadores jerárquicos asignados",
      requisicionId,
      valorTotal,
      aprobadores,
    });
  } catch (error) {
    console.error("❌ Error al crear la requisición:", error);
    res.status(500).json({ message: "Error al crear la requisición" });
  }
});

app.get("/api/requisiciones/pendientes", authMiddleware, async (req, res) => {
  try {
    const { nombre, cargo, area } = req.user; // viene del token o sesión

    // Consultar bandera 'solicitante' y nombre real del usuario desde DB
    const [userRows] = await pool.query(
      "SELECT solicitante, nombre FROM user WHERE id = ?",
      [req.user.id]
    );
    const userRecord = userRows[0];

    // Si el usuario es solicitante, devolver solo las requisiciones a su nombre
    if (userRecord && (userRecord.solicitante === 1 || userRecord.solicitante === true)) {
      const [requisicionesRows] = await pool.query(
        `
        SELECT 
          id AS requisicion_id,
          nombre_solicitante,
          fecha,
          justificacion,
          area,
          sede,
          urgencia,
          presupuestada,
          valor_total,
          status
        FROM requisiciones
        WHERE nombre_solicitante = ?
        ORDER BY fecha DESC
        `,
        [userRecord.nombre]
      );

      if (requisicionesRows.length === 0) return res.json([]);

      const ids = requisicionesRows.map((r) => r.requisicion_id);

      const [productos] = await pool.query(
        `
        SELECT 
          id,
          requisicion_id,
          nombre,
          descripcion,
          cantidad,
          valor_estimado,
          compra_tecnologica,
          ergonomico,
          aprobado
        FROM requisicion_productos
        WHERE requisicion_id IN (?) AND (aprobado IS NULL OR aprobado != 'rechazado')
        `,
        [ids]
      );

      const resultado = requisicionesRows.map((reqItem) => ({
        ...reqItem,
        productos: productos.filter((p) => p.requisicion_id === reqItem.requisicion_id),
      }));

      return res.json(resultado);
    }

    // Roles que pueden aprobar
    const rolesAprobadores = [
      "dicTYP",
      "gerTyC",
      "dicSST",
      "gerSST",
      "gerAdmin",
      "gerGeneral",
    ];

    if (!rolesAprobadores.includes(cargo)) {
      return res
        .status(403)
        .json({ message: "No autorizado para aprobar requisiciones" });
    }

    // Base query para aprobadores (mantener lógica anterior)
    let query = `
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
        r.status,
        a.id AS aprobacion_id,
        a.area AS area_aprobacion,
        a.rol_aprobador,
        a.nombre_aprobador,
        a.estado AS estado_aprobacion,
        a.orden,
        a.visible
      FROM requisiciones r
      INNER JOIN requisicion_aprobaciones a 
        ON r.id = a.requisicion_id
      WHERE a.estado = 'pendiente'
    `;

    const params = [];

    if (cargo !== "gerGeneral") {
      query += " AND a.rol_aprobador = ? AND a.area = ?";
      params.push(cargo, area);
    }

    query += " ORDER BY r.fecha DESC";

    const [requisiciones] = await pool.query(query, params);

    // DEDUPLICAR por requisicion_id para evitar duplicados en dashboard
    const uniqueMap = new Map();
    for (const r of requisiciones) {
      if (!uniqueMap.has(r.requisicion_id)) {
        uniqueMap.set(r.requisicion_id, r);
      }
    }
    const uniqueRequisiciones = Array.from(uniqueMap.values());

    if (uniqueRequisiciones.length === 0) return res.json([]);

    const ids = uniqueRequisiciones.map((r) => r.requisicion_id);

    // Obtener sólo items no rechazados
    const [productos] = await pool.query(
      `
      SELECT 
        id,
        requisicion_id,
        nombre,
        descripcion,
        cantidad,
        valor_estimado,
        compra_tecnologica,
        ergonomico,
        aprobado
      FROM requisicion_productos
      WHERE requisicion_id IN (?) AND (aprobado IS NULL OR aprobado != 'rechazado')
      `,
      [ids]
    );

    const resultado = uniqueRequisiciones.map((req) => ({
      ...req,
      productos: productos.filter((p) => p.requisicion_id === req.requisicion_id),
    }));

    res.json(resultado);
  } catch (error) {
    console.error("❌ Error al obtener requisiciones pendientes:", error);
    res.status(500).json({ message: "Error al obtener requisiciones pendientes" });
  }
});


// Obtener detalles de una requisición por ID
app.get("/api/requisiciones/:id", authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;

    const [requisiciones] = await pool.query(
      `
      SELECT id, nombre_solicitante, fecha, justificacion, area, sede, urgencia, presupuestada, valor_total, status
      FROM requisiciones
      WHERE id = ?
      `,
      [id]
    );

    if (requisiciones.length === 0) {
      return res.status(404).json({ message: "Requisición no encontrada" });
    }

    // devolver solo items que NO fueron rechazados (rechazados deben desaparecer en siguientes aprobaciones)
    const [productos] = await pool.query(
      `
      SELECT id, nombre, descripcion, cantidad, valor_estimado, compra_tecnologica, ergonomico, aprobado, centro_costo, cuenta_contable
      FROM requisicion_productos
      WHERE requisicion_id = ?
      `,
      [id]
    );

    // incluir datos del usuario actual (util para la UI)
    const currentUser = {
      id: req.user.id,
      nombre: req.user.nombre,
      cargo: req.user.cargo,
      area: req.user.area,
    };

    // Obtener progreso de aprobación (nuevo)
    const approvalProgress = await getApprovalProgress(id);

    res.json({ requisicion: requisiciones[0], productos, currentUser, approvalProgress });
  } catch (error) {
    console.error("❌ Error al obtener detalles de requisición:", error);
    res.status(500).json({ message: "Error al obtener detalles" });
  }
});

// NUEVO endpoint: obtener sólo el estado/progreso de aprobación de una requisición
app.get("/api/requisiciones/:id/aprobacion", authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;
    const progress = await getApprovalProgress(id);
    res.json(progress);
  } catch (err) {
    console.error("❌ Error al obtener progreso de aprobación:", err);
    res.status(500).json({ error: "Error al obtener progreso de aprobación" });
  }
});

app.put("/api/requisiciones/:id/aprobar-items", authMiddleware, async (req, res) => {
  try {
    const { id } = req.params; // ID de la requisición
    const { decisiones, action } = req.body; // action puede ser 'approve' | 'reject' (opcional)
    const { nombre, area } = req.user;

    if (!Array.isArray(decisiones)) {
      return res.status(400).json({ message: "Formato inválido: decisiones debe ser un array" });
    }

    // 1️⃣ Actualizar estado de cada producto (aprobado/rechazado)
    for (const { id: productoId, aprobado, fecha_aprobado } of decisiones) {
      await pool.query(
        `
        UPDATE requisicion_productos
        SET aprobado = ?, fecha_aprobado = ?
        WHERE id = ? AND requisicion_id = ?
        `,
        [aprobado ? "aprobado" : "rechazado", aprobado ? fecha_aprobado || new Date() : null, productoId, id]
      );
    }

    // 2️⃣ Calcular nuevo valor total SOLO con productos aprobados
    // considerar aprobados que estén guardados como 'aprobado' O como 1 (legacy / boolean)
    const [rows] = await pool.query(
      `
      SELECT SUM(COALESCE(valor_estimado,0) * COALESCE(cantidad,1)) AS nuevo_total
      FROM requisicion_productos
      WHERE requisicion_id = ? AND (aprobado = 'aprobado' OR aprobado = 1)
      `,
      [id]
    );

    const nuevoTotal = rows[0]?.nuevo_total || 0;

    // 3️⃣ ACTUALIZAR SOLO valor_total por ahora (no cambiar status todavía)
    await pool.query(
      `UPDATE requisiciones SET valor_total = ? WHERE id = ?`,
      [nuevoTotal, id]
    );

    // 4️⃣ Verificar si quedan productos aprobados
    const [countRows] = await pool.query(
      `SELECT COUNT(*) AS cnt FROM requisicion_productos WHERE requisicion_id = ? AND (aprobado = 'aprobado' OR aprobado = 1)`,
      [id]
    );
    const approvedCount = Number(countRows[0]?.cnt || 0);

    // 5️⃣ Si NO queda ningún producto aprobado -> marcar requisición como rechazada total, finalizar aprobaciones
    // 5️⃣ Si NO queda ningún producto aprobado -> marcar requisición como rechazada total, finalizar aprobaciones
    if (approvedCount === 0) {
      // verificar cuántos productos tenía originalmente
      const [totalProds] = await pool.query(
        `SELECT COUNT(*) AS total FROM requisicion_productos WHERE requisicion_id = ?`,
        [id]
      );
      const totalProductos = Number(totalProds[0]?.total || 0);

      // obtener processInstanceKey de la requisición
      const [proc] = await pool.query(
        `SELECT process_instance_key FROM requisiciones WHERE id = ?`,
        [id]
      );
      const processInstanceKey = proc[0]?.process_instance_key;

      // actualizar base de datos
      await pool.query(
        `UPDATE requisicion_aprobaciones SET estado = 'rechazada', visible = FALSE WHERE requisicion_id = ?`,
        [id]
      );
      await pool.query(
        `UPDATE requisiciones SET status = 'rechazada', valor_total = 0 WHERE id = ?`,
        [id]
      );

      console.log("Requisición marcada como RECHAZADA totalmente:", id);

      // 🧨 Si solo había 1 producto y existe processInstanceKey -> cancelar proceso en Camunda
      // 🧨 Si solo había 1 producto y existe processInstanceKey -> cancelar proceso en Camunda
      if (totalProductos === 1 && processInstanceKey) {
        try {
          const cancelRes = await fetch(
            `http://localhost:4000/api/process/${processInstanceKey}/cancel`, // ✅ usa el endpoint correcto
            { method: "POST" } // ✅ usa POST, no DELETE
          );

          if (!cancelRes.ok) throw new Error("No se pudo cancelar proceso Camunda");

          console.log(`🚫 Proceso Camunda ${processInstanceKey} cancelado correctamente.`);
        } catch (err) {
          console.error("⚠️ Error al cancelar proceso Camunda:", err);
        }
      }


      return res.json({
        message: "Requisición rechazada completamente. No quedan ítems aprobados.",
        nuevo_total: 0,
        pendientes: 0
      });
    }


    // 6️⃣ Si hay al menos un producto aprobado -> continuar flujo normal: marcar la aprobación del usuario actual y activar siguiente aprobador
    const [result] = await pool.query(
      `
      UPDATE requisicion_aprobaciones
      SET estado = 'aprobada', visible = FALSE, fecha_aprobacion = NOW()
      WHERE requisicion_id = ? AND nombre_aprobador = ? AND area = ?
      `,
      [id, nombre, area]
    );

    if (result.affectedRows === 0) {
      return res.status(404).json({ message: "No se encontró aprobación correspondiente al usuario actual." });
    }

    // 7️⃣ Obtener el orden del aprobador actual
    const [actual] = await pool.query(
      `
      SELECT orden
      FROM requisicion_aprobaciones
      WHERE requisicion_id = ? AND nombre_aprobador = ? AND area = ?
      `,
      [id, nombre, area]
    );

    const ordenActual = actual[0]?.orden;

    // 8️⃣ Activar al siguiente aprobador (si existe)
    if (ordenActual) {
      await pool.query(
        `
        UPDATE requisicion_aprobaciones
        SET visible = TRUE
        WHERE requisicion_id = ? AND orden = ?
        `,
        [id, ordenActual + 1]
      );
    }

    // 9️⃣ Verificar si quedan aprobaciones pendientes
    const [pendientesRows] = await pool.query(
      `SELECT COUNT(*) AS cnt FROM requisicion_aprobaciones WHERE requisicion_id = ? AND estado = 'pendiente'`,
      [id]
    );

    const pendientesCount = pendientesRows[0]?.cnt || 0;

    // 10️⃣ Decidir estado final de la requisición:
    // - si no quedan aprobaciones pendientes y hay items aprobados => 'aprobada'
    // - en cualquier otro caso dejar 'pendiente' (o el flujo ya activado)
    if (pendientesCount === 0 && approvedCount > 0) {
      await pool.query(
        `UPDATE requisiciones SET status = 'aprobada' WHERE id = ?`,
        [id]
      );
    }

    console.log("✅ Requisición procesada correctamente:", id);

    res.json({
      message: "Operación registrada correctamente.",
      nuevo_total: nuevoTotal,
      pendientes: pendientesCount
    });
  } catch (error) {
    console.error("❌ Error al aprobar/rechazar ítems:", error);
    res.status(500).json({ message: "Error al procesar ítems" });
  }
});


app.get("/api/requisiciones/aprobador/:nombre", async (req, res) => {
  try {
    const { nombre } = req.params;

    const [rows] = await pool.query(
      `
      SELECT 
        r.id AS requisicion_id,
        r.valor_total,
        r.status,
        r.nombre_solicitante,
        r.fecha,
        r.area,
        r.sede,
        r.urgencia,
        r.justificacion,
        a.estado AS estado_aprobacion
      FROM requisiciones r
      INNER JOIN requisicion_aprobaciones a ON r.id = a.requisicion_id
      WHERE a.nombre_aprobador = ?
      ORDER BY r.fecha DESC
      `,
      [nombre]
    );

    res.json(rows);
  } catch (error) {
    console.error("❌ Error obteniendo requisiciones por aprobador:", error);
    res.status(500).json({ error: "Error al obtener requisiciones" });
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

app.get("/requisiciones/:id/pdf", async (req, res) => {
  try {
    const { id } = req.params;
    const tempDir = path.join(__dirname, "temp");
    if (!fs.existsSync(tempDir)) fs.mkdirSync(tempDir);

    const plantillaPath = path.join(__dirname, "templates", "plantilla.xlsx");
    const excelTemp = path.join(tempDir, `requisicion_${id}.xlsx`);
    const pdfTemp = path.join(tempDir, `requisicion_${id}.pdf`);

    const [reqRows] = await pool.query(
      "SELECT * FROM requisiciones.requisiciones WHERE id = ?",
      [id]
    );
    console.log("📦 Requisiciones encontradas:", reqRows.length);

    if (reqRows.length === 0)
      return res.status(404).json({ error: "Requisición no encontrada" });

    const requisicion = reqRows[0];

    // 🧩 Cambiamos tabla: requisiciones.requisicion_productos
    const [productosRows] = await pool.query(
      "SELECT * FROM requisiciones.requisicion_productos WHERE requisicion_id = ?",
      [id]
    );

    console.log("📦 Productos encontrados:", productosRows.length);

    const workbook = new ExcelJS.Workbook();
    await workbook.xlsx.readFile(plantillaPath);
    const worksheet = workbook.getWorksheet("F-SGA-SG-19");

    // 🔹 Cabecera general (usar "N/A" si falta)
    worksheet.getCell("E7").value = requisicion.nombre_solicitante || "N/A";
    worksheet.getCell("E8").value = requisicion.fecha || "N/A";
    worksheet.getCell("E9").value = requisicion.fecha_requerido_entrega || "N/A";
    worksheet.getCell("E10").value = requisicion.justificacion || "N/A";
    worksheet.getCell("O7").value = requisicion.area || "N/A";
    worksheet.getCell("O8").value = requisicion.sede || "N/A";
    worksheet.getCell("K9").value = requisicion.urgencia || "N/A";
    worksheet.getCell("T10").value = (typeof requisicion.presupuestada !== "undefined") ? (requisicion.presupuestada ? "Sí" : "No") : "N/A";
    worksheet.getCell("T9").value = requisicion.tiempoAproximadoGestion || "N/A";

    // REMOVIDO: ya no rellenamos D28/D29/D34/D35 con el solicitante directamente
    // ...existing code...

    // 🔹 Formato numérico
    const parseCurrencyToNumber = (value) => {
      if (value == null) return NaN;
      const str = String(value).trim();
      if (str === "") return NaN;
      return Number(str.replace(/[^\d.-]/g, ""));
    };

    // 🔹 Productos (igual que antes)
    const startRow = 14;
    productosRows.forEach((item, idx) => {
      const row = worksheet.getRow(startRow + idx);
      row.getCell(2).value = idx + 1;
      row.getCell(3).value = item.nombre || "N/A";
      row.getCell(6).value =
        item.cantidad !== undefined && item.cantidad !== null
          ? Number(item.cantidad)
          : "";
      row.getCell(7).value = item.centro_costo || "N/A";
      row.getCell(8).value = item.cuenta_contable || "N/A";
      row.getCell(12).value = parseCurrencyToNumber(item.valor_estimado) || 0;
      row.getCell(10).value = requisicion.presupuestada ? "Sí" : "No";
      row.getCell(13).value = item.descripcion || "N/A";
      row.getCell(14).value = item.compra_tecnologica ? "Sí Aplica" : "No Aplica";
      row.getCell(18).value =
        item.ergonomico === 1 || item.ergonomico === true
          ? "Sí Aplica"
          : "No Aplica";

      row.commit();
    });

    // --- NUEVA LÓGICA DE APROBACIONES (con 4 columnas de aprobadores) ---
    try {
      // 1️⃣ Obtener área del solicitante
      const [userRows] = await pool.query(
        "SELECT area FROM user WHERE nombre = ? LIMIT 1",
        [requisicion.nombre_solicitante]
      );
      let solicitanteArea = (userRows[0]?.area || requisicion.area || "").toString().trim().toUpperCase();

      console.log("📌 Área solicitante:", solicitanteArea);

      // 2️⃣ Detectar flags
      const hasTecnologico = productosRows.some(p => !!(p.compra_tecnologica || p.compraTecnologica));
      const hasErgonomico = productosRows.some(p => !!(p.ergonomico));

      console.log("⚙️ Productos -> Tecnológico:", hasTecnologico, "| Ergonómico:", hasErgonomico);

      // 3️⃣ Determinar roles requeridos
      const rolesNeeded = new Set();

      // Si pertenece a SST
      if (solicitanteArea.includes("SST")) {
        rolesNeeded.add("dicSST"); // siempre su director
        if (hasTecnologico && hasErgonomico) {
          rolesNeeded.add("dicTYP");
          rolesNeeded.add("gerSST");
          rolesNeeded.add("gerTyC");
        } else if (hasTecnologico) {
          rolesNeeded.add("gerTyC");
        } else if (hasErgonomico) {
          rolesNeeded.add("gerSST");
        }
      }

      // Si pertenece a TYP
      else if (solicitanteArea.includes("TYP")) {
        rolesNeeded.add("dicTYP");
        if (hasTecnologico && hasErgonomico) {
          rolesNeeded.add("dicSST");
          rolesNeeded.add("gerTyC");
        } else if (hasTecnologico) {
          rolesNeeded.add("gerTyC");
        } else if (hasErgonomico) {
          rolesNeeded.add("gerSST");
        }
      }

      console.log("✅ Roles requeridos:", Array.from(rolesNeeded));

      // 4️⃣ Buscar nombres
      const rolesArray = Array.from(rolesNeeded);
      const usuariosPorCargo = {};
      if (rolesArray.length > 0) {
        const usuarios = await fetchUsersByRoles(rolesArray);
        usuarios.forEach(u => { usuariosPorCargo[u.cargo] = u.nombre; });
      }

      console.log("👤 Usuarios encontrados:", usuariosPorCargo);

      // 5️⃣ Limpiar celdas
      const nameCells = ["D28", "I28", "M28", "O28", "S28"];
      const sigCells = ["D29", "I29", "M29", "O29", "S29"];
      [...nameCells, ...sigCells].forEach(c => worksheet.getCell(c).value = "");

      // 6️⃣ Escribir solicitante
      worksheet.getCell("D28").value = requisicion.nombre_solicitante || "N/A";

      // 7️⃣ Escribir directores
      worksheet.getCell("I28").value = usuariosPorCargo["dicTYP"] || "N/A";
      worksheet.getCell("M28").value = usuariosPorCargo["dicSST"] || "N/A";

      // 8️⃣ Escribir gerentes
      worksheet.getCell("O28").value = usuariosPorCargo["gerTyC"] || "N/A";
      worksheet.getCell("S28").value = usuariosPorCargo["gerSST"] || "N/A";

      // Firmas vacías
      ["I29", "M29", "O29", "S29"].forEach(c => worksheet.getCell(c).value = "");

      // 9️⃣ Validar Gerencia Administrativa y General según monto
      try {
        const SMLV_local = 1300000;
        const limite_local = SMLV_local * 10;
        const valorTotalNum = Number(requisicion.valor_total || 0);

        const currentD39 = (worksheet.getCell('D39').value || "").toString().trim();
        const currentM39 = (worksheet.getCell('M39').value || "").toString().trim();

        if (!requisicion.presupuestada && valorTotalNum >= limite_local) {
          const missing = [];
          if (!usuariosPorCargo['gerAdmin']) missing.push('gerAdmin');
          if (!usuariosPorCargo['gerGeneral']) missing.push('gerGeneral');
          if (missing.length > 0) {
            const admins = await fetchUsersByRoles(missing);
            admins.forEach(u => { usuariosPorCargo[u.cargo] = u.nombre; });
          }
          if (!currentD39 || currentD39 === "N/A")
            worksheet.getCell('D39').value = usuariosPorCargo['gerAdmin'] || "N/A";
          if (!currentM39 || currentM39 === "N/A")
            worksheet.getCell('M39').value = usuariosPorCargo['gerGeneral'] || "N/A";
        } else {
          if (!currentD39) worksheet.getCell('D39').value = "";
          if (!currentM39) worksheet.getCell('M39').value = "";
        }
      } catch (e) {
        console.warn("⚠️ Error al obtener gerencias:", e);
        if (!(worksheet.getCell('D39').value || "").toString().trim())
          worksheet.getCell('D39').value = "N/A";
        if (!(worksheet.getCell('M39').value || "").toString().trim())
          worksheet.getCell('M39').value = "N/A";
      }

    } catch (err) {
      console.warn("⚠️ Error calculando/aplicando aprobaciones:", err);
    }

    // 🔹 Guardar el Excel (igual que antes)
    res.setHeader(
      "Content-Type",
      "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
    );
    res.setHeader(
      "Content-Disposition",
      `attachment; filename=requisicion_${id}.xlsx`
    );
    await workbook.xlsx.writeFile(excelTemp);

    await convertExcelToPdfConvertAPI(excelTemp, pdfTemp);

    // 3️⃣ Enviar el PDF al cliente
    res.setHeader("Content-Type", "application/pdf");
    res.setHeader(
      "Content-Disposition",
      `attachment; filename=requisicion_${id}.pdf`
    );

    const stream = fs.createReadStream(pdfTemp);
    stream.pipe(res);
    stream.on("close", async () => {
      await Promise.allSettled([
        fs.promises.unlink(excelTemp),
        fs.promises.unlink(pdfTemp),
      ]);
    });
  } catch (err) {
    console.error("❌ Error al generar PDF:", err);
    res.status(500).json({ error: "Error al generar PDF" });
  }
});

async function convertExcelToPdfConvertAPI(inputPath, outputPath) {
  try {
    const convertapi = new ConvertAPI("8qdWu0Di8FQ0LeJfJVGjWGTruUXRO9cr"); // 🔑 Reemplaza con tu API Secret
    console.log("🔄 Convirtiendo Excel a PDF con ConvertAPI...");

    const result = await convertapi.convert("pdf", {
      File: inputPath,
      PageOrientation: "landscape",
      PageSize: "A4",
      Margins: "normal",
    }, "xlsx");

    // Guarda el PDF en disco
    await result.file.save(outputPath);
    console.log("✅ PDF generado correctamente en:", outputPath);
    return true;
  } catch (error) {
    console.error("❌ Error general en la conversión:", error);
    throw error;
  }
}


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
        processDefinitionId: "Process_16fnxs5",
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

app.post("/api/process/:processInstanceKey/cancel", async (req, res) => {
  const { processInstanceKey } = req.params;
  try {
    const token = await getAccessToken();

    const response = await axios.post(
      `${CAMUNDA_ZEEBE_URL}/v2/process-instances/${processInstanceKey}/cancellation`,
      {},
      {
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      }
    );

    res.json({ message: "Proceso cancelado correctamente", response: response.data });
  } catch (error) {
    console.error("❌ Error al cancelar proceso:", error.response?.data || error.message);
    res.status(500).json({ error: "Error al cancelar el proceso" });
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

// NUEVO: endpoint para obtener listas de áreas y sedes desde la BD
app.get("/api/meta", async (req, res) => {
  try {
    const [areasRows] = await pool.query(
      `SELECT DISTINCT area FROM user WHERE area IS NOT NULL AND area != ''`
    );
    const [sedesRows] = await pool.query(
      `SELECT DISTINCT sede FROM user WHERE sede IS NOT NULL AND sede != ''`
    );

    const areas = areasRows.map((r) => r.area);
    const sedes = sedesRows.map((r) => r.sede);

    res.json({ areas, sedes });
  } catch (err) {
    console.error("❌ Error al obtener meta (areas/sedes):", err);
    res.status(500).json({ error: "Error al obtener áreas y sedes" });
  }
});

app.get("/api/requisiciones", authMiddleware, async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT id AS requisicion_id, nombre_solicitante, fecha, justificacion, area, sede, urgencia, presupuestada, valor_total, status
       FROM requisiciones
       ORDER BY fecha DESC`
    );
    res.json(rows);
  } catch (err) {
    console.error("❌ Error al obtener todas las requisiciones:", err);
    res.status(500).json({ error: "Error al obtener requisiciones" });
  }
});

app.delete("/api/requisiciones/:id", authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;
    await pool.query("DELETE FROM requisicion_productos WHERE requisicion_id = ?", [id]);
    await pool.query("DELETE FROM requisicion_aprobaciones WHERE requisicion_id = ?", [id]);
    const [result] = await pool.query("DELETE FROM requisiciones WHERE id = ?", [id]);
    if (result.affectedRows === 0) return res.status(404).json({ message: "Requisición no encontrada" });
    res.json({ message: "Requisición eliminada correctamente" });
  } catch (err) {
    console.error("❌ Error al eliminar requisición:", err);
    res.status(500).json({ error: "Error al eliminar requisión" });
  }
});

app.put("/api/requisiciones/:id", authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;
    const { nombre_solicitante, fecha, justificacion, area, sede, urgencia, presupuestada } = req.body;
    await pool.query(
      `UPDATE requisiciones SET nombre_solicitante=?, fecha=?, justificacion=?, area=?, sede=?, urgencia=?, presupuestada=? WHERE id=?`,
      [nombre_solicitante, fecha, justificacion, area, sede, urgencia, presupuestada ? 1 : 0, id]
    );
    res.json({ message: "Requisición actualizada correctamente" });
  } catch (err) {
    console.error("❌ Error al actualizar requisición:", err);
    res.status(500).json({ error: "Error al actualizar requisición" });
  }
});

app.get("/api/requisiciones/:id/excel", authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;
    const [reqRows] = await pool.query(
      `SELECT id, nombre_solicitante, fecha, justificacion, area, sede, urgencia, valor_total FROM requisiciones WHERE id = ?`,
      [id]
    );
    if (reqRows.length === 0) return res.status(404).json({ error: "No encontrado" });
    const requisicion = reqRows[0];

    const [productos] = await pool.query(
      `SELECT nombre, descripcion, cantidad, valor_estimado, compra_tecnologica, ergonomico FROM requisicion_productos WHERE requisicion_id = ?`,
      [id]
    );

    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet("Requisicion");

    sheet.addRow(["ID", requisicion.id]);
    sheet.addRow(["Solicitante", requisicion.nombre_solicitante]);
    sheet.addRow(["Fecha", requisicion.fecha]);
    sheet.addRow(["Area", requisicion.area]);
    sheet.addRow(["Sede", requisicion.sede]);
    sheet.addRow(["Urgencia", requisicion.urgencia]);
    sheet.addRow(["Valor total", requisicion.valor_total]);
    sheet.addRow([]);
    sheet.addRow(["#", "Producto", "Descripcion", "Cantidad", "Valor estimado", "Tecnologico", "Ergonomico"]);

    productos.forEach((p, idx) => {
      sheet.addRow([
        idx + 1,
        p.nombre,
        p.descripcion,
        p.cantidad,
        p.valor_estimado,
        p.compraTecnologica ? "Sí" : "No",
        p.ergonomico ? "Sí" : "No",
      ]);
    });

    res.setHeader("Content-Type", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    res.setHeader("Content-Disposition", `attachment; filename=requisicion_${id}.xlsx`);
    await workbook.xlsx.write(res);
    res.end();
  } catch (err) {
    console.error("❌ Error generar Excel requisición:", err);
    res.status(500).json({ error: "Error al generar Excel" });
  }
});

// --- Nuevo endpoint: Devolver requisición para corrección ---
app.post("/api/requisiciones/:id/devolver", authMiddleware, async (req, res) => {
  const { id } = req.params;
  const { motivo } = req.body || {}; // opcional: motivo de devolución

  try {
    // 1) Verificar existencia
    const [rows] = await pool.query("SELECT id FROM requisiciones WHERE id = ?", [id]);
    if (rows.length === 0) {
      return res.status(404).json({ message: "Requisición no encontrada" });
    }

    // 2) Marcar la requisición como 'devuelta'
    await pool.query("UPDATE requisiciones SET status = ? WHERE id = ?", ["devuelta", id]);

    // 3) Resetear estado de productos: dejar sin aprobado (NULL) para permitir corrección
    await pool.query(
      `UPDATE requisicion_productos
       SET aprobado = NULL
       WHERE requisicion_id = ?`,
      [id]
    );

    // 4) Resetear aprobaciones: poner todas en 'pendiente' y visible = FALSE
    await pool.query(
      `UPDATE requisicion_aprobaciones
       SET estado = 'pendiente', visible = FALSE
       WHERE requisicion_id = ?`,
      [id]
    );

    // 5) Activar la primera aprobación (orden = 1) para que vuelva a empezar la cadena de aprobaciones
    await pool.query(
      `UPDATE requisicion_aprobaciones
       SET visible = TRUE
       WHERE requisicion_id = ? AND orden = 1`,
      [id]
    );

    // 6) (Opcional) Registrar un log interno sobre la devolución (si existe tabla de logs)
    // await pool.query(`INSERT INTO requisicion_logs (requisicion_id, tipo, mensaje, creado_por) VALUES (?, 'devolucion', ?, ?)`, [id, motivo || 'Devuelta por comprador', req.user.nombre]);

    res.json({ message: "Requisición devuelta para corrección", requisicionId: Number(id) });
  } catch (err) {
    console.error("❌ Error al devolver la requisición:", err);
    res.status(500).json({ message: "Error al devolver la requisición" });
  }
});

// --- Nuevo endpoint: Aprobar totalmente una requisición (marcar como 'aprobada') ---
app.post("/api/requisiciones/:id/aprobar-total", authMiddleware, async (req, res) => {
  const { id } = req.params;
  try {
    // 1) Verificar existencia
    const [rows] = await pool.query("SELECT id FROM requisiciones WHERE id = ?", [id]);
    if (rows.length === 0) {
      return res.status(404).json({ message: "Requisición no encontrada" });
    }

    // 2) Marcar todos los productos como 'aprobado' (asegura que estén aprobados)
    await pool.query(
      `UPDATE requisicion_productos
       SET aprobado = 'aprobado'
       WHERE requisicion_id = ?`,
      [id]
    );

    // 3) Marcar todas las aprobaciones como 'aprobada' y ocultarlas (visible = FALSE)
    await pool.query(
      `UPDATE requisicion_aprobaciones
       SET estado = 'aprobada', visible = FALSE
       WHERE requisicion_id = ?`,
      [id]
    );

    // 4) Marcar la requisición como 'aprobada'
    await pool.query(
      `UPDATE requisiciones
       SET status = 'Totalmente Aprobada'
       WHERE id = ?`,
      [id]
    );

    // 5) (Opcional) Actualizar valor_total por si fuera necesario recalcular
    const [sumRows] = await pool.query(
      `SELECT SUM(COALESCE(valor_estimado,0) * COALESCE(cantidad,1)) AS total
       FROM requisicion_productos
       WHERE requisicion_id = ? AND aprobado = 'aprobado'`,
      [id]
    );
    const nuevoTotal = sumRows[0]?.total ?? 0;
    await pool.query(`UPDATE requisiciones SET valor_total = ? WHERE id = ?`, [nuevoTotal, id]);

    res.json({ message: "Requisición marcada como aprobada (total)", requisicionId: Number(id) });
  } catch (err) {
    console.error("❌ Error al aprobar totalmente la requisición:", err);
    res.status(500).json({ message: "Error al aprobar la requisición" });
  }
});

// --- NUEVO: reemplazar productos de una requisición ---
app.put("/api/requisiciones/:id/productos", authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;
    const { productos } = req.body;

    // validar existencia
    const [r] = await pool.query("SELECT id FROM requisiciones WHERE id = ?", [id]);
    if (r.length === 0) return res.status(404).json({ message: "Requisición no encontrada" });

    // eliminar productos previos
    await pool.query("DELETE FROM requisicion_productos WHERE requisicion_id = ?", [id]);

    if (Array.isArray(productos) && productos.length > 0) {
      const values = productos.map((p) => [
        id,
        p.nombre || '',
        p.cantidad ?? 1,
        p.descripcion || '',
        p.compraTecnologica ? 1 : 0,
        p.ergonomico ? 1 : 0,
        p.valorEstimado ?? 0,
        p.centroCosto || '',
        p.cuentaContable || '',
        null, // aprobado (reset)
      ]);

      await pool.query(
        `INSERT INTO requisicion_productos
         (requisicion_id, nombre, cantidad, descripcion, compra_tecnologica, ergonomico, valor_estimado, centro_costo, cuenta_contable, aprobado)
         VALUES ?`,
        [values]
      );
    }

    // recalcular valor_total
    const [sumRows] = await pool.query(
      `SELECT SUM(COALESCE(valor_estimado,0) * COALESCE(cantidad,1)) AS total
       FROM requisicion_productos WHERE requisicion_id = ?`,
      [id]
    );
    const nuevoTotal = sumRows[0]?.total ?? 0;
    await pool.query("UPDATE requisiciones SET valor_total = ? WHERE id = ?", [nuevoTotal, id]);

    res.json({ message: "Productos actualizados correctamente", nuevoTotal });
  } catch (err) {
    console.error("❌ Error al actualizar productos de requisición:", err);
    res.status(500).json({ message: "Error al actualizar productos" });
  }
});

async function getApprovalProgress(requisicionId) {
  const [reqValRows] = await pool.query(
    `SELECT valor_total FROM requisiciones WHERE id = ?`,
    [requisicionId]
  );
  const valorRequisicion = Number(reqValRows[0]?.valor_total || 0);

  const THRESHOLD = 10000000;

  const requiredMinimum = valorRequisicion >= THRESHOLD ? 4 : 2;

  const [countsRows] = await pool.query(
    `
    SELECT 
      COUNT(*) AS total,
      SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) AS aprobadas,
      SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
      MAX(CASE WHEN estado = 'aprobada' THEN orden ELSE NULL END) AS lastApprovedOrder
    FROM requisicion_aprobaciones
    WHERE requisicion_id = ?
    `,
    [requisicionId]
  );

  const totalApprovals = countsRows[0]?.total || 0;
  const approvedCount = Number(countsRows[0]?.aprobadas || 0);
  const pendientesCount = Number(countsRows[0]?.pendientes || 0);
  const lastApprovedOrder = countsRows[0]?.lastApprovedOrder ?? null;

  const nextOrder = pendientesCount > 0
    ? (lastApprovedOrder === null ? 1 : lastApprovedOrder + 1)
    : null;

  const [approvers] = await pool.query(
    `SELECT id, rol_aprobador, nombre_aprobador, area, estado, orden, visible, fecha_aprobacion
     FROM requisicion_aprobaciones 
     WHERE requisicion_id = ? 
     ORDER BY orden ASC`,
    [requisicionId]
  );

  return {
    valorRequisicion,
    requiredMinimum,
    totalApprovals,
    approvedCount,
    pendientesCount,
    lastApprovedOrder,
    nextOrder,
    approvers
  };
}

// NUEVO: endpoint para devolver las aprobaciones (usar en frontend para filtrar por nombre de aprobador)
app.get("/api/aprobaciones", authMiddleware, async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, requisicion_id, area, rol_aprobador, nombre_aprobador, estado, orden, visible, fecha_aprobacion
       FROM requisicion_aprobaciones
       ORDER BY requisicion_id, orden`
    );
    res.json(rows);
  } catch (err) {
    console.error("❌ Error al obtener aprobaciones:", err);
    res.status(500).json({ message: "Error al obtener aprobaciones" });
  }
});
import express from "express";
import cors from "cors";
import bodyParser from "body-parser";
import mysql from "mysql2/promise";
import fs from "fs";
import dotenv from 'dotenv';
import path from "path";
import ExcelJS from "exceljs";
import { fileURLToPath } from "url";
dotenv.config();
const app = express();
import axios from 'axios';
const port = process.env.PORT || 4000;
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

app.use(cors({
  origin: "http://localhost:3000",
  methods: ["GET", "POST", "PUT", "DELETE", "PATCH"],
  allowedHeaders: ["Content-Type", "Authorization"]
}));

app.use(express.json());
app.use(cors());
app.use(bodyParser.json());

const pool = mysql.createPool({
  host: "127.0.0.1",
  user: "root",
  password: "",
  database: "requisiciones",
  port: 3306
});

const ZEEBE_AUTHORIZATION_SERVER_URL = 'https://login.cloud.camunda.io/oauth/token';
const ZEEBE_CLIENT_ID = 'vytWkNDD-2kEpWRFXQqBf2SQ_xeUm__S';
const ZEEBE_CLIENT_SECRET = 'kibhYAaHwwsN997vSOLWn0VQuHoAMitK2-wRGyGyJ88dpWD6wzuDQO0FiZL1mMmu';
const CAMUNDA_TASKLIST_BASE_URL = 'https://lhr-1.tasklist.camunda.io/79fc9e4f-5c5f-40bb-9934-39516a17b786';
const AUDIENCE = 'tasklist.camunda.io';
const CAMUNDA_ZEEBE_URL = 'https://lhr-1.zeebe.camunda.io/79fc9e4f-5c5f-40bb-9934-39516a17b786'


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

app.put("/formularios/:id", async (req, res) => {
  const { id } = req.params;
  const { form, filas } = req.body;

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    await conn.query(
      `UPDATE formularios SET
        nombre = ?, fechaSolicitud = ?, fechaEntrega = ?, justificacion = ?, area = ?, sede = ?, urgenciaCompra = ?, tiempoGestion = ?, anexos = ?,
        observacionesOne = ?, observacionesTwo = ?, observacionesThree = ?,
        nombreSolicitante = ?, firmaSolicitante = ?, nombreAdministrativo = ?, firmaAdministrativo = ?, nombreGerente = ?, firmaGerente = ?,
        autorizacionGerencia = ?, fechaCompras = ?, horaCompras = ?, consecutivoCompras = ?, firmaCompras = ?
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
        form.observacionesOne,
        form.observacionesTwo,
        form.observacionesThree,
        form.nombreSolicitante,
        form.firmaSolicitante,
        form.nombreAdministrativo,
        form.firmaAdministrativo,
        form.nombreGerente,
        form.firmaGerente,
        form.autorizacionGerencia,
        form.fechaCompras,
        form.horaCompras,
        form.consecutivoCompras,
        form.firmaCompras,
        id
      ]
    );

    await conn.query("DELETE FROM items_formulario WHERE formulario_id = ?", [id]);

    for (const fila of filas) {
      await conn.query(
        `INSERT INTO items_formulario (
          formulario_id, descripcion, cantidad, centro, cuenta, presupuesto, valor, vobo
        ) VALUES (?,?,?,?,?,?,?,?)`,
        [
          id,
          fila.descripcion,
          fila.cantidad,
          fila.centro,
          fila.cuenta,
          fila.presupuesto,
          fila.valor,
          fila.vobo
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
        observacionesOne, observacionesTwo, observacionesThree,
        nombreSolicitante, firmaSolicitante, nombreAdministrativo, firmaAdministrativo, nombreGerente, firmaGerente,
        autorizacionGerencia, fechaCompras, horaCompras, consecutivoCompras, firmaCompras
      ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
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
        form.observacionesOne,
        form.observacionesTwo,
        form.observacionesThree,
        form.nombreSolicitante,
        form.firmaSolicitante,
        form.nombreAdministrativo,
        form.firmaAdministrativo,
        form.nombreGerente,
        form.firmaGerente,
        form.autorizacionGerencia,
        form.fechaCompras,
        form.horaCompras,
        form.consecutivoCompras,
        form.firmaCompras
      ]
    );

    const formularioId = result.insertId;

    for (const fila of filas) {
      await conn.query(
        `INSERT INTO items_formulario (
          formulario_id, descripcion, cantidad, centro, cuenta, presupuesto, valor, vobo
        ) VALUES (?,?,?,?,?,?,?,?)`,
        [
          formularioId,
          fila.descripcion,
          fila.cantidad,
          fila.centro,
          fila.cuenta,
          fila.presupuesto,
          fila.valor,
          fila.vobo
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

    // Traer los ítems relacionados
    const [itemsRows] = await pool.query(
      "SELECT * FROM items_formulario WHERE formulario_id = ?",
      [id]
    );

    // Enviar ambos al frontend
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

    worksheet.getCell("B26").value = form.observacionesOne;
    worksheet.getCell("B27").value = form.observacionesTwo;
    worksheet.getCell("B28").value = form.observacionesThree;

    worksheet.getCell("D31").value = form.nombreSolicitante;
    worksheet.getCell("D32").value = form.firmaSolicitante;

    worksheet.getCell("J31").value = form.nombreAdministrativo;
    worksheet.getCell("J32").value = form.firmaAdministrativo;

    worksheet.getCell("Q31").value = form.nombreGerente;
    worksheet.getCell("Q32").value = form.firmaGerente;

    worksheet.getCell("B36").value = form.autorizacionGerencia;
    worksheet.getCell("H36").value = form.fechaCompras;
    worksheet.getCell("J36").value = form.horaCompras;
    worksheet.getCell("L36").value = form.consecutivoCompras;
    worksheet.getCell("P36").value = form.firmaCompras;

    let startRow = 14;
    itemsRows.forEach((item, idx) => {
      const row = worksheet.getRow(startRow + idx);
      row.getCell(2).value = idx + 1; // Número de ítem
      row.getCell(3).value = item.descripcion;
      row.getCell(4).value = item.cantidad;
      row.getCell(5).value = item.centro;
      row.getCell(6).value = item.cuenta;
      row.getCell(7).value = item.presupuesto;
      row.getCell(8).value = item.valor;
      row.getCell(9).value = item.vobo;
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


//CAMUNDA


app.post("/api/process/start", async (req, res) => {
  try {
    const token = await getAccessToken();
    const { variables } = req.body;

    const response = await axios.post(
      `${CAMUNDA_ZEEBE_URL}/v2/process-instances`,
      {
        processDefinitionId: "Process_09wevs4",
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
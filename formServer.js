import express from "express";
import cors from "cors";
import bodyParser from "body-parser";
import mysql from "mysql2/promise";

const app = express();
app.use(cors());
app.use(bodyParser.json());

// Conexión a MySQL
const pool = mysql.createPool({
  host: "127.0.0.1",
  user: "root",
  password: "",  
  database: "requisiciones",
  port: 3306
});


app.post("/formularios", async (req, res) => {
  const { form, filas } = req.body;

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    // Insertar datos generales
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

    // Insertar las filas asociadas
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
    res.json(rows); // 👈 siempre responder JSON
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: "Error al obtener formularios" });
  }
});

app.get("/formularios/:id", async (req, res) => {
  try {
    const { id } = req.params;
    const [rows] = await pool.query("SELECT * FROM formularios WHERE id = ?", [id]);
    if (rows.length === 0) return res.status(404).json({ error: "No encontrado" });
    res.json(rows[0]);
  } catch (err) {
    res.status(500).json({ error: "Error al obtener formulario" });
  }
});

app.get("/formularios/:id", async (req, res) => {
  try {
    const { id } = req.params;

    // Traer el formulario principal
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


app.listen(4000, () => {
  console.log("✅ Servidor en http://localhost:4000");
});